<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Conceptos contables de Nómina (asientos_tipo con tipo_asiento = 'nomina') según CÓMO se paga cada
 * beneficio del empleado (pestaña Laboral: Fondos de Reserva, Décimo Tercero, Décimo Cuarto, Aporta
 * al IESS). Es la única fuente de verdad que comparten:
 *
 *   - RolAsientoService: de qué cadena de conceptos sale la cuenta de cada línea del asiento del rol.
 *   - La pestaña «Contable» del empleado (partials/ficha_contable.php + ficha_contable.js): qué
 *     conceptos se muestran con las opciones elegidas y qué cuenta se usará si se deja vacío.
 *
 * Idea: el GASTO de un beneficio es el mismo sin importar cómo se pague; lo que cambia con la forma
 * de pago es la CONTRAPARTIDA. Por eso existen conceptos opcionales por forma de pago que, sin
 * cuenta, caen al concepto de siempre (cadena de respaldo):
 *
 *   Fondos de Reserva pagados en rol   → Gasto Fondos de Reserva → Ingresos No Gravados
 *   Décimo Tercero mensualizado        → Gasto Décimo Tercero    → Ingresos No Gravados
 *   Décimo Cuarto mensualizado         → Gasto Décimo Cuarto     → Ingresos No Gravados
 *   Fondos de Reserva por Pagar IESS   → Fondos de Reserva por Pagar
 *
 * El último eslabón (Ingresos No Gravados) es donde iban esos rubros antes de existir esta pieza:
 * una empresa que no configuró nada nuevo nunca queda sin poder contabilizar.
 *
 * Resolución de la cuenta de una línea (RolAsientoService::cuentaCadena):
 *   1) la cuenta PROPIA del empleado en el primer concepto de la cadena que la tenga;
 *   2) si no tiene ninguna, la cuenta GENERAL del primer concepto de la cadena que la tenga.
 *
 * Manual: docs/manual/modulos/roles-pago.md y docs/manual/modulos/empleados.md.
 */
final class NominaConceptos
{
    /** Conceptos nuevos por forma de pago (database/2026-11-27_nomina_modalidades_cuentas.sql). */
    public const FONDOS_ROL        = 'FONDOSRESERVAROLNOMINA';
    public const DECIMO13_ROL      = 'DECIMOTERCEROROLNOMINA';
    public const DECIMO14_ROL      = 'DECIMOCUARTOROLNOMINA';
    public const FONDOS_IESS_PAGAR = 'FONDOSRESERVAIESSPORPAGARNOMINA';

    /**
     * Cadena de respaldo de los conceptos opcionales (el primero es el propio concepto). Los demás
     * conceptos de nómina no tienen cadena: su cadena es solo ellos mismos.
     */
    public const CADENAS = [
        self::FONDOS_ROL        => [self::FONDOS_ROL, 'GASTOFONDOSRESERVANOMINA', 'INGRESOSNOGRAVADOSNOMINA'],
        self::DECIMO13_ROL      => [self::DECIMO13_ROL, 'GASTODECIMOTERCERONOMINA', 'INGRESOSNOGRAVADOSNOMINA'],
        self::DECIMO14_ROL      => [self::DECIMO14_ROL, 'GASTODECIMOCUARTONOMINA', 'INGRESOSNOGRAVADOSNOMINA'],
        self::FONDOS_IESS_PAGAR => [self::FONDOS_IESS_PAGAR, 'FONDOSRESERVAPORPAGARNOMINA'],
    ];

    /**
     * Cuándo aplica cada concepto a un empleado, según su pestaña Laboral. Claves de condición:
     *   fr   fondos_reserva  (no_se_paga | rol | desde_anio | planilla)
     *   dt   decimo_tercero  (acumula | mensualiza | no_recibe)
     *   dc   decimo_cuarto   (acumula | mensualiza | no_recibe)
     *   iess aporta_iess     (si | no)
     * Un concepto que no está aquí aplica siempre. 'nunca' = no se resuelve por empleado (lo
     * contabiliza otro módulo solo con la cuenta General).
     *
     * 'desde_anio' muestra las dos caras: antes del año de servicio se provisiona (Gasto + Fondos de
     * Reserva por Pagar) y después se paga en el rol (Fondos de Reserva pagados en rol).
     */
    public const APLICA = [
        'GASTOAPORTEPATRONALNOMINA'    => ['iess' => ['si']],
        'IESSPORPAGARNOMINA'           => ['iess' => ['si']],

        'GASTODECIMOTERCERONOMINA'     => ['dt' => ['acumula']],
        'DECIMOTERCEROPORPAGARNOMINA'  => ['dt' => ['acumula']],
        self::DECIMO13_ROL             => ['dt' => ['mensualiza']],

        'GASTODECIMOCUARTONOMINA'      => ['dc' => ['acumula']],
        'DECIMOCUARTOPORPAGARNOMINA'   => ['dc' => ['acumula']],
        self::DECIMO14_ROL             => ['dc' => ['mensualiza']],

        self::FONDOS_ROL               => ['fr' => ['rol', 'desde_anio']],
        'GASTOFONDOSRESERVANOMINA'     => ['fr' => ['desde_anio', 'planilla']],
        'FONDOSRESERVAPORPAGARNOMINA'  => ['fr' => ['desde_anio']],
        self::FONDOS_IESS_PAGAR        => ['fr' => ['planilla']],

        // La Participación de Utilidades la contabiliza el módulo Utilidades, solo con la General.
        'GASTOPARTICIPACIONTRABAJADORESNOMINA'    => 'nunca',
        'PARTICIPACIONTRABAJADORESPORPAGARNOMINA' => 'nunca',
    ];

    /** Cadena de conceptos de un código (él mismo primero). */
    public static function cadena(string $codigo): array
    {
        return self::CADENAS[$codigo] ?? [$codigo];
    }

    /**
     * Concepto que se reporta como "falta configurar" cuando ninguna cuenta de la cadena existe: el
     * primero que NO es opcional (el de siempre), para no pedir un concepto nuevo que es opcional.
     */
    public static function conceptoObligatorio(string $codigo): string
    {
        foreach (self::cadena($codigo) as $c) {
            if (!isset(self::CADENAS[$c])) {
                return $c;
            }
        }
        return $codigo;
    }

    /**
     * ¿El concepto aplica a un empleado con estas opciones? $opciones con las claves fr/dt/dc/iess
     * (valores de la ficha o del rol). Una opción vacía o desconocida no oculta nada.
     */
    public static function aplica(string $codigo, array $opciones): bool
    {
        $regla = self::APLICA[$codigo] ?? null;
        if ($regla === null) {
            return true;
        }
        if ($regla === 'nunca') {
            return false;
        }
        foreach ($regla as $clave => $valores) {
            $actual = (string) ($opciones[$clave] ?? '');
            if ($actual !== '' && !in_array($actual, $valores, true)) {
                return false;
            }
        }
        return true;
    }

    /** Datos para el JS de la pestaña «Contable» del empleado. */
    public static function paraJs(): array
    {
        return ['cadenas' => self::CADENAS, 'aplica' => self::APLICA];
    }
}
