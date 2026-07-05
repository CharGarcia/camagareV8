-- Trazabilidad: recibo de venta generado a partir de una factura de venta.
-- id_factura_origen referencia ventas_cabecera(id). Nullable: un recibo puede
-- crearse directo (sin factura de origen) o desde una factura.
ALTER TABLE recibos_venta_cabecera
    ADD COLUMN IF NOT EXISTS id_factura_origen INTEGER NULL;

CREATE INDEX IF NOT EXISTS idx_recibos_factura_origen
    ON recibos_venta_cabecera(id_factura_origen);
