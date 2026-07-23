-- ============================================================================
-- Módulo Menú — categorías PROPIAS del menú (menu_categorias), separadas por
-- completo de `categorias` (esa es de Productos, no se toca — decisión
-- explícita: las categorías del menú son distintas de las de productos).
--
-- menu_items.id_categoria apuntaba a `categorias` desde 20260725; se corrige
-- para apuntar a `menu_categorias`. Sigue sin filas reales (0 registros).
-- ============================================================================

CREATE TABLE IF NOT EXISTS menu_categorias (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    nombre              VARCHAR(60) NOT NULL,
    destino_impresion   VARCHAR(20) NOT NULL DEFAULT 'ninguno', -- cocina|barra|ninguno — solo aplica a ítems del menú SIN producto vinculado
    orden               INTEGER NOT NULL DEFAULT 0,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

CREATE INDEX IF NOT EXISTS idx_menu_categorias_empresa ON menu_categorias (id_empresa, eliminado);

-- Retargeting de la FK: de categorias(id) a menu_categorias(id).
ALTER TABLE menu_items DROP CONSTRAINT IF EXISTS menu_items_id_categoria_fkey;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'menu_items_id_categoria_fkey') THEN
        ALTER TABLE menu_items
            ADD CONSTRAINT menu_items_id_categoria_fkey
            FOREIGN KEY (id_categoria) REFERENCES menu_categorias(id);
    END IF;
END $$;
