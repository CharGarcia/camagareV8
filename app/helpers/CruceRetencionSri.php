<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Cruce de una línea de retención (código guardado en el detalle) con su fila del
 * catálogo `retenciones_sri`. Fuente única para asientos, configuración contable,
 * detalle de retenciones y reportes, para que todos resuelvan el mismo concepto.
 *
 * Reglas:
 *   - RENTA se identifica por el código ATS (`cod_anexo_ret`): es el que trae el comprobante
 *     electrónico y no siempre coincide con `codigo_ret` (p. ej. 323 → fila 323I si se cruzara
 *     por codigo_ret).
 *   - IVA se identifica por `codigo_ret` (1, 2, 3, 9, 10…); su código ATS es otro (725, 730…).
 *   - Si con ese código no hay ninguna fila, se busca por el otro (renta por codigo_ret, IVA
 *     por código ATS), para no perder documentos guardados con el código "cruzado".
 *   - Un mismo código puede repetirse en el catálogo (vigencias distintas): gana siempre la fila
 *     de id más reciente ("fila canónica"). Es un LATERAL con LIMIT 1 para no duplicar líneas.
 *   - Si la línea guarda el id del catálogo (`id_retencion_sri`, retenciones de compra emitidas
 *     desde el sistema, que guardan `codigo_ret` como código), ese id solo dice QUÉ concepto es
 *     (impuesto + código visible) y se usa la fila canónica de ese concepto. Así la cuenta
 *     configurada no depende de qué vigencia del concepto se eligió al emitir.
 */
class CruceRetencionSri
{
    /**
     * Coincidencia principal: la fila `$r` del catálogo corresponde al código `$colCodigo` por el
     * código propio de su impuesto (ATS en renta, codigo_ret en IVA).
     * Solo recibe nombres de columna/alias escritos en el código, nunca entrada del usuario.
     */
    public static function condicion(string $r, string $colCodigo): string
    {
        return "((UPPER({$r}.impuesto_ret) = 'RENTA' AND {$r}.cod_anexo_ret = {$colCodigo})"
             . " OR (UPPER({$r}.impuesto_ret) <> 'RENTA' AND {$r}.codigo_ret = {$colCodigo}))";
    }

    /**
     * Coincidencia amplia: el código coincide con cualquiera de los dos códigos de la fila.
     * Se usa como respaldo cuando no hay coincidencia principal (p. ej. un documento de renta
     * guardado con codigo_ret, o uno de IVA con su código ATS).
     */
    public static function condicionAmplia(string $r, string $colCodigo): string
    {
        return "({$r}.cod_anexo_ret = {$colCodigo} OR {$r}.codigo_ret = {$colCodigo})";
    }

    /**
     * `LEFT JOIN LATERAL` que deja en `$alias` la fila del catálogo de la línea (o NULL).
     * Por código: gana la coincidencia principal; si no hay ninguna, la amplia (el otro código).
     *
     * @param string      $colCodigo columna con el código guardado (p. ej. `d.codigo_retencion`)
     * @param string|null $colIdSri  columna con el id del catálogo, si la tabla la tiene
     * @param string      $alias     alias con el que se leen las columnas del catálogo
     */
    public static function joinLateral(string $colCodigo, ?string $colIdSri = null, string $alias = 'rs'): string
    {
        $porCodigo = self::condicionAmplia('rsx_c', $colCodigo);
        $prioridad = 'CASE WHEN ' . self::condicion('rsx_c', $colCodigo) . ' THEN 0 ELSE 1 END';
        if ($colIdSri === null) {
            $where = $porCodigo;
        } else {
            // Mismo concepto que la fila del id guardado; si el id no existe, se cruza por código.
            $mismoConcepto = "EXISTS (SELECT 1 FROM retenciones_sri rsx_o
                                      WHERE rsx_o.id = {$colIdSri}
                                        AND UPPER(rsx_o.impuesto_ret) = UPPER(rsx_c.impuesto_ret)
                                        AND " . self::codigoVisible('rsx_o') . ' = ' . self::codigoVisible('rsx_c') . ")";
            $idValido = "EXISTS (SELECT 1 FROM retenciones_sri rsx_v WHERE rsx_v.id = {$colIdSri})";
            $where = "(({$idValido} AND {$mismoConcepto}) OR (NOT {$idValido} AND {$porCodigo}))";
            // Con id válido todas las filas son del mismo concepto: solo decide el id más reciente.
            $prioridad = "CASE WHEN {$idValido} THEN 0 ELSE ({$prioridad}) END";
        }

        return "LEFT JOIN LATERAL (
                    SELECT rsx_c.* FROM retenciones_sri rsx_c
                    WHERE {$where}
                    ORDER BY {$prioridad}, rsx_c.id DESC LIMIT 1
                ) {$alias} ON true";
    }

    /**
     * Código que exige el SRI para la línea (XML `codigoRetencion` y ATS `codRetAir`), según las
     * fichas técnicas: renta = código del Catálogo del ATS (Tablas 3.x, p. ej. 312A, 323I);
     * IVA = Tabla 20 de la ficha de comprobantes (9, 10, 1, 11, 2, 3, 7, 8).
     * `$alias` es la fila resuelta por joinLateral(); sin fila, queda el código guardado.
     */
    public static function codigoSri(string $alias, string $colCodigo): string
    {
        return "COALESCE(NULLIF(" . self::codigoVisible($alias) . ", ''), {$colCodigo})";
    }

    /** Código que se muestra al usuario para una fila del catálogo: el ATS en renta, codigo_ret en IVA. */
    public static function codigoVisible(string $r): string
    {
        return "(CASE WHEN UPPER({$r}.impuesto_ret) = 'RENTA' THEN {$r}.cod_anexo_ret ELSE {$r}.codigo_ret END)";
    }
}
