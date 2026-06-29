-- =============================================================
-- MÓDULO: Saldos Iniciales — Anticipos
-- Saldo inicial de anticipos atado SIEMPRE a un cliente o proveedor.
-- La forma de pago (tipo ANTICIPO) define la dirección:
--   aplica_en='INGRESO' -> anticipos de clientes
--   aplica_en='EGRESO'  -> anticipos a proveedores
-- =============================================================

CREATE TABLE IF NOT EXISTS saldos_iniciales_anticipos (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL,
    id_forma_pago   INTEGER NOT NULL,
    tipo            VARCHAR(10) NOT NULL CHECK (tipo IN ('CLIENTE','PROVEEDOR')),
    id_cliente      INTEGER,
    id_proveedor    INTEGER,
    nombre_tercero  VARCHAR(255) NOT NULL,
    ruc_tercero     VARCHAR(20),
    fecha_saldo     DATE NOT NULL,
    saldo_inicial   NUMERIC(14,2) NOT NULL DEFAULT 0,
    observaciones   TEXT,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by      INTEGER,
    updated_by      INTEGER,
    eliminado       BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER
);
CREATE INDEX IF NOT EXISTS idx_saldos_anticipos_empresa   ON saldos_iniciales_anticipos(id_empresa, eliminado, tipo);
CREATE INDEX IF NOT EXISTS idx_saldos_anticipos_cliente   ON saldos_iniciales_anticipos(id_cliente);
CREATE INDEX IF NOT EXISTS idx_saldos_anticipos_proveedor ON saldos_iniciales_anticipos(id_proveedor);
