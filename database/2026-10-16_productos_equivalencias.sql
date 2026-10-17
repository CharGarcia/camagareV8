-- ============================================================================
--  Productos equivalentes (grupos de equivalencia)
-- ----------------------------------------------------------------------------
--  Para qué: en la ficha del producto, pestaña Referencias, el usuario agrega
--  otros productos que sirven para lo mismo (p. ej. el filtro de aceite de otra
--  marca que le cabe al mismo carro, el genérico de un medicamento). Todos los
--  productos de un mismo grupo son equivalentes entre sí; cuando uno no tiene
--  saldo, Facturas de Venta y Car-Wash ofrecen primero sus equivalentes.
--
--  El usuario nunca ve el "grupo": agrega productos a la lista y el sistema
--  crea, une o disuelve los grupos solo (ProductoEquivalenciaService).
--
--  Tabla operativa (lleva id_empresa). Un producto pertenece a un solo grupo
--  (productos.id_grupo_equivalencia); NULL = sin equivalentes.
--
--  Toca datos: no. Reversible: sí
--    ALTER TABLE productos DROP COLUMN id_grupo_equivalencia;
--    DROP TABLE productos_grupos_equivalencia;
--  Idempotente: se puede ejecutar varias veces sin efecto.
--  Orden: ejecutar ANTES de desplegar el código (el código sin este SQL solo
--  esconde la sección, no falla).
-- ============================================================================

CREATE TABLE IF NOT EXISTS productos_grupos_equivalencia (
    id                 SERIAL PRIMARY KEY,
    id_empresa         INT NOT NULL,
    nombre             VARCHAR(300) NOT NULL,      -- por defecto, el nombre del producto que originó el grupo
    codigo_referencia  VARCHAR(100) NULL,          -- opcional: código común (p. ej. número de parte original)
    created_at         TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at         TIMESTAMP NULL,
    created_by         INT NULL,
    updated_by         INT NULL,
    eliminado          BOOLEAN NOT NULL DEFAULT false,
    deleted_at         TIMESTAMP NULL,
    deleted_by         INT NULL
);

COMMENT ON TABLE productos_grupos_equivalencia IS
    'Grupos de productos equivalentes por empresa. Los miembros son los productos con productos.id_grupo_equivalencia = id. Se administra desde la pestaña Referencias de la ficha del producto.';

CREATE INDEX IF NOT EXISTS idx_productos_grupos_equivalencia_empresa
    ON productos_grupos_equivalencia (id_empresa)
    WHERE eliminado = false;

ALTER TABLE productos
    ADD COLUMN IF NOT EXISTS id_grupo_equivalencia INT NULL;

COMMENT ON COLUMN productos.id_grupo_equivalencia IS
    'Grupo de productos equivalentes (productos_grupos_equivalencia.id). NULL = sin equivalentes.';

-- Miembros de un grupo (lista de la pestaña Referencias y sugerencias al vender).
CREATE INDEX IF NOT EXISTS idx_productos_grupo_equivalencia
    ON productos (id_empresa, id_grupo_equivalencia)
    WHERE id_grupo_equivalencia IS NOT NULL AND eliminado = false;

-- Comprobación (debe devolver 2 filas):
-- SELECT 'tabla' AS objeto, to_regclass('public.productos_grupos_equivalencia')::text AS valor
-- UNION ALL
-- SELECT 'columna', column_name FROM information_schema.columns
--  WHERE table_name = 'productos' AND column_name = 'id_grupo_equivalencia';
