-- ============================================================
-- Módulo de Inventario / Kardex
-- Las entradas y salidas se registran manualmente.
-- Los lotes y fechas de caducidad se gestionan por movimiento.
-- ============================================================

CREATE TABLE IF NOT EXISTS inventario_kardex (
    id SERIAL PRIMARY KEY,
    id_empresa         INTEGER NOT NULL,
    id_producto        INTEGER NOT NULL,
    id_bodega          INTEGER NOT NULL,
    tipo_movimiento    VARCHAR(20) NOT NULL CHECK (tipo_movimiento IN ('entrada','salida','ajuste','transferencia')),
    referencia_tipo    VARCHAR(30),           -- 'ajuste_manual', 'carga_excel', etc.
    referencia_id      INTEGER,
    fecha_movimiento   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cantidad           NUMERIC(18,6) NOT NULL,
    costo_unitario     NUMERIC(18,6) NOT NULL DEFAULT 0,
    costo_total        NUMERIC(14,2) NOT NULL DEFAULT 0,
    stock_anterior     NUMERIC(18,6) NOT NULL DEFAULT 0,
    stock_posterior    NUMERIC(18,6) NOT NULL DEFAULT 0,
    -- Trazabilidad por movimiento (no tabla aparte)
    numero_lote        VARCHAR(100),
    fecha_fabricacion  DATE,
    fecha_caducidad    DATE,
    nup                VARCHAR(100),
    observaciones      TEXT,
    -- Auditoría
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT fk_kardex_empresa  FOREIGN KEY (id_empresa)  REFERENCES empresas(id),
    CONSTRAINT fk_kardex_producto FOREIGN KEY (id_producto) REFERENCES productos(id),
    CONSTRAINT fk_kardex_bodega   FOREIGN KEY (id_bodega)   REFERENCES bodegas(id)
);

CREATE INDEX IF NOT EXISTS idx_kardex_empresa_producto ON inventario_kardex (id_empresa, id_producto);
CREATE INDEX IF NOT EXISTS idx_kardex_fecha            ON inventario_kardex (fecha_movimiento);
CREATE INDEX IF NOT EXISTS idx_kardex_lote             ON inventario_kardex (numero_lote) WHERE numero_lote IS NOT NULL;

-- Agregar campos de trazabilidad a ventas_detalle (informativo, sin afectar stock)
ALTER TABLE ventas_detalle ADD COLUMN IF NOT EXISTS numero_lote     VARCHAR(100);
ALTER TABLE ventas_detalle ADD COLUMN IF NOT EXISTS fecha_caducidad DATE;
ALTER TABLE ventas_detalle ADD COLUMN IF NOT EXISTS nup             VARCHAR(100);
