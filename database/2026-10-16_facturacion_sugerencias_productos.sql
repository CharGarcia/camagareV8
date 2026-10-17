-- ============================================================================
--  Configuración de facturación: sugerencias cuando un producto no tiene saldo
-- ----------------------------------------------------------------------------
--  Para qué: en Empresa → Facturación, debajo de "Mostrar unidad de medida en
--  facturación", dos interruptores por establecimiento:
--    - sugerir_productos_similares: al elegir un producto sin saldo, ofrecer
--      productos parecidos (misma categoría, marca o nombre) con saldo.
--    - sugerir_equivalencias: ofrecer los productos equivalentes definidos en
--      la ficha del producto (pestaña Referencias) con saldo.
--  Aplica a Facturas de Venta y Car-Wash, y solo si "La facturación afecta al
--  inventario" está activo.
--
--  Por defecto ambos en false: ninguna empresa ve la ventana hasta activarla.
--  OJO: antes de este cambio, las empresas con "¿Trabajar con stock positivo?"
--  veían la ventana de similares siempre; con este SQL deja de salir hasta que
--  activen "Mostrar sugerencias de productos similares".
--
--  Toca datos: no (solo agrega columnas con default). Reversible: sí
--    ALTER TABLE empresa_establecimiento DROP COLUMN sugerir_productos_similares,
--                                        DROP COLUMN sugerir_equivalencias;
--  Idempotente: se puede ejecutar varias veces sin efecto.
--  Orden: ejecutar ANTES de desplegar el código (sin el SQL el código asume
--  ambas opciones desactivadas y no falla).
-- ============================================================================

ALTER TABLE empresa_establecimiento
    ADD COLUMN IF NOT EXISTS sugerir_productos_similares BOOLEAN NOT NULL DEFAULT false;

ALTER TABLE empresa_establecimiento
    ADD COLUMN IF NOT EXISTS sugerir_equivalencias BOOLEAN NOT NULL DEFAULT false;

COMMENT ON COLUMN empresa_establecimiento.sugerir_productos_similares IS
    'Al elegir un producto sin saldo, ofrecer productos parecidos (categoría, marca, nombre) con saldo. Facturas de Venta y Car-Wash.';
COMMENT ON COLUMN empresa_establecimiento.sugerir_equivalencias IS
    'Al elegir un producto sin saldo, ofrecer sus productos equivalentes (ficha del producto, pestaña Referencias) con saldo. Facturas de Venta y Car-Wash.';

-- Comprobación (debe devolver 2 filas):
-- SELECT column_name FROM information_schema.columns
--  WHERE table_name = 'empresa_establecimiento'
--    AND column_name IN ('sugerir_productos_similares', 'sugerir_equivalencias');
