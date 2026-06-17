-- =============================================================
-- MÓDULO: Saldos Iniciales
-- Permite registrar saldos de apertura al migrar al sistema
-- =============================================================

-- Lotes de carga (rastrea cada importación Excel)
CREATE TABLE IF NOT EXISTS saldos_iniciales_lotes (
    id               SERIAL PRIMARY KEY,
    id_empresa       INTEGER NOT NULL,
    tipo             VARCHAR(10) NOT NULL CHECK (tipo IN ('CXC','CXP')),
    nombre_archivo   VARCHAR(255),
    total_registros  INTEGER NOT NULL DEFAULT 0,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by       INTEGER,
    eliminado        BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at       TIMESTAMP,
    deleted_by       INTEGER
);
CREATE INDEX IF NOT EXISTS idx_saldos_lotes_empresa ON saldos_iniciales_lotes(id_empresa, tipo);

-- Saldos iniciales CXC (facturas de venta pendientes de cobro)
CREATE TABLE IF NOT EXISTS saldos_iniciales_cxc (
    id               SERIAL PRIMARY KEY,
    id_empresa       INTEGER NOT NULL,
    id_lote          INTEGER REFERENCES saldos_iniciales_lotes(id),
    nro_documento    VARCHAR(50) NOT NULL,
    fecha_emision    DATE NOT NULL,
    fecha_vencimiento DATE,
    id_cliente       INTEGER,
    nombre_cliente   VARCHAR(255) NOT NULL,
    ruc_cliente      VARCHAR(20),
    saldo_inicial    NUMERIC(14,2) NOT NULL DEFAULT 0,
    monto_cobrado    NUMERIC(14,2) NOT NULL DEFAULT 0,
    saldo_pendiente  NUMERIC(14,2) NOT NULL DEFAULT 0,
    estado           VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE'
                         CHECK (estado IN ('PENDIENTE','PARCIAL','PAGADO')),
    observaciones    TEXT,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by       INTEGER,
    updated_by       INTEGER,
    eliminado        BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at       TIMESTAMP,
    deleted_by       INTEGER
);
CREATE INDEX IF NOT EXISTS idx_saldos_cxc_empresa ON saldos_iniciales_cxc(id_empresa, eliminado, estado);
CREATE INDEX IF NOT EXISTS idx_saldos_cxc_lote    ON saldos_iniciales_cxc(id_lote);

-- Saldos iniciales CXP (facturas de compra, liquidaciones, NC, ND)
CREATE TABLE IF NOT EXISTS saldos_iniciales_cxp (
    id               SERIAL PRIMARY KEY,
    id_empresa       INTEGER NOT NULL,
    id_lote          INTEGER REFERENCES saldos_iniciales_lotes(id),
    tipo_documento   VARCHAR(30) NOT NULL DEFAULT 'FACTURA_COMPRA'
                         CHECK (tipo_documento IN ('FACTURA_COMPRA','LIQUIDACION','NOTA_CREDITO','NOTA_DEBITO')),
    nro_documento    VARCHAR(50) NOT NULL,
    fecha_emision    DATE NOT NULL,
    fecha_vencimiento DATE,
    id_proveedor     INTEGER,
    nombre_proveedor VARCHAR(255) NOT NULL,
    ruc_proveedor    VARCHAR(20),
    saldo_inicial    NUMERIC(14,2) NOT NULL DEFAULT 0,
    monto_pagado     NUMERIC(14,2) NOT NULL DEFAULT 0,
    saldo_pendiente  NUMERIC(14,2) NOT NULL DEFAULT 0,
    estado           VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE'
                         CHECK (estado IN ('PENDIENTE','PARCIAL','PAGADO')),
    observaciones    TEXT,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by       INTEGER,
    updated_by       INTEGER,
    eliminado        BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at       TIMESTAMP,
    deleted_by       INTEGER
);
CREATE INDEX IF NOT EXISTS idx_saldos_cxp_empresa ON saldos_iniciales_cxp(id_empresa, eliminado, estado);
CREATE INDEX IF NOT EXISTS idx_saldos_cxp_lote    ON saldos_iniciales_cxp(id_lote);

-- Saldos iniciales de Bancos y Tarjetas
-- Una fila por cuenta por empresa (UPSERT al guardar)
CREATE TABLE IF NOT EXISTS saldos_iniciales_bancos (
    id               SERIAL PRIMARY KEY,
    id_empresa       INTEGER NOT NULL,
    id_forma_pago    INTEGER NOT NULL,
    fecha_saldo      DATE NOT NULL,
    saldo_inicial    NUMERIC(14,2) NOT NULL DEFAULT 0,
    observaciones    TEXT,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by       INTEGER,
    updated_by       INTEGER,
    eliminado        BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at       TIMESTAMP,
    deleted_by       INTEGER,
    UNIQUE (id_empresa, id_forma_pago)
);
CREATE INDEX IF NOT EXISTS idx_saldos_bancos_empresa ON saldos_iniciales_bancos(id_empresa, eliminado);
