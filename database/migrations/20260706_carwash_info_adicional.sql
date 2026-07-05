-- ============================================================================
--  Car-Wash: campo de Información Adicional en la orden (estilo factura)
--  Ejecutar solo si la tabla carwash_ordenes ya existe sin esta columna.
-- ============================================================================
ALTER TABLE carwash_ordenes ADD COLUMN IF NOT EXISTS info_adicional JSONB;
