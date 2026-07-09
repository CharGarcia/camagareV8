-- Campos extra del detalle de cargas de inventario (idempotente):
--   nup        → series/numeración única (para productos individuales), separadas por salto de línea.
--   observacion→ observación por línea.
ALTER TABLE inventario_cargas_detalle
    ADD COLUMN IF NOT EXISTS nup TEXT;

ALTER TABLE inventario_cargas_detalle
    ADD COLUMN IF NOT EXISTS observacion VARCHAR(300);
