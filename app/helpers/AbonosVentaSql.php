<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Fragmentos SQL compartidos para calcular los ABONOS de una factura de venta
 * (retenciones, notas de crédito y notas de débito) de forma idéntica en todos
 * los módulos que muestran un saldo por cobrar: Cuentas por Cobrar, Ingresos
 * (buscador de documentos pendientes) y Facturas de Venta (estado de pago).
 *
 * Reglas que unifica (antes cada repositorio las tenía copiadas con diferencias):
 *
 *  1. Una retención puede sustentar VARIAS facturas (retenciones electrónicas
 *     del SRI). Cada factura recibe únicamente la suma de `valor_retenido` de
 *     las líneas del detalle cuyo `num_doc_sustento` apunta a ella — nunca el
 *     total de la cabecera. El total de la cabecera solo se usa cuando la
 *     retención se registró desde la factura (`id_venta`) y su detalle no enlaza
 *     ninguna factura por número (sin detalle o con sustento vacío).
 *  2. El enlace por número (`num_doc_sustento`, `num_doc_modificado`) compara
 *     el número NORMALIZADO a 15 dígitos: `001-001-1120`, `001001000001120` y `001-001-000001120`
 *     son el mismo documento. Antes se comparaba el texto literal contra
 *     `EEE-PPP-SSSSSSSSS` y un formato distinto dejaba la retención/nota sin
 *     restar del saldo, aunque sí se restaba en Cartera y en saldos iniciales.
 *
 * Todos los métodos devuelven SQL para interpolar dentro de una consulta
 * preparada. `$empresaExpr` es un entero ya validado o un placeholder
 * (`':id_empresa'`); `$extraWhere` son condiciones adicionales que empiezan
 * con `AND` sobre el alias indicado en cada método.
 */
final class AbonosVentaSql
{
    /**
     * Normaliza un número de comprobante a 15 dígitos (EEEPPPSSSSSSSSS):
     *  - con guiones (`001-001-13`, `1-1-000000013`): cada parte se limpia de
     *    todo lo que no sea dígito y se rellena con ceros (3-3-9);
     *  - sin guiones (`001001000000013`, como viene en el XML del SRI): solo se
     *    quitan los caracteres no numéricos.
     * NULL → cadena vacía. Solo usa funciones IMMUTABLE para poder indexarse
     * (database/migrations/20260903_indices_documento_solo_digitos.sql).
     */
    public static function normalizar(string $expr): string
    {
        $x = "COALESCE({$expr}, '')";
        $p = static fn (int $n): string => "regexp_replace(split_part({$x}, '-', {$n}), '[^0-9]', '', 'g')";

        return "(CASE WHEN {$x} LIKE '%-%-%'"
            . " THEN lpad({$p(1)}, 3, '0') || lpad({$p(2)}, 3, '0') || lpad({$p(3)}, 9, '0')"
            . " ELSE regexp_replace({$x}, '[^0-9]', '', 'g') END)";
    }

    /**
     * Número EEE-PPP-SSSSSSSSS de una factura, normalizado con normalizar().
     * Se arma con `||` (inmutable) y no con CONCAT() (estable) por el mismo
     * motivo: que la expresión sea indexable.
     */
    public static function numFactura(string $alias = 'v'): string
    {
        return self::normalizar(
            "({$alias}.establecimiento || '-' || {$alias}.punto_emision || '-' || {$alias}.secuencial)"
        );
    }

    /**
     * Espejo en PHP de normalizar(): sirve para preparar el valor de un
     * parámetro que se comparará contra una columna normalizada en SQL.
     * Misma regla: con dos guiones se rellena 3-3-9 (lpad recorta por la
     * derecha si la parte es más larga); sin guiones, solo dígitos.
     */
    public static function normalizarValor(string $numero): string
    {
        $numero = trim($numero);
        $digitos = static fn (string $s): string => preg_replace('/[^0-9]/', '', $s) ?? '';
        if (substr_count($numero, '-') >= 2) {
            $partes = explode('-', $numero);
            $pad    = static fn (string $s, int $n): string => str_pad(substr($digitos($s), 0, $n), $n, '0', STR_PAD_LEFT);
            return $pad($partes[0] ?? '', 3) . $pad($partes[1] ?? '', 3) . $pad($partes[2] ?? '', 9);
        }
        return $digitos($numero);
    }

