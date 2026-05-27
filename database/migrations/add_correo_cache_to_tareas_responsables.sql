-- ============================================================
-- Módulo: Tareas y Obligaciones
-- Agregar correo_cache a tareas_responsables para soporte flexible
-- ============================================================

ALTER TABLE tareas_responsables 
    ADD COLUMN IF NOT EXISTS correo_cache VARCHAR(200);

-- Hacer id_usuario opcional para soportar responsables externos
ALTER TABLE tareas_responsables ALTER COLUMN id_usuario DROP NOT NULL;

-- Corregir FK de tareas para que apunte a clientes_tareas (global) en lugar de clientes (operativa)
ALTER TABLE tareas DROP CONSTRAINT IF EXISTS tareas_id_cliente_fkey;
ALTER TABLE tareas ADD CONSTRAINT tareas_id_cliente_fkey 
    FOREIGN KEY (id_cliente) REFERENCES clientes_tareas(id) ON DELETE SET NULL;

-- Comentario para explicar el uso
COMMENT ON COLUMN tareas_responsables.correo_cache IS 'Caché del correo del responsable (sea usuario del sistema o externo) para evitar JOINs costosos y mantener historial.';
