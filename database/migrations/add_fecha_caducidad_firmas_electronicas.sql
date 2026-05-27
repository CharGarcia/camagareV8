-- Agrega fecha de caducidad a firmas_electronicas
ALTER TABLE firmas_electronicas
    ADD COLUMN IF NOT EXISTS fecha_caducidad DATE;

CREATE INDEX IF NOT EXISTS idx_firmas_caducidad
    ON firmas_electronicas (id_empresa, fecha_caducidad, eliminado);
