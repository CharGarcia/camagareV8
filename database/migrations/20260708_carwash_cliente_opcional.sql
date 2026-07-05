-- ============================================================================
--  Car-Wash: el cliente es OPCIONAL al registrar la orden (obligatorio al facturar).
--  Ejecutar solo si la tabla carwash_ordenes tiene id_cliente NOT NULL.
-- ============================================================================
ALTER TABLE carwash_ordenes ALTER COLUMN id_cliente DROP NOT NULL;
