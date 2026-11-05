-- Diagnóstico (SOLO LECTURA), resumen por empresa: retenciones de compra AUTORIZADAS
-- y por qué Egresos / Cuentas por Pagar las descuentan o no de su factura.
-- Cambiar el id de empresa en la línea marcada.

WITH param AS (SELECT 94::int AS id_empresa),   -- <== id de la empresa
ret AS (
    SELECT r.*,
           regexp_replace(COALESCE(r.num_doc_sustento,''), '[^0-9]', '', 'g') AS dig,
           (SELECT COALESCE(SUM(d.valor_retenido),0) FROM retencion_compra_detalle d WHERE d.id_retencion = r.id) AS suma_detalle
    FROM retencion_compra_cabecera r, param
    WHERE r.id_empresa = param.id_empresa
      AND r.eliminado = false
      AND r.estado = 'autorizada'
),
clasif AS (
    SELECT r.id,
           CASE
             WHEN r.id_compra IS NOT NULL AND NOT EXISTS (SELECT 1 FROM compras_cabecera c WHERE c.id = r.id_compra AND c.eliminado = false)
                  THEN '3. id_compra apunta a compra eliminada/inexistente'
             WHEN r.id_compra IS NOT NULL OR r.id_liquidacion IS NOT NULL
                  THEN CASE WHEN ROUND(r.total_retenido,2) = 0 AND r.suma_detalle > 0
                            THEN '2. Enlazada, pero total_retenido = 0 (el detalle suma > 0)'
                            ELSE '1. OK enlazada por id' END
             WHEN EXISTS (SELECT 1 FROM compras_cabecera c
                          WHERE c.id_empresa = r.id_empresa AND c.eliminado = false AND c.id_proveedor = r.id_proveedor
                            AND regexp_replace(CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov), '[^0-9]', '', 'g') = r.dig)
                  THEN CASE WHEN ROUND(r.total_retenido,2) = 0 AND r.suma_detalle > 0
                            THEN '2. Enlazada, pero total_retenido = 0 (el detalle suma > 0)'
                            ELSE '1. OK enlazada por número' END
             WHEN EXISTS (SELECT 1 FROM compras_cabecera c
                          WHERE c.id_empresa = r.id_empresa AND c.eliminado = false
                            AND regexp_replace(CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov), '[^0-9]', '', 'g') = r.dig)
                  THEN '4. Número coincide pero el PROVEEDOR es otro'
             WHEN EXISTS (SELECT 1 FROM compras_cabecera c
                          WHERE c.id_empresa = r.id_empresa AND c.eliminado = false AND c.id_proveedor = r.id_proveedor
                            AND ltrim(c.secuencial_prov,'0') = ltrim(right(r.dig, 9),'0')
                            AND c.establecimiento_prov = substr(r.dig,1,3))
                  THEN '5. Misma factura, pero el número difiere en ceros/formato'
             ELSE '6. Sin factura de compra que cruce'
           END AS causa
    FROM ret r
)
SELECT causa, COUNT(*) AS retenciones, MIN(id) AS ejemplo_id_retencion
FROM clasif
GROUP BY causa
ORDER BY causa;