    /**
     * Subconsulta (sin alias) con lo retenido por factura: columnas
     * `id_venta` y `total_retenido`. Pensada para `WITH retenido AS (...)`.
     *
     * @param string $empresaExpr  Entero validado o placeholder de la empresa.
     * @param string $extraWhereR  Condiciones extra sobre la cabecera `r`
     *                             (p. ej. corte por fecha o ambiente).
     */
    public static function cteRetenidoPorFactura(string $empresaExpr, string $extraWhereR = ''): string
    {
        $numVc  = self::numFactura('vc');
        $numSus = self::normalizar('rd.num_doc_sustento');

        return "
            SELECT x.id_venta, SUM(x.monto) AS total_retenido
            FROM (
                -- (a) Líneas del detalle enlazadas a la factura por número de sustento
                --     (número normalizado): cada factura recibe lo retenido en SUS líneas.
                SELECT vc.id AS id_venta, SUM(rd.valor_retenido) AS monto
                FROM retencion_venta_cabecera r
                JOIN retencion_venta_detalle rd ON rd.id_retencion = r.id
                JOIN ventas_cabecera vc
                     ON vc.id_empresa = r.id_empresa
                    AND vc.eliminado  = false
                    AND {$numVc} = {$numSus}
                WHERE r.eliminado  = false
                  AND r.id_empresa = {$empresaExpr}
                  AND COALESCE(rd.num_doc_sustento, '') <> ''
                  {$extraWhereR}
                GROUP BY vc.id

                UNION ALL

                -- (b) Retenciones registradas desde la factura (id_venta) cuyo detalle
                --     no enlaza ninguna factura por número: total de la cabecera.
                SELECT r.id_venta, (r.total_renta + r.total_iva + r.total_isd) AS monto
                FROM retencion_venta_cabecera r
                WHERE r.eliminado  = false
                  AND r.id_empresa = {$empresaExpr}
                  AND r.id_venta IS NOT NULL
                  {$extraWhereR}
                  AND NOT EXISTS (
                      SELECT 1
                      FROM retencion_venta_detalle rd
                      JOIN ventas_cabecera vc
                           ON vc.id_empresa = r.id_empresa
                          AND vc.eliminado  = false
                          AND {$numVc} = {$numSus}
                      WHERE rd.id_retencion = r.id
                        AND COALESCE(rd.num_doc_sustento, '') <> ''
                  )
            ) x
            GROUP BY x.id_venta
        ";
    }

    /**
     * Subconsulta (sin alias) con el total de notas de crédito o débito por
     * documento modificado: columnas `num_norm` (número normalizado) y `$totalAlias`.
     * Enlazar con `ON alias.num_norm = AbonosVentaSql::numFactura('v')`.
     *
     * @param string $tabla       'notas_credito_cabecera' | 'nota_debito_cabecera'
     * @param string $totalAlias  Nombre de la columna sumada (`total_nc` / `total_nd`).
     * @param string $empresaExpr Entero validado o placeholder de la empresa.
     * @param string $extraWhere  Condiciones extra sobre el alias `n`.
     * @param bool   $porEmpresa  Si true, agrega la columna `id_empresa` al resultado (y al
     *                            GROUP BY) para que el llamador enlace también por empresa.
     *                            Necesario cuando `$empresaExpr` abarca varias empresas
     *                            (consolidado por RUC): dos establecimientos podrían tener
     *                            un número de documento igual, y sin la empresa en el
     *                            enlace la nota de uno descontaría la factura del otro.
     */
    public static function cteNotasPorFactura(string $tabla, string $totalAlias, string $empresaExpr, string $extraWhere = '', bool $porEmpresa = false): string
    {
        $tabla      = $tabla === 'nota_debito_cabecera' ? 'nota_debito_cabecera' : 'notas_credito_cabecera';
        $totalAlias = preg_replace('/[^a-z_]/', '', $totalAlias) ?: 'total';
        $numNorm    = self::normalizar('n.num_doc_modificado');
        $colEmp     = $porEmpresa ? 'n.id_empresa, ' : '';
        $groupBy    = $porEmpresa ? '1, 2' : '1';

        return "
            SELECT {$colEmp}{$numNorm} AS num_norm,
                   SUM(n.importe_total) AS {$totalAlias}
            FROM {$tabla} n
            WHERE n.estado    != 'anulado'
              AND n.eliminado  = false
              AND n.id_empresa = {$empresaExpr}
              {$extraWhere}
            GROUP BY {$groupBy}
        ";
    }

