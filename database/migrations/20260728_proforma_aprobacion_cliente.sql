-- Aprobación pública de proformas por el cliente (desde el correo, con token).
-- Añade el token del enlace y la metadata de la aceptación (comentario + IP + fecha).

ALTER TABLE proformas_cabecera
    ADD COLUMN IF NOT EXISTS aprobacion_token             VARCHAR(64),
    ADD COLUMN IF NOT EXISTS aprobacion_cliente_comentario TEXT,
    ADD COLUMN IF NOT EXISTS aprobacion_cliente_ip        VARCHAR(45),
    ADD COLUMN IF NOT EXISTS aprobacion_cliente_fecha     TIMESTAMP;

-- El token debe ser único (es la autorización del enlace público).
CREATE UNIQUE INDEX IF NOT EXISTS uq_proformas_aprob_token
    ON proformas_cabecera (aprobacion_token)
    WHERE aprobacion_token IS NOT NULL;
