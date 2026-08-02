-- Agrega el campo de "información adicional" por línea de detalle en Proformas,
-- igual que ya existe en ventas_detalle.info_adicional. La UI (columna "Adicional"
-- en la tabla de detalle) ya existía y enviaba el dato, pero no se persistía.
ALTER TABLE proformas_detalle ADD COLUMN IF NOT EXISTS info_adicional TEXT;
