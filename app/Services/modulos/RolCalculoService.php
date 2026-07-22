<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\CatalogoNovedades;

/**
 * Motor de cálculo de una línea de rol (por empleado). Función pura: recibe los
 * datos ya cargados y devuelve el desglose de ingresos/egresos + totales.
 *
 * Reglas (confirmadas):
 *  - Base: MENSUAL=sueldo_base prorrateado por días trabajados (ingreso/salida),
 *    QUINCENA=valor_quincena, SEMANAL=valor_semanal.
 *  - Prorrateo (solo MENSUAL): factor = díasTrabajados/30. Aplica a sueldo, décimo
 *    tercero/cuarto mensualizados y fondos de reserva. La base del IESS hereda el
 *    sueldo ya prorrateado. Las faltas adicionales se cargan con la novedad 10.
 *  - Horas (novedades 4/5/6): tarifa = sueldo/hora_normal * (1+recargo%) * nº horas.
 *  - IESS personal (solo MENSUAL): base_iess * empleado.aporte_personal%. La base
 *    incluye sueldo + horas + rubros/ingresos marcados "aporta IESS".
 *  - Fondos de reserva 8.33% del sueldo si fondos_reserva='rol' (solo MENSUAL).
 *  - Décimos si 'mensualiza' (13º = sueldo/12, 14º = SBU/12) (solo MENSUAL).
 *  - Días no laborados (novedad 10): días * sueldo/30 como egreso.
 *  - Anticipo (novedad 3): NO se descuenta al registrarse; se paga por egreso y el rol
 *    descuenta solo lo pagado ($anticiposPagados[id_novedad]).
 *  - Descuento (2): egreso directo por su valor.
 *  - Préstamos (7,8,9): la cuota descuenta directo SOLO si el préstamo ya fue desembolsado
 *    (pagado por egreso); si no, no descuenta ($prestamosNoDesembolsados[id_novedad]).
 *  - Neteo (solo MENSUAL): resta lo ya pagado en SEMANAL/QUINCENA del mes.
 */
