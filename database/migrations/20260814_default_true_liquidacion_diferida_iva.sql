-- =============================================================================
-- usa_liquidacion_diferida_iva: cambia el DEFAULT de la columna a true.
-- ----------------------------------------------------------------------------
-- Solo afecta empresas NUEVAS (el INSERT de creación de empresa no especifica
-- esta columna, así que toma el DEFAULT). Las empresas ya existentes conservan
-- su valor actual sin cambios: este ALTER no hace UPDATE de filas.
--
-- Idempotente, aditivo, no destructivo.
-- =============================================================================

BEGIN;

ALTER TABLE empresas
    ALTER COLUMN usa_liquidacion_diferida_iva SET DEFAULT true;

COMMIT;
