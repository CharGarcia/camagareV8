-- =============================================================================
-- Migración: vincular facturas de venta con su proforma de origen
-- Fecha: 2026-06-23
-- Descripción: Agrega ventas_cabecera.id_proforma para listar todas las
--              facturas generadas desde una proforma (relación 1:N).
-- =============================================================================

ALTER TABLE ventas_cabecera ADD COLUMN IF NOT EXISTS id_proforma INTEGER;

COMMENT ON COLUMN ventas_cabecera.id_proforma IS 'FK lógica a proformas_cabecera cuando la factura se generó desde una proforma';

CREATE INDEX IF NOT EXISTS idx_ventas_id_proforma ON ventas_cabecera(id_proforma);