class RolCalculoService
{
    public function calcular(array $emp, string $tipo, array $salario, array $rubrosFijos, array $novedades, float $neteo = 0.0, float $vacaciones = 0.0, int $diasTrabajados = 30, array $anticiposPagados = [], array $prestamosNoDesembolsados = [], array $tramosIr = [], float $rebajaGastosPersonalesAnual = 0.0): array
    {
        $esMensual = $tipo === 'MENSUAL';
        $rubros = [];
        $ingresos = 0.0;
        $egresos = 0.0;
        $baseIess = 0.0;

        $sueldoBase   = (float) ($emp['sueldo_base'] ?? 0);
        $sueldoDiario = $sueldoBase > 0 ? $sueldoBase / 30 : 0.0;

        // Días efectivamente laborados en el mes (prorrateo por fecha de ingreso/salida).
        // Solo aplica al MENSUAL; QUINCENA/SEMANAL usan su valor fijo.
        $diasTrab = $esMensual ? max(0, min(30, $diasTrabajados)) : 30;
        $factor   = $diasTrab / 30;

        // 1) Base de la corrida (el sueldo mensual se prorratea por días trabajados)
        $base = match ($tipo) {
            'QUINCENA' => (float) ($emp['valor_quincena'] ?? 0),
            'SEMANAL'  => (float) ($emp['valor_semanal'] ?? 0),
            default    => round($sueldoBase * $factor, 2),
        };
        $conceptoBase = match ($tipo) {
            'QUINCENA' => 'Quincena',
            'SEMANAL'  => 'Semana',
            default    => $diasTrab < 30 ? "Sueldo ({$diasTrab} días)" : 'Sueldo',
        };
        $rubros[] = $this->r('ingreso', $conceptoBase, null, 'sueldo', $base, $esMensual);
        $ingresos += $base;
        if ($esMensual) $baseIess += $base;

        // 2) Novedades del período (ya filtradas por aplica_en)
        $horaNormal   = (float) ($salario['hora_normal'] ?? 240) ?: 240;
        $recNocturna  = (float) ($salario['hora_nocturna'] ?? 25);
        $recSuplement = (float) ($salario['hora_suplementaria'] ?? 50);
        $recExtra     = (float) ($salario['hora_extraordinaria'] ?? 100);
        $tarifaHora   = $sueldoBase > 0 ? $sueldoBase / $horaNormal : 0.0;

        foreach ($novedades as $n) {
            $cod = (string) $n['tipo_codigo'];
            $val = (float) $n['valor'];
            $nom = (string) ($n['tipo_nombre'] ?? CatalogoNovedades::nombreTipo($cod));
            $idn = (int) $n['id'];

            if (CatalogoNovedades::esAvisoSalida($cod)) {
                continue; // evento, sin monto
            }

            // Horas (4 nocturnas, 5 suplementarias, 6 extraordinarias)
            if (in_array($cod, CatalogoNovedades::CODS_HORAS, true)) {
                $rec = match ($cod) { '4' => $recNocturna, '5' => $recSuplement, default => $recExtra };
                $monto = round($tarifaHora * (1 + $rec / 100) * $val, 2);
                $rubros[] = $this->r('ingreso', $nom . ' (' . $val . 'h)', $cod, 'novedad', $monto, true, $idn);
                $ingresos += $monto;
                if ($esMensual) $baseIess += $monto;
                continue;
            }

            // Anticipos / Préstamos: NO se descuentan al registrarse. Se pagan por egreso
            // (Egresos → Nómina) y el rol descuenta SOLO lo pagado por egreso contra la novedad.
            if (CatalogoNovedades::esPagoPorEgreso($cod)) {
                $pagado = round((float) ($anticiposPagados[$idn] ?? 0), 2);
                if ($pagado > 0.001) {
                    $rubros[] = $this->r('egreso', $nom, $cod, 'novedad', $pagado, false, $idn);
                    $egresos += $pagado;
                }
                continue; // sin pago → no descuenta (no genera rubro)
            }

            // Préstamos (cuota): descuento directo, PERO solo si el préstamo ya fue desembolsado.
            if (CatalogoNovedades::esPrestamo($cod)) {
                if (isset($prestamosNoDesembolsados[$idn])) {
                    continue; // préstamo aún no desembolsado (pagado) → no descuenta la cuota
                }
                $rubros[] = $this->r('egreso', $nom, $cod, 'novedad', $val, false, $idn);
                $egresos += $val;
                continue;
            }

            switch ($cod) {
                case '1': // Otros Ingresos
                    $rubros[] = $this->r('ingreso', $nom, $cod, 'novedad', $val, false, $idn);
                    $ingresos += $val;
                    break;
                case '10': // Días no laborados -> egreso valorizado
                    $monto = round($val * $sueldoDiario, 2);
                    $rubros[] = $this->r('egreso', $nom . ' (' . $val . 'd)', $cod, 'novedad', $monto, false, $idn);
                    $egresos += $monto;
                    break;
                default: // 2 Descuento: descuento directo, sin egreso
                    $rubros[] = $this->r('egreso', $nom, $cod, 'novedad', $val, false, $idn);
                    $egresos += $val;
                    break;
            }
        }

        // 3) Rubros fijos del empleado (según frecuencia vs tipo)
        foreach ($rubrosFijos as $rf) {
            if (!$this->rubroAplica($rf['frecuencia'] ?? null, $tipo)) continue;
            $val = (float) $rf['valor'];
            $ai  = $this->esVerdadero($rf['aporta_iess'] ?? false);
            $nom = (string) $rf['nombre'];
            if (($rf['tipo'] ?? 'ingreso') === 'ingreso') {
                $rubros[] = $this->r('ingreso', $nom, null, 'rubro_fijo', $val, $ai);
                $ingresos += $val;
                if ($esMensual && $ai) $baseIess += $val;
            } else {
                $rubros[] = $this->r('egreso', $nom, null, 'rubro_fijo', $val, false);
                $egresos += $val;
            }
        }

        // 3b) Vacaciones que alimentan el rol (módulo de vacaciones, solo MENSUAL).
        if ($esMensual && $vacaciones > 0) {
            $rubros[] = $this->r('ingreso', 'Vacaciones', null, 'vacaciones', $vacaciones, true);
            $ingresos += $vacaciones;
            $baseIess += $vacaciones;
        }

        // 4) Beneficios (solo MENSUAL)
        $aportePatronal = 0.0;
        if ($esMensual) {
            // Fondos de reserva (proporcional a días trabajados)
            if (($emp['fondos_reserva'] ?? '') === 'rol' && $sueldoBase > 0) {
                $pctFR = (float) ($salario['fondo_reserva'] ?? 8.33);
                $fr = round($sueldoBase * $factor * $pctFR / 100, 2);
                $rubros[] = $this->r('ingreso', 'Fondos de Reserva', null, 'fondos', $fr, false);
                $ingresos += $fr;
            }
            // Décimos mensualizados (proporcional a días trabajados)
            if (($emp['decimo_tercero'] ?? '') === 'mensualiza' && $sueldoBase > 0) {
                $dt = round($sueldoBase * $factor / 12, 2);
                $rubros[] = $this->r('ingreso', 'Décimo Tercero', null, 'decimo', $dt, false);
                $ingresos += $dt;
            }
            if (($emp['decimo_cuarto'] ?? '') === 'mensualiza') {
                $dc = round(((float) ($salario['sbu'] ?? 0)) * $factor / 12, 2);
                if ($dc > 0) {
                    $rubros[] = $this->r('ingreso', 'Décimo Cuarto', null, 'decimo', $dc, false);
                    $ingresos += $dc;
                }
            }
        }

        // 5) IESS personal (solo MENSUAL)
        $aporteIess = 0.0;
        if ($esMensual) {
            $pctPer = (float) ($emp['aporte_personal'] ?? 9.45);
            $aporteIess = round($baseIess * $pctPer / 100, 2);
            if ($aporteIess > 0) {
                $rubros[] = $this->r('egreso', 'Aporte IESS Personal (' . $pctPer . '%)', null, 'iess', $aporteIess, false);
                $egresos += $aporteIess;
            }
            $pctPat = (float) ($emp['aporte_patronal'] ?? 12.15);
            $aportePatronal = round($baseIess * $pctPat / 100, 2);
        }

        // 5b) Impuesto a la Renta (retención en la fuente, relación de dependencia).
        // Solo MENSUAL; requiere tabla de tramos del año cargada y que el empleado
        // no esté marcado como excluido del cálculo.
        $retencionRenta = 0.0;
        if ($esMensual && !$this->esVerdadero($emp['excluir_calculo_ir'] ?? false)) {
            $pctPer = (float) ($emp['aporte_personal'] ?? 9.45);
            $retencionRenta = ImpuestoRentaEmpleadoService::calcularRetencionMensual(
                $baseIess, $pctPer, $tramosIr, $rebajaGastosPersonalesAnual
            );
            if ($retencionRenta > 0) {
                $rubros[] = $this->r('egreso', 'Impuesto a la Renta (relación de dependencia)', null, 'ir', $retencionRenta, false);
                $egresos += $retencionRenta;
            }
        }

        // 6) Neteo de semanas/quincenas (solo MENSUAL)
        if ($esMensual && $neteo > 0) {
            $neteo = round($neteo, 2);
            $rubros[] = $this->r('egreso', 'Neteo semanas/quincenas del mes', null, 'neteo', $neteo, false);
            $egresos += $neteo;
        }

        $ingresos = round($ingresos, 2);
        $egresos  = round($egresos, 2);

        return [
            'dias_trabajados' => $diasTrab,
            'sueldo_base'     => round($base, 2),
            'total_ingresos'  => $ingresos,
            'total_egresos'   => $egresos,
            'aporte_iess'     => $aporteIess,
            'aporte_patronal' => $aportePatronal,
            'retencion_renta' => $retencionRenta,
            'neto'            => round($ingresos - $egresos, 2),
            'rubros'          => $rubros,
        ];
    }

    private function r(string $tipo, string $concepto, ?string $codigo, string $origen, float $valor, bool $aportaIess, ?int $idNovedad = null): array
    {
        return [
            'tipo' => $tipo, 'concepto' => $concepto, 'codigo' => $codigo,
            'origen' => $origen, 'valor' => round($valor, 2), 'aporta_iess' => $aportaIess, 'id_novedad' => $idNovedad,
        ];
    }

    /** Un rubro fijo aplica al MENSUAL si su frecuencia es rol/mensual/vacía; a QUINCENA/SEMANAL solo si coincide. */
    private function rubroAplica(?string $frecuencia, string $tipo): bool
    {
        $f = strtolower(trim((string) $frecuencia));
        if ($tipo === 'MENSUAL') return $f === '' || $f === 'rol' || $f === 'mensual';
        if ($tipo === 'QUINCENA') return $f === 'quincena';
        if ($tipo === 'SEMANAL')  return $f === 'semanal';
        return false;
    }

    private function esVerdadero($v): bool
    {
        if (is_bool($v)) return $v;
        return in_array(strtolower((string) $v), ['1', 't', 'true', 'si', 'sí'], true);
    }
}
