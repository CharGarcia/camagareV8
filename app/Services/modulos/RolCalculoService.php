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
 *  - Prorrateo (solo MENSUAL): factor = díasTrabajados/30 (días de contrato en el
 *    mes). Aplica a sueldo, décimo tercero/cuarto mensualizados y fondos de reserva.
 *    La base del IESS hereda el sueldo ya prorrateado.
 *  - Días no laborados (novedad 10) en el MENSUAL: restan del sueldo ganado. Van como
 *    ingreso NEGATIVO marcado "aporta IESS" (no como egreso), así bajan la base del
 *    IESS y del IR, los fondos de reserva y el 13º mensualizados (calculados sobre
 *    los días laborados) y, sin tocar nada más, las provisiones y el 13º del módulo
 *    Décimo Tercero, que suman los ingresos gravados. El 14º sigue por días de
 *    contrato, igual que el módulo Décimo Cuarto. En QUINCENA/SEMANAL siguen siendo
 *    egreso: así el neteo del mensual los respeta (ver "Descuentos aplicados...").
 *  - Horas (novedades 4/5/6): tarifa = sueldo/hora_normal * (1+recargo%) * nº horas.
 *  - Otros Ingresos (1) y horas llevan la marca "aporta IESS" de la novedad
 *    (CatalogoNovedades::aportaIess); sin marca, las horas sí y Otros Ingresos no.
 *  - IESS personal (solo MENSUAL): base_iess * empleado.aporte_personal%. La base
 *    incluye sueldo + horas/otros ingresos/rubros fijos marcados "aporta IESS" + vacaciones.
 *  - Fondos de reserva 8.33% del sueldo si fondos_reserva='rol' (solo MENSUAL).
 *  - Décimos si 'mensualiza' (13º = sueldo/12, 14º = SBU/12) (solo MENSUAL).
 *  - Días no laborados (novedad 10): días * sueldo/30 (ver arriba cómo se aplica).
 *  - Anticipo (novedad 3): NO se descuenta al registrarse; se paga por egreso y el rol
 *    descuenta solo lo pagado ($anticiposPagados[id_novedad]).
 *  - Descuento (2): egreso directo por su valor.
 *  - Préstamo Quirografario (7) e Hipotecario (8): egreso directo por su valor, igual que
 *    un Descuento. Los desembolsa el IESS/banco directamente al empleado, no la empresa,
 *    así que no dependen de un egreso propio para empezar a descontarse.
 *  - Préstamo Empresa (9): la cuota descuenta directo SOLO si el préstamo ya fue desembolsado
 *    por la empresa (pagado por egreso); si no, no descuenta ($prestamosNoDesembolsados[id_novedad]).
 *  - Neteo (solo MENSUAL): resta lo ya pagado en SEMANAL/QUINCENA del mes, MÁS los
 *    descuentos que ya se aplicaron en esas corridas (sin esto, un descuento de quincena
 *    no afecta el total del mes: solo se corre de la quincena al cierre de mes). Y
 *    vuelve a sumar, como ingreso, lo que esas corridas pagaron además de su base
 *    (horas, otros ingresos, rubros fijos): como el neteo resta TODO lo pagado, sin eso
 *    esos ingresos se pagaban en la quincena y se descontaban a fin de mes. Los que
 *    aportan al IESS entran a la base del mes (en quincena/semana no hay IESS).
 *  - En quincena/semana solo hay ingresos y descuentos: los días no laborados y el
 *    aviso de salida van siempre al mensual (NovedadService los fija en 'rol').
 */
class RolCalculoService
{
    public function calcular(array $emp, string $tipo, array $salario, array $rubrosFijos, array $novedades, float $neteo = 0.0, float $vacaciones = 0.0, int $diasTrabajados = 30, array $anticiposPagados = [], array $prestamosNoDesembolsados = [], array $tramosIr = [], float $rebajaGastosPersonalesAnual = 0.0, float $descuentosNeteo = 0.0, array $ingresosNeteo = []): array
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
        // Si el empleado aporta al IESS, el concepto avisa cuál ingreso va sin IESS
        // (si no aporta, ninguno va: sería ruido en cada línea).
        $empAportaIess = $this->esVerdadero($emp['aporta_iess'] ?? true);
        // Días no laborados ya restados del sueldo del mensual (valorizados).
        $descuentoDias = 0.0;

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
                $ai = CatalogoNovedades::aportaIess($cod, $n['aporta_iess'] ?? null);
                $sinIess = $empAportaIess && !$ai ? ', sin IESS' : '';
                $rubros[] = $this->r('ingreso', $nom . ' (' . $val . 'h' . $sinIess . ')', $cod, 'novedad', $monto, $ai, $idn);
                $ingresos += $monto;
                if ($esMensual && $ai) $baseIess += $monto;
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
                case '1': // Otros Ingresos (bonos, comisiones): aporta al IESS según su marca
                    $ai = CatalogoNovedades::aportaIess($cod, $n['aporta_iess'] ?? null);
                    $rubros[] = $this->r('ingreso', $nom . ($empAportaIess && !$ai ? ' (sin IESS)' : ''), $cod, 'novedad', $val, $ai, $idn);
                    $ingresos += $val;
                    if ($esMensual && $ai) $baseIess += $val;
                    break;
                case '10': // Días no laborados, valorizados a sueldo/30
                    $monto = round($val * $sueldoDiario, 2);
                    if ($esMensual) {
                        // Restan del sueldo ganado (ver cabecera): nunca más que el sueldo del mes.
                        $monto = min($monto, max(0.0, round($base - $descuentoDias, 2)));
                        $rubros[] = $this->r('ingreso', $nom . ' (' . $val . 'd)', $cod, 'novedad', -$monto, true, $idn);
                        $ingresos      -= $monto;
                        $baseIess      -= $monto;
                        $descuentoDias += $monto;
                    } else {
                        $rubros[] = $this->r('egreso', $nom . ' (' . $val . 'd)', $cod, 'novedad', $monto, false, $idn);
                        $egresos += $monto;
                    }
                    break;
                default: // 2 Descuento, 7 Préstamo Quirografario, 8 Préstamo Hipotecario: descuento directo, sin egreso
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

