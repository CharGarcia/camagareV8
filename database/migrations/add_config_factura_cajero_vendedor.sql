-- Migración: campos de configuración de facturación en tabla empresas
-- Agrega columnas para mostrar cajero y vendedor en info adicional de la factura

ALTER TABLE empresas ADD COLUMN IF NOT EXISTS mostrar_cajero_factura  VARCHAR(10) DEFAULT 'false';
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS mostrar_vendedor_factura VARCHAR(10) DEFAULT 'false';
