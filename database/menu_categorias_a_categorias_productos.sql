-- ============================================================================
-- Menú: las categorías pasan a ser las de Productos, y la estación de
-- impresión pasa a configurarse por ítem del menú.
--
-- Antes: menu_items.id_categoria -> menu_categorias (tabla propia del menú), y
--        la estación de cocina/barra salía de menu_categorias.id_estacion_impresion.
-- Ahora: menu_items.id_categoria -> categorias (las mismas de Productos), y
--        cada ítem lleva su propia menu_items.id_estacion_impresion.
--
-- Los ítems quedan SIN categoría (id_categoria = NULL): los ids de
-- menu_categorias no equivalen a los de categorias, así que conservarlos
-- clasificaría cada plato en una categoría equivocada. Hay que reasignarlos a
-- mano desde el módulo Menú.
--
-- La estación SÍ se conserva: se copia al ítem la que tenía por su categoría
-- del menú, para que los platos sigan llegando a la misma cocina/barra.
--
-- menu_categorias NO se borra: queda sin uso, con sus datos, por si hay que
-- consultar cómo estaba clasificado algo.
-- ============================================================================

BEGIN;

-- 1. Estación por ítem (nueva). Nullable: un ítem sin estación se entrega
--    directo, sin pasar por el KDS (ej. una bebida embotellada).
ALTER TABLE menu_items
    ADD COLUMN IF NOT EXISTS id_estacion_impresion INTEGER NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'menu_items_id_estacion_impresion_fkey'
    ) THEN
        ALTER TABLE menu_items
            ADD CONSTRAINT menu_items_id_estacion_impresion_fkey
            FOREIGN KEY (id_estacion_impresion) REFERENCES estaciones_impresion(id);
    END IF;
END $$;

-- 2. Conservar el enrutamiento actual: la estación que el ítem tenía a través
--    de su categoría del menú pasa a ser suya.
UPDATE menu_items m
   SET id_estacion_impresion = mc.id_estacion_impresion
  FROM menu_categorias mc
 WHERE mc.id = m.id_categoria
   AND mc.id_estacion_impresion IS NOT NULL
   AND m.id_estacion_impresion IS NULL;

-- 3. Repuntar la categoría a las de Productos. Primero se suelta la FK vieja,
--    luego se anulan los valores (apuntan a ids de otra tabla) y recién ahí se
--    crea la FK nueva: al revés fallaría por violación de la referencia.
ALTER TABLE menu_items DROP CONSTRAINT IF EXISTS menu_items_id_categoria_fkey;

UPDATE menu_items SET id_categoria = NULL WHERE id_categoria IS NOT NULL;

ALTER TABLE menu_items
    ADD CONSTRAINT menu_items_id_categoria_fkey
    FOREIGN KEY (id_categoria) REFERENCES categorias(id);

COMMIT;

-- Verificación (debe devolver 0 filas con categoría y las estaciones copiadas):
-- SELECT id, nombre, id_categoria, id_estacion_impresion FROM menu_items WHERE eliminado = false ORDER BY id;
