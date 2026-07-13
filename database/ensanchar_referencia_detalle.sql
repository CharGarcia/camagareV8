-- =============================================================================
-- Ensancha asientos_contables_detalle.referencia_detalle de varchar(255) a varchar(500).
--
-- Motivo: la migración de contabilidad del sistema viejo trae detalles por línea
-- (detalle_item) de hasta ~367 caracteres, y la columna era varchar(255) → error
-- 22001 "value too long". Con 500 caben holgados.
--
-- SEGURIDAD: ampliar el límite de un varchar es un cambio de METADATO en PostgreSQL.
--   - NO reescribe la tabla (sin rewrite, sin scan de verificación).
--   - NO modifica ni un dato existente.
--   - El código que lee/escribe la columna sigue igual (solo dispone de más espacio).
--   - Solo toma un lock ACCESS EXCLUSIVE instantáneo para actualizar el catálogo.
--
-- Idempotente: si ya está en 500 (o más), no hace nada.
-- =============================================================================

DO $$
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_name = 'asientos_contables_detalle'
      AND column_name = 'referencia_detalle'
      AND character_maximum_length < 500
  ) THEN
    ALTER TABLE asientos_contables_detalle
      ALTER COLUMN referencia_detalle TYPE varchar(500);
    RAISE NOTICE 'referencia_detalle ampliada a varchar(500).';
  ELSE
    RAISE NOTICE 'referencia_detalle ya tiene 500+ caracteres; sin cambios.';
  END IF;
END $$;
