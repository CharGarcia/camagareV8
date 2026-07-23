-- ============================================================================
-- Módulo: Menú (carta del restaurante) — Fase A (modelo de datos + CRUD admin)
-- Ruta MVC: modulos/menu
--
-- Contexto:
--   Catálogo visual (con fotos) de lo que el restaurante vende, pensado para
--   dos usos: (1) selector de ítems en modulos/comandas/ver, junto a los
--   productos "crudos" del sistema; (2) portal público sin login por QR
--   (tabla menu_config_portal, fase siguiente) para que el cliente vea la
--   carta desde su celular.
--
--   Un ítem del menú PUEDE vincularse a un producto existente (id_producto) —
--   incluye productos compuestos (tipo_produccion='02' + productos_componentes,
--   ya soportado por InventarioService: descuenta cada componente solo. NO se
--   necesita ningún sistema de recetas nuevo aquí, un "combo" del menú es
--   simplemente un menu_item apuntando a un producto compuesto ya armado en
--   Productos) — o puede ser independiente (id_producto NULL: un ítem que no
--   se rastrea en inventario, con su propio nombre/precio/foto).
--
-- Reglas del sistema:
--   - Multiempresa: todas las tablas llevan id_empresa.
--   - Eliminación lógica: eliminado / deleted_at / deleted_by.
--   - Auditoría: created_at/by, updated_at/by.
-- ============================================================================

CREATE TABLE IF NOT EXISTS menu_items (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    id_producto         INTEGER REFERENCES productos(id), -- NULL = ítem independiente, sin inventario
    nombre              VARCHAR(200) NOT NULL,
    descripcion         TEXT,
    precio              NUMERIC(14,2) NOT NULL DEFAULT 0,
    imagen              VARCHAR(255),                 -- ruta relativa, mismo patrón que productos.imagen (public/uploads/menu/)
    categoria_menu      VARCHAR(60),                  -- agrupación de VISUALIZACIÓN (ej. "Entradas","Platos fuertes","Bebidas"),
                                                        -- independiente de productos.id_categoria (esa maneja cocina/barra e impuestos)
    destino_impresion   VARCHAR(20) DEFAULT 'ninguno', -- cocina|barra|ninguno — solo aplica si id_producto es NULL
                                                        -- (si hay producto vinculado, manda la categoría de ESE producto, igual que el resto de comandas)
    disponible          BOOLEAN NOT NULL DEFAULT TRUE,  -- "se acabó" sin necesidad de eliminar el ítem
    destacado           BOOLEAN NOT NULL DEFAULT FALSE, -- plato del día / resaltado en el menú público
    orden               INTEGER NOT NULL DEFAULT 0,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

CREATE INDEX IF NOT EXISTS idx_menu_items_empresa    ON menu_items (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_menu_items_disponible ON menu_items (id_empresa, disponible, eliminado);
CREATE INDEX IF NOT EXISTS idx_menu_items_producto   ON menu_items (id_producto);

-- ---------------------------------------------------------------------------
-- Portal público por QR (fase siguiente): un slug único por empresa, sin login.
-- Mismo patrón que citas_config_portal (módulo Reservas).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS menu_config_portal (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL UNIQUE,
    slug                VARCHAR(100) NOT NULL UNIQUE,
    nombre_publico      VARCHAR(150),                 -- nombre a mostrar al cliente (puede diferir del razón social)
    activo              BOOLEAN NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------------------
-- comanda_detalle: hasta ahora id_producto era obligatorio. Un ítem de menú
-- independiente (sin producto vinculado) no tiene id_producto — se permite
-- NULL, y se guarda id_menu_item para saber qué se pidió (etiqueta/foto en
-- el KDS y la comanda). Esta tabla YA tiene filas reales (no es un CREATE
-- nuevo), por eso va en migración aparte con ALTER, no editando la anterior.
-- ---------------------------------------------------------------------------
ALTER TABLE comanda_detalle ALTER COLUMN id_producto DROP NOT NULL;
ALTER TABLE comanda_detalle ADD COLUMN IF NOT EXISTS id_menu_item INTEGER REFERENCES menu_items(id);

CREATE INDEX IF NOT EXISTS idx_comanda_det_menu_item ON comanda_detalle (id_menu_item);
