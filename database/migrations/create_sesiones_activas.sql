-- Migración: Control de sesiones activas por usuario
-- Permite detectar y gestionar sesiones concurrentes

CREATE TABLE IF NOT EXISTS sesiones_activas (
    id              SERIAL PRIMARY KEY,
    id_usuario      INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    session_token   VARCHAR(128) NOT NULL UNIQUE,
    ip              VARCHAR(45),
    user_agent      TEXT,
    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    ultima_actividad TIMESTAMP NOT NULL DEFAULT NOW(),
    activa          BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE INDEX IF NOT EXISTS idx_sesiones_activas_usuario ON sesiones_activas(id_usuario, activa);
CREATE INDEX IF NOT EXISTS idx_sesiones_activas_token   ON sesiones_activas(session_token);
