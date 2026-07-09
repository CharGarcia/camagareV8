-- ============================================================
-- MÓDULO: Cargas de Inventario (Documentos) — script idempotente
-- ============================================================
-- Documento operativo que agrupa una carga masiva de movimientos de inventario
-- (entrada / salida / ajuste), importada desde Excel/CSV. Afecta el kardex SOLO
-- al ser aprobada (si la config del establecimiento exige aprobación).
--
--   inventario_cargas          → cabecera (número interno, tipo, estado, aprobación)
--   inventario_cargas_detalle  → líneas (producto, bodega, cantidad, costo, lote)
-- ============================================================

CREATE TABLE IF NOT EXISTS inventario_cargas (
    id                 SERIAL PRIMARY KEY,
    id_empresa         INTEGER      NOT NULL,
    numero             INTEGER      NOT NULL,               -- correlativo interno por empresa
    fecha              DATE         NOT NULL DEFAULT CURRENT_DATE,
    tipo_movimiento    VARCHAR(20)  NOT NULL DEFAULT 'entrada'
                                    CHECK (tipo_movimiento IN ('entrada', 'salida', 'ajuste')),
    observacion        TEXT,

    estado             VARCHAR(20)  NOT NULL DEFAULT 'pendiente'
                                    CHECK (estado IN ('pendiente', 'aprobada', 'rechazada')),
    -- Validación: la carga solo puede aprobarse si está comprobada (todas las líneas OK).
    validada           BOOLEAN      NOT NULL DEFAULT false,
    errores_validacion TEXT,

    aprobada_por       INTEGER,
    aprobada_at        TIMESTAMP,
    motivo_rechazo     TEXT,

    total_lineas       INTEGER      NOT NULL DEFAULT 0,

    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

CREATE INDEX IF NOT EXISTS idx_inv_cargas_empresa ON inventario_cargas (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_inv_cargas_estado  ON inventario_cargas (estado, id_empresa);

CREATE TABLE IF NOT EXISTS inventario_cargas_detalle (
    id               SERIAL PRIMARY KEY,
    id_carga         INTEGER        NOT NULL REFERENCES inventario_cargas(id) ON DELETE CASCADE,
    id_empresa       INTEGER        NOT NULL,
    id_producto      INTEGER,
    id_bodega        INTEGER,
    cantidad         NUMERIC(18,6)  NOT NULL DEFAULT 0,
    costo_unitario   NUMERIC(18,6)  NOT NULL DEFAULT 0,
    numero_lote      VARCHAR(100),
    fecha_caducidad  DATE,
    -- Resultado de la validación de esta línea (para "comprobar" antes de aprobar).
    linea_valida     BOOLEAN        NOT NULL DEFAULT false,
    error_linea      VARCHAR(300),
    -- Datos crudos del archivo para trazabilidad / re-validación.
    cod_producto_raw VARCHAR(100),
    cod_bodega_raw   VARCHAR(100),

    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

CREATE INDEX IF NOT EXISTS idx_inv_cargas_det_carga ON inventario_cargas_detalle (id_carga, eliminado);
