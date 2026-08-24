-- ============================================================
-- Control Bancario sin contabilidad
-- (modulos/control-bancario)
--
-- Hay empresas que NO llevan contabilidad pero sí controlan su cuenta
-- bancaria. En ellas la forma de pago bancaria no tiene id_cuenta_contable,
-- por lo que no existe ninguna línea de asiento a la cual anclar la
-- clasificación manual del movimiento (tipo, Nº de cheque, fecha banco).
--
-- Esta migración permite que la anotación se ancle, alternativamente, al
-- COBRO/PAGO de origen (ingresos_pagos / egresos_pagos):
--   * id_asiento_detalle pasa a ser opcional.
--   * origen_tipo + origen_id identifican la fila de pago cuando no hay asiento.
--   * un CHECK garantiza que siempre exista uno de los dos anclajes.
--
-- No destructiva e idempotente: no toca datos existentes (las filas actuales
-- conservan su id_asiento_detalle y quedan con origen_tipo/origen_id en NULL).
-- ============================================================

ALTER TABLE control_bancario_movimientos
    ALTER COLUMN id_asiento_detalle DROP NOT NULL;

ALTER TABLE control_bancario_movimientos
    ADD COLUMN IF NOT EXISTS origen_tipo VARCHAR(10) NULL;

ALTER TABLE control_bancario_movimientos
    ADD COLUMN IF NOT EXISTS origen_id INTEGER NULL;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_cbm_origen_tipo') THEN
        ALTER TABLE control_bancario_movimientos
            ADD CONSTRAINT chk_cbm_origen_tipo
            CHECK (origen_tipo IS NULL OR origen_tipo IN ('ingreso', 'egreso'));
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_cbm_ancla') THEN
        ALTER TABLE control_bancario_movimientos
            ADD CONSTRAINT chk_cbm_ancla
            CHECK (id_asiento_detalle IS NOT NULL
                   OR (origen_tipo IS NOT NULL AND origen_id IS NOT NULL));
    END IF;
END $$;

-- 1:1 con la fila de pago de origen (mismo criterio que la UNIQUE de
-- id_asiento_detalle: no filtra por eliminado, para que reclasificar un
-- movimiento reviva la fila en vez de duplicarla).
CREATE UNIQUE INDEX IF NOT EXISTS ux_cbm_origen
    ON control_bancario_movimientos (origen_tipo, origen_id)
    WHERE origen_tipo IS NOT NULL;

COMMENT ON COLUMN control_bancario_movimientos.origen_tipo IS
    'ingreso|egreso: tabla del cobro/pago al que se ancla la anotación cuando la cuenta no tiene cuenta contable (sin asiento).';
COMMENT ON COLUMN control_bancario_movimientos.origen_id IS
    'id de ingresos_pagos / egresos_pagos según origen_tipo.';
