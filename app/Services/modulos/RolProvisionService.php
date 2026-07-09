<?php

declare(strict_types=1);

namespace App\Services\modulos;

/**
 * Calcula las provisiones (beneficios sociales que se acumulan) de una línea de
 * rol y arma el asiento contable de nómina (débitos/créditos) de ese empleado.
 *
 * Provisiones estándar Ecuador (base = materia gravada = ingresos que aportan al IESS):
 *   - Décimo Tercero  = base / 12
 *   - Décimo Cuarto   = SBU / 12
 *   - Vacaciones      = base / 24
 *   - Fondos Reserva  = base * 8.33%
 *   - Desahucio       = base * 25% / 12   (provisión mensual aproximada)
 * Los conceptos que el empleado ya cobra en el rol (décimos 'mensualiza', fondos
 * 'rol') NO se provisionan de nuevo (evita doble contabilización).
 */
class RolProvisionService
{
    public function calcularProvisiones(array $lin, array $salario): array
    {
        // Base gravada = suma de ingresos marcados "aporta IESS".
        $base = 0.0;
        foreach ($lin['rubros'] ?? [] as $r) {
            if (($r['tipo'] ?? '') === 'ingreso' && $this->truthy($r['aporta_iess'] ?? false)) {
                $base += (float) $r['valor'];
            }
        }
        $sbu   = (float) ($salario['sbu'] ?? 0);
        $pctFR = (float) ($salario['fondo_reserva'] ?? 8.33) ?: 8.33;
        // El 14º (SBU/12) se prorratea por días trabajados; el resto ya hereda la base
        // (que sale de los rubros del rol, prorrateados en origen).
        $diasTrab = min(30, max(0, (int) ($lin['dias_trabajados'] ?? 30)));
        $factorDC = $diasTrab / 30;

        $dtMode = (string) ($lin['decimo_tercero'] ?? '');
        $dcMode = (string) ($lin['decimo_cuarto'] ?? '');
        $frMode = (string) ($lin['fondos_reserva'] ?? '');

        $mk = fn(string $c, float $v, bool $incl, string $nota) => [
            'concepto' => $c, 'valor' => round($v, 2), 'incluir' => $incl,
            'nota' => $nota,
        ];

        return [
            $mk('Décimo Tercero', $base / 12, $dtMode !== 'mensualiza', $dtMode === 'mensualiza' ? 'Pagado en rol' : 'Provisión mensual'),
            $mk('Décimo Cuarto', $sbu / 12 * $factorDC, $dcMode !== 'mensualiza', $dcMode === 'mensualiza' ? 'Pagado en rol' : 'Provisión mensual'),
            $mk('Vacaciones', $base / 24, true, 'Provisión mensual'),
            $mk('Fondos de Reserva', $base * $pctFR / 100, $frMode !== 'rol', $frMode === 'rol' ? 'Pagado en rol' : 'Provisión mensual'),
            $mk('Desahucio', $base * 0.25 / 12, true, 'Provisión mensual'),
        ];
    }

    /**
     * Arma el asiento contable de la línea (cuadra por construcción).
     */
    public function construirAsiento(array $lin, array $provisiones): array
    {
        $ingresos = (float) $lin['total_ingresos'];
        $egresos  = (float) $lin['total_egresos'];
        $iessPer  = (float) $lin['aporte_iess'];
        $iessPat  = (float) $lin['aporte_patronal'];
        $neto     = (float) $lin['neto'];
        // Deducciones distintas del IESS personal (anticipos, préstamos, descuentos, neteo, días).
        $dedu = round($egresos - $iessPer, 2);

        $provIncl = array_values(array_filter($provisiones, fn($p) => $p['incluir'] && $p['valor'] > 0));
        $provTotal = array_sum(array_map(fn($p) => $p['valor'], $provIncl));

        $debe = [];
        $haber = [];
        $debe[] = ['concepto' => 'Gasto Sueldos y Salarios', 'valor' => round($ingresos, 2)];
        if ($iessPat > 0) $debe[] = ['concepto' => 'Gasto Aporte Patronal IESS', 'valor' => $iessPat];
        foreach ($provIncl as $p) $debe[] = ['concepto' => 'Gasto ' . $p['concepto'], 'valor' => $p['valor']];

        if (($iessPer + $iessPat) > 0) $haber[] = ['concepto' => 'IESS por Pagar', 'valor' => round($iessPer + $iessPat, 2)];
        foreach ($provIncl as $p) $haber[] = ['concepto' => $p['concepto'] . ' por Pagar', 'valor' => $p['valor']];
        if ($dedu > 0) $haber[] = ['concepto' => 'Anticipos / Descuentos por cobrar', 'valor' => $dedu];
        if ($neto != 0) $haber[] = ['concepto' => 'Bancos / Líquido a pagar', 'valor' => round($neto, 2)];

        $totDebe  = round(array_sum(array_map(fn($x) => $x['valor'], $debe)), 2);
        $totHaber = round(array_sum(array_map(fn($x) => $x['valor'], $haber)), 2);

        return [
            'debe' => $debe, 'haber' => $haber,
            'total_debe' => $totDebe, 'total_haber' => $totHaber,
            'cuadrado' => abs($totDebe - $totHaber) < 0.01,
            'prov_total' => round($provTotal, 2),
        ];
    }

    private function truthy($v): bool
    {
        if (is_bool($v)) return $v;
        return in_array(strtolower((string) $v), ['1', 't', 'true', 'si', 'sí'], true);
    }
}
