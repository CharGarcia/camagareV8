-- =============================================================================
-- Diagnóstico (SOLO LECTURA): documentos migrados desde MySQL y si cuentan en Métricas
-- -----------------------------------------------------------------------------
-- Métricas del sistema cuenta un documento como "emitido" solo si:
--   tipo_ambiente = '2' (producción)  Y  estado autorizado/autorizada (SRI)
--   o, en compras/recibos/proformas, estado distinto de anulado.
-- El migrador estampa el ambiente que tenía la EMPRESA al migrar: si la empresa estaba en
-- pruebas, sus documentos migrados quedaron en '1' y no aparecen en ningún reporte.
--
-- Una sola grilla: una fila por tabla / ambiente / estado, con la columna "cuenta_en_metricas".
-- No modifica nada. Pegar en pgAdmin y ejecutar (F5).
-- =============================================================================

WITH mig AS (
    SELECT 'ventas_cabecera' AS tabla, v.tipo_ambiente::text AS amb, lower(COALESCE(v.estado,'')) AS est, v.fecha_emision AS fecha
      FROM migracion_mysql_map m JOIN ventas_cabecera v ON v.id = m.id_destino AND v.eliminado = false
     WHERE m.entidad = 'facturas'
    UNION ALL
    SELECT 'notas_credito_cabecera', n.tipo_ambiente::text, lower(COALESCE(n.estado,'')), n.fecha_emision
      FROM migracion_mysql_map m JOIN notas_credito_cabecera n ON n.id = m.id_destino AND n.eliminado = false
     WHERE m.entidad = 'notas_credito'
    UNION ALL
    SELECT 'guias_remision_cabecera', g.tipo_ambiente::text, lower(COALESCE(g.estado,'')), g.fecha_emision
      FROM migracion_mysql_map m JOIN guias_remision_cabecera g ON g.id = m.id_destino AND g.eliminado = false
     WHERE m.entidad = 'guias'
    UNION ALL
    SELECT 'retencion_compra_cabecera', r.tipo_ambiente::text, lower(COALESCE(r.estado,'')), r.fecha_emision
      FROM migracion_mysql_map m JOIN retencion_compra_cabecera r ON r.id = m.id_destino AND r.eliminado = false
     WHERE m.entidad = 'retenciones_compra'
    UNION ALL
    SELECT 'liquidaciones_cabecera', l.tipo_ambiente::text, lower(COALESCE(l.estado,'')), l.fecha_emision
      FROM migracion_mysql_map m JOIN liquidaciones_cabecera l ON l.id = m.id_destino AND l.eliminado = false
     WHERE m.entidad = 'liquidaciones'
    UNION ALL
    SELECT 'compras_cabecera', c.tipo_ambiente::text, lower(COALESCE(c.estado,'')), c.fecha_emision
      FROM migracion_mysql_map m JOIN compras_cabecera c ON c.id = m.id_destino AND c.eliminado = false
     WHERE m.entidad = 'compras'
    UNION ALL
    SELECT 'recibos_venta_cabecera', r.tipo_ambiente::text, lower(COALESCE(r.estado,'')), r.fecha_emision
      FROM migracion_mysql_map m JOIN recibos_venta_cabecera r ON r.id = m.id_destino AND r.eliminado = false
     WHERE m.entidad = 'recibos'
    UNION ALL
    SELECT 'proformas_cabecera', p.tipo_ambiente::text, lower(COALESCE(p.estado,'')), p.fecha_emision
      FROM migracion_mysql_map m JOIN proformas_cabecera p ON p.id = m.id_destino AND p.eliminado = false
     WHERE m.entidad = 'proformas'
)
SELECT tabla,
       amb  AS tipo_ambiente,
       est  AS estado,
       COUNT(*)   AS cantidad,
       MIN(fecha) AS desde,
       MAX(fecha) AS hasta,
       CASE
         WHEN amb IS DISTINCT FROM '2' THEN 'NO: ambiente de pruebas'
         WHEN tabla IN ('compras_cabecera','recibos_venta_cabecera','proformas_cabecera')
              THEN CASE WHEN est IN ('anulado','anulada') THEN 'NO: anulado' ELSE 'SI' END
         WHEN est IN ('autorizado','autorizada') THEN 'SI'
         WHEN est IN ('anulado','anulada') THEN 'NO: anulado'
         ELSE 'NO: sin autorizar (' || est || ')'
       END AS cuenta_en_metricas
FROM mig
GROUP BY tabla, amb, est
ORDER BY tabla, cantidad DESC;
