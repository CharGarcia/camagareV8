-- ============================================================================
-- Videos de Ayuda — Registro de VISTAS por video
--
-- Agrega un contador rápido en videos_ayuda y una tabla de detalle (append-only)
-- que registra cada reproducción: qué usuario, desde qué empresa activa, cuándo,
-- IP y user agent. Sirve para mostrar "N vistas" y para reportes futuros.
--
-- La vista se cuenta UNA vez cuando el usuario inicia la reproducción (evento
-- "play" en el visor), no en cada request de streaming (el Range genera muchos).
-- ============================================================================

-- Contador rápido para mostrar en la gestión.
ALTER TABLE videos_ayuda ADD COLUMN IF NOT EXISTS vistas INTEGER DEFAULT 0;

-- Detalle de cada reproducción (log de uso; no lleva eliminación lógica).
CREATE TABLE IF NOT EXISTS videos_ayuda_vistas (
    id          SERIAL PRIMARY KEY,
    id_video    INTEGER NOT NULL REFERENCES videos_ayuda(id) ON DELETE CASCADE,
    id_usuario  INTEGER,                 -- usuario que vio el video
    id_empresa  INTEGER,                 -- empresa activa al momento de ver (informativo)
    ip          VARCHAR(45),
    user_agent  TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_videos_ayuda_vistas_video ON videos_ayuda_vistas (id_video);
CREATE INDEX IF NOT EXISTS idx_videos_ayuda_vistas_fecha ON videos_ayuda_vistas (created_at);
