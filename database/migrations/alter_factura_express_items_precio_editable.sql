-- Agrega la opción "precio editable" por ítem en las plantillas Factura Express QR.
-- Permite que el cliente edite el precio en el formulario público (igual que la cantidad).
ALTER TABLE factura_express_items
    ADD COLUMN IF NOT EXISTS precio_editable BOOLEAN NOT NULL DEFAULT false;
