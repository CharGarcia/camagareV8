-- Agrega columnas de serie y forma de pago a factura_express_plantillas
ALTER TABLE factura_express_plantillas
    ADD COLUMN IF NOT EXISTS id_establecimiento  INTEGER DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS id_punto_emision     INTEGER DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS forma_pago           VARCHAR(10) DEFAULT '20';

-- Agrega dirección del cliente a factura_express_solicitudes
ALTER TABLE factura_express_solicitudes
    ADD COLUMN IF NOT EXISTS direccion_cliente VARCHAR(200) DEFAULT NULL;
