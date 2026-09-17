-- =====================================================================================
-- Facturación de consignaciones: registros generados por Cambios de productos
-- -------------------------------------------------------------------------------------
-- Qué hace:
--   Agrega consignaciones_facturas.id_cambio_producto (el cambio que generó el registro)
--   y consignaciones_facturas_detalles.id_cambio_detalle (la línea de entrega del cambio),
--   con índices parciales.
--   Al emitir un cambio de productos, lo que se entrega desde una consignación queda como
--   'facturada' en la factura de venta de la unidad devuelta, SIN crear factura nueva. Estas
--   columnas identifican esos registros para que la unidad no se descuente dos veces del
--   saldo de la consignación, no reciban asiento de reingreso y no se reviertan al anular la
--   factura de venta original.
--
-- Toca datos: NO. Solo agrega columnas vacías e índices.
--
-- Orden de despliegue: ejecutar este SQL ANTES de subir el código.
--   Sin estas columnas el sistema sigue funcionando como antes, pero no deja emitir cambios
--   que entregan desde consignación (avisa que falta este archivo).
--   Requiere 20260915_cambios_producto_cv_origen_consignacion.sql ya aplicado.
--
-- Reversible: sí, mientras no se hayan generado registros desde cambios:
--   DROP INDEX IF EXISTS idx_cons_facturas_det_cambio_detalle;
--   DROP INDEX IF EXISTS idx_cons_facturas_cambio_producto;
--   ALTER TABLE consignaciones_facturas_detalles DROP COLUMN IF EXISTS id_cambio_detalle;
--   ALTER TABLE consignaciones_facturas DROP COLUMN IF EXISTS id_cambio_producto;
--
-- Listo para pgAdmin: pegar y ejecutar de una vez (F5). Idempotente.
-- =====================================================================================

ALTER TABLE consignaciones_facturas
    ADD COLUMN IF NOT EXISTS id_cambio_producto INTEGER;

ALTER TABLE consignaciones_facturas_detalles
    ADD COLUMN IF NOT EXISTS id_cambio_detalle INTEGER;

COMMENT ON COLUMN consignaciones_facturas.id_cambio_producto IS
    'Cambio de productos (cambios_producto_cv.id) que generó este registro. NULL = facturación normal, con factura de venta propia.';

COMMENT ON COLUMN consignaciones_facturas_detalles.id_cambio_detalle IS
    'Línea de entrega del cambio de productos (cambios_producto_cv_detalles.id) registrada como facturada.';

CREATE INDEX IF NOT EXISTS idx_cons_facturas_cambio_producto
    ON consignaciones_facturas (id_cambio_producto)
 WHERE id_cambio_producto IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_cons_facturas_det_cambio_detalle
    ON consignaciones_facturas_detalles (id_cambio_detalle)
 WHERE id_cambio_detalle IS NOT NULL;

-- Comprobación (deben salir 2 filas):
-- SELECT table_name, column_name
--   FROM information_schema.columns
--  WHERE (table_name = 'consignaciones_facturas'          AND column_name = 'id_cambio_producto')
--     OR (table_name = 'consignaciones_facturas_detalles' AND column_name = 'id_cambio_detalle');
