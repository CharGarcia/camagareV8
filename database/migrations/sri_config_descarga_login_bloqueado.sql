-- =====================================================================
-- Migración: agregar columnas de bloqueo de login a sri_config_descarga_auto
-- Ejecutar UNA SOLA VEZ en base de datos existente.
-- =====================================================================

ALTER TABLE sri_config_descarga_auto
    ADD COLUMN IF NOT EXISTS login_bloqueado        BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS login_bloqueado_motivo TEXT;

COMMENT ON COLUMN sri_config_descarga_auto.login_bloqueado
    IS 'TRUE cuando el scraper reportó credenciales incorrectas. Se desbloquea al guardar una clave nueva.';
COMMENT ON COLUMN sri_config_descarga_auto.login_bloqueado_motivo
    IS 'Mensaje del error de credenciales que causó el bloqueo.';
