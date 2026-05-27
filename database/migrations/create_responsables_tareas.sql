-- ============================================================
-- Tabla propia de responsables para tareas
-- Soporta tanto usuarios del sistema como externos
-- ============================================================

CREATE TABLE IF NOT EXISTS responsables_tareas (
    id          SERIAL PRIMARY KEY,
    cedula      VARCHAR(20),
    nombre      VARCHAR(200) NOT NULL,
    correo      VARCHAR(200) NOT NULL,
    telefono    VARCHAR(30),
    eliminado   BOOLEAN     NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMPTZ,
    created_by  INT REFERENCES usuarios(id) ON DELETE SET NULL,
    updated_by  INT REFERENCES usuarios(id) ON DELETE SET NULL,
    deleted_at  TIMESTAMPTZ,
    deleted_by  INT REFERENCES usuarios(id) ON DELETE SET NULL
);

COMMENT ON TABLE responsables_tareas IS 'Responsables propios del módulo de tareas (pueden no ser usuarios del sistema)';

CREATE INDEX IF NOT EXISTS idx_responsables_tareas_nombre    ON responsables_tareas(nombre);
CREATE INDEX IF NOT EXISTS idx_responsables_tareas_cedula    ON responsables_tareas(cedula);
CREATE INDEX IF NOT EXISTS idx_responsables_tareas_eliminado ON responsables_tareas(eliminado);

-- ============================================================
-- Ampliar tareas_responsables para soportar ambos tipos
-- id_usuario  -> usuario del sistema (usuarios)
-- id_resp_tarea -> responsable propio (responsables_tareas)
-- Solo UNO de los dos debe ser NOT NULL  
-- ============================================================

ALTER TABLE tareas_responsables
    ADD COLUMN IF NOT EXISTS id_resp_tarea INT REFERENCES responsables_tareas(id) ON DELETE CASCADE;

-- El constraint anterior era UNIQUE(id_tarea, id_usuario) solo para usuarios del sistema.
-- Ahora necesitamos también unicidad para responsables propios.
-- Eliminamos el constraint viejo si existe y creamos uno más flexible.
DO $$ BEGIN
    IF EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'uq_tarea_responsable'
    ) THEN
        ALTER TABLE tareas_responsables DROP CONSTRAINT uq_tarea_responsable;
    END IF;
END $$;

-- Nuevo constraint parcial para cada tipo
CREATE UNIQUE INDEX IF NOT EXISTS uq_tarea_usuario
    ON tareas_responsables(id_tarea, id_usuario)
    WHERE id_usuario IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_tarea_resp_tarea
    ON tareas_responsables(id_tarea, id_resp_tarea)
    WHERE id_resp_tarea IS NOT NULL;

-- Agregar columna nombre_cache para mostrar en listado sin JOIN extra
ALTER TABLE tareas_responsables
    ADD COLUMN IF NOT EXISTS nombre_cache VARCHAR(200);
