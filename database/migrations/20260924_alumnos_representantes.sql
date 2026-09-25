-- ============================================================================
-- Módulo Alumnos: representantes y personas autorizadas a retirar.
--
-- Qué hace: crea la tabla alumnos_representantes (varias personas por alumno,
--   con datos libres: nombres, identificación, teléfono, relación y la marca
--   "puede retirar" del centro educativo). NO toca datos existentes: el cliente
--   que factura sigue en alumnos.id_cliente (pestaña Facturación).
-- Idempotente: se puede ejecutar más de una vez.
-- Reversible: DROP TABLE IF EXISTS alumnos_representantes;  (borra lo cargado)
-- Orden de despliegue: ejecutar este SQL ANTES de desplegar el código. Si el
--   código llega primero, la pestaña Representantes simplemente no guarda nada
--   (el resto del alumno funciona igual).
--
-- Ejecutar en pgAdmin (Query Tool, F5) sobre la base del sistema.
-- ============================================================================

BEGIN;

CREATE TABLE IF NOT EXISTS alumnos_representantes (
    id                  SERIAL PRIMARY KEY,
    id_alumno           INTEGER NOT NULL REFERENCES alumnos(id) ON DELETE CASCADE,
    id_empresa          INTEGER NOT NULL,
    nombres             VARCHAR(200) NOT NULL,      -- nombres y apellidos de la persona
    identificacion      VARCHAR(20),
    telefono            VARCHAR(30),
    relacion            VARCHAR(30),                -- padre|madre|tutor|abuelo|hermano|tio|otro
    puede_retirar       BOOLEAN NOT NULL DEFAULT FALSE, -- autorizada a retirar al alumno
    observacion         VARCHAR(200),
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

CREATE INDEX IF NOT EXISTS idx_alumnos_representantes_alumno
    ON alumnos_representantes (id_alumno, eliminado);

COMMIT;

-- Comprobación (debe salir 1 fila):
-- SELECT to_regclass('public.alumnos_representantes');
