-- ============================================================
-- Módulo: Avisos de mensajes WhatsApp no leídos
-- ============================================================

-- Configuración de avisos por empresa
CREATE TABLE IF NOT EXISTS whatsapp_aviso_config (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    activo              BOOLEAN NOT NULL DEFAULT TRUE,
    umbral_minutos      INTEGER NOT NULL DEFAULT 30,   -- notificar si hay mensajes sin leer hace más de X min
    cooldown_minutos    INTEGER NOT NULL DEFAULT 60,   -- esperar al menos X min entre avisos repetidos
    plantilla_nombre    VARCHAR(150) DEFAULT NULL,     -- nombre de plantilla Meta (opcional)
    plantilla_idioma    VARCHAR(20)  NOT NULL DEFAULT 'es',

    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,

    CONSTRAINT uq_whatsapp_aviso_config_empresa UNIQUE (id_empresa)
);

-- Números de teléfono que recibirán los avisos (múltiples por empresa)
CREATE TABLE IF NOT EXISTS whatsapp_aviso_numeros (
    id          SERIAL PRIMARY KEY,
    id_empresa  INTEGER NOT NULL,
    telefono    VARCHAR(30) NOT NULL,
    nombre      VARCHAR(100) DEFAULT NULL,
    activo      BOOLEAN NOT NULL DEFAULT TRUE,

    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER NOT NULL,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

-- Historial de avisos enviados (para controlar el cooldown y auditoría)
CREATE TABLE IF NOT EXISTS whatsapp_aviso_log (
    id                   SERIAL PRIMARY KEY,
    id_empresa           INTEGER NOT NULL,
    chats_pendientes     INTEGER NOT NULL,
    numeros_notificados  INTEGER NOT NULL DEFAULT 0,
    detalle              JSONB,
    fecha_envio          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Índices
CREATE INDEX IF NOT EXISTS idx_wa_aviso_config_empresa  ON whatsapp_aviso_config(id_empresa);
CREATE INDEX IF NOT EXISTS idx_wa_aviso_numeros_empresa ON whatsapp_aviso_numeros(id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_wa_aviso_log_empresa     ON whatsapp_aviso_log(id_empresa, fecha_envio DESC);

COMMENT ON TABLE whatsapp_aviso_config   IS 'Configuración de avisos automáticos de mensajes no leídos por empresa';
COMMENT ON TABLE whatsapp_aviso_numeros  IS 'Números de teléfono que reciben los avisos de mensajes no leídos';
COMMENT ON TABLE whatsapp_aviso_log      IS 'Historial de avisos enviados (para cooldown y auditoría)';
