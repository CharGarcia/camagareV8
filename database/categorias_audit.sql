-- Migración para añadir campos de auditoría y borrado lógico a la tabla categorias

ALTER TABLE categorias ADD COLUMN IF NOT EXISTS status SMALLINT DEFAULT 1;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS created_by INTEGER NULL;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS updated_by INTEGER NULL;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS eliminado BOOLEAN DEFAULT false;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP WITHOUT TIME ZONE NULL;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS deleted_by INTEGER NULL;

-- Relaciones opcionales (si se quiere en el futuro)
-- ALTER TABLE categorias ADD CONSTRAINT fk_cat_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL;
-- ALTER TABLE categorias ADD CONSTRAINT fk_cat_updated_by FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL;
-- ALTER TABLE categorias ADD CONSTRAINT fk_cat_deleted_by FOREIGN KEY (deleted_by) REFERENCES usuarios(id) ON DELETE SET NULL;
