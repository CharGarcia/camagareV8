-- Diagnóstico: por qué 2026-10-29_compras_detalle_info_adicional_desde_xml.sql no llenó
-- el adicional. Repite sus pasos (extraer comprobante, contar líneas, comparar cantidad y
-- precio de cada pareja) y agrupa las compras por el resultado. Solo lectura.
WITH c AS (
    SELECT c.id,
           substring(c.detalle_xml FROM '(<(factura|notaCredito)[ >].*</(factura|notaCredito)>)') AS comp
      FROM compras_cabecera c
     WHERE c.eliminado = false
       AND c.tipo_comprobante IN ('01', '04')
       AND c.detalle_xml LIKE '%<detAdicional%'
), x AS (
    SELECT c.id, c.comp,
           CASE WHEN c.comp IS NOT NULL AND xml_is_well_formed_document(c.comp)
                THEN xpath('/*/detalles/detalle', c.comp::xml) END AS dets
      FROM c
), y AS (
    SELECT x.id, x.comp, x.dets,
           COALESCE(array_length(x.dets, 1), 0) AS n_xml,
           (SELECT count(*) FROM compras_detalle d WHERE d.id_compra = x.id) AS n_bd
      FROM x
), z AS (
    SELECT y.*,
           (SELECT count(*)
              FROM (SELECT d.cantidad, d.precio_unitario,
                           row_number() OVER (ORDER BY d.id) AS rn
                      FROM compras_detalle d WHERE d.id_compra = y.id) b
              JOIN LATERAL (
                    SELECT NULLIF(BTRIM((xpath('/detalle/cantidad/text()', y.dets[b.rn]))[1]::text), '')::numeric AS cant,
                           NULLIF(BTRIM((xpath('/detalle/precioUnitario/text()', y.dets[b.rn]))[1]::text), '')::numeric AS pu
              ) xm ON true
             WHERE y.dets IS NOT NULL AND y.n_xml = y.n_bd
               AND (abs(COALESCE(xm.cant, 0) - b.cantidad) > 0.000001
                    OR abs(COALESCE(xm.pu, 0) - b.precio_unitario) > 0.000001)) AS n_distintas
      FROM y
)
SELECT CASE WHEN comp IS NULL       THEN '1. no se encontró <factura>/<notaCredito> en el XML'
            WHEN dets IS NULL       THEN '2. XML mal formado'
            WHEN n_xml <> n_bd      THEN '3. distinto número de líneas'
            WHEN n_distintas > 0    THEN '4. cantidad o precio distinto al XML'
            ELSE                         '5. OK: el script la actualizaría' END AS estado,
       count(*)              AS compras,
       min(id)               AS ejemplo_id_compra,
       min(left(comp, 90))   AS muestra_xml
  FROM z
 GROUP BY 1
 ORDER BY 1;
