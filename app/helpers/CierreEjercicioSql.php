<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Cómo tratan los reportes los dos asientos que genera el módulo Cierre del Ejercicio
 * (modulos/cierre_ejercicio):
 *
 *   - Asiento de CIERRE (31-12, tipo 'cierre', origen 'cierre_ejercicio'): salda las cuentas
 *     de resultados (4/5/6 y la 7 de los migrados) contra Utilidad/Pérdida del Ejercicio.
 *   - Asiento de APERTURA (01-01 del año siguiente, tipo 'apertura', origen
 *     'apertura_ejercicio'): arrastra los saldos de balance (1/2/3) y pasa el resultado a
 *     Resultados Acumulados.
 *
 * Dos familias de consultas, dos reglas:
 *
 *   1. Reportes POR RANGO de fechas (Estados Financieros, Balance de Comprobación, Supercías,
 *      presupuesto, dividendos): ven la apertura generada SOLO si cae en el primer día del
 *      rango, y NUNCA ven el asiento de cierre. Así un año cerrado se ve igual que antes de
 *      cerrarlo (el Estado de Resultados no sale en cero) y un rango que cruza años no cuenta
 *      dos veces lo que la apertura ya repite. → {@see rango()}
 *
 *   2. Consultas ACUMULADAS desde el inicio de la historia (comprobación con contabilidad,
 *      bancos, flujo de caja, saldos de una cuenta): ignoran los DOS asientos. Ya suman todo
 *      lo anterior; la apertura lo repetiría y duplicaría los saldos. → {@see acumulado()}
 *
 * Solo afecta a los asientos de este módulo: un asiento de cierre o de apertura registrado a
 * mano o traído de la migración sigue contando como siempre.
 */
final class CierreEjercicioSql
{
    /** modulo_origen del asiento de cierre (31-12). */
    public const ORIGEN_CIERRE = 'cierre_ejercicio';

    /** modulo_origen del asiento de apertura (01-01 del año siguiente). */
    public const ORIGEN_APERTURA = 'apertura_ejercicio';

    /**
     * Condición para reportes por rango. $alias es el alias de asientos_contables_cabecera y
     * $fechaInicio la expresión SQL (placeholder) con el primer día del rango.
     */
    public static function rango(string $alias, string $fechaInicio): string
    {
        $a = $alias;
        return "NOT (COALESCE({$a}.modulo_origen, '') = '" . self::ORIGEN_CIERRE . "'"
            . " OR (COALESCE({$a}.modulo_origen, '') = '" . self::ORIGEN_APERTURA . "'"
            . " AND {$a}.fecha_asiento > CAST({$fechaInicio} AS DATE)))";
    }

    /**
     * Condición para reportes por rango que NO tienen un "primer día" (p. ej. un año fijo por
     * EXTRACT(YEAR)): la apertura cuenta solo si es la del 01-01 de ese año. Como la apertura
     * generada siempre va al 01-01, basta con excluir el cierre.
     */
    public static function sinCierre(string $alias): string
    {
        return "COALESCE({$alias}.modulo_origen, '') <> '" . self::ORIGEN_CIERRE . "'";
    }

    /**
     * Condición para consultas acumuladas que cruzan documento por documento o que suman sus
     * propios saldos iniciales (bancos, cartera, inventario): ignora los dos asientos del módulo.
     * La apertura no trae terceros ni documentos y repetiría lo que ya suman.
     */
    public static function acumulado(string $alias): string
    {
        return "COALESCE({$alias}.modulo_origen, '') NOT IN ('" . self::ORIGEN_CIERRE . "', '" . self::ORIGEN_APERTURA . "')";
    }

    /**
     * Condición para el SALDO de una cuenta a una fecha (sin cruce por documento): arranca en la
     * última apertura generada hasta $hasta (inclusive) e ignora lo anterior, que esa apertura ya
     * resume, y nunca cuenta el asiento de cierre. Sin aperturas generadas, suma todo el
     * histórico, como siempre. Es la única forma de que una cuenta de patrimonio refleje el
     * traslado del resultado a Resultados Acumulados que hace la apertura.
     */
    public static function desdeUltimaApertura(string $alias, string $hasta): string
    {
        $a = $alias;
        return self::sinCierre($a) . " AND {$a}.fecha_asiento >= COALESCE((
                    SELECT MAX(xa.fecha_asiento)
                      FROM asientos_contables_cabecera xa
                     WHERE xa.id_empresa = {$a}.id_empresa
                       AND xa.modulo_origen = '" . self::ORIGEN_APERTURA . "'
                       AND xa.estado = 'contabilizado'
                       AND xa.eliminado = false
                       AND xa.tipo_ambiente = {$a}.tipo_ambiente
                       AND xa.fecha_asiento <= CAST({$hasta} AS DATE)
                ), DATE '1900-01-01')";
    }
}
