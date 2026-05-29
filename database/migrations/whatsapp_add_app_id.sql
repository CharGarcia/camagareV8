-- Migración: Agregar campo app_id a empresa_whatsapp_config
-- Fecha: 2026-05-28

ALTER TABLE empresa_whatsapp_config
    ADD COLUMN IF NOT EXISTS app_id VARCHAR(100);

COMMENT ON COLUMN empresa_whatsapp_config.app_id IS 'ID de la aplicación de Meta (usado para Resumable Upload API y webhooks)';
