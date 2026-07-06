-- ============================================================================
-- Videos de Ayuda — "Me gusta" (like) por video
--
-- Cada usuario puede dar/quitar like a un video (toggle). Se guarda el detalle
-- (un registro por usuario y video, único) y un contador rápido en videos_ayuda.
-- ============================================================================

-- Contador rápido para mostrar en el visor y la gestión.
ALTER TABLE videos_ayuda ADD COLUMN IF NOT EXISTS likes INTEGER DEFAULT 0;

-- Un like por usuario y video (la restricción única evita duplicados).
CREATE TABLE IF NOT EXISTS videos_ayuda_likes (
    id          SERIAL PRIMARY KEY,
    id_video    INTEGER NOT NULL REFERENCES videos_ayuda(id) ON DELETE CASCADE,
    id_usuario  INTEGER NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_videos_ayuda_likes UNIQUE (id_video, id_usuario)
);

CREATE INDEX IF NOT EXISTS idx_videos_ayuda_likes_video ON videos_ayuda_likes (id_video);
