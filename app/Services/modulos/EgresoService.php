<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\EgresoRepository;
use App\Rules\modulos\EgresoRules;
use App\Services\LogSistemaService;
use App\core\Database;
use App\Services\modulos\PeriodosContablesService;
use App\repositories\modulos\PeriodosContablesRepository;
use App\Rules\modulos\PeriodosContablesRules;
use App\repositories\modulos\AsientoContableRepository;
use App\Rules\modulos\AsientoContableRules;

class EgresoService
{
    private EgresoRepository $repository;
    private EgresoRules $rules;
    private LogSistemaService $logService;
    private PeriodosContablesService $periodosService;

    public function __construct(EgresoRepository $repository, EgresoRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;

        // Inicializar manualmente el servicio de periodos
        $periodosRepo = new PeriodosContablesRepository();
        $periodosRules = new PeriodosContablesRules();
        $this->periodosService = new PeriodosContablesService($periodosRepo, $periodosRules, $this->logService);
    }

    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'fecha_emision', string $ordenDir = 'DESC'): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $egreso = $this->repository->getPorId($id, $idEmpresa);
        if (!$egreso) {
            return null;
        }
        $egreso['detalles'] = $this->repository->getDetalles($id);
        $egreso['pagos']    = $this->repository->getPagos($id);
        return $egreso;
    }

    private function validarSecuencial(array $data): void
    {
        if (!empty($data['secuencial']) && !empty($data['id_establecimiento']) && !empty($data['id_punto_emision'])) {
            if ($this->repository->existeSecuencial(
                (int) $data['id_empresa'],
                (int) $data['id_establecimiento'],
                (int) $data['id_punto_emision'],
                (string) $data['secuencial']
            )) {
                throw new \Exception('El número de secuencial de egreso ya existe en la base de datos.');
            }
        }
    }

    public function registrar(array $data): int
    {
        // 1. Validar reglas negocio
        $this->rules->validar($data);
        
        // 2. Validar secuencial duplicado
        $this->validarSecuencial($data);

        // 3. Validar Periodo Contable
        $this->validarPeriodo($data, 'No se puede registrar el egreso porque el periodo contable está cerrado.');

        $db = Database::getConnection();
        $inTrans = $db->inTransaction();
        if (!$inTrans) $db->beginTransaction();

        try {
            // Insertar Cabecera
            $idEgreso = $this->repository->insertCabecera($data);

            // Insertar Detalles
            if (!empty($data['detalles'])) {
                foreach ($data['detalles'] as $det) {
                    $det['id_egreso'] = $idEgreso;
                    $this->repository->insertDetalle($det);
                }
            }

            // Insertar Pagos
            if (!empty($data['pagos'])) {
                foreach ($data['pagos'] as $pago) {
                    $pago['id_egreso'] = $idEgreso;
                    $this->repository->insertPago($pago);
                }
            }

            // Log de Auditoría
            $this->logService->registrar(
                (int) $data['usuario_id'],
                (int) $data['id_empresa'],
                'CREAR',
                'egresos_cabecera',
                $idEgreso,
                null,
                ['monto' => $data['monto_total'], 'tipo' => $data['tipo_egreso']]
            );

            if (!$inTrans) $db->commit();

        } catch (\Throwable $e) {
            if (!$inTrans && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // Asiento contable fuera de la transacción: un fallo aquí no revierte el egreso.
        if (!$inTrans) {
            $this->generarAsientoContableSeguro($idEgreso, $data);
        }

        return $idEgreso;
    }

    public function anular(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $egreso = $this->repository->getPorId($id, $idEmpresa);
        if (!$egreso) throw new \Exception("Documento no encontrado.");
        if ($egreso['estado'] === 'anulado') throw new \Exception("El egreso ya está anulado.");

        // Validar Periodo Contable antes de anular
        $this->periodosService->validarFechaPermitida(
            $egreso['fecha_emision'], 
            $idEmpresa, 
            'No se puede anular el egreso porque el periodo contable original está cerrado.'
        );

        $db = Database::getConnection();
        $inTrans = $db->inTransaction();
        if (!$inTrans) $db->beginTransaction();

        try {
            $res = $this->repository->anular($id, $idEmpresa, $idUsuario);
            
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ANULAR',
                'egresos_cabecera',
                $id,
                ['estado' => $egreso['estado']],
                ['estado' => 'anulado']
            );

            if (!$inTrans) $db->commit();
        } catch (\Throwable $e) {
            if (!$inTrans && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // Anular el asiento contable asociado (fuera de la transacción).
        $this->anularAsientoContable($id, $idEmpresa, $idUsuario);

        return $res;
    }

    public function actualizarPagos(int $id, array $pagos, int $idEmpresa, int $idUsuario, ?string $fechaEmision = null, array $extraData = []): void
    {
        $egreso = $this->repository->getPorId($id, $idEmpresa);
        if (!$egreso) throw new \Exception("Egreso no encontrado.");
        if ($egreso['estado'] === 'anulado') throw new \Exception("No se pueden modificar los pagos de un egreso anulado.");

        // 1. Validar Periodo Contable de la fecha original
        $this->periodosService->validarFechaPermitida(
            $egreso['fecha_emision'], 
            $idEmpresa, 
            'No se pueden actualizar pagos porque el periodo contable original está cerrado.'
        );

        // 2. Si la fecha cambia, validar periodo contable de destino
        if ($fechaEmision && $fechaEmision !== $egreso['fecha_emision']) {
            $this->periodosService->validarFechaPermitida(
                $fechaEmision, 
                $idEmpresa, 
                'No se puede cambiar a la nueva fecha porque el periodo contable de destino está cerrado.'
            );
        }

        // Validar consistencia: suma de pagos debe igualar monto_total (que puede variar si es general)
        $sumaNuevosPagos = array_reduce($pagos, fn($carry, $p) => $carry + (float)($p['monto'] ?? 0), 0.0);
        $montoTotal = !empty($extraData['es_general']) ? (float)($extraData['monto_total'] ?? 0) : (float)$egreso['monto_total'];
        
        if (abs($sumaNuevosPagos - $montoTotal) > 0.01) {
            throw new \Exception("Inconsistencia: la suma de las formas de pago ($" . number_format($sumaNuevosPagos, 2) . ") no coincide con el total del egreso ($" . number_format($montoTotal, 2) . ").");
        }

        $db = Database::getConnection();
        $inTrans = $db->inTransaction();
        if (!$inTrans) $db->beginTransaction();

        try {
            // Registrar pagos viejos para la auditoría
            $pagosViejos = $this->repository->getPagos($id);

            // 1. Eliminar lógicamente los pagos históricos
            $this->repository->query("UPDATE egresos_pagos SET eliminado = TRUE WHERE id_egreso = ? AND eliminado = FALSE", [$id]);

            // 2. Insertar nuevos pagos
            foreach ($pagos as $pago) {
                $pago['id_egreso'] = $id;
                $this->repository->insertPago($pago);
            }

            // Preparación datos auditoría
            $datosAnteriores = ['pagos' => $pagosViejos];
            $datosNuevos     = ['pagos' => $pagos];

            // 3. SI ES GENERAL, ACTUALIZAR DETALLES, SUJETO Y MONTOS EN LA CABECERA
            if (!empty($extraData['es_general'])) {
                $datosAnteriores['detalles'] = $this->repository->getDetalles($id);
                $datosAnteriores['tipo_sujeto'] = $egreso['tipo_sujeto'];
                $datosAnteriores['id_proveedor'] = $egreso['id_proveedor'];
                $datosAnteriores['id_empleado'] = $egreso['id_empleado'];
                $datosAnteriores['observaciones'] = $egreso['observaciones'];
                $datosAnteriores['monto_total'] = $egreso['monto_total'];

                // 3.1. Eliminar detalles viejos lógicamente
                $this->repository->query("UPDATE egresos_detalle SET eliminado = TRUE WHERE id_egreso = ? AND eliminado = FALSE", [$id]);

                // 3.2. Registrar nuevos detalles
                $detallesNuevos = $extraData['detalles'] ?? [];
                foreach ($detallesNuevos as $det) {
                    $det['id_egreso'] = $id;
                    $this->repository->insertDetalle($det);
                }

                // 3.3. Actualizar datos expandidos en cabecera
                $this->repository->query(
                    "UPDATE egresos_cabecera 
                     SET tipo_sujeto = ?, 
                         id_proveedor = ?, 
                         id_empleado = ?, 
                         observaciones = ?, 
                         monto_total = ?, 
                         updated_at = CURRENT_TIMESTAMP, 
                         updated_by = ? 
                     WHERE id = ? AND id_empresa = ?",
                    [
                        $extraData['tipo_sujeto'] ?? 'PROVEEDOR',
                        !empty($extraData['id_proveedor']) ? (int)$extraData['id_proveedor'] : null,
                        !empty($extraData['id_empleado']) ? (int)$extraData['id_empleado'] : null,
                        $extraData['observaciones'] ?? null,
                        $montoTotal,
                        $idUsuario,
                        $id,
                        $idEmpresa
                    ]
                );

                $datosNuevos['detalles'] = $detallesNuevos;
                $datosNuevos['tipo_sujeto'] = $extraData['tipo_sujeto'];
                $datosNuevos['id_proveedor'] = $extraData['id_proveedor'] ?? null;
                $datosNuevos['id_empleado'] = $extraData['id_empleado'] ?? null;
                $datosNuevos['observaciones'] = $extraData['observaciones'] ?? null;
                $datosNuevos['monto_total'] = $montoTotal;
            }

            // 4. Si la fecha cambió, actualizarla en la cabecera
            if ($fechaEmision && $fechaEmision !== $egreso['fecha_emision']) {
                $this->repository->query(
                    "UPDATE egresos_cabecera SET fecha_emision = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ? WHERE id = ? AND id_empresa = ?", 
                    [$fechaEmision, $idUsuario, $id, $idEmpresa]
                );
                $datosAnteriores['fecha_emision'] = $egreso['fecha_emision'];
                $datosNuevos['fecha_emision']     = $fechaEmision;
            }

            // 5. Registrar en Auditoría
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ACTUALIZAR_EGRESO',
                'egresos_cabecera',
                $id,
                $datosAnteriores,
                $datosNuevos
            );

            if (!$inTrans) $db->commit();
        } catch (\Throwable $e) {
            if (!$inTrans && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // Regenerar el asiento contable fuera de la transacción.
        if (!$inTrans) {
            $this->generarAsientoContableSeguro($id, ['id_empresa' => $idEmpresa, 'usuario_id' => $idUsuario]);
        }
    }

    public function getConceptosEgreso(int $idEmpresa): array
    {
        return $this->repository->getConceptosEgreso($idEmpresa);
    }

    public function getDocumentosPendientesProveedor(int $idProveedor, int $idEmpresa): array
    {
        return $this->repository->getDocumentosPendientesProveedor($idProveedor, $idEmpresa);
    }
    
    public function getDocumentosPendientesEmpleado(int $idEmpleado, int $idEmpresa): array
    {
        // Placeholder para rol de pagos y prestamos a empleados si las tablas existieran
        // Por ahora devolvemos vacío ya que usualmente esas tablas no están listas.
        return [];
    }

    public function getUltimoNumeroCheque(int $idFormaPago): ?string
    {
        return $this->repository->getUltimoNumeroCheque($idFormaPago);
    }

    private function validarPeriodo(array $data, ?string $mensaje = null): void
    {
        $fecha = $data['fecha_emision'] ?? null;
        $idEmpresa = (int) ($data['id_empresa'] ?? 0);

        if ($fecha && $idEmpresa) {
            $this->periodosService->validarFechaPermitida($fecha, $idEmpresa, $mensaje);
        }
    }

    /**
     * Expuesto para verificación rápida desde el controller (AJAX).
     */
    public function verificarPeriodo(string $fecha, int $idEmpresa): void
    {
        $this->periodosService->validarFechaPermitida(
            $fecha,
            $idEmpresa,
            "La fecha $fecha corresponde a un periodo contable cerrado. No se pueden registrar transacciones en ese periodo."
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ASIENTO CONTABLE
    // ─────────────────────────────────────────────────────────────────────────

    private function asientoContableService(): AsientoContableService
    {
        return new AsientoContableService(
            new AsientoContableRepository(),
            new AsientoContableRules(),
            $this->logService
        );
    }

    /** Devuelve el asiento contable (cabecera + detalles) generado para el egreso, o null. */
    public function getAsientoContable(int $idEgreso, int $idEmpresa): ?array
    {
        $asientoService = $this->asientoContableService();
        $previo = $asientoService->getAsientoPorOrigen('egreso', $idEgreso, $idEmpresa);
        if (!$previo) {
            return null;
        }
        $detalle = $asientoService->getDetalleAsiento((int) $previo['id'], $idEmpresa);
        return $detalle ?: null;
    }

    /** Genera el asiento sin propagar errores (lo contable no bloquea lo operativo). */
    private function generarAsientoContableSeguro(int $idEgreso, array $data): void
    {
        try {
            $this->procesarAsientoContable($idEgreso, $data);
        } catch (\Throwable $e) {
            error_log('[Egreso] Asiento no generado para egreso #' . $idEgreso . ': ' . $e->getMessage());
        }
    }

    /**
     * Genera (o regenera) y enlaza el asiento contable del egreso.
     * Contrapartida: cuenta de la opción (concepto directo) o cuenta por pagar (cartera);
     * pata banco/caja: cuentas de las formas de pago. No hace nada si el asiento queda vacío.
     */
    public function procesarAsientoContable(int $idEgreso, array $data): void
    {
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) ($data['usuario_id'] ?? $data['id_usuario'] ?? 0);

        $egreso = $this->repository->getPorId($idEgreso, $idEmpresa);
        if (!$egreso) {
            return;
        }

        $detalles = (new AsientoBuilderService())->generarAsientoEgreso($idEmpresa, $idEgreso);
        if (empty($detalles)) {
            return;
        }

        $num        = $egreso['numero_egreso'] ?? (string) $idEgreso;
        $tipoSujeto = strtolower((string) ($egreso['tipo_sujeto'] ?? ''));
        foreach ($detalles as &$d) {
            $d['documento_referencia'] = 'Egreso ' . $num;
            if ($tipoSujeto === 'proveedor' && !empty($egreso['id_proveedor'])) {
                $d['id_entidad']   = (int) $egreso['id_proveedor'];
                $d['tipo_entidad'] = 'proveedor';
            } elseif ($tipoSujeto === 'empleado' && !empty($egreso['id_empleado'])) {
                $d['id_entidad']   = (int) $egreso['id_empleado'];
                $d['tipo_entidad'] = 'empleado';
            }
        }
        unset($d);

        $asientoService = $this->asientoContableService();
        $previo    = $asientoService->getAsientoPorOrigen('egreso', $idEgreso, $idEmpresa);
        $idAsiento = $previo ? (int) $previo['id'] : 0;

        $cabecera = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $egreso['fecha_emision'],
            'tipo_comprobante'     => 'egresos',
            'numero_comprobante'   => '',
            'concepto'             => 'Egreso ' . $num,
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'egreso',
            'id_referencia_origen' => $idEgreso,
            'observaciones'        => $egreso['observaciones'] ?? null,
        ];

        $idGenerado = $asientoService->guardarAsiento($cabecera, $detalles, $idEmpresa, $idUsuario);
        $this->repository->updateAsientoContable($idEgreso, $idGenerado);
    }

    /**
     * Genera el asiento de un egreso por sincronización masiva (control de asientos en
     * Estados Financieros). Toma empresa/usuario de la propia cabecera y PROPAGA la excepción
     * si no se puede generar (descuadre / cuentas faltantes) para contabilizarlo como pendiente.
     */
    public function procesarAsientoContablePorSincronizacion(int $idEgreso): void
    {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT id_empresa, COALESCE(created_by, updated_by) AS usr FROM egresos_cabecera WHERE id = ? AND eliminado = false");
        $st->execute([$idEgreso]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }
        $this->procesarAsientoContable($idEgreso, [
            'id_empresa' => (int) $row['id_empresa'],
            'usuario_id' => (int) ($row['usr'] ?? 0),
        ]);
    }

    /** Anula el asiento contable asociado al egreso, si existe y no está ya anulado. */
    private function anularAsientoContable(int $idEgreso, int $idEmpresa, int $idUsuario): void
    {
        try {
            $asientoService = $this->asientoContableService();
            $previo = $asientoService->getAsientoPorOrigen('egreso', $idEgreso, $idEmpresa);
            if ($previo && ($previo['estado'] ?? '') !== 'anulado') {
                $asientoService->anular((int) $previo['id'], $idEmpresa, $idUsuario);
                $this->repository->updateAsientoContable($idEgreso, null);
            }
        } catch (\Throwable $e) {
            error_log('[Egreso] No se pudo anular el asiento del egreso #' . $idEgreso . ': ' . $e->getMessage());
        }
    }
}
