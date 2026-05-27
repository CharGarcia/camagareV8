-- ============================================================
-- Módulo: Órdenes de Compra
-- ============================================================

CREATE TABLE IF NOT EXISTS ordenes_compra (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    id_proveedor        INTEGER NOT NULL,
    id_establecimiento  INTEGER NOT NULL,
    id_punto_emision    INTEGER NOT NULL,
    establecimiento     VARCHAR(3)  NOT NULL,
    punto_emision       VARCHAR(3)  NOT NULL,
    secuencial          VARCHAR(9)  NOT NULL,
    numero_orden        VARCHAR(20) GENERATED ALWAYS AS (establecimiento || '-' || punto_emision || '-' || secuencial) STORED,
    fecha_orden         DATE        NOT NULL DEFAULT CURRENT_DATE,
    fecha_recepcion     DATE        DEFAULT NULL,
    observaciones       TEXT        DEFAULT NULL,
    estado              VARCHAR(20) NOT NULL DEFAULT 'borrador',
    created_at          TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER     NOT NULL,
    updated_by          INTEGER     NOT NULL,
    eliminado           BOOLEAN     NOT NULL DEFAULT FALSE,
    deleted_at          TIMESTAMP   DEFAULT NULL,
    deleted_by          INTEGER     DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS ordenes_compra_detalle (
    id                          SERIAL PRIMARY KEY,
    id_orden                    INTEGER         NOT NULL REFERENCES ordenes_compra(id),
    id_empresa                  INTEGER         NOT NULL,
    id_producto                 INTEGER         DEFAULT NULL,
    descripcion                 VARCHAR(300)    NOT NULL,
    cantidad                    NUMERIC(18,6)   NOT NULL DEFAULT 1,
    precio_unitario             NUMERIC(18,6)   NOT NULL DEFAULT 0,
    created_at                  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by                  INTEGER         NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_ordenes_compra_empresa     ON ordenes_compra(id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_ordenes_compra_proveedor   ON ordenes_compra(id_proveedor);
CREATE INDEX IF NOT EXISTS idx_ordenes_compra_detalle_ord ON ordenes_compra_detalle(id_orden);