    /**
     * Igual que cteRetenidoPorFactura(), pero solo para las facturas de un conjunto ya
     * filtrado `$docs` (un CTE con columnas `id`, `id_empresa` y `num_norm` = numFactura()).
     * Columnas: `id_venta` y `total_retenido`.
     *
     * cteRetenidoPorFactura() normaliza el número de TODAS las facturas de la empresa para
     * cruzarlas con las retenciones; en un reporte de cartera, donde casi todo el histórico
     * ya está cobrado, ese trabajo crece con los años aunque lo pendiente sea poco. Aquí el
     * número sale del conjunto (se calcula una vez por factura candidata).
     *
     * @param string $docs         Nombre del CTE con las facturas candidatas.
     * @param string $empresaExpr  Entero validado o expresión `ANY(ARRAY[...])` de la empresa.
     * @param string $extraWhereR  Condiciones extra sobre la cabecera `r` (p. ej. corte por fecha).
     * @param bool   $porIndice    true si existe `idx_ret_vta_det_sustento_norm` (índice sobre el
     *                             sustento normalizado, database/migrations/20260903_…): busca las
     *                             líneas de cada factura por ese índice. Sin él, normaliza todas
     *                             las líneas de retención una vez y las cruza por hash (una
     *                             búsqueda por factura sin índice leería la tabla entera cada vez).
     */
    public static function cteRetenidoDeDocumentos(string $docs, string $empresaExpr, string $extraWhereR = '', bool $porIndice = false): string
    {
        $numVc  = self::numFactura('vc');
        $numSus = self::normalizar('rd.num_doc_sustento');

        // (a) Líneas del detalle enlazadas a la factura por número de sustento.
        $lineas = $porIndice
            ? "SELECT d.id AS id_venta, ln.monto
                FROM {$docs} d
                CROSS JOIN LATERAL (
                    SELECT SUM(rd.valor_retenido) AS monto
                    FROM retencion_venta_detalle rd
                    JOIN retencion_venta_cabecera r
                         ON r.id = rd.id_retencion
                        AND r.id_empresa = d.id_empresa
                        AND r.eliminado  = false
                    WHERE {$numSus} = d.num_norm
                      AND COALESCE(rd.num_doc_sustento, '') <> ''
                      {$extraWhereR}
                ) ln
                WHERE ln.monto IS NOT NULL"
            : "SELECT d.id AS id_venta, SUM(rd.valor_retenido) AS monto
                FROM {$docs} d
                JOIN retencion_venta_cabecera r
                     ON r.id_empresa = d.id_empresa
                    AND r.eliminado  = false
                JOIN retencion_venta_detalle rd
                     ON rd.id_retencion = r.id
                    AND {$numSus} = d.num_norm
                WHERE COALESCE(rd.num_doc_sustento, '') <> ''
                  {$extraWhereR}
                GROUP BY d.id";

        return "
            SELECT x.id_venta, SUM(x.monto) AS total_retenido
            FROM (
                {$lineas}

                UNION ALL

                -- (b) Retenciones registradas desde la factura (id_venta) cuyo detalle
                --     no enlaza ninguna factura por número: total de la cabecera.
                --     OFFSET 0 obliga a cruzar primero con las candidatas: sin esa barrera el
                --     planificador evaluaba el NOT EXISTS en TODAS las retenciones con id_venta.
                SELECT r.id_venta, (r.total_renta + r.total_iva + r.total_isd) AS monto
                FROM (
                    SELECT r.*
                    FROM {$docs} d
                    JOIN retencion_venta_cabecera r ON r.id_venta = d.id
                    WHERE r.eliminado  = false
                      AND r.id_empresa = {$empresaExpr}
                      {$extraWhereR}
                    OFFSET 0
                ) r
                WHERE NOT EXISTS (
                      SELECT 1
                      FROM retencion_venta_detalle rd
                      JOIN ventas_cabecera vc
                           ON vc.id_empresa = r.id_empresa
                          AND vc.eliminado  = false
                          AND {$numVc} = {$numSus}
                      WHERE rd.id_retencion = r.id
                        AND COALESCE(rd.num_doc_sustento, '') <> ''
                  )
            ) x
            GROUP BY x.id_venta
        ";
    }

