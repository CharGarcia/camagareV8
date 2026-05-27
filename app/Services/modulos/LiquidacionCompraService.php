<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\LiquidacionCompraRepository;
use App\Rules\modulos\LiquidacionCompraRules;
use App\Services\LogSistemaService;

class LiquidacionCompraService
{
    private $repository;
    private $rules;
    private $logService;

    public function __construct(LiquidacionCompraRepository $repository, LiquidacionCompraRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $empresaConfig = $data['empresa_config'] ?? [];
        $data['tipo_ambiente'] = (string) ($empresaConfig['tipo_ambiente'] ?? '1');
        $data['tipo_emision']  = (string) ($empresaConfig['tipo_emision']  ?? '1');

        $data['clave_acceso'] = \App\Services\ClaveAccesoService::generar(
            (string) ($data['fecha_emision']   ?? ''),
            \App\Services\ClaveAccesoService::LIQUIDACION_COMPRA,
            (string) ($empresaConfig['ruc']    ?? ''),
            $data['tipo_ambiente'],
            (string) ($data['establecimiento'] ?? ''),
            (string) ($data['punto_emision']   ?? ''),
            (string) ($data['secuencial']      ?? ''),
            $data['tipo_emision']
        );

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();

        try {
            $id = $this->repository->insertCabecera($data);

            foreach ($data['detalles'] as $det) {
                $det['id_cabecera'] = $id;
                $idDetalle = $this->repository->insertDetalle($det);

                if (!empty($det['impuestos'])) {
                    foreach ($det['impuestos'] as $imp) {
                        $imp['id_detalle'] = $idDetalle;
                        $this->repository->insertImpuesto($imp);
                    }
                }
            }

            foreach ($data['pagos'] as $pago) {
                $pago['id_cabecera'] = $id;
                $this->repository->insertPago($pago);
            }

            if (!empty($data['info_adicional'])) {
                foreach ($data['info_adicional'] as $info) {
                    $info['id_cabecera'] = $id;
                    $this->repository->insertInfoAdicional($info);
                }
            }

            $this->logService->registrar(
                (int) $data['id_usuario'],
                (int) $data['id_empresa'],
                'crear',
                'liquidaciones_cabecera',
                (int) $id,
                null,
                $data
            );

            $db->commit();
            $this->generarYGuardarXml($id, $data);
            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, array $data): int
    {
        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera) {
            throw new \Exception('Liquidación no encontrada.');
        }

        $this->rules->validar($data);

        $empresaConfig = $data['empresa_config'] ?? [];
        $data['tipo_ambiente'] = (string) ($empresaConfig['tipo_ambiente'] ?? '1');
        $data['tipo_emision']  = (string) ($empresaConfig['tipo_emision']  ?? '1');

        $codigoNumerico = \App\Services\ClaveAccesoService::extraerCodigoNumerico($cabecera['clave_acceso'] ?? '');
        $data['clave_acceso'] = \App\Services\ClaveAccesoService::generar(
            (string) ($data['fecha_emision']  ?? ''),
            \App\Services\ClaveAccesoService::LIQUIDACION_COMPRA,
            (string) ($empresaConfig['ruc']   ?? ''),
            $data['tipo_ambiente'],
            (string) ($data['establecimiento'] ?? ''),
            (string) ($data['punto_emision']   ?? ''),
            (string) ($data['secuencial']      ?? ''),
            $data['tipo_emision'],
            $codigoNumerico
        );

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();

        try {
            $this->repository->updateCabecera($id, $data);

            // Reemplazar detalles
            $this->repository->deleteDetalles($id);
            foreach ($data['detalles'] as $det) {
                $det['id_cabecera'] = $id;
                $idDetalle = $this->repository->insertDetalle($det);

                if (!empty($det['impuestos'])) {
                    foreach ($det['impuestos'] as $imp) {
                        $imp['id_detalle'] = $idDetalle;
                        $this->repository->insertImpuesto($imp);
                    }
                }
            }

            // Reemplazar pagos
            $this->repository->deletePagos($id);
            foreach ($data['pagos'] as $pago) {
                $pago['id_cabecera'] = $id;
                $this->repository->insertPago($pago);
            }

            // Reemplazar info adicional
            $this->repository->deleteInfoAdicional($id);
            if (!empty($data['info_adicional'])) {
                foreach ($data['info_adicional'] as $info) {
                    $info['id_cabecera'] = $id;
                    $this->repository->insertInfoAdicional($info);
                }
            }

            $this->logService->registrar(
                (int) $data['id_usuario'],
                (int) $data['id_empresa'],
                'actualizar',
                'liquidaciones_cabecera',
                (int) $id,
                null,
                $data
            );

            $db->commit();
            $this->generarYGuardarXml($id, $data);
            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function anular(int $id, int $idEmpresa, int $idUsuario): void
    {
        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera || (int)$cabecera['id_empresa'] !== $idEmpresa) {
            throw new \Exception('Liquidación no encontrada.');
        }
        if (($cabecera['estado'] ?? '') === 'anulado') {
            throw new \Exception('La liquidación ya está anulada.');
        }

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            $st = $db->prepare("UPDATE liquidaciones_cabecera SET estado = 'anulado', updated_at = NOW(), updated_by = ? WHERE id = ? AND id_empresa = ?");
            $st->execute([$idUsuario, $id, $idEmpresa]);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ANULAR',
                'liquidaciones_cabecera',
                $id,
                $cabecera,
                ['nuevo_estado' => 'anulado']
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $db = \App\core\Database::getConnection();
        $db->beginTransaction();

        try {
            $sql = "UPDATE liquidaciones_cabecera SET eliminado = true, deleted_at = NOW(), deleted_by = ? WHERE id = ? AND id_empresa = ?";
            $st = $db->prepare($sql);
            $st->execute([$idUsuario, $id, $idEmpresa]);

            $this->logService->registrar(
                (int) $idUsuario,
                (int) $idEmpresa,
                'eliminar',
                'liquidaciones_cabecera',
                (int) $id
            );

            $db->commit();
            return true;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ── XML en base de datos ──────────────────────────────────────────────────

    private function generarYGuardarXml(int $idLiq, array $data): void
    {
        try {
            $cabecera = $this->repository->getPorId($idLiq);
            if (!$cabecera) return;

            $detalles = $this->repository->getDetalles($idLiq);
            foreach ($detalles as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);

            $pagos         = $this->repository->getPagos($idLiq);
            $infoAdicional = $this->repository->getInfoAdicional($idLiq);

            $idEmpresa    = (int) $cabecera['id_empresa'];
            $empresaModel = new \App\models\Empresa();
            $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

            $dirEstablecimiento = null;
            if (!empty($cabecera['id_establecimiento'])) {
                try {
                    $estRepo = new \App\repositories\modulos\EmpresaRepository();
                    foreach ($estRepo->getEstablecimientos($idEmpresa) as $est) {
                        if ((int)$est['id'] === (int)$cabecera['id_establecimiento']) {
                            $dirEstablecimiento = $est['direccion'] ?? null;
                            break;
                        }
                    }
                } catch (\Throwable) {}
            }

            // XmlLiquidacionCompraService genera el XML del comprobante
            $xml = (new \App\Services\Xml\XmlLiquidacionCompraService())->generar(
                $cabecera, $detalles, $pagos, $infoAdicional, $empresa, $dirEstablecimiento
            );
            $this->repository->updateDetalleXml($idLiq, $xml);
        } catch (\Throwable $e) {
            error_log('[Liq] Error generando XML para liquidación #' . $idLiq . ': ' . $e->getMessage());
        }
    }
}
