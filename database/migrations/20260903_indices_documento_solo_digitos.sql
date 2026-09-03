-- =====================================================================
-- Índices de apoyo para el enlace por NÚMERO NORMALIZADO entre facturas de
-- venta y sus abonos (retenciones por num_doc_sustento, notas de crédito y
-- débito por num_doc_modificado). Desde 2026-09-03 Cuentas por Cobrar,
-- Ingresos y Facturas de Venta comparan el número normalizado a 15 dígitos
-- (App\Helpers\AbonosVentaSql::normalizar) en vez del texto literal; el índice
-- va sobre EXACTAMENTE la misma expresión para que PostgreSQL pueda usarlo.
-- (Archivo generado desde el helper; si cambia la expresión, regenerar.)
--
-- Opcional: sin estos índices la consulta funciona igual, solo que recorre las
-- tablas completas por empresa. Ejecutar completo en pgAdmin (sin CONCURRENTLY).
-- =====================================================================

CREATE INDEX IF NOT EXISTS idx_ventas_cab_numero_norm
    ON ventas_cabecera (id_empresa, ((CASE WHEN COALESCE((establecimiento || '-' || punto_emision || '-' || secuencial), '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE((establecimiento || '-' || punto_emision || '-' || secuencial), ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((establecimiento || '-' || punto_emision || '-' || secuencial), ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((establecimiento || '-' || punto_emision || '-' || secuencial), ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE((establecimiento || '-' || punto_emision || '-' || secuencial), ''), '[^0-9]', '', 'g') END)))
    WHERE eliminado = false;

CREATE INDEX IF NOT EXISTS idx_ret_vta_det_sustento_norm
    ON retencion_venta_detalle (((CASE WHEN COALESCE(num_doc_sustento, '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE(num_doc_sustento, ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(num_doc_sustento, ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(num_doc_sustento, ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE(num_doc_sustento, ''), '[^0-9]', '', 'g') END)));

CREATE INDEX IF NOT EXISTS idx_nc_cab_doc_modificado_norm
    ON notas_credito_cabecera (id_empresa, ((CASE WHEN COALESCE(num_doc_modificado, '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE(num_doc_modificado, ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(num_doc_modificado, ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(num_doc_modificado, ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE(num_doc_modificado, ''), '[^0-9]', '', 'g') END)))
    WHERE eliminado = false;

CREATE INDEX IF NOT EXISTS idx_nd_cab_doc_modificado_norm
    ON nota_debito_cabecera (id_empresa, ((CASE WHEN COALESCE(num_doc_modificado, '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE(num_doc_modificado, ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(num_doc_modificado, ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(num_doc_modificado, ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE(num_doc_modificado, ''), '[^0-9]', '', 'g') END)))
    WHERE eliminado = false;

ANALYZE ventas_cabecera;
ANALYZE retencion_venta_detalle;
ANALYZE notas_credito_cabecera;
ANALYZE nota_debito_cabecera;
