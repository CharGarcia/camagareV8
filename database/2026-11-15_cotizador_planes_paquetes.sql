-- ============================================================================
-- Cotizador de Planes — PAQUETES (p. ej. «Emprendedor»).
--
-- Ejecutar DESPUÉS de 2026-11-13_cotizador_planes.sql y
-- 2026-11-15_cotizador_planes_semestral.sql. Sin este SQL la pantalla del
-- cotizador avisa que falta instalarlo.
--
-- Reglas (aprobadas 15-11-2026):
--  - Un paquete agrupa módulos del catálogo. Vale la SUMA de sus módulos menos
--    un % propio del paquete (descuento_pct); si cambia el precio de un módulo,
--    el paquete se actualiza solo.
--  - El vendedor elige un paquete por establecimiento: sus módulos quedan fijos
--    (no se quitan) y puede agregar módulos sueltos aparte, a su precio normal.
--
-- Tablas operativas (id_empresa) con auditoría y eliminación lógica (CLAUDE.md §5).
-- Idempotente: se puede ejecutar más de una vez.
-- ============================================================================

CREATE TABLE IF NOT EXISTS cotizador_planes_paquetes (
    id             SERIAL PRIMARY KEY,
    id_empresa     INTEGER      NOT NULL,
    nombre         VARCHAR(100) NOT NULL,
    descripcion    TEXT         NULL,
    descuento_pct  NUMERIC(6,2) NOT NULL DEFAULT 0,
    orden          INTEGER      NOT NULL DEFAULT 0,
    estado         VARCHAR(10)  NOT NULL DEFAULT 'activo',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NULL,
    created_by   INTEGER   NULL,
    updated_by   INTEGER   NULL,
    eliminado    BOOLEAN   NOT NULL DEFAULT false,
    deleted_at   TIMESTAMP NULL,
    deleted_by   INTEGER   NULL,
    CONSTRAINT ck_cot_paq_estado    CHECK (estado IN ('activo', 'inactivo')),
    CONSTRAINT ck_cot_paq_descuento CHECK (descuento_pct >= 0 AND descuento_pct <= 100)
);
CREATE INDEX IF NOT EXISTS idx_cot_paquetes_empresa
    ON cotizador_planes_paquetes (id_empresa) WHERE eliminado = false;

CREATE TABLE IF NOT EXISTS cotizador_planes_paquetes_items (
    id           SERIAL PRIMARY KEY,
    id_empresa   INTEGER NOT NULL,
    id_paquete   INTEGER NOT NULL REFERENCES cotizador_planes_paquetes (id),
    id_item      INTEGER NOT NULL REFERENCES cotizador_planes_items (id),
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NULL,
    created_by   INTEGER   NULL,
    updated_by   INTEGER   NULL,
    eliminado    BOOLEAN   NOT NULL DEFAULT false,
    deleted_at   TIMESTAMP NULL,
    deleted_by   INTEGER   NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_cot_paq_item
    ON cotizador_planes_paquetes_items (id_paquete, id_item) WHERE eliminado = false;

-- Paquete elegido en cada establecimiento de una cotización (copia del nombre y
-- del % al guardar: un cambio posterior del paquete no altera la cotización).
ALTER TABLE cotizaciones_planes_establecimientos
    ADD COLUMN IF NOT EXISTS id_paquete            INTEGER      NULL;
ALTER TABLE cotizaciones_planes_establecimientos
    ADD COLUMN IF NOT EXISTS paquete_nombre        VARCHAR(100) NULL;
ALTER TABLE cotizaciones_planes_establecimientos
    ADD COLUMN IF NOT EXISTS paquete_descuento_pct NUMERIC(6,2) NOT NULL DEFAULT 0;

-- Ítems que entraron por el paquete y el % que se les aplica (copia).
ALTER TABLE cotizaciones_planes_detalle
    ADD COLUMN IF NOT EXISTS de_paquete            BOOLEAN      NOT NULL DEFAULT false;
ALTER TABLE cotizaciones_planes_detalle
    ADD COLUMN IF NOT EXISTS descuento_paquete_pct NUMERIC(6,2) NOT NULL DEFAULT 0;

COMMENT ON TABLE cotizador_planes_paquetes IS 'Cotizador de Planes: paquetes de módulos con % de descuento propio (p. ej. Emprendedor).';
