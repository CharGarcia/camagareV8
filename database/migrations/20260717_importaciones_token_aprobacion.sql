-- Token para aprobar/rechazar una importación desde el enlace del correo
-- (ruta pública, sin login). Se genera al quedar pendiente_aprobacion y se
-- invalida al usarse. Mismo patrón que inventario_cargas.token_aprobacion.
ALTER TABLE importaciones_cabecera
    ADD COLUMN IF NOT EXISTS token_aprobacion VARCHAR(64);

CREATE INDEX IF NOT EXISTS idx_importaciones_token ON importaciones_cabecera (token_aprobacion);
