-- ============================================================================
-- Módulo Alumnos: historial de las cargas de facturación por Excel.
--
-- Qué hace:
--   Crea alumnos_cargas: una fila por cada archivo cargado en Alumnos →
--      Cargar facturación (quién, cuándo, archivo y cuántos cambios).
--   Crea alumnos_cargas_detalle: por cada alumno que cambió en la carga, cómo
--      estaban sus servicios e información adicional ANTES y DESPUÉS. Con eso la
--      pestaña Historial puede revertir una carga completa mientras no se haya
--      facturado ni modificado después a esos alumnos.
-- No modifica ni borra datos existentes.
-- Idempotente: se puede ejecutar más de una vez.
-- Reversible:
--   DROP TABLE IF EXISTS alumnos_cargas_detalle;
--   DROP TABLE IF EXISTS alumnos_cargas;
-- Orden de despliegue: ejecutar ANTES de desplegar el código. Si el código llega
--   primero, la carga por Excel funciona igual pero no queda en el historial
--   (y esa carga no se podrá revertir desde la pantalla).
--
-- Ejecutar en pgAdmin (Query Tool, F5) sobre la base del sistema.
-- ============================================================================

BEGIN;

CREATE TABLE IF NOT EXISTS alumnos_cargas (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL,
    archivo         VARCHAR(255),
    alumnos         INTEGER NOT NULL DEFAULT 0,   -- alumnos que cambiaron
    agregados       INTEGER NOT NULL DEFAULT 0,
    cambiados       INTEGER NOT NULL DEFAULT 0,
    quitados        INTEGER NOT NULL DEFAULT 0,
    info            INTEGER NOT NULL DEFAULT 0,   -- alumnos con información adicional reemplazada
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by      INTEGER,
    updated_by      INTEGER,
    eliminado       BOOLEAN DEFAULT FALSE,        -- true = carga revertida
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER
);

CREATE INDEX IF NOT EXISTS idx_alumnos_cargas_empresa
    ON alumnos_cargas (id_empresa, eliminado, id DESC);

CREATE TABLE IF NOT EXISTS alumnos_cargas_detalle (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL,
    id_carga        INTEGER NOT NULL REFERENCES alumnos_cargas(id) ON DELETE CASCADE,
    id_alumno       INTEGER NOT NULL REFERENCES alumnos(id) ON DELETE CASCADE,
    antes           JSONB NOT NULL,               -- {servicios: [...], info_adicional: [...]|null, info_cambiada: bool}
    despues         JSONB NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by      INTEGER,
    updated_by      INTEGER,
    eliminado       BOOLEAN DEFAULT FALSE,
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER
);

CREATE INDEX IF NOT EXISTS idx_alumnos_cargas_detalle_carga
    ON alumnos_cargas_detalle (id_carga, eliminado);
CREATE INDEX IF NOT EXISTS idx_alumnos_cargas_detalle_alumno
    ON alumnos_cargas_detalle (id_empresa, id_alumno);

COMMIT;

-- Comprobación (debe salir una fila con los dos nombres de tabla):
-- SELECT to_regclass('public.alumnos_cargas') AS cargas, to_regclass('public.alumnos_cargas_detalle') AS detalle;
