-- Token para aprobar/rechazar una carga de inventario desde el enlace del correo
-- (ruta pública, sin login). Se genera al quedar pendiente y se invalida al usarse.
ALTER TABLE inventario_cargas
    ADD COLUMN IF NOT EXISTS token_aprobacion VARCHAR(64);

CREATE INDEX IF NOT EXISTS idx_inv_cargas_token ON inventario_cargas (token_aprobacion);
