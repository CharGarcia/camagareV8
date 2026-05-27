-- Script para crear la tabla de auditoría log_sistema
CREATE TABLE IF NOT EXISTS log_sistema (
    id SERIAL PRIMARY KEY,
    id_usuario INT,
    id_empresa INT,
    accion VARCHAR(100) NOT NULL, -- 'crear', 'actualizar', 'eliminar', 'login', etc.
    tabla_afectada VARCHAR(100),
    id_registro INT,
    datos_anteriores JSONB,
    datos_nuevos JSONB,
    ip_usuario VARCHAR(50),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Agregar campos de auditoría a la tabla clientes si no existen
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS created_by INT;
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS updated_by INT;
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS deleted_by INT;
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;

-- Asegurar que 'id_usuario' en clientes se use como 'created_by' si es necesario
-- Pero en la nueva arquitectura usaremos explícitamente created_by.
