-- ============================================================================
-- Resumen Diario: saldos de apertura por forma de pago y traslados entre formas
-- ============================================================================
-- QUÉ HACE    Crea dos tablas operativas (por empresa) que usa el módulo
--             Resumen Diario (modulos/reporte_resumen_diario):
--             - caja_saldos_apertura: el saldo con que arranca cada forma de pago
--               en una fecha ("al 01-10-2026 había $500 en efectivo"). Desde esa
--               fecha el saldo de la forma se acumula día a día con los Ingresos,
--               Egresos y traslados. Una apertura vigente por forma de pago.
--             - caja_traslados: dinero que pasa de una forma de pago a otra sin
--               ser ingreso ni egreso (depositar el efectivo en el banco, retirar
--               del banco a caja). Resta de la forma origen y suma a la destino.
--             Ninguna de las dos genera asientos contables.
-- TOCA DATOS  No. Tablas nuevas, vacías.
-- ORDEN       Aplicar ANTES de desplegar el código. Si el código llega primero el
--             resumen sigue funcionando: sin las tablas, el saldo se acumula desde
--             el primer Ingreso/Egreso y no se pueden registrar traslados ni
--             aperturas (avisa que falta este SQL).
-- REVERSIBLE  Sí:  DROP TABLE IF EXISTS caja_traslados;
--                  DROP TABLE IF EXISTS caja_saldos_apertura;
-- USO         pgAdmin → Query Tool → F5. Idempotente.
-- ============================================================================

CREATE TABLE IF NOT EXISTS caja_saldos_apertura (
    id             SERIAL PRIMARY KEY,
    id_empresa     INTEGER       NOT NULL REFERENCES empresas(id),
    id_forma_pago  INTEGER       NOT NULL REFERENCES empresa_formas_pago(id),
    fecha          DATE          NOT NULL,          -- saldo al INICIO de este día
    valor          NUMERIC(14,2) NOT NULL DEFAULT 0,
    observaciones  VARCHAR(300),
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP,
    created_by     INTEGER,
    updated_by     INTEGER,
    eliminado      BOOLEAN       NOT NULL DEFAULT false,
    deleted_at     TIMESTAMP,
    deleted_by     INTEGER
);

-- Una sola apertura vigente por forma de pago.
CREATE UNIQUE INDEX IF NOT EXISTS ux_caja_saldos_apertura_forma
    ON caja_saldos_apertura (id_empresa, id_forma_pago) WHERE eliminado = false;

CREATE TABLE IF NOT EXISTS caja_traslados (
    id                SERIAL PRIMARY KEY,
    id_empresa        INTEGER       NOT NULL REFERENCES empresas(id),
    fecha             DATE          NOT NULL,
    id_forma_origen   INTEGER       NOT NULL REFERENCES empresa_formas_pago(id),
    id_forma_destino  INTEGER       NOT NULL REFERENCES empresa_formas_pago(id),
    valor             NUMERIC(14,2) NOT NULL CHECK (valor > 0),
    observaciones     VARCHAR(300),
    created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP,
    created_by        INTEGER,
    updated_by        INTEGER,
    eliminado         BOOLEAN       NOT NULL DEFAULT false,
    deleted_at        TIMESTAMP,
    deleted_by        INTEGER,
    CONSTRAINT ck_caja_traslados_formas CHECK (id_forma_origen <> id_forma_destino)
);

CREATE INDEX IF NOT EXISTS ix_caja_traslados_empresa_fecha
    ON caja_traslados (id_empresa, fecha) WHERE eliminado = false;

-- Comprobación (deben salir 2 filas):
-- SELECT table_name FROM information_schema.tables
--  WHERE table_schema = 'public' AND table_name IN ('caja_saldos_apertura', 'caja_traslados');
