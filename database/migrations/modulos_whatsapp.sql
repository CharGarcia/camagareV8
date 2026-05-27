-- Tablas para el módulo de WhatsApp
-- Autor: Antigravity

-- 1. Tabla de configuración por empresa
CREATE TABLE IF NOT EXISTS empresa_whatsapp_config (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    access_token TEXT,
    phone_number_id VARCHAR(100),
    waba_id VARCHAR(100),
    webhook_verify_token VARCHAR(255),
    status BOOLEAN DEFAULT TRUE,
    
    -- Campos de control de auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER NOT NULL,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

-- Indices
CREATE INDEX IF NOT EXISTS idx_empresa_whatsapp_config_empresa ON empresa_whatsapp_config(id_empresa, eliminado);

COMMENT ON TABLE empresa_whatsapp_config IS 'Configuraciones de la API de Meta WhatsApp Business por empresa';

-- 2. Tabla para almacenar plantillas locales sincronizadas desde Meta
CREATE TABLE IF NOT EXISTS whatsapp_plantillas (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    meta_id VARCHAR(100),
    nombre VARCHAR(150) NOT NULL,
    categoria VARCHAR(50),
    idioma VARCHAR(20),
    estado_meta VARCHAR(50),
    componentes JSONB, -- Estructura de la plantilla (header, body, buttons, etc)
    status BOOLEAN DEFAULT TRUE,
    
    -- Campos de control de auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER NOT NULL,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

-- Indices
CREATE INDEX IF NOT EXISTS idx_whatsapp_plantillas_empresa ON whatsapp_plantillas(id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_whatsapp_plantillas_nombre ON whatsapp_plantillas(nombre);

COMMENT ON TABLE whatsapp_plantillas IS 'Plantillas de mensajes sincronizadas desde Meta para cada empresa';
