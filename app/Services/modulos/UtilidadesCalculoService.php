<?php

declare(strict_types=1);

namespace App\Services\modulos;

/**
 * Cálculo de la participación de los trabajadores en las utilidades (Ecuador,
 * Art. 97 y siguientes del Código del Trabajo + Reglamento para el pago y
 * legalización de utilidades).
 *
 *  - Período: el ejercicio fiscal completo (1-ene a 31-dic). Liquidación hasta
 *    el 31-mar y pago hasta el 15-abr del año siguiente.
 *  - 10% del monto a repartir: proporcional a los días laborados de cada
 *    trabajador o ex trabajador (base 30/360, tope 360 días).
 *  - 5%: proporcional a días × cargas familiares. Si ningún trabajador tiene
 *    cargas acreditadas, ese 5% se reparte igual que el 10%.
 *  - Tope: lo que recibe cada trabajador no puede superar 24 SBU; el excedente
 *    no se le paga (va al IESS, régimen de prestaciones solidarias).
 *
 * El reparto es cerrado: la suma de las participaciones debe dar exactamente el
 * monto repartido, así que los centavos de redondeo se ajustan en el trabajador
 * con más días (la diferencia nunca pasa de unos centavos).
 */
class UtilidadesCalculoService
{
    use Dias360Trait;

    public const DIAS_ANIO = 360;

    /** Porcentaje legal de participación sobre la utilidad líquida. */
    public const PORCENTAJE_LEGAL = 15.0;

    /** Tope individual, en salarios básicos unificados. */
    public const TOPE_SBU = 24;

    /**
     * Fechas del ejercicio y plazo legal de pago.
     * @return array{fecha_desde:string, fecha_hasta:string, fecha_limite:string}
     */
    public function periodo(int $anio): array
    {
        return [
            'fecha_desde'  => $anio . '-01-01',
            'fecha_hasta'  => $anio . '-12-31',
            'fecha_limite' => ($anio + 1) . '-04-15',
        ];
    }

    /** 15% de la utilidad líquida, redondeado a centavos. */
    public function montoLegal(float $utilidadLiquida): float
    {
        return round(max(0.0, $utilidadLiquida) * self::PORCENTAJE_LEGAL / 100, 2);
    }

    /**
     * Días laborados dentro del ejercicio, sumando todos los períodos de empleo
     * (empleado_periodos) que se traslapen con él. Tope 360.
     *
     * @param array<int, array{fecha_ingreso:string, fecha_salida:?string}> $periodosEmpleado
     */
    public function diasLaborados(array $periodosEmpleado, string $periodoDesde, string $periodoHasta): int
    {
        return $this->diasLaborados360($periodosEmpleado, $periodoDesde, $periodoHasta);
    }

    /**
     * Reparte el monto entre los trabajadores.
     *
     * @param array<int, array{dias:int, cargas:int}> $trabajadores  indexado por id_empleado
     * @param float $montoRepartir  el 15% (o lo que la empresa decida repartir)
     * @param float $sbu            SBU del ejercicio, para el tope de 24 SBU
     * @return array{
     *   monto_10: float, monto_5: float, tope: float, total_dias: int, total_cargas: int,
     *   sin_cargas: bool,
     *   filas: array<int, array{valor_10: float, valor_5: float, valor_bruto: float, excedente: float, valor: float}>
     * }
     */
    public function repartir(array $trabajadores, float $montoRepartir, float $sbu): array
    {
        $montoRepartir = round(max(0.0, $montoRepartir), 2);
        $monto10 = round($montoRepartir * 10 / 15, 2);
        $monto5  = round($montoRepartir - $monto10, 2);
        $tope    = round(max(0.0, $sbu) * self::TOPE_SBU, 2);

        $conDias = array_filter($trabajadores, fn($t) => (int) $t['dias'] > 0);
        $totalDias   = array_sum(array_map(fn($t) => (int) $t['dias'], $conDias));
        $totalCargas = array_sum(array_map(fn($t) => max(0, (int) $t['cargas']), $conDias));
        $factorB     = array_sum(array_map(fn($t) => (int) $t['dias'] * max(0, (int) $t['cargas']), $conDias));
        $sinCargas   = $factorB <= 0;

        // Pesos de cada reparto: el 10% por días; el 5% por días × cargas, o por
        // días si nadie acreditó cargas.
        $pesos10 = [];
        $pesos5  = [];
        foreach ($conDias as $id => $t) {
            $dias = (int) $t['dias'];
            $pesos10[$id] = $dias;
            $pesos5[$id]  = $sinCargas ? $dias : $dias * max(0, (int) $t['cargas']);
        }

        $rep10 = $this->prorratear($monto10, $pesos10);
        $rep5  = $this->prorratear($monto5, $pesos5);

        $filas = [];
        foreach ($trabajadores as $id => $t) {
            $v10   = $rep10[$id] ?? 0.0;
            $v5    = $rep5[$id] ?? 0.0;
            $bruto = round($v10 + $v5, 2);
            $exced = $tope > 0 ? round(max(0.0, $bruto - $tope), 2) : 0.0;
            $filas[$id] = [
                'valor_10'    => $v10,
                'valor_5'     => $v5,
                'valor_bruto' => $bruto,
                'excedente'   => $exced,
                'valor'       => round($bruto - $exced, 2),
            ];
        }

        return [
            'monto_10'     => $monto10,
            'monto_5'      => $monto5,
            'tope'         => $tope,
            'total_dias'   => (int) $totalDias,
            'total_cargas' => (int) $totalCargas,
            'sin_cargas'   => $sinCargas,
            'filas'        => $filas,
        ];
    }

    /**
     * Reparte $monto según $pesos (id => peso) con redondeo a centavos y la suma
     * exacta garantizada: la diferencia de redondeo se carga a la clave de mayor
     * peso. Con todos los pesos en 0 no reparte nada.
     *
     * @param array<int, int|float> $pesos
     * @return array<int, float>
     */
    public function prorratear(float $monto, array $pesos): array
    {
        $total = array_sum($pesos);
        $out = [];
        if ($total <= 0 || $monto <= 0) {
            foreach ($pesos as $id => $p) $out[$id] = 0.0;
            return $out;
        }
        $suma = 0.0;
        $mayor = null;
        foreach ($pesos as $id => $p) {
            $v = round($monto * $p / $total, 2);
            $out[$id] = $v;
            $suma += $v;
            if ($mayor === null || $p > $pesos[$mayor]) $mayor = $id;
        }
        $dif = round($monto - $suma, 2);
        if ($dif != 0.0 && $mayor !== null) {
            $out[$mayor] = round($out[$mayor] + $dif, 2);
        }
        return $out;
    }
}
