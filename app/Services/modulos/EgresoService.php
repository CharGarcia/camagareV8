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
use App\Services\modulos\RolPagoService;
use App\repositories\modulos\RolPagoRepository;
use App\Rules\modulos\RolPagoRules;

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

    /**
     * Actualiza la fecha de cobro de un cheque emitido, solo si aún NO fue
     * reportado como cobrado (conciliado en Control Bancario con fecha_banco).
     */
    public function actualizarFechaCobroCheque(int $idEmpresa, int $idPago, string $fecha, int $idUsuario): void
    {
        $pago = $this->repository->getPagoChequeParaEdicion($idEmpresa, $idPago);
        if (!$pago) {
            throw new \RuntimeException('Pago no encontrado.');
        }
        if (strtoupper((string) $pago['tipo_operacion_bancaria']) !== 'CHEQUE') {
            throw new \RuntimeException('El pago no es un cheque.');
        }
        if (strtoupper((string) $pago['egreso_estado']) === 'ANULADO') {
            throw new \RuntimeException('El egreso está anulado.');
        }
        // PDO/pgsql devuelve el boolean como 't'/'f'.
        $conciliado = in_array($pago['cheque_conciliado'], [true, 't', '1', 1], true);
        if ($conciliado) {
            throw new \RuntimeException('El cheque ya fue reportado como cobrado (conciliado en Control Bancario); no se puede modificar la fecha de cobro.');
        }

        $d = \DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$d || $d->format('Y-m-d') !== $fecha) {
            throw new \InvalidArgumentException('Fecha de cobro inválida.');
        }

        $antes = ['fecha_cobro' => $pago['fecha_cobro']];
        $this->repository->actualizarFechaCobro($idPago, $fecha);
        $this->logService->registrar(
            $idUsuario, $idEmpresa, 'ACTUALIZAR_FECHA_COBRO_CHEQUE', 'egresos_pagos', $idPago,
            $antes, ['fecha_cobro' => $fecha]
        );
    }

    /**
     * Cambia el nombre a imprimir en el cheque SIN alterar el beneficiario del
     * egreso. Cadena vacía = vuelve a usar el beneficiario del egreso.
     */
    public function actualizarBeneficiarioCheque(int $idEmpresa, int $idPago, string $nombre, int $idUsuario): void
    {
        $pago = $this->repository->getPagoChequeParaEdicion($idEmpresa, $idPago);
        if (!$pago) {
            throw new \RuntimeException('Pago no encontrado.');
        }
        if (strtoupper((string) $pago['tipo_operacion_bancaria']) !== 'CHEQUE') {
            throw new \RuntimeException('El pago no es un cheque.');
        }
        if (strtoupper((string) $pago['egreso_estado']) === 'ANULADO') {
            throw new \RuntimeException('El egreso está anulado.');
        }

        $nombre = trim($nombre);
        $this->repository->actualizarBeneficiarioCheque($idPago, $nombre);
        $this->logService->registrar(
            $idUsuario, $idEmpresa, 'ACTUALIZAR_NOMBRE_CHEQUE', 'egresos_pagos', $idPago,
            null, ['beneficiario_cheque' => ($nombre !== '' ? $nombre : null)]
        );
    }

    /**
     * Anula un cheque puntual de un egreso vigente (error de impresión, cheque dañado,
     * etc.), dejando la fila como historial (no se borra ni se marca `eliminado`). El
     * egreso y el resto de sus pagos NO se tocan; el monto anulado deja de contarse en
     * el asiento contable (que se regenera más chico automáticamente) y en Control
     * Bancario/Impresión de Cheques. Si el pago quedaba sin cobertura, el usuario agrega
     * otra forma de pago desde el mismo modal (flujo ya existente de "editar pagos").
     */
    public function anularCheque(int $idEmpresa, int $idPago, string $motivo, int $idUsuario): void
    {
        $pago = $this->repository->getPagoChequeParaEdicion($idEmpresa, $idPago);
        if (!$pago) {
            throw new \RuntimeException('Pago no encontrado.');
        }
        if (strtoupper((string) $pago['tipo_operacion_bancaria']) !== 'CHEQUE') {
            throw new \RuntimeException('El pago no es un cheque.');
        }
        if (strtoupper((string) $pago['egreso_estado']) === 'ANULADO') {
            throw new \RuntimeException('El egreso está anulado.');
        }
        if (($pago['estado_cheque'] ?? 'vigente') === 'anulado') {
            throw new \RuntimeException('El cheque ya está anulado.');
        }
        // PDO/pgsql devuelve el boolean como 't'/'f'.
        $conciliado = in_array($pago['cheque_conciliado'], [true, 't', '1', 1], true);
        if ($conciliado) {
            throw new \RuntimeException('El cheque ya fue reportado como cobrado (conciliado en Control Bancario); no se puede anular.');
        }

        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new \InvalidArgumentException('Debe indicar el motivo de anulación del cheque.');
        }

        $this->periodosService->validarFechaPermitida(
            $pago['fecha_emision'],
            $idEmpresa,
            'No se puede anular el cheque porque el periodo contable del egreso está cerrado.'
        );

        $db = Database::getConnection();
        $inTrans = $db->inTransaction();
        if (!$inTrans) $db->beginTransaction();

        try {
            $this->repository->anularCheque($idPago, $motivo, $idUsuario);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ANULAR_CHEQUE',
                'egresos_pagos',
                $idPago,
                ['estado_cheque' => 'vigente'],
                ['estado_cheque' => 'anulado', 'motivo' => $motivo, 'numero_cheque' => $pago['numero_cheque'] ?? null]
            );

            if (!$inTrans) $db->commit();
        } catch (\Throwable $e) {
            if (!$inTrans && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // Asiento fuera de la transacción (mismo patrón que registrar()/actualizarPagos()):
        // lineasFormas() ya excluye el pago anulado, así que el asiento se recalcula más
        // chico automáticamente, sin necesidad de un asiento de reversión.
        $this->generarAsientoContableSeguro((int) $pago['id_egreso'], [
            'id_empresa' => $idEmpresa,
            'usuario_id' => $idUsuario,
        ]);
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $egreso = $this->repository->getPorId($id, $idEmpresa);
        if (!$egreso) {
            return null;
        }
        $egreso['detalles'] = $this->repository->getDetalles($id);
        $egreso['pagos']    = $this->repository->getPagos($id);
        // Enriquecer las líneas manuales con la cuenta contable usada en el asiento (si existe),
        // para que la grilla del modal muestre la cuenta elegida al reabrir.
        $this->enriquecerCuentasDetalle($egreso, $id, $idEmpresa);
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
            // Revalidar, DENTRO de la transacción y con el documento bloqueado, que cada
            // documento (Compra/Liquidación/Rol/Anticipo/Préstamo/Décimo) todavía tiene
            // saldo suficiente: el "saldo_anterior" que mandó el navegador puede estar
            // desactualizado si, mientras el modal seguía abierto, otro egreso ya pagó
            // parte (o todo) el mismo documento (CLAUDE.md §8). Se agrupa por documento
            // porque el pago "por ítems" manda varias líneas para un mismo id_referencia
            // (una por ítem): hay que sumarlas antes de comparar contra el saldo real, no
            // validar cada línea por separado.
            $idEmpresaChk = (int) $data['id_empresa'];
            $montoPorDoc = [];
            $numeroPorDoc = [];
            foreach ($data['detalles'] ?? [] as $det) {
                $tipoDoc = $det['tipo_documento'] ?? '';
                if ($tipoDoc === 'MANUAL' || empty($det['id_referencia_documento'])) {
                    continue;
                }
                $clave = $tipoDoc . ':' . (int) $det['id_referencia_documento'];
                $montoPorDoc[$clave] = ($montoPorDoc[$clave] ?? 0) + (float) ($det['monto_pagado'] ?? 0);
                $numeroPorDoc[$clave] ??= $det['numero_documento'] ?? $det['id_referencia_documento'];
            }
            foreach ($montoPorDoc as $clave => $montoTotalDoc) {
                [$tipoDoc, $idRef] = explode(':', $clave, 2);
                $idRef = (int) $idRef;
                $this->repository->lockDocumentoPago($tipoDoc, $idRef, $idEmpresaChk);
                $saldoReal = $this->repository->getSaldoPendienteDocumento($tipoDoc, $idRef, $idEmpresaChk);
                if ($montoTotalDoc > $saldoReal + 0.01) {
                    throw new \Exception(
                        "El documento " . $numeroPorDoc[$clave] .
                        " ya no tiene saldo suficiente (disponible: $" . number_format($saldoReal, 2) .
                        "). Es posible que otro egreso lo haya pagado mientras tanto; cierre y vuelva a abrir la búsqueda de documentos pendientes."
                    );
                }
            }

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

        // Asiento y sincronización de nómina van FUERA de la transacción: un fallo aquí no debe
        // revertir el egreso — y, si la transacción la abrió el llamador, un error SQL en estas
        // tareas dejaría su transacción abortada y su COMMIT fallaría (Postgres, 25P02). Cuando el
        // llamador gestiona la transacción, las ejecuta él con tareasPostCommit().
        if (!$inTrans) {
            $this->tareasPostCommit($idEgreso, $data);
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

        // Al anular un egreso que pagaba semanas/quincenas, el mensual debe des-netear:
        // se regenera con lo que queda realmente pagado.
        $this->sincronizarNominaMensual($id, $idEmpresa, $idUsuario);

        return $res;
    }

    /**
     * Re-sincroniza la nómina tras registrar/anular un egreso:
     *  (a) Pago de líneas SEMANAL/QUINCENA → regenera el MENSUAL (neteo por lo pagado).
     *  (b) Pago de anticipos/préstamos (novedades) → regenera el rol de su aplica_en+período
     *      para aplicar el descuento por lo realmente pagado.
     * Nunca debe romper el flujo del egreso: cualquier fallo se ignora.
     */
    private function sincronizarNominaMensual(int $idEgreso, int $idEmpresa, int $idUsuario): void
    {
        try {
            $db = Database::getConnection();
            $rolSvc = new RolPagoService(new RolPagoRepository(), new RolPagoRules(), $this->logService);

            // (a) SEMANAL/QUINCENA pagadas → regenerar el MENSUAL del período.
            $st = $db->prepare("SELECT DISTINCT c.periodo_anio, c.periodo_mes
                                FROM egresos_detalle ed
                                JOIN rol_detalle d ON d.id = ed.id_referencia_documento
                                JOIN rol_cabecera c ON c.id = d.id_rol
                                WHERE ed.id_egreso = :eg AND ed.tipo_documento = 'ROL'
                                  AND c.tipo_rol IN ('SEMANAL','QUINCENA')");
            $st->execute([':eg' => $idEgreso]);
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $p) {
                $rolSvc->regenerarAfectados($idEmpresa, 'rol', (int) $p['periodo_anio'], (int) $p['periodo_mes'], $idUsuario);
            }

            // (b) Anticipos pagados → regenerar el rol de su aplica_en+período (descuenta lo pagado).
            $st2 = $db->prepare("SELECT DISTINCT n.aplica_en, n.periodo_anio, n.periodo_mes
                                 FROM egresos_detalle ed
                                 JOIN novedades n ON n.id = ed.id_referencia_documento
                                 WHERE ed.id_egreso = :eg AND ed.tipo_documento = 'ANTICIPO'");
            $st2->execute([':eg' => $idEgreso]);
            foreach ($st2->fetchAll(\PDO::FETCH_ASSOC) as $p) {
                $rolSvc->regenerarAfectados($idEmpresa, (string) ($p['aplica_en'] ?: 'rol'), (int) $p['periodo_anio'], (int) $p['periodo_mes'], $idUsuario);
            }

            // (c) Desembolso de préstamo pagado/anulado → regenerar los roles de TODAS las cuotas
            //     de ese empleado+tipo (el desembolso habilita el descuento de las cuotas). Solo
            //     el tipo 9 (Préstamo Empresa) pasa por desembolso de egreso (ver
            //     CatalogoNovedades::CODS_PRESTAMO); el filtro queda igual por si hay egresos
            //     históricos de 7/8 de antes de este cambio.
            $st3 = $db->prepare("SELECT DISTINCT n.aplica_en, n.periodo_anio, n.periodo_mes
                                 FROM egresos_detalle ed
                                 JOIN novedades n ON n.id_empleado = ed.id_referencia_documento
                                      AND ('PRESTAMO' || n.tipo_codigo) = ed.tipo_documento
                                 WHERE ed.id_egreso = :eg AND ed.tipo_documento LIKE 'PRESTAMO%'
                                   AND n.eliminado = false AND n.estado = 'activo'
                                   AND n.tipo_codigo IN ('7','8','9')");
            $st3->execute([':eg' => $idEgreso]);
            foreach ($st3->fetchAll(\PDO::FETCH_ASSOC) as $p) {
                $rolSvc->regenerarAfectados($idEmpresa, (string) ($p['aplica_en'] ?: 'rol'), (int) $p['periodo_anio'], (int) $p['periodo_mes'], $idUsuario);
            }
        } catch (\Throwable $e) {
            // El módulo de nómina puede no estar disponible en todas las instalaciones.
        }
    }

    public function actualizarPagos(int $id, array $pagos, int $idEmpresa, int $idUsuario, ?string $fechaEmision = null, array $extraData = []): void
    {
        $egreso = $this->repository->getPorId($id, $idEmpresa);
        if (!$egreso) throw new \Exception("Egreso no encontrado.");
        if ($egreso['estado'] === 'anulado') throw new \Exception("No se pueden modificar los pagos de un egreso anulado.");

        if ($fechaEmision && strtotime($fechaEmision) > strtotime(date('Y-m-d'))) {
            throw new \Exception("La fecha de emisión no puede ser posterior a la fecha actual.");
        }

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

            // 1. Eliminar lógicamente los pagos vigentes (los cheques ya anulados se
            //    preservan tal cual: son historial, no se vuelven a tocar aquí).
            $this->repository->query(
                "UPDATE egresos_pagos SET eliminado = TRUE
                 WHERE id_egreso = ? AND eliminado = FALSE AND COALESCE(estado_cheque, 'vigente') <> 'anulado'",
                [$id]
            );

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

        // Regenerar el asiento contable fuera de la transacción. Se pasan los detalles (con su
        // cuenta contable por línea) cuando el egreso es GENERAL, para que el asiento las refleje.
        if (!$inTrans) {
            $this->generarAsientoContableSeguro($id, [
                'id_empresa' => $idEmpresa,
                'usuario_id' => $idUsuario,
                'detalles'   => $extraData['detalles'] ?? [],
            ]);
        }
    }

    /**
     * Agrega a cada línea manual del egreso la cuenta contable con la que se contabilizó en el
     * asiento (match por descripción ↔ referencia_detalle del lado Debe). No persiste nada: solo
     * enriquece la respuesta para que el modal muestre la cuenta elegida.
     */
    private function enriquecerCuentasDetalle(array &$egreso, int $idEgreso, int $idEmpresa): void
    {
        if (empty($egreso['detalles'])) {
            return;
        }
        $asiento = $this->asientoContableService()->getAsientoPorOrigen('egreso', $idEgreso, $idEmpresa);
        if (!$asiento || empty($asiento['detalles'])) {
            return;
        }
        $mapa = [];
        foreach ($asiento['detalles'] as $ad) {
            if ((float) ($ad['debe'] ?? 0) <= 0) continue; // contrapartida del egreso = lado Debe
            $ref = trim((string) ($ad['referencia_detalle'] ?? ''));
            if ($ref === '' || isset($mapa[$ref])) continue;
            $mapa[$ref] = [
                'id'     => (int) ($ad['id_cuenta_contable'] ?? 0),
                'codigo' => $ad['codigo_cuenta'] ?? '',
                'nombre' => $ad['nombre_cuenta'] ?? '',
            ];
        }
        foreach ($egreso['detalles'] as &$d) {
            if (($d['tipo_documento'] ?? '') !== 'MANUAL') continue;
            $ref = trim((string) ($d['descripcion'] ?? ''));
            if (isset($mapa[$ref])) {
                $d['id_cuenta_contable'] = $mapa[$ref]['id'];
                $d['cuenta_codigo']      = $mapa[$ref]['codigo'];
                $d['cuenta_nombre']      = $mapa[$ref]['nombre'];
            }
        }
        unset($d);
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
        // Líneas de rol pendientes de pago del empleado (rol_detalle con saldo).
        return $this->repository->getDocumentosPendientesEmpleado($idEmpleado, $idEmpresa);
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
        // Fallback (documentos migrados): el asiento histórico tiene modulo_origen='migracion', así que
        // getAsientoPorOrigen no lo halla; se resuelve por el enlace id_asiento_contable del documento.
        if (!$previo) {
            $row = $this->repository->getPorId($idEgreso, $idEmpresa);
            $idAsiento = (int) ($row['id_asiento_contable'] ?? 0);
            if ($idAsiento > 0) {
                return $asientoService->getDetalleAsiento($idAsiento, $idEmpresa) ?: null;
            }
            return null;
        }
        $detalle = $asientoService->getDetalleAsiento((int) $previo['id'], $idEmpresa);
        return $detalle ?: null;
    }

    /**
     * Tareas que van DESPUÉS de que el egreso está confirmado en la base: asiento contable y
     * sincronización del rol mensual (si el egreso paga semanas/quincenas). Ninguna debe correr
     * dentro de la transacción que insertó el egreso.
     *
     * registrar() las ejecuta solo cuando él mismo gestiona la transacción. Si la abrió el
     * llamador —el alta que reserva el secuencial antes de insertar (CLAUDE.md §8)—, es ese
     * llamador quien debe invocar este método justo después de su COMMIT.
     */
    public function tareasPostCommit(int $idEgreso, array $data): void
    {
        $this->generarAsientoContableSeguro($idEgreso, $data);
        $this->sincronizarNominaMensual($idEgreso, (int) $data['id_empresa'], (int) $data['usuario_id']);
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

        // Cuentas por línea elegidas en el modal (si vinieron en el payload). En regeneración
        // masiva/sincronización no llegan y el builder las recupera del asiento existente.
        $detallesConCuenta = $data['detalles'] ?? [];
        $builder  = new AsientoBuilderService();
        $detalles = $builder->generarAsientoEgreso($idEmpresa, $idEgreso, $detallesConCuenta);
        if (empty($detalles)) {
            return;
        }

        // Si el asiento quedó descuadrado por cuentas sin configurar, fallar aquí con el motivo
        // concreto (qué cuenta falta y dónde se configura). Sin esto el error que ve el usuario es
        // el de AsientoContableRules —"Total Debe (0) no coincide con Total Haber (…)"—, que no
        // dice qué corregir. Si no se pudo determinar la causa no lanza nada y sigue el camino
        // de siempre.
        AsientoBuilderService::verificarCuadre($detalles, $builder->getMotivosFaltantes());

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
            $idAsiento = $previo ? (int) $previo['id'] : 0;

            // Fallback (documentos migrados): el asiento histórico tiene modulo_origen='migracion',
            // así que getAsientoPorOrigen no lo halla; se resuelve por el enlace id_asiento_contable
            // del propio egreso (mismo criterio que getAsientoContable()).
            if ($idAsiento <= 0) {
                $row = $this->repository->getPorId($idEgreso, $idEmpresa);
                $idAsiento = (int) ($row['id_asiento_contable'] ?? 0);
            }

            if ($idAsiento > 0) {
                $asiento = $asientoService->getDetalleAsiento($idAsiento, $idEmpresa);
                if ($asiento && ($asiento['estado'] ?? '') !== 'anulado') {
                    $asientoService->anular($idAsiento, $idEmpresa, $idUsuario);
                }
                $this->repository->updateAsientoContable($idEgreso, null);
            }
        } catch (\Throwable $e) {
            error_log('[Egreso] No se pudo anular el asiento del egreso #' . $idEgreso . ': ' . $e->getMessage());
        }
    }
}
