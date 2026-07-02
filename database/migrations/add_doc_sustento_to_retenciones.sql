-- ============================================================
-- Retenciones en Compras — Datos del documento sustento capturados manualmente
-- Se usan cuando la compra/liquidación NO está registrada en el sistema,
-- para poder emitir el comprobante de retención v2.0.0 (docSustento requiere
-- totalSinImpuestos, importeTotal e impuestosDocSustento).
-- ============================================================

ALTER TABLE retencion_compra_cabecera
    ADD COLUMN IF NOT EXISTS doc_sustento_subtotal NUMERIC(14,2) NOT NULL DEFAULT 0, -- totalSinImpuestos del sustento
    ADD COLUMN IF NOT EXISTS doc_sustento_iva      NUMERIC(14,2) NOT NULL DEFAULT 0, -- valor IVA del sustento
    ADD COLUMN IF NOT EXISTS doc_sustento_total    NUMERIC(14,2) NOT NULL DEFAULT 0; -- importeTotal del sustento
