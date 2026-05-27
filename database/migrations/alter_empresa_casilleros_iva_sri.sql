-- Migración: Actualizar tabla empresa_casilleros_iva_sri
-- 1. Renombrar columna id_tarifa_iva -> codigo
ALTER TABLE empresa_casilleros_iva_sri RENAME COLUMN id_tarifa_iva TO codigo;

-- 2. Agregar nuevas columnas
ALTER TABLE empresa_casilleros_iva_sri
    ADD COLUMN IF NOT EXISTS casillero_iva_compras VARCHAR(20) DEFAULT '',
    ADD COLUMN IF NOT EXISTS casillero_iva_ventas  VARCHAR(20) DEFAULT '',
    ADD COLUMN IF NOT EXISTS tabla                 VARCHAR(50) DEFAULT 'iva';
