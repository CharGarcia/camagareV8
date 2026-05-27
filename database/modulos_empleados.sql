-- database/modulos_empleados.sql
-- Creación de tabla operativa de empleados con multitenancy y auditoría completa.

CREATE TABLE IF NOT EXISTS empleados (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL REFERENCES empresas(id),
    id_usuario_sistema INTEGER NULL REFERENCES usuarios(id), -- Opcional: relación con el usuario de sistema
    
    tipo_id VARCHAR(20) NOT NULL, -- cedula, pasaporte
    identificacion VARCHAR(25) NOT NULL,
    nombres_apellidos VARCHAR(255) NOT NULL,
    direccion TEXT,
    email VARCHAR(100),
    telefono VARCHAR(50),
    contacto_emergencia TEXT,
    fecha_nacimiento DATE,
    sexo CHAR(1), -- M (Masculino), F (Femenino), O (Otro)
    estado VARCHAR(20) DEFAULT 'activo', -- activo, inactivo
    
    id_banco_ecuador INTEGER NULL, -- Referencia a bancos_ecuador(id_bancos)
    tipo_cuenta VARCHAR(20), -- ahorros, corriente, virtual
    numero_cuenta VARCHAR(50),

    -- Campos de control de auditoría requeridos por la arquitectura
    eliminado BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    created_by INTEGER NOT NULL,
    updated_by INTEGER,
    deleted_by INTEGER,

    CONSTRAINT uk_empleado_identificacion_empresa UNIQUE (identificacion, id_empresa)
);

-- Índices para búsqueda y rendimiento en multitenancy
CREATE INDEX IF NOT EXISTS idx_empleados_empresa ON empleados(id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_empleados_identificacion ON empleados(identificacion);
CREATE INDEX IF NOT EXISTS idx_empleados_status ON empleados(estado);

-- Comentarios explicativos
COMMENT ON TABLE empleados IS 'Tabla operativa de empleados por empresa';
COMMENT ON COLUMN empleados.id_usuario_sistema IS 'Vínculo opcional con la cuenta de usuario del sistema';
COMMENT ON COLUMN empleados.sexo IS 'M=Masculino, F=Femenino, O=Otro';
