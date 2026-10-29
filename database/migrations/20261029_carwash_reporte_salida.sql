-- ============================================================================
--  Car-Wash: Reporte de salida del vehículo (pestaña junto a Condiciones de ingreso).
--  Texto con formato: trabajos realizados, recomendaciones al cliente y lo que se
--  debe hacer en la próxima cita. Se imprime en el PDF "Reporte de salida".
--  Sin esta columna el módulo sigue funcionando (la pestaña avisa que falta el SQL).
-- ============================================================================
ALTER TABLE carwash_ordenes ADD COLUMN IF NOT EXISTS reporte_salida_html TEXT;
