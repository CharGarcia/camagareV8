-- ============================================================================
--  Car-Wash: bodega en la orden (fuente del inventario al facturar)
--  Ejecutar solo si la tabla carwash_ordenes ya existe sin esta columna.
-- ============================================================================
ALTER TABLE carwash_ordenes ADD COLUMN IF NOT EXISTS id_bodega INTEGER;
