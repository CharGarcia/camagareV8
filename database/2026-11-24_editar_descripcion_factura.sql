-- ============================================================================
--  Configuración de facturación: permitir modificar la descripción del ítem
-- ----------------------------------------------------------------------------
--  Para qué: en Empresa → Facturación, un interruptor por establecimiento:
--    - editar_descripcion_factura: en Facturas de Venta, Recibos de Venta y Proformas, después de elegir un
--      producto o servicio del catálogo, se puede modificar su descripción solo
--      para ese documento. El código y el producto del catálogo no cambian.
--
--  Por defecto en false: ninguna empresa cambia de comportamiento hasta activarlo.
--
--  Toca datos: no (solo agrega una columna con default). Reversible: sí
--    ALTER TABLE empresa_establecimiento DROP COLUMN editar_descripcion_factura;
--  Idempotente: se puede ejecutar varias veces sin efecto.
--  Orden: ejecutar ANTES de desplegar el código (sin el SQL el código asume la
--  opción desactivada y no falla).
-- ============================================================================

ALTER TABLE empresa_establecimiento
    ADD COLUMN IF NOT EXISTS editar_descripcion_factura BOOLEAN NOT NULL DEFAULT false;

COMMENT ON COLUMN empresa_establecimiento.editar_descripcion_factura IS
    'Facturas de Venta, Recibos de Venta y Proformas: permite modificar la descripción de un producto/servicio del catálogo solo en ese documento (el catálogo no cambia).';

-- Comprobación (debe devolver 1 fila):
-- SELECT column_name FROM information_schema.columns
--  WHERE table_name = 'empresa_establecimiento'
--    AND column_name = 'editar_descripcion_factura';
