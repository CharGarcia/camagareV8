-- =====================================================================================
-- Pedidos y Consignaciones de venta: unidad de medida por línea.
--
-- QUÉ HACE
--   Agrega la unidad en que se escribió la cantidad de cada línea (p. ej. CAJA X100),
--   igual que ventas_detalle.id_unidad_medida en Facturas de venta. La cantidad de la
--   línea queda en esa unidad (4 cajas = 4); el kardex sale siempre convertido a la
--   unidad del producto (4 CAJA X100 = 400).
--
--   Solo se usa cuando Empresa → Facturación → "Mostrar unidad de medida" está activo.
--   NULL = la unidad del producto (todas las líneas existentes quedan así, sin cambios).
--
-- TOCA DATOS: no (solo esquema). Idempotente: se puede correr varias veces.
-- El código degrada si las columnas aún no existen (no muestra el selector y todo va en
-- la unidad del producto, como antes), así que el orden recomendado es: correr este SQL
-- y luego desplegar.
-- =====================================================================================

ALTER TABLE pedidos_detalle ADD COLUMN IF NOT EXISTS id_unidad_medida INTEGER;

ALTER TABLE consignaciones_ventas_detalles ADD COLUMN IF NOT EXISTS id_unidad_medida INTEGER;

-- Comprobación (deben salir 2 filas):
-- SELECT table_name, column_name FROM information_schema.columns
--  WHERE column_name = 'id_unidad_medida'
--    AND table_name IN ('pedidos_detalle', 'consignaciones_ventas_detalles');
