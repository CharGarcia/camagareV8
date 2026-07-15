-- ============================================================
-- Módulo: Control Bancario — Conciliaciones (bloqueo de período)
-- Al conciliar un período contra el estado de cuenta del banco, se
-- registra aquí para impedir que se reclasifiquen/desclasifiquen
-- movimientos dentro de ese rango y así el saldo no se descuadre
-- después. "Reabrir" una conciliación = marcarla eliminado=true
-- (no se borra el historial, solo se desactiva el bloqueo).
-- No destructiva: no modifica asientos_contables ni otras tablas.
-- ============================================================

CREATE TABLE IF NOT EXISTS control_bancario_conciliaciones (
    id               SERIAL PRIMARY KEY,
    id_empresa       INTEGER NOT NULL REFERENCES empresas(id),
    id_forma_pago    INTEGER NOT NULL REFERENCES empresa_formas_pago(id),
    fecha_inicio     DATE NOT NULL,
    fecha_fin        DATE NOT NULL CHECK (fecha_fin >= fecha_inicio),
    saldo_inicial    NUMERIC(14,2) NOT NULL DEFAULT 0,
    saldo_final      NUMERIC(14,2) NOT NULL DEFAULT 0,  -- saldo final del sistema al momento de conciliar
    saldo_banco      NUMERIC(14,2) NULL,                -- saldo del estado de cuenta bancario (informativo)
    observaciones    TEXT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado  BOOLEAN NOT NULL DEFAULT FALSE,  -- true = conciliación reabierta (ya no bloquea)
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

CREATE INDEX IF NOT EXISTS idx_cbc_forma_rango ON control_bancario_conciliaciones(id_forma_pago, fecha_inicio, fecha_fin) WHERE eliminado = FALSE;
