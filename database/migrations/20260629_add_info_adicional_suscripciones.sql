-- ------------------------------------------------------------
-- Información adicional por suscripción (concepto/detalle), igual
-- que en la factura de venta. Se usa al generar el comprobante.
-- Estructura JSON: [{"concepto": "...", "detalle": "..."}, ...]
-- ------------------------------------------------------------
ALTER TABLE suscripciones
    ADD COLUMN IF NOT EXISTS info_adicional JSONB;
