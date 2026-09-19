-- =============================================================================
-- Notas de crédito: cada ítem guarda la línea de la factura de la que viene
-- =============================================================================
-- QUÉ HACE
--   Agrega notas_credito_detalle.id_venta_detalle (id de ventas_detalle). Al cargar
--   la factura en la nota de crédito, cada ítem queda enlazado —sin mostrarlo— a su
--   línea de factura, y la devolución al inventario toma el lote, NUP y caducidad de
--   ESA línea. Antes se repartía entre los lotes de la factura en el orden en que
--   salieron, y en una devolución parcial podía quedar el lote o el serial equivocado.
--
-- TOCA DATOS: no. Solo agrega una columna que admite NULL; las notas ya emitidas
--   quedan en NULL y no cambian.
--
-- ORDEN: aplicar ANTES de desplegar el código. Si el código llega primero no se
--   rompe nada: detecta que falta la columna y guarda los ítems sin el enlace.
--
-- REVERSIBLE: ALTER TABLE notas_credito_detalle DROP COLUMN IF EXISTS id_venta_detalle;
--
-- EJECUCIÓN: pegar completo en el Query Tool de pgAdmin y ejecutar (F5). Es
--   idempotente: se puede volver a ejecutar sin error.
-- =============================================================================

ALTER TABLE notas_credito_detalle ADD COLUMN IF NOT EXISTS id_venta_detalle INTEGER NULL;

COMMENT ON COLUMN notas_credito_detalle.id_venta_detalle IS
    'Línea de la factura (ventas_detalle.id) de la que viene el ítem; de ella sale el lote / NUP / caducidad que la nota devuelve al inventario.';

-- COMPROBACIÓN (ejecutar aparte; debe devolver 1 fila):
-- SELECT column_name, data_type, is_nullable
--   FROM information_schema.columns
--  WHERE table_schema = 'public' AND table_name = 'notas_credito_detalle'
--    AND column_name = 'id_venta_detalle';
