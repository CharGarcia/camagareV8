-- ============================================================================
-- Módulo: Videos de Ayuda (ayuda del sistema)
-- Ruta MVC: /videos-ayuda   (controlador global, NO operativo)
--
-- Catálogo GLOBAL: los videos de ayuda son únicos para toda la aplicación
-- (misma ayuda para todas las empresas), por eso la tabla NO lleva id_empresa.
--
-- Reglas del sistema aplicadas:
--   - Global: sin id_empresa (catálogo/configuración global).
--   - Eliminación lógica: eliminado / deleted_at / deleted_by.
--   - Auditoría: created_at/by, updated_at/by (registro en log_sistema vía Service).
--
-- Acceso:
--   - Ver videos: cualquier usuario autenticado (ícono de ayuda en el navbar).
--   - Cargar / editar / eliminar: SOLO superadministrador (nivel 3).
--
-- Los archivos de video se guardan en:  storage/videos_ayuda/
-- (solo el nombre del archivo se persiste en la columna "archivo").
--
-- NOTA DE PRODUCCIÓN (php.ini): para subir videos grandes puede ser necesario
-- aumentar upload_max_filesize, post_max_size y max_execution_time en el servidor.
-- ============================================================================

CREATE TABLE IF NOT EXISTS videos_ayuda (
    id              SERIAL PRIMARY KEY,
    titulo          VARCHAR(200) NOT NULL,
    descripcion     TEXT,
    categoria       VARCHAR(100),                 -- para agrupar en la galería (opcional)
    archivo         VARCHAR(255) NOT NULL,        -- nombre único en disco (storage/videos_ayuda/)
    nombre_original VARCHAR(255),                 -- nombre original del archivo subido
    mime_type       VARCHAR(100),
    tamano_bytes    BIGINT DEFAULT 0,
    orden           INTEGER DEFAULT 0,            -- orden de aparición en la galería
    estado          VARCHAR(20) DEFAULT 'activo', -- activo / inactivo (no reemplaza a "eliminado")
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by      INTEGER,
    updated_by      INTEGER,
    eliminado       BOOLEAN DEFAULT FALSE,
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER
);

-- Índice para el listado del visor (videos activos, no eliminados).
CREATE INDEX IF NOT EXISTS idx_videos_ayuda_visibles
    ON videos_ayuda (orden, categoria, titulo)
    WHERE eliminado = FALSE AND estado = 'activo';
