-- Diagnóstico (SOLO LECTURA): ¿por qué una retención de compra no se descuenta del saldo
-- de su factura en Egresos (compras por pagar) y en Cuentas por Pagar?
--
-- Egresos y CxP enlazan la retención con la factura por una de dos vías:
--   A) retencion_compra_cabecera.id_compra = compras_cabecera.id
--   B) si id_compra es NULL: dígitos de num_doc_sustento = dígitos de
--      establecimiento_prov-punto_emision_prov-secuencial_prov, MISMO id_proveedor.
-- y solo cuentan las retenciones con eliminado = false y estado no borrador/pendiente/anulada.
--
-- Cambiar el número de factura de compra (tal como sale en la lista) en la línea marcada.

WITH param AS (
    SELECT '001-001-000000123'::text AS factura   -- <== número de la factura de compra
),
compra AS (
    SELECT c.id, c.id_empresa, c.id_proveedor, c.importe_total, c.estado, c.tipo_ambiente,
           CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov) AS numero,
           regexp_replace(CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov), '[^0-9]', '', 'g') AS digitos
    FROM compras_cabecera c, param
    WHERE c.eliminado = false
      AND regexp_replace(CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov), '[^0-9]', '', 'g')
          = regexp_replace(param.factura, '[^0-9]', '', 'g')
)
SELECT c.id                AS id_compra,
       c.id_empresa,
       c.numero            AS factura,
       c.id_proveedor      AS prov_compra,
       c.importe_total,
       r.id                AS id_retencion,
       CONCAT(r.establecimiento,'-',r.punto_emision,'-',r.secuencial) AS retencion,
       r.id_compra         AS ret_id_compra,
       r.id_liquidacion    AS ret_id_liq,
       r.id_proveedor      AS prov_ret,
       r.num_doc_sustento,
       r.estado,
       r.eliminado,
       r.total_retenido,
       r.fecha_emision     AS fecha_ret,
       CASE
         WHEN r.id IS NULL                                   THEN 'NO HAY RETENCION que apunte a esta factura'
         WHEN r.eliminado IS DISTINCT FROM false             THEN 'eliminado no es false (' || COALESCE(r.eliminado::text,'NULL') || ')'
         WHEN UPPER(COALESCE(r.estado,'')) IN ('ANULADO','ANULADA','BORRADOR','PENDIENTE') THEN 'estado excluido: ' || COALESCE(r.estado,'NULL')
         WHEN r.id_compra = c.id                             THEN 'OK via id_compra'
         WHEN r.id_compra IS NOT NULL                        THEN 'id_compra apunta a OTRA compra (' || r.id_compra || ')'
         WHEN r.id_liquidacion IS NOT NULL                   THEN 'tiene id_liquidacion, no cruza con compra'
         WHEN r.id_proveedor IS DISTINCT FROM c.id_proveedor THEN 'proveedor distinto (ret ' || COALESCE(r.id_proveedor::text,'NULL') || ' vs compra ' || c.id_proveedor || ')'
         WHEN regexp_replace(COALESCE(r.num_doc_sustento,''), '[^0-9]', '', 'g') <> c.digitos THEN 'num_doc_sustento no coincide en digitos'
         ELSE 'OK via num_doc_sustento'
       END AS diagnostico
FROM compra c
LEFT JOIN retencion_compra_cabecera r
       ON r.id_empresa = c.id_empresa
      AND (   r.id_compra = c.id
           OR right(regexp_replace(COALESCE(r.num_doc_sustento,''), '[^0-9]', '', 'g'), 6) = right(c.digitos, 6))
ORDER BY c.id, r.id;