        // 3c) Ingresos ya pagados en las quincenas/semanas del mes, sin su base (ver
        // cabecera). Conservan código, origen y marca de IESS, así el asiento, las
        // provisiones y el módulo Décimo Tercero los tratan igual que si fueran del mes.
        if ($esMensual) {
            foreach ($ingresosNeteo as $in) {
                $val = round((float) ($in['valor'] ?? 0), 2);
                if ($val == 0.0) continue;
                $ai = $this->esVerdadero($in['aporta_iess'] ?? false);
                $codigo = ($in['codigo'] ?? '') !== '' ? (string) $in['codigo'] : null;
                $rubros[] = $this->r('ingreso', $in['concepto'] . ' — ' . $this->nombreCorrida($in), $codigo, (string) $in['origen'], $val, $ai);
                $ingresos += $val;
                if ($ai) $baseIess += $val;
            }
        }

        // 4) Beneficios (solo MENSUAL)
        $aportePatronal = 0.0;
        if ($esMensual) {
            // Sueldo de los días laborados: el de los días de contrato menos los días
            // no laborados. Es la base de los fondos de reserva y del 13º mensualizados.
            $sueldoLaborado = max(0.0, $sueldoBase * $factor - $descuentoDias);

            // Fondos de reserva (proporcional a días laborados).
            // 'desde_anio' = se paga solo una vez cumplido el año de servicio; quien
            // resuelve si ya corresponde en este período es RolPagoService, que sí
            // conoce el mes del rol y los períodos del empleado (fondos_reserva_aplica).
            $modoFR = (string) ($emp['fondos_reserva'] ?? '');
            $pagaFR = $modoFR === 'rol'
                || ($modoFR === 'desde_anio' && $this->esVerdadero($emp['fondos_reserva_aplica'] ?? false));

            if ($pagaFR && $sueldoBase > 0) {
                $pctFR = (float) ($salario['fondo_reserva'] ?? 8.33);
                $fr = round($sueldoLaborado * $pctFR / 100, 2);
                if ($fr > 0) {
                    $rubros[] = $this->r('ingreso', 'Fondos de Reserva', null, 'fondos', $fr, false);
                    $ingresos += $fr;
                }
            }
            // Décimo tercero mensualizado (proporcional a días laborados)
            if (($emp['decimo_tercero'] ?? '') === 'mensualiza' && $sueldoBase > 0) {
                $dt = round($sueldoLaborado / 12, 2);
                if ($dt > 0) {
                    $rubros[] = $this->r('ingreso', 'Décimo Tercero', null, 'decimo', $dt, false);
                    $ingresos += $dt;
                }
            }
            // Décimo cuarto mensualizado: por días de contrato (no descuenta días no
            // laborados), igual que el módulo Décimo Cuarto.
            if (($emp['decimo_cuarto'] ?? '') === 'mensualiza') {
                $dc = round(((float) ($salario['sbu'] ?? 0)) * $factor / 12, 2);
                if ($dc > 0) {
                    $rubros[] = $this->r('ingreso', 'Décimo Cuarto', null, 'decimo', $dc, false);
                    $ingresos += $dc;
                }
            }
        }

        // 5) IESS personal y patronal (solo MENSUAL, y solo si el empleado aporta al
        // IESS). Si aporta_iess=false, no se descuenta nada al empleado NI se genera
        // gasto patronal — sin importar qué porcentajes tenga guardados en su ficha.
        $aporteIess = 0.0;
        if ($esMensual && $this->esVerdadero($emp['aporta_iess'] ?? true)) {
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
        // 6b) Descuentos ya aplicados en esas quincenas/semanas (código 2, préstamos cobrados,
        // días no laborados, etc.). El neteo (6) solo resta el NETO ya pagado; sin esta línea,
        // ese descuento no reduce el total del mes, solo se traslada de la quincena al cierre.
        if ($esMensual && $descuentosNeteo > 0) {
            $descuentosNeteo = round($descuentosNeteo, 2);
            $rubros[] = $this->r('egreso', 'Descuentos aplicados en quincenas/semanas del mes', null, 'neteo', $descuentosNeteo, false);
            $egresos += $descuentosNeteo;
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

    /** "Quincena 1", "Semana 3": la corrida en la que se pagó un ingreso que se netea. */
    private function nombreCorrida(array $in): string
    {
        $nombre = ($in['tipo_rol'] ?? '') === 'SEMANAL' ? 'Semana' : 'Quincena';
        $n = (int) ($in['numero_periodo'] ?? 0);
        return $n > 0 ? "{$nombre} {$n}" : $nombre;
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
