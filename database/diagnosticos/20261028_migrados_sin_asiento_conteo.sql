-- =============================================================================
-- Diagnóstico (SOLO LECTURA): documentos INSERTADOS por la migración que siguen
-- sin asiento contable, por módulo y mes. Sirve para decidir desde qué fecha el
-- aviso de pendientes debe volver a tomarlos (generarlos afecta ese periodo).
-- Cambiar el id de empresa en el bloque p (122 = RUC 1793196214001).
-- =============================================================================
WITH p AS (SELECT 122 AS id_empresa),
docs AS (
    SELECT 'Facturas' AS modulo, d.fecha_emision, d.estado FROM p JOIN migracion_mysql_map m ON m.id_empresa = p.id_empresa AND m.entidad = 'facturas' AND m.vinculado IS NOT TRUE
      JOIN ventas_cabecera d ON d.id = m.id_destino WHERE d.eliminado = false AND d.id_asiento_contable IS NULL
    UNION ALL
    SELECT 'Notas de crédito', d.fecha_emision, d.estado FROM p JOIN migracion_mysql_map m ON m.id_empresa = p.id_empresa AND m.entidad = 'notas_credito' AND m.vinculado IS NOT TRUE
      JOIN notas_credito_cabecera d ON d.id = m.id_destino WHERE d.eliminado = false AND d.id_asiento_contable IS NULL
    UNION ALL
    SELECT 'Recibos', d.fecha_emision, d.estado FROM p JOIN migracion_mysql_map m ON m.id_empresa = p.id_empresa AND m.entidad = 'recibos' AND m.vinculado IS NOT TRUE
      JOIN recibos_venta_cabecera d ON d.id = m.id_destino WHERE d.eliminado = false AND d.id_asiento_contable IS NULL
    UNION ALL
    SELECT 'Compras', d.fecha_emision, d.estado FROM p JOIN migracion_mysql_map m ON m.id_empresa = p.id_empresa AND m.entidad = 'compras' AND m.vinculado IS NOT TRUE
      JOIN compras_cabecera d ON d.id = m.id_destino WHERE d.eliminado = false AND d.id_asiento_contable IS NULL
    UNION ALL
    SELECT 'Liquidaciones', d.fecha_emision, d.estado FROM p JOIN migracion_mysql_map m ON m.id_empresa = p.id_empresa AND m.entidad = 'liquidaciones' AND m.vinculado IS NOT TRUE
      JOIN liquidaciones_cabecera d ON d.id = m.id_destino WHERE d.eliminado = false AND d.id_asiento_contable IS NULL
    UNION ALL
    SELECT 'Retenciones de venta', d.fecha_emision, NULL FROM p JOIN migracion_mysql_map m ON m.id_empresa = p.id_empresa AND m.entidad = 'retenciones_venta' AND m.vinculado IS NOT TRUE
      JOIN retencion_venta_cabecera d ON d.id = m.id_destino WHERE d.eliminado = false AND d.id_asiento_contable IS NULL
    UNION ALL
    SELECT 'Retenciones de compra', d.fecha_emision, d.estado FROM p JOIN migracion_mysql_map m ON m.id_empresa = p.id_empresa AND m.entidad = 'retenciones_compra' AND m.vinculado IS NOT TRUE
      JOIN retencion_compra_cabecera d ON d.id = m.id_destino WHERE d.eliminado = false AND d.id_asiento_contable IS NULL
    UNION ALL
    SELECT 'Ingresos', d.fecha_emision, d.estado FROM p JOIN migracion_mysql_map m ON m.id_empresa = p.id_empresa AND m.entidad = 'ingresos' AND m.vinculado IS NOT TRUE
      JOIN ingresos_cabecera d ON d.id = m.id_destino WHERE d.eliminado = false AND d.id_asiento_contable IS NULL
    UNION ALL
    SELECT 'Egresos', d.fecha_emision, d.estado FROM p JOIN migracion_mysql_map m ON m.id_empresa = p.id_empresa AND m.entidad = 'egresos' AND m.vinculado IS NOT TRUE
      JOIN egresos_cabecera d ON d.id = m.id_destino WHERE d.eliminado = false AND d.id_asiento_contable IS NULL
)
SELECT modulo, TO_CHAR(fecha_emision, 'YYYY-MM') AS mes, COUNT(*) AS documentos
FROM docs
WHERE UPPER(TRIM(COALESCE(estado, ''))) <> 'ANULADO'
GROUP BY modulo, TO_CHAR(fecha_emision, 'YYYY-MM')
ORDER BY mes, modulo;
