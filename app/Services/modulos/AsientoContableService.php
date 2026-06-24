<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\AsientoContableCabecera;
use App\models\AsientoContableDetalle;
use App\repositories\modulos\AsientoContableRepository;
use App\Rules\modulos\AsientoContableRules;
use App\Services\LogSistemaService;

class AsientoContableService
{
    private AsientoContableCabecera $modelCabecera;
    private AsientoContableDetalle $modelDetalle;

    public function __construct(
        private AsientoContableRepository $repository,
        private AsientoContableRules $rules,
        private LogSistemaService $logService
    ) {
        $this->modelCabecera = new AsientoContableCabecera();
        $this->modelDetalle = new AsientoContableDetalle();
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
    }

    public function getDetalleAsiento(int $id, int $idEmpresa): array
    {
        return $this->repository->getDetalleAsiento($id, $idEmpresa);
    }

    public function getAsientoPorOrigen(string $modulo, int $idRef, int $idEmpresa): ?array
    {
        return $this->repository->getAsientoPorOrigen($modulo, $idRef, $idEmpresa);
    }

    public function guardarAsiento(array $cabeceraData, array $detallesData, int $idEmpresa, int $idUsuario): int
    {
        // Ordenar detalles: cuentas con 'debe' > 0 primero
        usort($detallesData, function($a, $b) {
            $debeA = (float)($a['debe'] ?? 0);
            $debeB = (float)($b['debe'] ?? 0);
            if ($debeA > 0 && $debeB <= 0) return -1;
            if ($debeB > 0 && $debeA <= 0) return 1;
            return 0;
        });

        // 1. Recalcular totales desde los detalles para seguridad
        $totalDebe = 0.00;
        $totalHaber = 0.00;
        foreach ($detallesData as $det) {
            $totalDebe += round((float)($det['debe'] ?? 0), 2);
            $totalHaber += round((float)($det['haber'] ?? 0), 2);
        }
        
        $cabeceraData['total_debe'] = round($totalDebe, 2);
        $cabeceraData['total_haber'] = round($totalHaber, 2);

        // 2. Validaciones
        $this->rules->validarCabecera($cabeceraData);
        $this->rules->validarDetalles($detallesData);

        // 3. Iniciar Transacción (solo si no hay una activa — puede ser llamado desde otro servicio)
        $pdo = \App\core\Database::getConnection();
        $managedTransaction = !$pdo->inTransaction();
        if ($managedTransaction) $pdo->beginTransaction();

        try {
            $idAsiento = (int)($cabeceraData['id'] ?? 0);
            $isUpdate = $idAsiento > 0;

            // Generar número de comprobante si es nuevo
            if (!$isUpdate && empty($cabeceraData['numero_comprobante'])) {
                $cabeceraData['numero_comprobante'] = $this->repository->generarNumeroComprobante($idEmpresa, $cabeceraData['tipo_comprobante']);
            }

            // Preparar data de cabecera (normalizar tipo y estado a minúsculas para consistencia)
            $saveData = [
                'id_empresa' => $idEmpresa,
                'fecha_asiento' => $cabeceraData['fecha_asiento'],
                'tipo_comprobante' => strtolower(trim($cabeceraData['tipo_comprobante'] ?? 'diario')),
                'numero_comprobante' => $cabeceraData['numero_comprobante'],
                'concepto' => $cabeceraData['concepto'],
                'estado' => strtolower(trim($cabeceraData['estado'] ?? 'contabilizado')),
                'modulo_origen' => $cabeceraData['modulo_origen'] ?? 'manual',
                'id_referencia_origen' => !empty($cabeceraData['id_referencia_origen']) ? (int)$cabeceraData['id_referencia_origen'] : null,
                'total_debe' => $cabeceraData['total_debe'],
                'total_haber' => $cabeceraData['total_haber'],
                'observaciones' => $cabeceraData['observaciones'] ?? null,
            ];

            if ($isUpdate) {
                $saveData['updated_by'] = $idUsuario;
                $saveData['updated_at'] = date('Y-m-d H:i:s');
                $this->repository->updateCabecera($idAsiento, $saveData);
                
                // Auditar
                $this->logService->registrar(
                    idUsuario: $idUsuario,
                    idEmpresa: $idEmpresa,
                    accion: 'Actualizar Asiento',
                    tabla: 'asientos_contables_cabecera',
                    idRegistro: $idAsiento,
                    antes: null,
                    despues: $saveData
                );
            } else {
                $saveData['created_by'] = $idUsuario;
                $idAsiento = $this->repository->insertCabecera($saveData);
                
                // Auditar
                $this->logService->registrar(
                    idUsuario: $idUsuario,
                    idEmpresa: $idEmpresa,
                    accion: 'Crear Asiento',
                    tabla: 'asientos_contables_cabecera',
                    idRegistro: $idAsiento,
                    antes: null,
                    despues: $saveData
                );
            }

            // 4. Manejar Detalles
            if ($isUpdate) {
                $this->repository->deleteDetalles($idAsiento);
            }

            foreach ($detallesData as $det) {
                $detData = [
                    'id_empresa' => $idEmpresa,
                    'id_asiento' => $idAsiento,
                    'id_cuenta_contable' => (int)$det['id_cuenta_contable'],
                    'id_centro_costo' => !empty($det['id_centro_costo']) ? (int)$det['id_centro_costo'] : null,
                    'id_proyecto' => !empty($det['id_proyecto']) ? (int)$det['id_proyecto'] : null,
                    'debe' => round((float)($det['debe'] ?? 0), 2),
                    'haber' => round((float)($det['haber'] ?? 0), 2),
                    'referencia_detalle' => $det['referencia_detalle'] ?? null,
                    'documento_referencia' => $det['documento_referencia'] ?? null,
                    'id_entidad' => !empty($det['id_entidad']) ? (int)$det['id_entidad'] : null,
                    'tipo_entidad' => $det['tipo_entidad'] ?? null,
                    'created_by' => $idUsuario,
                ];
                $this->repository->insertDetalle($detData);
            }

            if ($managedTransaction) $pdo->commit();
            return $idAsiento;

        } catch (\Throwable $e) {
            if ($managedTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function anular(int $idAsiento, int $idEmpresa, int $idUsuario): void
    {
        $asiento = $this->repository->getDetalleAsiento($idAsiento, $idEmpresa);
        if (!$asiento) {
            throw new \Exception('Asiento no encontrado.');
        }
        if ($asiento['estado'] === 'anulado') {
            throw new \Exception('El asiento ya se encuentra anulado.');
        }

        $pdo = \App\core\Database::getConnection();
        $managedTransaction = !$pdo->inTransaction();
        if ($managedTransaction) $pdo->beginTransaction();
        try {
            $update = [
                'estado' => 'anulado',
                'updated_by' => $idUsuario,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            $this->repository->updateEstado($idAsiento, 'anulado', $idUsuario);

            // Si el asiento pertenece a una factura de venta, desvincular el campo id_asiento_contable
            if (
                ($asiento['tipo_comprobante'] ?? '') === 'ventas' &&
                ($asiento['modulo_origen'] ?? '') === 'factura_venta' &&
                !empty($asiento['id_referencia_origen'])
            ) {
                $this->repository->desvincularAsientoVenta((int)$asiento['id_referencia_origen']);

                $this->logService->registrar(
                    idUsuario: $idUsuario,
                    idEmpresa: $idEmpresa,
                    accion: 'Desvincular Asiento de Factura Venta',
                    tabla: 'ventas_cabecera',
                    idRegistro: (int)$asiento['id_referencia_origen'],
                    antes: ['id_asiento_contable' => $idAsiento],
                    despues: ['id_asiento_contable' => null]
                );
            }

            // Si el asiento pertenece a una retención en ventas, desvincular su id_asiento_contable
            if (
                ($asiento['modulo_origen'] ?? '') === 'retencion_venta' &&
                !empty($asiento['id_referencia_origen'])
            ) {
                $this->repository->desvincularAsientoRetencionVenta((int)$asiento['id_referencia_origen']);

                $this->logService->registrar(
                    idUsuario: $idUsuario,
                    idEmpresa: $idEmpresa,
                    accion: 'Desvincular Asiento de Retención Venta',
                    tabla: 'retencion_venta_cabecera',
                    idRegistro: (int)$asiento['id_referencia_origen'],
                    antes: ['id_asiento_contable' => $idAsiento],
                    despues: ['id_asiento_contable' => null]
                );
            }

            // Si el asiento pertenece a un ingreso o egreso, desvincular su id_asiento_contable.
            // Así, si el documento sigue activo, el control de Estados Financieros lo regenerará.
            $origenDoc = $asiento['modulo_origen'] ?? '';
            if (in_array($origenDoc, ['ingreso', 'egreso'], true) && !empty($asiento['id_referencia_origen'])) {
                if ($origenDoc === 'ingreso') {
                    $this->repository->desvincularAsientoIngreso((int)$asiento['id_referencia_origen']);
                } else {
                    $this->repository->desvincularAsientoEgreso((int)$asiento['id_referencia_origen']);
                }

                $this->logService->registrar(
                    idUsuario: $idUsuario,
                    idEmpresa: $idEmpresa,
                    accion: 'Desvincular Asiento de ' . ucfirst($origenDoc),
                    tabla: $origenDoc === 'ingreso' ? 'ingresos_cabecera' : 'egresos_cabecera',
                    idRegistro: (int)$asiento['id_referencia_origen'],
                    antes: ['id_asiento_contable' => $idAsiento],
                    despues: ['id_asiento_contable' => null]
                );
            }

            $this->logService->registrar(
                idUsuario: $idUsuario,
                idEmpresa: $idEmpresa,
                accion: 'Anular Asiento',
                tabla: 'asientos_contables_cabecera',
                idRegistro: $idAsiento,
                antes: ['estado' => $asiento['estado']],
                despues: $update
            );

            if ($managedTransaction) $pdo->commit();
        } catch (\Throwable $e) {
            if ($managedTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Restablece un asiento anulado a 'contabilizado'. Solo permitido para asientos de
     * tipo Diario (los demás se regeneran desde su documento de origen, no se reactivan a mano).
     */
    public function restablecer(int $idAsiento, int $idEmpresa, int $idUsuario): void
    {
        $asiento = $this->repository->getDetalleAsiento($idAsiento, $idEmpresa);
        if (!$asiento) {
            throw new \Exception('Asiento no encontrado.');
        }
        if (($asiento['estado'] ?? '') !== 'anulado') {
            throw new \Exception('Solo se puede restablecer un asiento que esté anulado.');
        }
        if (strtolower(trim($asiento['tipo_comprobante'] ?? '')) !== 'diario') {
            throw new \Exception('Solo los asientos de tipo Diario se pueden restablecer a contabilizado.');
        }

        $pdo = \App\core\Database::getConnection();
        $managedTransaction = !$pdo->inTransaction();
        if ($managedTransaction) $pdo->beginTransaction();
        try {
            $this->repository->updateEstado($idAsiento, 'contabilizado', $idUsuario);

            $this->logService->registrar(
                idUsuario: $idUsuario,
                idEmpresa: $idEmpresa,
                accion: 'Restablecer Asiento',
                tabla: 'asientos_contables_cabecera',
                idRegistro: $idAsiento,
                antes: ['estado' => 'anulado'],
                despues: ['estado' => 'contabilizado']
            );

            if ($managedTransaction) $pdo->commit();
        } catch (\Throwable $e) {
            if ($managedTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
