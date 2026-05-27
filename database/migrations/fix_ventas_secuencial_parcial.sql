-- Migración: reemplazar restricción única de secuencial por índice único parcial
-- Solo aplica la unicidad cuando eliminado = false (las facturas eliminadas pueden repetir secuencial)

-- 1. Eliminar la restricción unique anterior si existe
DO $$ BEGIN
    IF EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'uk_ventas_secuencial'
          AND conrelid = 'ventas_cabecera'::regclass
    ) THEN
        ALTER TABLE ventas_cabecera DROP CONSTRAINT uk_ventas_secuencial;
    END IF;
END $$;

-- 2. Eliminar el índice si existía creado como índice (no constraint)
DROP INDEX IF EXISTS uk_ventas_secuencial;

-- 3. Crear índice único parcial: solo para facturas activas (eliminado = false)
CREATE UNIQUE INDEX IF NOT EXISTS uix_ventas_secuencial_activo
    ON ventas_cabecera (id_empresa, id_establecimiento, id_punto_emision, secuencial)
    WHERE eliminado = false;
