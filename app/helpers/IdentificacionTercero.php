<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Identidad "real" de un tercero (cliente o proveedor) cuando el mismo contribuyente
 * está registrado dos veces: una con la CÉDULA (10 dígitos) y otra con el RUC
 * (13 dígitos = la misma cédula + '001').
 *
 * En Ecuador el RUC de una persona natural es su cédula seguida del código de
 * establecimiento '001', así que ambos registros son el MISMO tercero aunque en
 * `clientes` / `proveedores` sean filas distintas con documentos propios. Sin esto,
 * la cartera del tercero aparece partida en dos: dos entradas en el buscador, dos
 * grupos en la vista agrupada, dos estados de cuenta y dos correos.
 *
 * La clave base es el denominador común de esos registros:
 *   '1717136574'     → '1717136574'   (cédula)
 *   '1717136574001'  → '1717136574'   (RUC de esa misma cédula)
 *   '1791234567002'  → '1791234567002' (sucursal 002: no agrupa)
 *   'AB123456'       → 'AB123456'      (pasaporte u otro: no agrupa)
 *
 * Uso típico (patrón de dos pasos, indexable):
 *   1. leer la identificación de los ids elegidos,
 *   2. `variantes()` de cada una y buscar con `identificacion IN (...)`.
 * Se prefiere ese camino sobre comparar `claveBaseSql()` en un JOIN porque una
 * expresión sobre la columna no usa el índice de `identificacion`.
 *
 * Lo consumen Cuentas por Cobrar, Cuentas por Pagar y Reporte de Cartera; el
 * espejo en JavaScript es `public/js/components/identificacion_tercero.js`.
 */
class IdentificacionTercero
{
    /**
     * Clave con la que dos registros del mismo tercero se reconocen entre sí.
     * Un RUC de 13 dígitos terminado en '001' se reduce a sus 10 primeros dígitos
     * (la cédula); cualquier otra identificación se devuelve tal cual (recortada),
     * de modo que solo cruza consigo misma — el comportamiento de siempre.
     */
    public static function claveBase(?string $identificacion): string
    {
        $ide = trim((string) $identificacion);
        if ($ide === '') {
            return '';
        }
        if (preg_match('/^\d{13}$/', $ide) === 1 && substr($ide, -3) === '001') {
            return substr($ide, 0, 10);
        }
        return $ide;
    }

    /**
     * Todas las formas en que puede estar escrita esa identificación en la BD:
     * la cédula y su RUC. Para una identificación que no sigue el patrón devuelve
     * solo esa identificación, así que quien la use no necesita distinguir casos.
     *
     * @return string[] Sin vacíos ni repetidos; array vacío si no hay identificación.
     */
    public static function variantes(?string $identificacion): array
    {
        $base = self::claveBase($identificacion);
        if ($base === '') {
            return [];
        }
        if (preg_match('/^\d{10}$/', $base) === 1) {
            return [$base, $base . '001'];
        }
        return [$base];
    }

    /**
     * Igual que variantes(), pero para un conjunto de identificaciones.
     *
     * @param  iterable<string|null> $identificaciones
     * @return string[]
     */
    public static function variantesDeVarias(iterable $identificaciones): array
    {
        $out = [];
        foreach ($identificaciones as $ide) {
            foreach (self::variantes($ide) as $v) {
                $out[$v] = true;
            }
        }
        return array_keys($out);
    }

    /**
     * La misma regla como expresión SQL, para agrupar o deduplicar dentro de una
     * consulta (`GROUP BY`, `DISTINCT ON`). No usar en un JOIN sobre tablas grandes:
     * al envolver la columna se pierde el índice; para eso está `variantes()`.
     *
     * @param string $col Nombre de columna ya validado por el llamador (nunca entrada del usuario).
     */
    public static function claveBaseSql(string $col): string
    {
        return "CASE WHEN TRIM({$col}) ~ '^[0-9]{13}$' AND RIGHT(TRIM({$col}), 3) = '001'"
             . " THEN LEFT(TRIM({$col}), 10) ELSE TRIM({$col}) END";
    }

    /**
     * Clave de agrupación para una fila ya leída: la clave base si hay identificación,
     * y si no un valor propio del registro (nombre o id) para que los terceros sin
     * identificación no terminen todos en el mismo grupo.
     */
    public static function claveGrupo(?string $identificacion, string $respaldo): string
    {
        $base = self::claveBase($identificacion);
        return $base !== '' ? 'i:' . $base : 'r:' . $respaldo;
    }
}
