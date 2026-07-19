<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ActivoFijoCategoriaRepository;
use App\repositories\modulos\ActivoFijoLoteRepository;
use App\repositories\modulos\ActivoFijoRepository;
use App\repositories\modulos\ComprasRepository;
use App\Rules\modulos\ActivoFijoRules;
use App\Services\LogSistemaService;

class ActivoFijoService
{
    public function __construct(
        private ActivoFijoRepository $repository,
        private ActivoFijoCategoriaRepository $categoriaRepository,
        private ActivoFijoLoteRepository $loteRepository,
        private ComprasRepository $comprasRepository,
        private ActivoFijoRules $rules,
        private LogSistemaService $logService
    ) {
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuario): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuario);
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        return $this->repository->getPorId($id, $idEmpresa);
    }

    public function getHistorialDepreciaciones(int $idActivo): array
    {
        return $this->repository->getHistorialDepreciaciones($idActivo);
    }

    public function crear(array $data): int
    {
        $data['origen'] = $data['origen'] ?? 'manual';

        if ($data['origen'] === 'compra') {
            $detalleCompra = $this->comprasRepository->getDetalleById((int) ($data['id_compra_detalle'] ?? 0), (int) $data['id_empresa']);
            if (!$detalleCompra) {
                throw new \Exception('La línea de factura de compra seleccionada no existe.');
            }
            $data['id_compra'] = (int) $detalleCompra['id_compra'];
            $data['id_proveedor'] = (int) $detalleCompra['id_proveedor'];
            $data['valor_adquisicion'] = (float) $detalleCompra['precio_total_sin_impuesto'];
            $data['fecha_adquisicion'] = $detalleCompra['fecha_emision'];
            if (empty($data['nombre'])) {
                $data['nombre'] = $detalleCompra['descripcion'];
            }
            $data['proveedor_texto'] = null;
        } else {
            $data['id_compra'] = null;
            $data['id_compra_detalle'] = null;
        }

        $this->rules->validar($data);

        $categoria = $this->categoriaRepository->getPorId((int) $data['id_categoria'], (int) $data['id_empresa']);

        $valorAdquisicion = round((float) $data['valor_adquisicion'], 2);
        $valorResidual = round((float) ($data['valor_residual'] ?? 0), 2);
        $porcentaje = (float) $categoria['porcentaje_depreciacion_anual'];

        $data['valor_adquisicion'] = $valorAdquisicion;
        $data['valor_residual'] = $valorResidual;
        $data['valor_depreciable'] = round($valorAdquisicion - $valorResidual, 2);
        $data['porcentaje_depreciacion_anual'] = $porcentaje;
        $data['meses_vida_util'] = $porcentaje > 0 ? (int) round(1200 / $porcentaje) : 0;
        $data['depreciacion_acumulada'] = 0.0;
        $data['valor_en_libros'] = $valorAdquisicion;
        $data['estado'] = 'activo';
        $data['fecha_inicio_depreciacion'] = $this->calcularInicioDepreciacion((int) $data['id_empresa'], (string) $data['fecha_adquisicion']);

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            $id = $this->repository->insert($data);
            $this->logService->registrar(
                (int) $data['id_usuario'],
                (int) $data['id_empresa'],
                'crear',
                'activos_fijos',
                $id,
                null,
                $data
            );
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        // Asiento de alta SOLO si es manual (una compra ya tiene su propio asiento).
        // Fuera de la transacción: un fallo de configuración contable no revierte el alta.
        if ($data['origen'] === 'manual') {
            try {
                $this->procesarAsientoAlta($id, $data);
            } catch (\Throwable $eAs) {
                error_log("[ActivoFijo] Asiento de alta no generado para activo $id: " . $eAs->getMessage());
            }
        }

        return $id;
    }

    public function actualizar(int $id, array $data): int
    {
        $idEmpresa = (int) $data['id_empresa'];
        $activo = $this->repository->getPorId($id, $idEmpresa);
        if (!$activo) {
            throw new \Exception('Activo fijo no encontrado.');
        }

        $this->rules->validarEdicion($activo, $data);

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            if ($this->repository->tieneDepreciacionesGeneradas($id)) {
                $this->repository->updateDescriptivo($id, $data);
            } else {
                $valorResidual = round((float) ($data['valor_residual'] ?? $activo['valor_residual']), 2);
                $data['valor_residual'] = $valorResidual;
                $data['valor_depreciable'] = round((float) $activo['valor_adquisicion'] - $valorResidual, 2);
                $this->repository->update($id, $data);
            }
            $this->logService->registrar(
                (int) $data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'activos_fijos',
                $id,
                $activo,
                $data
            );
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        return $id;
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $this->rules->validarEliminacion($id);
        $this->repository->softDelete($id, $idEmpresa, $idUsuario);
        $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'activos_fijos', $id, null, null);
        return true;
    }

    /**
     * Arma (vía AsientoBuilderService::generarAsientoAltaActivoFijo) y persiste el
     * asiento de alta manual. Idempotente: si ya existe asiento para este activo lo actualiza.
     */
    public function procesarAsientoAlta(int $idActivo, array $data): void
    {
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) ($data['id_usuario'] ?? $_SESSION['id_usuario'] ?? 0);

        $builder = new AsientoBuilderService();
        $detallesSugeridos = $builder->generarAsientoAltaActivoFijo($idEmpresa, $idActivo);
        if (empty($detallesSugeridos)) {
            return;
        }

        $detalles = [];
        foreach ($detallesSugeridos as $det) {
            $detalles[] = [
                'id_cuenta_contable'   => $det['id_cuenta_contable'],
                'debe'                 => $det['debe'],
                'haber'                => $det['haber'],
                'referencia_detalle'   => $det['referencia_detalle'] ?? 'Alta de activo fijo',
                'documento_referencia' => 'Activo Fijo #' . $idActivo,
            ];
        }

        $asientoRepo    = new \App\repositories\modulos\AsientoContableRepository();
        $asientoRules   = new \App\Rules\modulos\AsientoContableRules();
        $asientoService = new AsientoContableService($asientoRepo, $asientoRules, $this->logService);

        $asientoPrevio = $asientoService->getAsientoPorOrigen('activos_fijos_alta', $idActivo, $idEmpresa);
        $idAsiento = $asientoPrevio ? (int) $asientoPrevio['id'] : 0;

        $cabeceraData = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $data['fecha_adquisicion'],
            'tipo_comprobante'     => 'diario',
            'numero_comprobante'   => '',
            'concepto'             => 'Alta de activo fijo: ' . $data['nombre'],
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'activos_fijos_alta',
            'id_referencia_origen' => $idActivo,
            'observaciones'        => $data['observaciones'] ?? null,
        ];

        $idAsientoGenerado = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);
        $this->repository->setAsientoAlta($idActivo, $idAsientoGenerado);
    }

    /**
     * Primer día del mes de la fecha de adquisición; si ese período ya tiene un lote
     * de depreciación contabilizado, se recorre al primer día del mes siguiente abierto
     * (sin depreciación retroactiva en v1).
     */
    private function calcularInicioDepreciacion(int $idEmpresa, string $fechaAdquisicion): string
    {
        $ts = strtotime($fechaAdquisicion) ?: time();
        $anio = (int) date('Y', $ts);
        $mes = (int) date('n', $ts);

        $intentos = 0;
        while ($this->loteRepository->existsLote($idEmpresa, $anio, $mes) && $intentos < 240) {
            $mes++;
            if ($mes > 12) {
                $mes = 1;
                $anio++;
            }
            $intentos++;
        }

        return sprintf('%04d-%02d-01', $anio, $mes);
    }
}
