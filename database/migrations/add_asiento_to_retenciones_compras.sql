-- Enlace del asiento contable en retenciones de compras (igual que retención de venta).
ALTER TABLE retencion_compra_cabecera
    ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER;
