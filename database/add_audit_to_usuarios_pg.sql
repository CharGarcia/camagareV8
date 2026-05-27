-- PostgreSQL: Auditoría para la tabla usuarios
-- Agrega campos requeridos por generales.md para tablas operativas/globales

ALTER TABLE usuarios 
ADD COLUMN eliminado BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN updated_at TIMESTAMP DEFAULT NULL,
ADD COLUMN deleted_at TIMESTAMP DEFAULT NULL,
ADD COLUMN created_by INTEGER DEFAULT NULL,
ADD COLUMN updated_by INTEGER DEFAULT NULL,
ADD COLUMN deleted_by INTEGER DEFAULT NULL;

-- Índices para mejorar la búsqueda de auditoría y eliminados
CREATE INDEX idx_usuarios_eliminado ON usuarios (eliminado);

COMMENT ON COLUMN usuarios.eliminado IS 'Indicador de baja lógica';
COMMENT ON COLUMN usuarios.deleted_at IS 'Fecha y hora de eliminación lógica';
COMMENT ON COLUMN usuarios.deleted_by IS 'ID del usuario que realizó la eliminación';
