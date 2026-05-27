-- Migración: agregar campos Tipo Persona y datos Jurídica a firmas_electronicas
ALTER TABLE firmas_electronicas
    ADD COLUMN IF NOT EXISTS tipo_persona   VARCHAR(20) NOT NULL DEFAULT 'natural',
    ADD COLUMN IF NOT EXISTS con_ruc        BOOLEAN     NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS ruc_empresa    VARCHAR(13),
    ADD COLUMN IF NOT EXISTS nombre_empresa VARCHAR(200),
    ADD COLUMN IF NOT EXISTS cargo          VARCHAR(100);

CREATE INDEX IF NOT EXISTS idx_firmas_tipo_persona ON firmas_electronicas (id_empresa, tipo_persona, eliminado);