    /**
     * Igual que cteNotasPorFactura(..., porEmpresa: true), pero solo para las facturas del
     * conjunto `$docs` (ver cteRetenidoDeDocumentos()). Columnas: `id_venta` y `$totalAlias`.
     * Dos facturas del conjunto con la misma empresa y número reciben ambas la nota, igual
     * que con el enlace por (empresa, número) de cteNotasPorFactura().
     *
     * @param string $extraWhere Condiciones extra sobre el alias `n`.
     */
    public static function cteNotasDeDocumentos(string $tabla, string $totalAlias, string $docs, string $extraWhere = ''): string
    {
        $tabla      = $tabla === 'nota_debito_cabecera' ? 'nota_debito_cabecera' : 'notas_credito_cabecera';
        $totalAlias = preg_replace('/[^a-z_]/', '', $totalAlias) ?: 'total';
        $numNorm    = self::normalizar('n.num_doc_modificado');

        return "
            SELECT d.id AS id_venta, SUM(n.importe_total) AS {$totalAlias}
            FROM {$docs} d
            JOIN {$tabla} n
                 ON n.id_empresa = d.id_empresa
                AND {$numNorm}   = d.num_norm
            WHERE n.estado    != 'anulado'
              AND n.eliminado  = false
              {$extraWhere}
            GROUP BY d.id
        ";
    }

    /**
     * Subconsulta escalar (correlacionada con la factura `$alias`) con lo
     * retenido de esa factura. Misma regla que cteRetenidoPorFactura(); para
     * listados que calculan el saldo fila a fila (Facturas de Venta).
     */
    public static function subRetenidoFactura(string $alias = 'v'): string
    {
        $numV   = self::numFactura($alias);
        $numVc  = self::numFactura('vc');
        $numSus = self::normalizar('rd.num_doc_sustento');

        return "(SELECT COALESCE(SUM(x.monto), 0) FROM (
                    SELECT rd.valor_retenido AS monto
                    FROM retencion_venta_cabecera r
                    JOIN retencion_venta_detalle rd ON rd.id_retencion = r.id
                    WHERE r.eliminado = false AND r.id_empresa = {$alias}.id_empresa
                      AND COALESCE(rd.num_doc_sustento, '') <> ''
                      AND {$numSus} = {$numV}
                    UNION ALL
                    SELECT (r.total_renta + r.total_iva + r.total_isd)
                    FROM retencion_venta_cabecera r
                    WHERE r.eliminado = false AND r.id_empresa = {$alias}.id_empresa
                      AND r.id_venta = {$alias}.id
                      AND NOT EXISTS (
                          SELECT 1 FROM retencion_venta_detalle rd
                          JOIN ventas_cabecera vc
                               ON vc.id_empresa = r.id_empresa AND vc.eliminado = false
                              AND {$numVc} = {$numSus}
                          WHERE rd.id_retencion = r.id AND COALESCE(rd.num_doc_sustento, '') <> ''
                      )
                ) x)";
    }

    /**
     * Subconsulta escalar (correlacionada con la factura `$alias`) con el total
     * de notas de crédito o débito aplicadas a esa factura (enlace por dígitos).
     */
    public static function subNotasFactura(string $tabla, string $alias = 'v'): string
    {
        $tabla   = $tabla === 'nota_debito_cabecera' ? 'nota_debito_cabecera' : 'notas_credito_cabecera';
        $numV    = self::numFactura($alias);
        $numNorm = self::normalizar('n.num_doc_modificado');

        return "(SELECT COALESCE(SUM(n.importe_total), 0) FROM {$tabla} n
                 WHERE n.id_empresa = {$alias}.id_empresa AND n.estado != 'anulado' AND n.eliminado = false
                   AND {$numNorm} = {$numV})";
    }
}
