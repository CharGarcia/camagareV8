-- Respuestas rápidas para el chat de WhatsApp
-- id_usuario NULL = pertenece a la empresa (compartida)
-- id_usuario <> NULL = pertenece al usuario (personal)

CREATE TABLE IF NOT EXISTS whatsapp_respuestas_rapidas (
    id          SERIAL PRIMARY KEY,
    id_empresa  INTEGER      NOT NULL,
    id_usuario  INTEGER      DEFAULT NULL,   -- NULL = empresa, valor = personal
    titulo      VARCHAR(100) NOT NULL,
    contenido   TEXT         NOT NULL,
    orden       INTEGER      DEFAULT 0,

    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER      NOT NULL,
    updated_by  INTEGER,
    eliminado   BOOLEAN      DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

CREATE INDEX IF NOT EXISTS idx_wrr_empresa
    ON whatsapp_respuestas_rapidas(id_empresa, eliminado);

CREATE INDEX IF NOT EXISTS idx_wrr_usuario
    ON whatsapp_respuestas_rapidas(id_empresa, id_usuario, eliminado);

COMMENT ON TABLE  whatsapp_respuestas_rapidas              IS 'Respuestas rápidas reutilizables para el chat de WhatsApp';
COMMENT ON COLUMN whatsapp_respuestas_rapidas.id_usuario   IS 'NULL = compartida por empresa; valor = respuesta personal del usuario';
