-- Migración v2: ajustes adicionales en ventas_cabecera
-- Idempotente: usa IF NOT EXISTS / IF EXISTS y comprobaciones de tipo

-- ============================================================
-- 1. plazo: cambiar de INTEGER a VARCHAR(20)
--    (almacena la unidad de tiempo SRI: "dias", "meses", "años")
-- ============================================================
DO $$ BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'ventas_cabecera' AND column_name = 'plazo'
          AND data_type = 'integer'
    ) THEN
        ALTER TABLE ventas_cabecera ALTER COLUMN plazo TYPE VARCHAR(20) USING plazo::text;
        ALTER TABLE ventas_cabecera ALTER COLUMN plazo DROP DEFAULT;
        ALTER TABLE ventas_cabecera ALTER COLUMN plazo DROP NOT NULL;
    END IF;
END $$;

-- ============================================================
-- 2. tipo_ambiente  (1=Pruebas, 2=Producción — viene de empresas)
-- ============================================================
ALTER TABLE ventas_cabecera ADD COLUMN IF NOT EXISTS tipo_ambiente VARCHAR(5);

-- ============================================================
-- 3. tipo_emision   (1=Normal — viene de empresas)
-- ============================================================
ALTER TABLE ventas_cabecera ADD COLUMN IF NOT EXISTS tipo_emision VARCHAR(5);

-- ============================================================
-- 4. estado_correo  (pendiente | enviado)
-- ============================================================
ALTER TABLE ventas_cabecera ADD COLUMN IF NOT EXISTS estado_correo VARCHAR(20) NOT NULL DEFAULT 'pendiente';
