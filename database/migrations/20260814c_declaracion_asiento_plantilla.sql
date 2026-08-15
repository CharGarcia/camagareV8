-- =============================================================================
-- Plantilla sugerida de cuentas por casillero, para el asiento de Declaración de
-- IVA / Declaración de Retenciones.
-- ----------------------------------------------------------------------------
-- Reemplaza el mecanismo anterior (asientos_programados por concepto
-- 'declaracion_iva'/'declaracion_retenciones', con 6 líneas fijas de
-- AsientoBuilderService::generarAsientoDeclaracionIva()/Retenciones): ahora el
-- asiento se arma con una línea sugerida POR CASILLERO con valor (la "tercera
-- columna" del Formulario 104, o el valor de F103), y el usuario elige/ajusta
-- la cuenta contable de cada línea en el modal estándar de Asientos Contables.
-- Cada vez que el usuario guarda el asiento, la cuenta elegida por casillero se
-- guarda aquí como sugerencia (upsert) para precargar el próximo período.
-- =============================================================================

BEGIN;

CREATE TABLE IF NOT EXISTS declaracion_asiento_plantilla (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INT NOT NULL,
    tipo_declaracion    VARCHAR(20) NOT NULL, -- 'iva' | 'retenciones'
    casillero           VARCHAR(10) NOT NULL,
    id_cuenta_contable  INT NOT NULL,
    lado                VARCHAR(5) NOT NULL DEFAULT 'debe', -- 'debe' | 'haber'
    eliminado           BOOLEAN NOT NULL DEFAULT false,
    created_at          TIMESTAMP NOT NULL DEFAULT now(),
    updated_at          TIMESTAMP NOT NULL DEFAULT now(),
    created_by          INT NULL,
    updated_by          INT NULL,
    deleted_at          TIMESTAMP NULL,
    deleted_by          INT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_declaracion_asiento_plantilla
    ON declaracion_asiento_plantilla (id_empresa, tipo_declaracion, casillero)
    WHERE eliminado = false;

COMMIT;
