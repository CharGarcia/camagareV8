-- ============================================================
-- Retenciones en Compras — Código de sustento tributario (tabla 5 SRI)
-- Para documentos NO registrados (captura manual), permite elegir el sustento
-- que se declara en el comprobante de retención v2.0.0 (codSustento).
-- ============================================================

ALTER TABLE retencion_compra_cabecera
    ADD COLUMN IF NOT EXISTS id_sustento_tributario INTEGER;
