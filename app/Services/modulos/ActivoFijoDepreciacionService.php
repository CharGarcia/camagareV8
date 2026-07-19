<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\CatalogoNovedades;
use App\repositories\modulos\ActivoFijoDepreciacionRepository;
use App\repositories\modulos\ActivoFijoLoteRepository;
use App\repositories\modulos\ActivoFijoRepository;
use App\Rules\modulos\ActivoFijoDepreciacionRules;
use App\Services\LogSistemaService;

/**
 * Contabiliza la depreciación MENSUAL de todos los activos fijos de la empresa
 * en un solo lote/asiento consolidado (patrón análogo a RolAsientoService::contabilizar,
 * pero aquí el asiento se genera DENTRO de la misma transacción del lote: si el
 * asiento falla —p. ej. cuentas mal configuradas—, se revierte todo el lote).
 */
class ActivoFijoDepreciacionService
{
    public function __construct(
        private ActivoFijoRepository $activoRepository,
        private ActivoFijoLoteRepository $loteRepository,
        private ActivoFijoDepreciacionRepository $depreciacionRepository,
        private ActivoFijoDepreciacionRules $rules,
        private LogSistemaService $logService
    ) {
    }

    public function getListadoLotes(int $idEmpresa): array
    {
        return $this->loteRepository->getListado($idEmpresa);
    }

    /**
     * Períodos (año/mes) con activos pendientes de depreciar y sin lote contabilizado
     * todavía. Usado para el aviso informativo en Estados Financieros (§ver
     * EstadosFinancierosController) — solo lectura, no dispara ninguna generación.
     *
     * @param int|null $mes Si se indica, solo evalúa ese mes; si es null, evalúa los 12
     *                       meses del año (omitiendo los que aún no llegan).
     */
    public function getPeriodosPendientes(int $idEmpresa, int $anio, ?int $mes = null): array
    {
        $anioActual = (int) date('Y');
        $mesActual = (int) date('n');
        $meses = $mes ? [$mes] : range(1, 12);

        $pendientes = [];
        foreach ($meses as $m) {
            if ($anio > $anioActual || ($anio === $anioActual && $m > $mesActual)) {
                continue; // período futuro: aún no aplica generar su depreciación.
            }
            if ($this->loteRepository->existsLote($idEmpresa, $anio, $m)) {
                continue;
            }
            if (!empty($this->activoRepository->getActivosDepreciables($idEmpresa, $anio, $m))) {
                $pendientes[] = [
                    'anio'       => $anio,
                    'mes'        => $m,
                    'mes_nombre' => CatalogoNovedades::MESES[$m] ?? (string) $m,
                ];
            }
        }
        return $pendientes;
    }

    public function getLote(int $idLote, int $idEmpresa): ?array
    {
        $lote = $this->loteRepository->getPorId($idLote, $idEmpresa);
        if (!$lote) {
            return null;
        }
        $lote['detalle'] = $this->depreciacionRepository->getPorLote($idLote);
        return $lote;
    }

