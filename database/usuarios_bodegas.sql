-- ============================================================
-- Tabla: usuarios_bodegas
-- Gestiona el acceso de usuarios específicos a bodegas y la bodega por defecto.
-- ============================================================

CREATE TABLE IF NOT EXISTS usuarios_bodegas (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    id_usuario      INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    id_bodega       INTEGER NOT NULL REFERENCES bodegas(id) ON DELETE CASCADE,
    es_default      BOOLEAN NOT NULL DEFAULT FALSE,
    
    -- Campos de Auditoría Estándar
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by      INTEGER,
    updated_by      INTEGER,
    eliminado       BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER,
    
    CONSTRAINT uk_usuario_bodega UNIQUE (id_empresa, id_usuario, id_bodega)
);

CREATE INDEX idx_usuarios_bodegas_usuario ON usuarios_bodegas(id_usuario);
CREATE INDEX idx_usuarios_bodegas_bodega  ON usuarios_bodegas(id_bodega);
CREATE INDEX idx_usuarios_bodegas_empresa ON usuarios_bodegas(id_empresa);

COMMENT ON TABLE usuarios_bodegas IS 'Relación de permisos de acceso de usuarios a bodegas y marcación de bodega predeterminada.';
