-- Tab: Emisor Electrónico (Expandir tabla empresas)
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS resolucion_contribuyente VARCHAR(50);
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS id_tipo_regimen INTEGER;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS tipo_ambiente INTEGER DEFAULT 1; -- 1: Pruebas, 2: Produccion
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS agente_retencion VARCHAR(5) DEFAULT 'NO';
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS tipo_emision VARCHAR(20) DEFAULT 'Normal';

-- Tab: Configuración correo emisor
CREATE TABLE IF NOT EXISTS empresa_correo (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    ssl_habilitado BOOLEAN DEFAULT TRUE,
    asunto_correo VARCHAR(255),
    host VARCHAR(255),
    puerto INTEGER,
    correo_emisor VARCHAR(255),
    password_correo_emisor TEXT,
    -- Audit fields
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,
    CONSTRAINT fk_id_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id)
);

-- Tab: Firma Electrónica
CREATE TABLE IF NOT EXISTS empresa_firma (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    archivo_nombre VARCHAR(255),
    archivo_ruta TEXT,
    password_firma TEXT,
    fecha_emision DATE,
    fecha_expiracion DATE,
    es_activo BOOLEAN DEFAULT FALSE,
    -- Audit fields
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,
    CONSTRAINT fk_id_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id)
);

-- Tab: Puntos de Emisión
CREATE TABLE IF NOT EXISTS empresa_punto_emision (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    nombre VARCHAR(255),
    codigo_punto VARCHAR(3),
    direccion_punto TEXT,
    logo_ruta TEXT,
    estado VARCHAR(20) DEFAULT 'activo',
    -- Audit fields
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,
    CONSTRAINT fk_id_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id)
);

-- Tab: Secuenciales
CREATE TABLE IF NOT EXISTS empresa_secuencial (
    id SERIAL PRIMARY KEY,
    id_punto_emision INTEGER NOT NULL,
    tipo_documento VARCHAR(100),
    numero_secuencial BIGINT DEFAULT 1,
    -- Audit fields
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,
    CONSTRAINT fk_id_punto_emision FOREIGN KEY (id_punto_emision) REFERENCES empresa_punto_emision(id)
);

-- Tab: Establecimientos
CREATE TABLE IF NOT EXISTS empresa_establecimiento (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    codigo VARCHAR(3) NOT NULL,
    direccion TEXT,
    tipo VARCHAR(50) DEFAULT 'Matriz',
    logo_ruta TEXT,
    estado VARCHAR(20) DEFAULT 'activo',
    -- Audit fields
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,
    CONSTRAINT fk_id_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id)
);

-- Asegurar que id_establecimiento exista en puntos de emisión
DO $$ 
BEGIN 
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='empresa_punto_emision' AND column_name='id_establecimiento') THEN
        ALTER TABLE empresa_punto_emision ADD COLUMN id_establecimiento INTEGER REFERENCES empresa_establecimiento(id);
    END IF;
END $$;

-- Insertar el módulo si no existe
INSERT INTO submodulos_menu (id_modulo, nombre_submodulo, ruta, status)
SELECT m.id, 'Configuración Empresa', 'modulos/empresa', 1
FROM modulos_menu m
WHERE (m.nombre_modulo = 'Configuración' OR m.nombre_modulo = 'CONFIGURACIÓN')
AND NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/empresa')
LIMIT 1;
