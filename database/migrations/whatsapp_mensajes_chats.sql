-- Tablas para el mdulo de WhatsApp - Opcin 2 y 3
-- Autor: Antigravity

-- 1. Tabla para agrupar los mensajes (Sesiones o Chats de WhatsApp)
CREATE TABLE IF NOT EXISTS whatsapp_chats (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    telefono_cliente VARCHAR(50) NOT NULL,
    nombre_cliente VARCHAR(150),
    ultimo_mensaje TEXT,
    mensajes_sin_leer INTEGER DEFAULT 0,
    estado VARCHAR(50) DEFAULT 'open', -- open, closed, archived
    
    -- Campos de control de auditora
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER NOT NULL,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT uq_whatsapp_chats_empresa_telefono UNIQUE (id_empresa, telefono_cliente)
);

CREATE INDEX IF NOT EXISTS idx_whatsapp_chats_empresa ON whatsapp_chats(id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_whatsapp_chats_updated ON whatsapp_chats(updated_at DESC);

COMMENT ON TABLE whatsapp_chats IS 'Almacena las sesiones activas/histricas de chat por cliente';

-- 2. Tabla para almacenar todos los mensajes entrantes y salientes
CREATE TABLE IF NOT EXISTS whatsapp_mensajes (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_chat INTEGER NOT NULL REFERENCES whatsapp_chats(id),
    direccion VARCHAR(10) NOT NULL, -- 'IN' para entrantes, 'OUT' para salientes
    telefono_cliente VARCHAR(50) NOT NULL,
    tipo_mensaje VARCHAR(50) NOT NULL, -- text, image, document, audio, template
    contenido JSONB NOT NULL, -- El contenido raw o parseado en JSON
    meta_message_id VARCHAR(150), -- ID del mensaje otorgado por Meta
    estado_meta VARCHAR(50), -- sent, delivered, read, failed (principalmente para 'OUT')
    error_message TEXT,
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Campos de control de auditora
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER NOT NULL,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

CREATE INDEX IF NOT EXISTS idx_whatsapp_mensajes_chat ON whatsapp_mensajes(id_chat, eliminado);
CREATE INDEX IF NOT EXISTS idx_whatsapp_mensajes_meta_id ON whatsapp_mensajes(meta_message_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_mensajes_fecha ON whatsapp_mensajes(fecha_hora DESC);

COMMENT ON TABLE whatsapp_mensajes IS 'Historial detallado de todos los mensajes enviados y recibidos';
