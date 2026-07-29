-- Estado de envío por correo de la proforma (columna para el listado general).
ALTER TABLE proformas_cabecera
    ADD COLUMN IF NOT EXISTS estado_correo VARCHAR(20) NOT NULL DEFAULT 'pendiente';
