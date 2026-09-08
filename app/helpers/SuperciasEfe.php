<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Estado de Flujos de Efectivo (EFE) de Supercías: reglas de clasificación.
 *
 * Método directo (95xx): cada asiento que toca una cuenta de EFECTIVO (ESF 10101xx) se reparte
 * entre sus contrapartidas; cada contrapartida se clasifica por su casillero ESF/ERI en un
 * casillero del EFE. Signo: entradas de efectivo positivas, salidas negativas.
 *
 * Conciliación (96-9820): los "cambios en activos y pasivos" (98xx) salen de la variación del
 * año de los grupos del ESF; los ajustes (97xx) de los casilleros del ERI.
 *
 * El cálculo vive en EstadosFinancierosService::calcularEfe().
 */
final class SuperciasEfe
{
    /** Casilleros de detalle del método directo (los que reciben valores; los totales se suman en código). */
    public const OPERACION_COBROS = ['95010101', '95010102', '95010103', '95010104', '95010105'];
    public const OPERACION_PAGOS  = ['95010201', '95010202', '95010203', '95010204', '95010205'];
    public const OPERACION_OTROS  = ['950103', '950104', '950105', '950106', '950107', '950108'];
    public const INVERSION   = ['950201', '950202', '950203', '950204', '950205', '950206', '950207', '950208', '950209', '950210',
                                '950211', '950212', '950213', '950214', '950215', '950216', '950217', '950218', '950219', '950220', '950221'];
    public const FINANCIACION = ['950301', '950302', '950303', '950304', '950305', '950306', '950307', '950308', '950309', '950310'];
    public const AJUSTES      = ['9701', '9702', '9703', '9704', '9705', '9706', '9707', '9708', '9709', '9710', '9711'];
    public const CAMBIOS      = ['9801', '9802', '9803', '9804', '9805', '9806', '9807', '9808', '9809', '9810'];

    /** Casilleros "otros" del método directo: lo que cae aquí conviene revisarlo. */
    public const OTROS = ['95010105', '95010205', '950108', '950221', '950310'];

    /** ¿La cuenta es efectivo o equivalente? (ESF 10101 Caja, bancos públicos y privados.) */
    public static function esEfectivo(?string $esf): bool
    {
        return $esf !== null && str_starts_with($esf, '10101');
    }

    /**
     * Líneas "accesorias" de un asiento (IVA, retenciones, crédito tributario): no son la razón
     * del movimiento de efectivo; su importe se suma a la contrapartida principal del asiento.
     */
    public static function esAccesoria(?string $esf): bool
    {
        if ($esf === null || $esf === '') return false;
        return in_array($esf, ['1010501', '1010502', '2010701'], true)
            || str_starts_with($esf, '1010501') || str_starts_with($esf, '1010502') || str_starts_with($esf, '2010701');
    }

