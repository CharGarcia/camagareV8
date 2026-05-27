-- Tabla de solicitudes de firma electrónica (formulario público por token)
-- Ejecutar en la base de datos del sistema

CREATE TABLE IF NOT EXISTS firma_solicitudes (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    token               CHAR(64) NOT NULL UNIQUE,
    correo_destino      VARCHAR(255) NOT NULL,
    nombre_destino      VARCHAR(255),
    estado              VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                            CHECK (estado IN ('pendiente','completado','expirado','cancelado')),
    id_firma_generada   INTEGER REFERENCES firmas_electronicas(id) ON DELETE SET NULL,
    observaciones       TEXT,
    expira_at           TIMESTAMP NOT NULL,
    completado_at       TIMESTAMP,
    eliminado           BOOLEAN NOT NULL DEFAULT false,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

CREATE INDEX IF NOT EXISTS idx_firma_solicitudes_token    ON firma_solicitudes(token);
CREATE INDEX IF NOT EXISTS idx_firma_solicitudes_empresa  ON firma_solicitudes(id_empresa);
CREATE INDEX IF NOT EXISTS idx_firma_solicitudes_estado   ON firma_solicitudes(estado);

-- Permitir id_usuario NULL para registros creados desde el formulario público
ALTER TABLE firmas_electronicas ALTER COLUMN id_usuario DROP NOT NULL;
