-- Agrega columna opciones (JSONB) a la tabla productos
ALTER TABLE productos
    ADD COLUMN IF NOT EXISTS opciones JSONB NOT NULL DEFAULT '{"compra": true, "venta": true}';

UPDATE productos
    SET opciones = '{"compra": true, "venta": true}'
    WHERE opciones = '{}' OR opciones IS NULL;

-- Agrega columna id_cuenta_ingreso a la tabla productos
ALTER TABLE productos
    ADD COLUMN IF NOT EXISTS id_cuenta_ingreso INTEGER NULL REFERENCES plan_cuentas(id);
