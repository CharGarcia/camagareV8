-- ============================================================================
-- Opciones de Ingresos y Egresos: el Nombre / Concepto admite hasta 50 caracteres
-- (antes la pantalla limitaba a 20).
--
-- Solo AMPLÍA la columna si hoy es más corta que 50; si ya es de 50 o más (en la base
-- local es varchar(200)) no hace nada. Nunca la achica. Idempotente. Listo para pgAdmin.
-- ============================================================================

-- 1. (Opcional) Ver el largo actual:
-- SELECT character_maximum_length FROM information_schema.columns
--  WHERE table_name = 'empresa_opciones_ingreso_egreso' AND column_name = 'nombre';

DO $$
DECLARE
    v_largo integer;
BEGIN
    SELECT character_maximum_length INTO v_largo
      FROM information_schema.columns
     WHERE table_schema = 'public'
       AND table_name = 'empresa_opciones_ingreso_egreso'
       AND column_name = 'nombre';

    IF v_largo IS NOT NULL AND v_largo < 50 THEN
        ALTER TABLE empresa_opciones_ingreso_egreso ALTER COLUMN nombre TYPE varchar(50);
        RAISE NOTICE 'Columna nombre ampliada de % a 50 caracteres.', v_largo;
    ELSE
        RAISE NOTICE 'Sin cambios: la columna nombre ya admite % caracteres (o no tiene límite).', COALESCE(v_largo::text, 'sin límite');
    END IF;
END $$;