    /**
     * Casillero del EFE (método directo) para una contrapartida.
     * @param string|null $esf    Casillero ESF de la cuenta de contrapartida.
     * @param string|null $eri    Casillero ERI de la cuenta de contrapartida.
     * @param bool   $entrada     true si el efectivo entró (valor positivo), false si salió.
     * @param string|null $moduloOrigen modulo_origen de la cabecera (desempate: nómina, activos fijos).
     * @return array{casillero:string, regla:string}
     */
    public static function clasificar(?string $esf, ?string $eri, bool $entrada, ?string $moduloOrigen = null): array
    {
        $esf = trim((string) $esf);
        $eri = trim((string) $eri);
        $pick = fn(?string $cobro, ?string $pago, string $regla) => [
            'casillero' => $entrada ? ($cobro ?? $pago) : ($pago ?? $cobro),
            'regla'     => $regla,
        ];
        $sw = fn(string $s, array $prefijos) => array_reduce($prefijos, fn($c, $p) => $c || str_starts_with($s, $p), false);

        // Desempate por módulo de origen
        if ($moduloOrigen === 'nomina') return $pick('95010203', '95010203', 'Nómina');
        if ($moduloOrigen === 'activos_fijos_alta') return $pick('950208', '950209', 'Alta de activo fijo');

        if ($esf !== '') {
            if (in_array($esf, ['2010702', '1010503'], true) || $eri === '603')
                return $pick('950107', '950107', 'Impuesto a la renta (ESF ' . $esf . ')');
            if ($sw($esf, ['2010703', '2010704', '2010705', '20112', '20207']))
                return $pick('95010203', '95010203', 'IESS / beneficios a empleados (ESF ' . $esf . ')');
            if ($esf === '2010706')
                return $pick('950308', '950308', 'Dividendos por pagar');
            if ($esf === '2010605')
                return $pick('950106', '950105', 'Intereses por pagar');
            if ($sw($esf, ['1010201', '1010202', '1010203', '1010204', '10206', '1020806', '1020807', '1020808', '1020809', '1020810']))
                return $pick('950204', '950205', 'Inversiones / activos financieros (ESF ' . $esf . ')');
            if ($sw($esf, ['1010205', '1010206', '1010207', '10209', '10210', '20110']))
                return $pick('95010101', '95010101', 'Clientes / anticipos de clientes (ESF ' . $esf . ')');
            if ($sw($esf, ['20103', '10103', '1010403', '1010404']))
                return $pick('95010201', '95010201', 'Proveedores / inventario / anticipos (ESF ' . $esf . ')');
            if ($sw($esf, ['10201', '10202', '10203']))
                return $pick('950208', '950209', 'Propiedad, planta y equipo (ESF ' . $esf . ')');
            if ($sw($esf, ['10204']))
                return $pick('950210', '950211', 'Activos intangibles (ESF ' . $esf . ')');
            if ($sw($esf, ['10207', '10208', '10205']))
                return $pick('950212', '950213', 'Otros activos a largo plazo (ESF ' . $esf . ')');
            if ($sw($esf, ['20104', '20204', '201080101', '201080102', '201080201', '201080202', '202080101', '202080102']))
                return $pick('950304', '950305', 'Préstamos (ESF ' . $esf . ')');
            if ($sw($esf, ['20106', '20206']))
                return $pick('950302', '950305', 'Valores emitidos (ESF ' . $esf . ')');
            if ($sw($esf, ['20102', '20202']))
                return $pick('950310', '950306', 'Arrendamientos (ESF ' . $esf . ')');
            if ($sw($esf, ['20109']))
                return $pick('950310', '950310', 'Otros pasivos financieros (ESF ' . $esf . ')');
            if ($sw($esf, ['301', '302', '303']))
                return $pick('950301', '950303', 'Capital / aportes (ESF ' . $esf . ')');
            if (str_starts_with($esf, '3'))
                return $pick('950301', '950308', 'Patrimonio: resultados / dividendos (ESF ' . $esf . ')');
            if (str_starts_with($esf, '1') || str_starts_with($esf, '2'))
                return $pick('95010105', '95010205', 'Otros activos / pasivos (ESF ' . $esf . ')');
        }

        if ($eri !== '') {
            if ($sw($eri, ['40110']) || in_array($eri, ['4010601', '4010602', '4010603', '4011002'], true))
                return $pick('950106', '950106', 'Intereses ganados (ERI ' . $eri . ')');
            if (str_starts_with($eri, '4'))
                return $pick('95010101', '95010101', 'Ingresos (ERI ' . $eri . ')');
            if ($sw($eri, ['50203']))
                return $pick('950105', '950105', 'Gastos financieros (ERI ' . $eri . ')');
            if (in_array($eri, ['5020101', '5020102', '5020103', '5020104', '5020201', '5020202', '5020203', '5020204'], true))
                return $pick('95010203', '95010203', 'Sueldos y beneficios (ERI ' . $eri . ')');
            if (in_array($eri, ['603', '5020126', '5020227'], true))
                return $pick('950107', '950107', 'Impuesto a la renta (ERI ' . $eri . ')');
            if (str_starts_with($eri, '5') || str_starts_with($eri, '6'))
                return $pick('95010201', '95010201', 'Costos y gastos (ERI ' . $eri . ')');
        }

        return $pick('95010105', '95010205', 'Sin regla: cuenta sin casillero ESF/ERI reconocido');
    }

    /**
     * ¿El ESF corresponde a inversión (activos no corrientes, activos financieros) o financiación
     * (deuda financiera, arrendamientos, dividendos, préstamos de relacionadas)? Esas cuentas no
     * entran a "cambios en activos y pasivos": su efectivo va a 9502 / 9503 por el método directo.
     */
    public static function esInversionOFinanciacion(string $esf): bool
    {
        $esf = trim($esf);
        if ($esf === '') return false;
        foreach (['1010201', '1010202', '1010203', '1010204', '10201', '10202', '10203', '10204', '10206', '10207',
                  '1020801', '1020803', '1020805', '1020806', '1020807', '1020808', '1020809', '1020810',
                  '20101', '20102', '20104', '20106', '20109', '20201', '20202', '20203', '20204', '20206',
                  '2010706', '201080101', '201080102', '201080201', '201080202', '202080101', '202080102'] as $p) {
            if (str_starts_with($esf, $p)) return true;
        }
        return false;
    }

    /**
     * Casillero de "cambios en activos y pasivos" (98xx) para una cuenta según su ESF, o null si la
     * cuenta no es capital de trabajo (efectivo, inversiones, activo fijo, deuda financiera,
     * dividendos, patrimonio, resultados). El valor del casillero es −(variación debe−haber).
     */
    public static function casilleroCambio(?string $esf): ?string
    {
        $esf = trim((string) $esf);
        if ($esf === '') return null;
        $sw = fn(array $prefijos) => array_reduce($prefijos, fn($c, $p) => $c || str_starts_with($esf, $p), false);

        if (in_array($esf, ['10102050221', '101020604', '1020903', '1021004'], true)) return '9802';
        if ($sw(['1010205', '1010206', '1010207', '10209', '10210'])) return '9801';
        if ($sw(['1010403'])) return '9803';
        if ($sw(['10103'])) return '9804';
        if ($sw(['10104', '10105', '10106', '10107', '10108', '10205', '1020802', '1020811'])) return '9805';
        if ($sw(['20103'])) return '9806';
        if ($sw(['2010703', '2010704', '2010705', '20112', '20207'])) return '9808';
        if ($sw(['20110'])) return '9809';
        if ($sw(['2010706'])) return null; // dividendos: financiación
        if ($sw(['201080101', '201080102', '201080201', '201080202'])) return null; // préstamos relacionadas: financiación
        if ($sw(['20107', '20108', '20113', '2010605'])) return '9807';
        if ($sw(['20105', '20111', '20101', '20205', '20208', '20209', '20210', '20211', '20212'])) return '9810';
        return null;
    }
}
