-- Migración: ajustes de columnas en tablas de ventas
-- Idempotente: usa IF NOT EXISTS / IF EXISTS

-- ============================================================
-- 1. ventas_cabecera — columnas nuevas
-- ============================================================
ALTER TABLE ventas_cabecera ADD COLUMN IF NOT EXISTS id_vendedor  INTEGER;
ALTER TABLE ventas_cabecera ADD COLUMN IF NOT EXISTS dias_credito INTEGER NOT NULL DEFAULT 0;
ALTER TABLE ventas_cabecera ADD COLUMN IF NOT EXISTS plazo        INTEGER NOT NULL DEFAULT 0;

-- ============================================================
-- 2. ventas_cabecera — eliminar factura_numero
--    La columna tiene UNIQUE NOT NULL; DROP la elimina con su constraint.
-- ============================================================
ALTER TABLE ventas_cabecera DROP COLUMN IF EXISTS factura_numero;

-- ============================================================
-- 3. ventas_cabecera — unicidad por empresa + establecimiento + punto + secuencial
-- ============================================================
DO $$ BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'uk_ventas_secuencial'
          AND conrelid = 'ventas_cabecera'::regclass
    ) THEN
        ALTER TABLE ventas_cabecera
        ADD CONSTRAINT uk_ventas_secuencial
        UNIQUE (id_empresa, id_establecimiento, id_punto_emision, secuencial);
    END IF;
END $$;

-- ============================================================
-- 4. ventas_detalle — unidad de medida
-- ============================================================
ALTER TABLE ventas_detalle ADD COLUMN IF NOT EXISTS id_unidad_medida INTEGER;
