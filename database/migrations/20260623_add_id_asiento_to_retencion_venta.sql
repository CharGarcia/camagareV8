-- =============================================================================
-- Migración: vincular la retención en ventas con su asiento contable
-- Fecha: 2026-06-23
-- Descripción: Agrega retencion_venta_cabecera.id_asiento_contable para registrar
--              el asiento contable generado al guardar la retención.
-- =============================================================================

ALTER TABLE retencion_venta_cabecera ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER;

COMMENT ON COLUMN retencion_venta_cabecera.id_asiento_contable IS 'FK lógica a asientos_contables generado al guardar la retención';

CREATE INDEX IF NOT EXISTS idx_ret_vta_cab_asiento ON retencion_venta_cabecera(id_asiento_contable);
