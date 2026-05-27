-- ============================================================================
-- MIGRACIÓN: Agregar campo detalle_xml a tablas de cabecera de documentos
-- Objetivo: Guardar el XML completo del documento electrónico para auditoría
--           y trazabilidad, permitiendo reconstruir o reenviar el documento.
-- ============================================================================

ALTER TABLE compras_cabecera          ADD COLUMN IF NOT EXISTS detalle_xml TEXT NULL;
ALTER TABLE guias_remision_cabecera   ADD COLUMN IF NOT EXISTS detalle_xml TEXT NULL;
ALTER TABLE liquidaciones_cabecera    ADD COLUMN IF NOT EXISTS detalle_xml TEXT NULL;
ALTER TABLE notas_credito_cabecera    ADD COLUMN IF NOT EXISTS detalle_xml TEXT NULL;
ALTER TABLE retencion_compra_cabecera ADD COLUMN IF NOT EXISTS detalle_xml TEXT NULL;
ALTER TABLE ventas_cabecera           ADD COLUMN IF NOT EXISTS detalle_xml TEXT NULL;

-- Comentarios de columna para documentación
COMMENT ON COLUMN compras_cabecera.detalle_xml          IS 'XML completo del comprobante electrónico recibido del SRI';
COMMENT ON COLUMN guias_remision_cabecera.detalle_xml   IS 'XML completo del comprobante electrónico recibido del SRI';
COMMENT ON COLUMN liquidaciones_cabecera.detalle_xml    IS 'XML completo del comprobante electrónico recibido del SRI';
COMMENT ON COLUMN notas_credito_cabecera.detalle_xml    IS 'XML completo del comprobante electrónico recibido del SRI';
COMMENT ON COLUMN retencion_compra_cabecera.detalle_xml IS 'XML completo del comprobante electrónico recibido del SRI';
COMMENT ON COLUMN ventas_cabecera.detalle_xml           IS 'XML completo del comprobante electrónico emitido/recibido del SRI';
