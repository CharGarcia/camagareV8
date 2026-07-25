-- ============================================================================
-- Módulo: Impresión de Cheques
-- Tabla de control de cheques impresos (registro de cada impresión / reimpresión)
-- para que el usuario no se equivoque e imprima dos veces por error.
--
-- Un cheque = un pago de egreso con tipo_operacion_bancaria = 'CHEQUE'
-- (tabla egresos_pagos). Cada vez que se imprime se inserta una fila aquí.
-- La primera impresión: es_reimpresion = FALSE. Las siguientes: TRUE.
-- La tabla también se autocrea en runtime desde ChequeRepository (defensivo);
-- este SQL es la versión canónica para desplegar en producción.
-- ============================================================================

CREATE TABLE IF NOT EXISTS cheques_impresos (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER      NOT NULL,
    id_egreso_pago      INTEGER      NOT NULL,          -- egresos_pagos.id (el cheque)
    id_egreso           INTEGER,                        -- egresos_cabecera.id (conveniencia)
    id_forma_pago       INTEGER,                        -- empresa_formas_pago.id (cuenta/banco)
    numero_cheque       VARCHAR(50),
    beneficiario        VARCHAR(255),
    beneficiario_ident  VARCHAR(50),
    monto               NUMERIC(18,6) DEFAULT 0,
    fecha_cheque        DATE,                           -- fecha de cobro del cheque
    banco_nombre        VARCHAR(150),
    cuenta_numero       VARCHAR(50),
    es_reimpresion      BOOLEAN      NOT NULL DEFAULT FALSE,
    fecha_impresion     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    impreso_por         INTEGER,
    anulado             BOOLEAN      NOT NULL DEFAULT FALSE,
    anulado_at          TIMESTAMP,
    anulado_by          INTEGER,
    eliminado           BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

-- Listado por empresa
CREATE INDEX IF NOT EXISTS idx_cheques_impresos_empresa
    ON cheques_impresos (id_empresa, eliminado);

-- Búsqueda de "¿ya se imprimió este pago?" (solo impresiones vigentes)
CREATE INDEX IF NOT EXISTS idx_cheques_impresos_pago
    ON cheques_impresos (id_egreso_pago)
    WHERE anulado = FALSE AND eliminado = FALSE;

COMMENT ON TABLE  cheques_impresos IS 'Registro de impresiones de cheques (egresos_pagos tipo CHEQUE). Control anti-error de reimpresión.';
COMMENT ON COLUMN cheques_impresos.es_reimpresion IS 'FALSE = primera impresión del cheque; TRUE = reimpresión posterior.';
COMMENT ON COLUMN cheques_impresos.anulado IS 'Marca una impresión como anulada (p. ej. cheque dañado); no cuenta como impreso vigente.';
