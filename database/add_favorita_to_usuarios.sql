-- Add id_empresa_favorita to usuarios table
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS id_empresa_favorita INTEGER DEFAULT NULL;

-- If you want to link it with a foreign key (optional, might fail if data is inconsistent)
-- ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_empresa_favorita FOREIGN KEY (id_empresa_favorita) REFERENCES empresas (id) ON DELETE SET NULL;
