-- Script para agregar campo id_tipo_medida a la tabla productos
ALTER TABLE productos ADD COLUMN IF NOT EXISTS id_tipo_medida INTEGER;

-- Comentario
COMMENT ON COLUMN productos.id_tipo_medida IS 'FK a la tabla tipo_medida para facilitar la visualización en la UI';

-- Si ya existe la relación con unidades_medida pero se llama id_medida, 
-- nos aseguramos de que los datos sean consistentes con la nueva tabla.
-- Nota: id_medida en productos suele apuntar a unidades_medida.id
