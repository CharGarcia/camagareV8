-- Tablas de Unidades de Medida
-- Autor: Antigravity

-- 1. Tabla tipo_medida
CREATE TABLE IF NOT EXISTS tipo_medida (
    id SERIAL PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL,
    id_empresa INTEGER NOT NULL,
    id_usuario INTEGER NOT NULL,
    nombre VARCHAR(100) NOT NULL,
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

-- 2. Tabla unidades_medida
CREATE TABLE IF NOT EXISTS unidades_medida (
    id SERIAL PRIMARY KEY,
    id_tipo INTEGER NOT NULL REFERENCES tipo_medida(id),
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    abreviatura VARCHAR(20) NOT NULL,
    factor_base NUMERIC(15, 6) DEFAULT 1,
    status BOOLEAN DEFAULT TRUE,
    -- Campos de control y multitenancy
    id_empresa INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER NOT NULL,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

-- Indices para optimización de consultas
CREATE INDEX IF NOT EXISTS idx_tipo_medida_empresa ON tipo_medida(id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_unidades_medida_empresa ON unidades_medida(id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_unidades_medida_tipo ON unidades_medida(id_tipo);

-- Comentarios de tablas
COMMENT ON TABLE tipo_medida IS 'Clasificación de las unidades de medida (Peso, Longitud, etc.)';
COMMENT ON TABLE unidades_medida IS 'Unidades de medida específicas relacionadas a un tipo (Kg, Llb, Mt, etc.)';
