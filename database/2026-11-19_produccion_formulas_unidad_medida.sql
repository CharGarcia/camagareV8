-- ============================================================================
-- Producción · Fórmulas: unidad de medida por línea (insumos y otras salidas)
-- ----------------------------------------------------------------------------
-- Contexto:
--   En la tabla de Insumos / Otras salidas de una fórmula, la cantidad se puede
--   escribir en otra unidad del MISMO tipo que la unidad del producto
--   (p. ej. el producto está en KILOGRAMO y la receta usa GRAMO o QUINTAL).
--   Esta columna guarda la unidad elegida. NULL = la unidad del producto
--   (lo de siempre).
--
--   El stock, el kardex y las órdenes de producción siguen SIEMPRE en la unidad
--   del producto: al crear la orden (y al estimar el costo) la cantidad se
--   convierte con los factores de unidades_medida (InventarioService::
--   cantidadEnUnidadProducto). Por eso esta columna no cambia nada de lo ya
--   producido.
--
-- Qué hace:
--   Agrega una columna que admite NULL. NO modifica ni borra datos: todas las
--   líneas existentes quedan en NULL (= unidad del producto, igual que hoy).
--
-- Idempotente: sí (ADD COLUMN IF NOT EXISTS). Se puede ejecutar varias veces.
-- Sin la columna el sistema sigue funcionando: el selector de unidad no guarda
-- y todo queda en la unidad del producto.
--
-- Reversible: sí, sin pérdida de datos de lo ya producido —
--   ALTER TABLE produccion_formulas_lineas DROP COLUMN IF EXISTS id_unidad_medida;
--   (las fórmulas que usaban otra unidad volverían a leer su cantidad como si
--    estuviera en la unidad del producto: revisarlas antes).
--
-- CÓMO EJECUTARLO EN pgAdmin: Query Tool sobre la base, pegar todo y F5.
-- ============================================================================

ALTER TABLE produccion_formulas_lineas
    ADD COLUMN IF NOT EXISTS id_unidad_medida INTEGER REFERENCES unidades_medida(id);

COMMENT ON COLUMN produccion_formulas_lineas.id_unidad_medida IS
    'Unidad en que está escrita la cantidad de la línea (mismo tipo que la unidad del producto). NULL = unidad del producto.';

-- Comprobación (debe salir 1 fila: id_unidad_medida | integer | YES):
-- SELECT column_name, data_type, is_nullable
--   FROM information_schema.columns
--  WHERE table_name = 'produccion_formulas_lineas' AND column_name = 'id_unidad_medida';
