<?php
declare(strict_types=1);

namespace App\Services;

use App\repositories\ComprobacionContableRepository;
use App\Rules\RangoFechasRules;

/**
 * Comprobación con Contabilidad (solo lectura), común a los módulos que llevan un saldo
 * propio que debe cuadrar con sus cuentas contables: Reporte de Inventarios, Cuentas por
 * Cobrar y Cuentas por Pagar. Control Bancario tiene la suya (mismo criterio y misma
 * pantalla), anterior a este motor.
 *
 * Cada módulo arma su definición en su repositorio (qué documentos y montos forman su
 * saldo, qué cuentas contables lo representan y cómo se enlaza cada asiento a su
 * documento) y este Service devuelve el resultado listo para la pantalla común
 * (public/js/comprobacion_contable.js).
 */
class ComprobacionContableService
{
    public const LIMITE_PARTIDAS = 3000;

    private ComprobacionContableRepository $repository;

    public function __construct(?ComprobacionContableRepository $repository = null)
    {
        $this->repository = $repository ?? new ComprobacionContableRepository();
    }

    public function getRepository(): ComprobacionContableRepository
    {
        return $this->repository;
    }

    /**
     * Solo los saldos de cada lado al inicio y al fin del período, sin el detalle por
     * documento (para el cuadro "Cuadre con módulos" de Estados Financieros).
     */
    public function resumir(array $def, int $idEmpresa, string $fechaInicio, string $fechaFin): array
    {
        $def = $this->prepararDefinicion($def, $idEmpresa, $fechaInicio, $fechaFin);
        if ($def === null) {
            return ['sin_cuentas' => true];
        }
        $t = $this->repository->getTotales($def, $idEmpresa, $fechaInicio, $fechaFin);
        return ['sin_cuentas' => false, 'cuentas' => $def['cuentas_detalle']] + $this->saldos($t);
    }

    /**
     * Saldo de cada cuenta al inicio y al fin del período (signo: 1 deudora, −1 acreedora).
     * Para módulos con su propio cruce (Control Bancario) que muestran la pantalla común.
     */
    public function saldosPorCuentas(array $idsCuentas, int $signo, int $idEmpresa, string $fechaInicio, string $fechaFin): array
    {
        return $this->repository->getSaldosPorCuenta(['cuentas' => $idsCuentas, 'signo' => $signo], $idEmpresa, $fechaInicio, $fechaFin);
    }

    /** Valida el período y resuelve las cuentas de la definición; null si no tiene cuentas. */
    private function prepararDefinicion(array $def, int $idEmpresa, string $fechaInicio, string $fechaFin): ?array
    {
        if ($fechaInicio === '' || $fechaFin === '') {
            throw new \InvalidArgumentException('Debe indicar el período a comprobar (Desde y Hasta).');
        }
        (new RangoFechasRules())->validar($fechaInicio, $fechaFin);

        // Cuentas del módulo: las fijas que trae la definición o, si no, las configuradas en
        // Configuración Contable para sus conceptos.
        $cuentas = array_key_exists('cuentas_ids', $def)
            ? $this->repository->getCuentasPorIds($idEmpresa, array_filter((array) $def['cuentas_ids']))
            : $this->repository->getCuentasPorConceptos(
                $idEmpresa,
                $def['conceptos'] ?? [],
                $def['patron_concepto'] ?? null
            );
        if (!$cuentas) {
            return null;
        }
        $def['cuentas'] = array_column($cuentas, 'id');
        $def['cuentas_detalle'] = $cuentas;
        return $def;
    }

    /** Saldos redondeados de cada lado y sus diferencias, a partir de los totales. */
    private function saldos(array $t): array
    {
        $librosIni = round($t['doc_ini'], 2);
        $librosFin = round($t['doc_fin'], 2);
        $contIni = round($t['cont_ini'], 2);
        $contFin = round($t['cont_fin'], 2);
        return [
            'inicio' => ['libros' => $librosIni, 'contable' => $contIni, 'diferencia' => round($librosIni - $contIni, 2)],
            'fin' => ['libros' => $librosFin, 'contable' => $contFin, 'diferencia' => round($librosFin - $contFin, 2)],
            'diferencia_periodo' => round(($librosFin - $librosIni) - ($contFin - $contIni), 2),
        ];
    }

    /**
     * @param array $def definición del módulo (ver ComprobacionContableRepository).
     */
    public function comprobar(array $def, int $idEmpresa, string $fechaInicio, string $fechaFin): array
    {
        $conceptosTexto = $def['conceptos_texto'] ?? '';
        $def = $this->prepararDefinicion($def, $idEmpresa, $fechaInicio, $fechaFin);
        if ($def === null) {
            return ['sin_cuentas' => true, 'conceptos' => $conceptosTexto];
        }

        $t = $this->repository->getTotales($def, $idEmpresa, $fechaInicio, $fechaFin);
        $saldos = $this->saldos($t);
        $limite = self::LIMITE_PARTIDAS;
        $partidas = $this->repository->getPartidas($def, $idEmpresa, $fechaInicio, $fechaFin, $limite + 1);
        $truncado = count($partidas) > $limite;
        $partidas = array_slice($partidas, 0, $limite);

        // Mayor comparado: cada lado arranca en su saldo al inicio y arrastra fila por fila;
        // la primera fila donde cambia la diferencia acumulada es donde se descuadra.
        $saldoLibros = $saldos['inicio']['libros'];
        $saldoCont = $saldos['inicio']['contable'];
        $resumenClases = [];
        $conDiferencia = 0;
        foreach ($partidas as &$p) {
            $saldoLibros = round($saldoLibros + (float) $p['efecto_doc'], 2);
            $saldoCont = round($saldoCont + (float) $p['efecto_contable'], 2);
            $p['saldo_libros'] = $saldoLibros;
            $p['saldo_contable'] = $saldoCont;
            $p['diferencia_acumulada'] = round($saldoLibros - $saldoCont, 2);

            $c = $p['clase'];
            if ($c === 'cuadra') {
                continue;
            }
            $conDiferencia++;
            $resumenClases[$c]['cantidad'] = ($resumenClases[$c]['cantidad'] ?? 0) + 1;
            $resumenClases[$c]['diferencia'] = round(($resumenClases[$c]['diferencia'] ?? 0) + (float) $p['diferencia'], 2);
        }
        unset($p);

        return [
            'sin_cuentas' => false,
            'cuentas' => $this->repository->getSaldosPorCuenta($def, $idEmpresa, $fechaInicio, $fechaFin),
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
        ] + $saldos + [
            'resumen_clases' => $resumenClases,
            'partidas' => $partidas,
            'partidas_con_diferencia' => $conDiferencia,
            'truncado' => $truncado,
            'limite' => $limite,
        ];
    }
}
