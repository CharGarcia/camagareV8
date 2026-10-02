-- ============================================================
-- Control Bancario: traspasos de fondos como movimientos del banco
-- (modulos/control-bancario)
--
-- Un traspaso (traspasos_cabecera) saca dinero de una forma de pago y lo pone en
-- otra (p. ej. de Caja al Banco). Su asiento mueve la cuenta contable del banco,
-- así que el saldo de Control Bancario debe incluirlo: entra como crédito en la
-- cuenta DESTINO y como débito en la cuenta ORIGEN.
--
-- La clasificación manual del movimiento (Fecha Banco, tipo, observación) se ancla
-- a (origen_tipo, origen_id). Un mismo traspaso entre dos bancos es un movimiento
-- en cada uno, así que tiene dos anclas posibles:
--   * 'trasp_in'  + id del traspaso: la entrada en la cuenta destino.
--   * 'trasp_out' + id del traspaso: la salida de la cuenta origen.
-- (origen_tipo es VARCHAR(10): por eso no se usa 'traspaso_in'.)
--
-- Esta migración solo amplía el CHECK de origen_tipo. No toca datos.
-- Idempotente: se puede ejecutar varias veces.
-- Aplicar ANTES de desplegar el código (sin ella, clasificar un traspaso falla).
-- ============================================================

ALTER TABLE control_bancario_movimientos DROP CONSTRAINT IF EXISTS chk_cbm_origen_tipo;

ALTER TABLE control_bancario_movimientos
    ADD CONSTRAINT chk_cbm_origen_tipo
    CHECK (origen_tipo IS NULL OR origen_tipo IN ('ingreso', 'egreso', 'trasp_in', 'trasp_out'));

COMMENT ON COLUMN control_bancario_movimientos.origen_tipo IS
    'ingreso|egreso|trasp_in|trasp_out: cobro/pago (ingresos_pagos/egresos_pagos) o lado del traspaso (traspasos_cabecera) al que se ancla la anotación cuando no hay línea de asiento.';
COMMENT ON COLUMN control_bancario_movimientos.origen_id IS
    'id de ingresos_pagos / egresos_pagos / traspasos_cabecera según origen_tipo.';
