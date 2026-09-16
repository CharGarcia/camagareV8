-- ============================================================================
-- Cambios de productos (modulos/cambio-producto-cv): entregas desde consignación
-- ----------------------------------------------------------------------------
-- Qué hace: amplía cambios_producto_cv_detalles.origen_tipo de VARCHAR(10) a
--   VARCHAR(15) para admitir el origen 'CONSIGNACION' (12 caracteres) en las líneas
--   de ENTREGA tomadas de una consignación que el cliente ya tiene en su poder.
--   Hasta ahora solo se guardaban 'FACTURA' y 'CAMBIO' (devoluciones).
-- Toca datos: NO (solo cambia el tipo de la columna; los valores existentes se
--   conservan tal cual).
-- Reversible: SÍ, mientras no existan líneas con origen_tipo = 'CONSIGNACION':
--   ALTER TABLE cambios_producto_cv_detalles ALTER COLUMN origen_tipo TYPE VARCHAR(10);
-- Idempotente: se puede ejecutar más de una vez.
-- Orden: aplicar este SQL ANTES de desplegar el código; sin él, guardar una entrega
--   desde consignación falla con un mensaje que remite a este archivo.
-- ============================================================================

ALTER TABLE cambios_producto_cv_detalles
    ALTER COLUMN origen_tipo TYPE VARCHAR(15);

COMMENT ON COLUMN cambios_producto_cv_detalles.origen_tipo IS
    'Devolución: FACTURA | CAMBIO. Entrega: CONSIGNACION (tomada de una consignación del cliente) | NULL (desde bodega / catálogo).';

-- Comprobación (debe salir 1 fila con character_maximum_length = 15):
-- SELECT column_name, character_maximum_length
-- FROM information_schema.columns
-- WHERE table_name = 'cambios_producto_cv_detalles' AND column_name = 'origen_tipo';
