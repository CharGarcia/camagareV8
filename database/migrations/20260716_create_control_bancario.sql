-- ============================================================
-- Módulo: Control Bancario (modulos/control-bancario)
-- Tabla de anotación/clasificación 1:1 opcional sobre líneas de
-- asientos_contables_detalle que afectan una cuenta bancaria
-- (empresa_formas_pago con id_banco + id_cuenta_contable).
-- No destructiva: no modifica asientos_contables, ingresos_pagos
-- ni egresos_pagos. Idempotente.
-- ============================================================

CREATE TABLE IF NOT EXISTS control_bancario_movimientos (
    id                 SERIAL PRIMARY KEY,
    id_empresa         INTEGER NOT NULL REFERENCES empresas(id),
    id_asiento_detalle INTEGER NOT NULL UNIQUE REFERENCES asientos_contables_detalle(id),
    id_forma_pago      INTEGER NOT NULL REFERENCES empresa_formas_pago(id),

    tipo_transaccion   VARCHAR(20) NOT NULL DEFAULT 'OTRO'
        CHECK (tipo_transaccion IN ('DEPOSITO','CHEQUE','TRANSFERENCIA','NOTA_DEBITO','NOTA_CREDITO','OTRO')),
    cheque_direccion   VARCHAR(10) NULL
        CHECK (cheque_direccion IS NULL OR cheque_direccion IN ('EMITIDO','RECIBIDO')),
    numero_cheque      VARCHAR(50) NULL,
    fecha_cheque       DATE NULL,   -- fecha impresa en el cheque; "posfechado" = fecha_cheque > CURRENT_DATE
    fecha_banco        DATE NULL,   -- fecha real de proceso bancario (conciliación), editable
    observacion        TEXT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado  BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

CREATE INDEX IF NOT EXISTS idx_cbm_empresa ON control_bancario_movimientos(id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_cbm_forma   ON control_bancario_movimientos(id_forma_pago, eliminado);
CREATE INDEX IF NOT EXISTS idx_cbm_fecha_cheque ON control_bancario_movimientos(fecha_cheque) WHERE tipo_transaccion = 'CHEQUE';
