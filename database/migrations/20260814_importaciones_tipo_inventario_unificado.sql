-- =============================================================================
-- Importaciones: unifica es_activo_fijo + es_materia_prima en un solo campo
-- ----------------------------------------------------------------------------
-- Las dos migraciones anteriores (20260814_importaciones_activo_fijo_casilleros_iva.sql
-- y 20260814_importaciones_materia_prima_producto_terminado.sql) agregaron dos
-- columnas booleanas INDEPENDIENTES por línea de producto. Eso permitía marcar
-- una misma línea como "Activo Fijo" Y "Materia Prima" a la vez, algo sin
-- sentido de negocio (un activo fijo no es materia prima ni producto terminado
-- para la venta).
--
-- Esta migración reemplaza ambas por un solo campo mutuamente excluyente:
--   tipo_inventario: 'producto_terminado' (default) | 'materia_prima' | 'activo_fijo'
--
-- Si las columnas viejas ya existían con datos (empresas que ya probaron el
-- checkbox), se hace backfill antes de eliminarlas. Si nunca se llegó a correr
-- esa migración anterior, los IF EXISTS simplemente no hacen nada.
--
-- Idempotente.
-- =============================================================================

BEGIN;

ALTER TABLE importaciones_detalle
    ADD COLUMN IF NOT EXISTS tipo_inventario VARCHAR(20) NOT NULL DEFAULT 'producto_terminado';

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'importaciones_detalle' AND column_name = 'es_materia_prima') THEN
        UPDATE importaciones_detalle SET tipo_inventario = 'materia_prima'
         WHERE es_materia_prima = true AND tipo_inventario = 'producto_terminado';
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'importaciones_detalle' AND column_name = 'es_activo_fijo') THEN
        -- Activo Fijo tiene prioridad si por algún motivo quedaron ambas marcadas.
        UPDATE importaciones_detalle SET tipo_inventario = 'activo_fijo'
         WHERE es_activo_fijo = true;
    END IF;
END $$;

ALTER TABLE importaciones_detalle DROP COLUMN IF EXISTS es_activo_fijo;
ALTER TABLE importaciones_detalle DROP COLUMN IF EXISTS es_materia_prima;

ALTER TABLE importaciones_detalle DROP CONSTRAINT IF EXISTS importaciones_detalle_tipo_inventario_check;
ALTER TABLE importaciones_detalle ADD CONSTRAINT importaciones_detalle_tipo_inventario_check
    CHECK (tipo_inventario IN ('producto_terminado', 'materia_prima', 'activo_fijo'));

COMMIT;
