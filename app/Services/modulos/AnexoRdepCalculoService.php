<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\CatalogoRdep;

/**
 * Resumen impositivo de una fila del Anexo RDEP, con las fórmulas que el SRI
 * recalcula al recibir el archivo (catálogo 2024, vigente desde 2022):
 *
 *   ingGravConEsteEmpl = suelSal + sobSuelComRemu + partUtil + impRentEmpl
 *   basImp  = suelSal + sobSuelComRemu + partUtil + intGrabGen + impRentEmpl
 *             − apoPerIess − aporPerIessConOtrosEmpls − exoDiscap − exoTerEd
 *   impRentCaus = tabla progresiva del ejercicio sobre basImp
 *   rebajaGastosPersonales = % × MIN(CFB × factor por cargas [× IPCEG], Σ gastos personales)
 *   impuestoRentaRebajaGastosPersonales = MAX(0, impRentCaus − rebaja)
 *
 * Los décimos, los fondos de reserva, el salario digno y los ingresos no gravados
 * NO entran en la base; los gastos personales tampoco (solo generan la rebaja).
 *
 * Exoneraciones (una sola, la más beneficiosa):
 *   - Tercera edad (65 años o más): hasta 1 fracción básica desgravada.
 *   - Discapacidad (tipo 02/03 y grado ≥ 30%): 60/70/80/100 % de 2 fracciones
 *     básicas según el grado.
 *   Ninguna puede dejar la base en negativo.
 */
class AnexoRdepCalculoService
{
    /**
     * @param array $f  fila del detalle (claves de anexo_rdep_detalle)
     * @param array $p  parámetros del anexo: fraccion_basica, canasta_basica,
     *                  porcentaje_rebaja, ipceg, factores (array), tramos (array)
     * @return array    campos calculados: ing_grav_este_empl, exo_discap, exo_ter_ed,
     *                  bas_imp, imp_rent_caus, rebaja_gastos, imp_rent_rebaja
     */
    public function calcular(array $f, array $p): array
    {
        $n = fn($k) => round((float) ($f[$k] ?? 0), 2);
        $fb = round((float) ($p['fraccion_basica'] ?? 0), 2);

        $ingGrav = round($n('suel_sal') + $n('sob_suel') + $n('part_util') + $n('imp_rent_empl'), 2);

        // Base antes de exoneraciones: lo gravado (este y otros empleadores) menos aportes.
        $baseSinExo = round($ingGrav + $n('int_grab_gen') - $n('apo_per_iess') - $n('apor_per_iess_otros'), 2);
        $topeExo = max(0.0, $baseSinExo);

        $exoTer = 0.0;
        if (!empty($f['tercera_edad']) && $this->esVerdadero($f['tercera_edad'])) {
            $exoTer = min($fb, $topeExo);
        }
        $exoDis = 0.0;
        $tipoDiscap = (string) ($f['tipo_discap'] ?? '01');
        if (in_array($tipoDiscap, ['02', '03'], true)) {
            $pct = CatalogoRdep::porcentajeExoneracionDiscapacidad((int) ($f['porcentaje_discap'] ?? 0));
            if ($pct > 0) {
                $exoDis = min(round(2 * $fb * $pct / 100, 2), $topeExo);
            }
        }
        // No se aplican a la vez: se toma la más beneficiosa para el trabajador.
        if ($exoDis > 0 && $exoTer > 0) {
            if ($exoDis >= $exoTer) $exoTer = 0.0; else $exoDis = 0.0;
        }
        $exoDis = round($exoDis, 2);
        $exoTer = round($exoTer, 2);

        $basImp = round(max(0.0, $baseSinExo - $exoDis - $exoTer), 2);
        $causado = ImpuestoRentaEmpleadoService::impuestoCausado($basImp, (array) ($p['tramos'] ?? []));

        $rebaja = $this->rebajaGastosPersonales($f, $p);
        $impRebaja = round(max(0.0, $causado - $rebaja), 2);

        return [
            'ing_grav_este_empl' => $ingGrav,
            'exo_discap'         => $exoDis,
            'exo_ter_ed'         => $exoTer,
            'bas_imp'            => $basImp,
            'imp_rent_caus'      => $causado,
            'rebaja_gastos'      => $rebaja,
            'imp_rent_rebaja'    => $impRebaja,
        ];
    }

    /** Suma de los seis rubros de gastos personales (TGP de la ficha). */
    public function totalGastosPersonales(array $f): float
    {
        $t = 0.0;
        foreach (['deduc_vivienda', 'deduc_salud', 'deduc_educ', 'deduc_aliment', 'deduc_vestim', 'deduc_turismo'] as $k) {
            $t += (float) ($f[$k] ?? 0);
        }
        return round($t, 2);
    }

    /**
     * Rebaja por gastos personales: % × MIN(tope, TGP). El tope es la canasta
     * básica por el factor de cargas (7, 9, 11, 14, 17, 20; 100 con discapacidad
     * o enfermedad catastrófica) y, en Galápagos, por el IPCEG.
     */
    public function rebajaGastosPersonales(array $f, array $p): float
    {
        $tgp = $this->totalGastosPersonales($f);
        if ($tgp <= 0) return 0.0;
        $canasta = (float) ($p['canasta_basica'] ?? 0);
        $pct     = (float) ($p['porcentaje_rebaja'] ?? 18);
        $factores = (array) ($p['factores'] ?? ImpuestoRentaEmpleadoService::FACTORES_DEFECTO);
        $especial = (string) ($f['enf_catastro'] ?? 'NO') === 'SI';
        $factor = ImpuestoRentaEmpleadoService::factorCanastas($factores, (int) ($f['num_cargas'] ?? 0), $especial);
        $tope = $canasta > 0 ? round($canasta * $factor, 2) : null;
        if ($tope !== null && (string) ($f['ben_galapagos'] ?? 'NO') === 'SI') {
            $tope = round($tope * (float) ($p['ipceg'] ?? 1.803), 2);
        }
        $base = $tope === null ? $tgp : min($tope, $tgp);
        return round($base * $pct / 100, 2);
    }

    /** ¿Cumplió 65 años al 31 de diciembre del ejercicio? */
    public function esTerceraEdad(?string $fechaNacimiento, int $anio): bool
    {
        if (!$fechaNacimiento) return false;
        $nac = \DateTime::createFromFormat('Y-m-d', substr($fechaNacimiento, 0, 10));
        if (!$nac) return false;
        $cierre = new \DateTime($anio . '-12-31');
        return $nac->diff($cierre)->y >= 65;
    }

    private function esVerdadero($v): bool
    {
        if (is_bool($v)) return $v;
        return in_array(strtolower((string) $v), ['1', 't', 'true'], true);
    }
}