    public function generarLote(int $idEmpresa, int $anio, int $mes, int $idUsuario): array
    {
        $this->rules->validarPeriodo($anio, $mes);

        $mesNombre = CatalogoNovedades::MESES[$mes] ?? (string) $mes;

        if ($this->loteRepository->existsLote($idEmpresa, $anio, $mes)) {
            throw new \Exception("La depreciación de $mesNombre $anio ya fue generada.");
        }

        $activos = $this->activoRepository->getActivosDepreciables($idEmpresa, $anio, $mes);
        if (empty($activos)) {
            throw new \Exception('No hay activos pendientes de depreciar en este período.');
        }

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            $idLote = $this->loteRepository->insertLote([
                'id_empresa'       => $idEmpresa,
                'periodo_anio'     => $anio,
                'periodo_mes'      => $mes,
                'cantidad_activos' => 0,
                'total_depreciado' => 0,
                'estado'           => 'contabilizado',
                'id_usuario'       => $idUsuario,
            ]);

            $totalDepreciado = 0.0;
            $cantidad = 0;

            foreach ($activos as $activo) {
                $acumuladoActual = round((float) $activo['depreciacion_acumulada'], 2);
                $depreciable = round((float) $activo['valor_depreciable'], 2);
                $porcentaje = (float) $activo['porcentaje_depreciacion_anual'];

                $cuota = round($depreciable * $porcentaje / 100 / 12, 2);
                if ($acumuladoActual + $cuota >= $depreciable) {
                    $cuota = round($depreciable - $acumuladoActual, 2);
                }
                if ($cuota <= 0.0) {
                    continue;
                }

                $nuevoAcumulado = round($acumuladoActual + $cuota, 2);
                $nuevoValorLibros = round((float) $activo['valor_adquisicion'] - $nuevoAcumulado, 2);
                $nuevoEstado = $nuevoAcumulado >= $depreciable ? 'depreciado_total' : 'activo';

                $this->depreciacionRepository->insertDetalle([
                    'id_empresa'                    => $idEmpresa,
                    'id_activo'                      => $activo['id'],
                    'id_lote'                         => $idLote,
                    'periodo_anio'                     => $anio,
                    'periodo_mes'                       => $mes,
                    'valor_depreciado'                   => $cuota,
                    'depreciacion_acumulada_after'        => $nuevoAcumulado,
                    'valor_libros_after'                   => $nuevoValorLibros,
                    'id_usuario'                             => $idUsuario,
                ]);

                $this->activoRepository->actualizarTrasDepreciacion((int) $activo['id'], $nuevoAcumulado, $nuevoValorLibros, $nuevoEstado);

                $totalDepreciado += $cuota;
                $cantidad++;
            }

            if ($cantidad === 0) {
                throw new \Exception('No hay valores por depreciar en este período.');
            }

            // Asiento consolidado (agrupado por categoría), dentro de la misma transacción.
            $builder = new AsientoBuilderService();
            $detallesSugeridos = $builder->generarAsientoDepreciacionLote($idEmpresa, $idLote);
            if (empty($detallesSugeridos)) {
                throw new \Exception('No se pudo armar el asiento de depreciación: verifique las cuentas configuradas en las categorías.');
            }

            $detalles = [];
            foreach ($detallesSugeridos as $det) {
                $detalles[] = [
                    'id_cuenta_contable'   => $det['id_cuenta_contable'],
                    'debe'                 => $det['debe'],
                    'haber'                => $det['haber'],
                    'referencia_detalle'   => $det['referencia_detalle'] ?? 'Depreciación de activos fijos',
                    'documento_referencia' => "Depreciación $mesNombre $anio",
                ];
            }

            $fechaAsiento = date('Y-m-t', mktime(0, 0, 0, $mes, 1, $anio));

            $asientoRepo    = new \App\repositories\modulos\AsientoContableRepository();
            $asientoRules   = new \App\Rules\modulos\AsientoContableRules();
            $asientoService = new AsientoContableService($asientoRepo, $asientoRules, $this->logService);

            $cabeceraData = [
                'id'                   => null,
                'fecha_asiento'        => $fechaAsiento,
                'tipo_comprobante'     => 'diario',
                'numero_comprobante'   => '',
                'concepto'             => "Depreciación de Activos Fijos - $mesNombre $anio",
                'estado'               => 'contabilizado',
                'modulo_origen'        => 'activos_fijos_depreciacion',
                'id_referencia_origen' => $idLote,
                'observaciones'        => null,
            ];

            $idAsiento = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);

            $this->loteRepository->updateLote($idLote, [
                'cantidad_activos'    => $cantidad,
                'total_depreciado'    => round($totalDepreciado, 2),
                'estado'              => 'contabilizado',
                'id_asiento_contable' => $idAsiento,
                'id_usuario'          => $idUsuario,
            ]);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'generar_lote',
                'activos_fijos_lotes',
                $idLote,
                null,
                ['periodo_anio' => $anio, 'periodo_mes' => $mes, 'cantidad_activos' => $cantidad, 'total_depreciado' => $totalDepreciado, 'id_asiento' => $idAsiento]
            );

            $db->commit();

            return [
                'id_lote'          => $idLote,
                'cantidad_activos' => $cantidad,
                'total_depreciado' => round($totalDepreciado, 2),
                'id_asiento'       => $idAsiento,
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
