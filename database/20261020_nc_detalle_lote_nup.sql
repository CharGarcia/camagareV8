-- =============================================================================
-- Notas de crédito: lote y NUP elegidos por el usuario en cada ítem
-- =============================================================================
-- QUÉ HACE
--   Agrega notas_credito_detalle.numero_lote y notas_credito_detalle.nup. Cuando el
--   establecimiento trabaja con lotes (y/o NUP), la nota muestra las columnas Lote y
--   NUP con los lotes que salieron en la factura modificada, y el usuario elige a cuál
--   regresa la mercadería. Aquí se guarda esa elección para volver a mostrarla.
--
-- TOCA DATOS: no. Solo agrega columnas que admiten NULL; las notas ya emitidas quedan
--   en NULL (su lote se repartió automáticamente, como siempre).
--
-- ORDEN: aplicar ANTES de desplegar el código. Si el código llega primero no se rompe
--   nada: la devolución al inventario usa igual el lote elegido, pero al reabrir la
--   nota no se vuelve a mostrar.
--
-- REVERSIBLE:
--   ALTER TABLE notas_credito_detalle DROP COLUMN IF EXISTS numero_lote;
--   ALTER TABLE notas_credito_detalle DROP COLUMN IF EXISTS nup;
--
-- EJECUCIÓN: pegar completo en el Query Tool de pgAdmin y ejecutar (F5). Es
--   idempotente: se puede volver a ejecutar sin error.
-- =============================================================================

ALTER TABLE notas_credito_detalle ADD COLUMN IF NOT EXISTS numero_lote VARCHAR(100) NULL;
ALTER TABLE notas_credito_detalle ADD COLUMN IF NOT EXISTS nup         VARCHAR(100) NULL;

COMMENT ON COLUMN notas_credito_detalle.numero_lote IS
    'Lote al que el usuario eligió devolver el ítem (uno de los que salieron en la factura modificada). NULL = reparto automático.';
COMMENT ON COLUMN notas_credito_detalle.nup IS
    'NUP / serial al que el usuario eligió devolver el ítem. NULL = reparto automático.';

-- COMPROBACIÓN (ejecutar aparte; debe devolver 2 filas):
-- SELECT column_name, data_type, is_nullable
--   FROM information_schema.columns
--  WHERE table_schema = 'public' AND table_name = 'notas_credito_detalle'
--    AND column_name IN ('numero_lote', 'nup');
