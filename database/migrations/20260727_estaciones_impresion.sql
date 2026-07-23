-- ============================================================================
-- Estaciones de impresión: reemplaza el enum fijo 'cocina'|'barra'|'ninguno'
-- por un catálogo configurable por empresa (ej. "Barra 1", "Barra 2",
-- "Cocina Caliente", "Parrilla" — tantas como el restaurante necesite).
-- Se usa desde categorías de Productos, categorías del Menú, y el KDS.
--
-- Migra los datos existentes: cualquier categorías/menu_categorías con
-- destino_impresion = 'cocina' o 'barra' obtiene una estación equivalente
-- ya creada y vinculada, para no perder configuración.
-- ============================================================================

CREATE TABLE IF NOT EXISTS estaciones_impresion (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    nombre              VARCHAR(60) NOT NULL,
    tipo                VARCHAR(20) NOT NULL DEFAULT 'cocina', -- cocina|barra|otro — solo informativo (ícono/color), no restringe nada
    orden               INTEGER NOT NULL DEFAULT 0,
    activo              BOOLEAN NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

CREATE INDEX IF NOT EXISTS idx_estaciones_impresion_empresa ON estaciones_impresion (id_empresa, eliminado, activo);

-- ---------------------------------------------------------------------------
-- Semilla: una estación "Cocina" y/o "Barra" por cada empresa que ya tenía
-- categorías configuradas con esos valores, para no perder la configuración.
-- ---------------------------------------------------------------------------
INSERT INTO estaciones_impresion (id_empresa, nombre, tipo, orden)
SELECT DISTINCT id_empresa, 'Cocina', 'cocina', 1 FROM menu_categorias WHERE destino_impresion = 'cocina'
UNION
SELECT DISTINCT id_empresa, 'Barra', 'barra', 2 FROM menu_categorias WHERE destino_impresion = 'barra'
UNION
SELECT DISTINCT id_empresa, 'Cocina', 'cocina', 1 FROM categorias WHERE destino_impresion = 'cocina'
UNION
SELECT DISTINCT id_empresa, 'Barra', 'barra', 2 FROM categorias WHERE destino_impresion = 'barra';

-- ---------------------------------------------------------------------------
-- menu_categorias: destino_impresion (texto) → id_estacion_impresion (FK)
-- ---------------------------------------------------------------------------
ALTER TABLE menu_categorias ADD COLUMN IF NOT EXISTS id_estacion_impresion INTEGER REFERENCES estaciones_impresion(id);

UPDATE menu_categorias mc
SET id_estacion_impresion = (
    SELECT ei.id FROM estaciones_impresion ei
    WHERE ei.id_empresa = mc.id_empresa AND ei.tipo = mc.destino_impresion
    ORDER BY ei.id LIMIT 1
)
WHERE mc.destino_impresion IN ('cocina', 'barra');

ALTER TABLE menu_categorias DROP COLUMN IF EXISTS destino_impresion;

-- ---------------------------------------------------------------------------
-- categorias (Productos): mismo tratamiento.
-- ---------------------------------------------------------------------------
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS id_estacion_impresion INTEGER REFERENCES estaciones_impresion(id);

UPDATE categorias c
SET id_estacion_impresion = (
    SELECT ei.id FROM estaciones_impresion ei
    WHERE ei.id_empresa = c.id_empresa AND ei.tipo = c.destino_impresion
    ORDER BY ei.id LIMIT 1
)
WHERE c.destino_impresion IN ('cocina', 'barra');

ALTER TABLE categorias DROP COLUMN IF EXISTS destino_impresion;

-- ---------------------------------------------------------------------------
-- comanda_detalle: el snapshot que lee el KDS pasa de texto a FK también.
-- ---------------------------------------------------------------------------
ALTER TABLE comanda_detalle ADD COLUMN IF NOT EXISTS id_estacion_impresion INTEGER REFERENCES estaciones_impresion(id);
ALTER TABLE comanda_detalle DROP COLUMN IF EXISTS destino_impresion;

CREATE INDEX IF NOT EXISTS idx_comanda_det_estacion ON comanda_detalle (id_empresa, estado_linea, id_estacion_impresion);
