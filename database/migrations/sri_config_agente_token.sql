-- =====================================================================
-- Token del agente de escritorio SRI (descarga desde la PC del operador)
-- Cada empresa puede generar un token con el que su agente local se
-- autentica contra el servidor para traer credenciales y registrar claves.
-- =====================================================================

ALTER TABLE sri_config_descarga_auto
    ADD COLUMN IF NOT EXISTS agente_token VARCHAR(64);

CREATE INDEX IF NOT EXISTS idx_sri_config_agente_token
    ON sri_config_descarga_auto(agente_token)
    WHERE agente_token IS NOT NULL AND eliminado = FALSE;

COMMENT ON COLUMN sri_config_descarga_auto.agente_token IS
    'Token (64 hex) para autenticar el agente de escritorio SRI de esta empresa. Regenerable.';

-- El log ya tiene la columna origen; ahora también acepta 'agente'.
COMMENT ON COLUMN sri_descarga_auto_log.origen IS
    'cron = automático nocturno | manual = interfaz | asistido = visor remoto | agente = agente de escritorio (PC del operador)';
