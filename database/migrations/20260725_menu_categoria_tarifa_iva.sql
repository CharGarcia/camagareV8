-- ============================================================================
-- Módulo Menú — ajuste: usar la tabla real `categorias` (la misma que
-- Productos, ya con destino_impresion) en vez de texto libre, y agregar
-- tarifa de IVA propia para ítems sin producto vinculado.
--
-- menu_items ya se creó en 20260724_create_menu_module.sql sin filas reales
-- (módulo recién construido) — se ajusta con ALTER en vez de recrear.
-- ============================================================================

ALTER TABLE menu_items ADD COLUMN IF NOT EXISTS id_categoria INTEGER REFERENCES categorias(id);
ALTER TABLE menu_items ADD COLUMN IF NOT EXISTS id_tarifa_iva INTEGER REFERENCES tarifa_iva(id);

-- Ya no hacen falta: la categoría real trae su propio destino_impresion
-- (categorias.destino_impresion, agregado en la Fase 0 de POS Restaurantes),
-- y categoria_menu era el texto libre que reemplaza id_categoria.
ALTER TABLE menu_items DROP COLUMN IF EXISTS categoria_menu;
ALTER TABLE menu_items DROP COLUMN IF EXISTS destino_impresion;

CREATE INDEX IF NOT EXISTS idx_menu_items_categoria ON menu_items (id_categoria);
