-- Script para agregar campos de auditoría a la tabla vendedores
ALTER TABLE vendedores ADD COLUMN IF NOT EXISTS created_by INT;
ALTER TABLE vendedores ADD COLUMN IF NOT EXISTS updated_by INT;
ALTER TABLE vendedores ADD COLUMN IF NOT EXISTS deleted_by INT;
ALTER TABLE vendedores ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;
ALTER TABLE vendedores ADD COLUMN IF NOT EXISTS creado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP; -- Para uniformidad
ALTER TABLE vendedores ADD COLUMN IF NOT EXISTS actualizado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP; -- Para uniformidad
ALTER TABLE vendedores ADD COLUMN IF NOT EXISTS eliminado BOOLEAN DEFAULT FALSE;

ALTER TABLE vendedores ALTER COLUMN correo DROP NOT NULL;
ALTER TABLE vendedores ALTER COLUMN telefono DROP NOT NULL;
ALTER TABLE vendedores ALTER COLUMN direccion DROP NOT NULL;

-- Sincronizar created_at y updated_at si ya existen
-- (En research vimos que ya existen created_at y updated_at en vendedores)
-- Hagamos una limpieza si es necesario o usemos los existentes.

-- Si ya existen created_at y updated_at, los mantenemos.
-- El research mostró: created_at | timestamp without time zone | YES | CURRENT_TIMESTAMP
-- Así que no hay problema.
