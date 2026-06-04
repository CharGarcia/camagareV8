-- Agrega el campo estado_correo a notas_credito_cabecera para registrar el
-- estado de envío del correo al cliente tras la autorización del SRI.
-- Valores: 'pendiente' (default), 'enviado', 'error'.
ALTER TABLE notas_credito_cabecera
    ADD COLUMN IF NOT EXISTS estado_correo VARCHAR(20) DEFAULT 'pendiente';
