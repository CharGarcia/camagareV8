-- Migración para añadir campos de autorización y caducidad a compras
ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS autorizacion_desde VARCHAR(20);
ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS autorizacion_hasta VARCHAR(20);
ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS fecha_caducidad DATE;
