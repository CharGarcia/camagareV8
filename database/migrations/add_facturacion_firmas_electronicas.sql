-- Migración: agregar campos de facturación a firmas_electronicas
ALTER TABLE firmas_electronicas
    ADD COLUMN IF NOT EXISTS facturacion_mismos_datos BOOLEAN     NOT NULL DEFAULT true,
    ADD COLUMN IF NOT EXISTS facturacion_tipo_id      VARCHAR(20),
    ADD COLUMN IF NOT EXISTS facturacion_num_id       VARCHAR(13),
    ADD COLUMN IF NOT EXISTS facturacion_nombres      VARCHAR(200),
    ADD COLUMN IF NOT EXISTS facturacion_direccion    VARCHAR(255),
    ADD COLUMN IF NOT EXISTS facturacion_correo       VARCHAR(150),
    ADD COLUMN IF NOT EXISTS facturacion_telefono     VARCHAR(20),
    ADD COLUMN IF NOT EXISTS id_factura               INTEGER;
