-- Agrega campo max_usuarios a la tabla empresas
-- Default: 3 usuarios por empresa
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS max_usuarios INTEGER NOT NULL DEFAULT 3;

-- Comentario descriptivo
COMMENT ON COLUMN empresas.max_usuarios IS 'Número máximo de usuarios permitidos para esta empresa. Default 3.';
