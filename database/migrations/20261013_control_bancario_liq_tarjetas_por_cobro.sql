-- ============================================================
-- Control Bancario: liquidaciones de tarjetas COBRO POR COBRO
-- (modulos/control-bancario + modulos/conciliacion-tarjetas)
--
-- Al cerrar una Conciliación de Tarjetas, lo depositado ya no se registra en el
-- banco destino como un solo movimiento consolidado sino como un movimiento por
-- cada cobro (línea cruzada del estado de cuenta), para que coincida con el
-- estado de cuenta de la tarjeta que emite el banco.
--
-- En cuentas sin contabilidad, la clasificación manual (Fecha Banco, tipo,
-- observación) de cada uno de esos movimientos se ancla a:
--   * 'liq_tarj_l' + id de conciliacion_tarjetas_lineas.
-- (origen_tipo es VARCHAR(10): 'liq_tarj_l' tiene exactamente 10 caracteres.)
-- Las anotaciones anteriores sobre la liquidación entera ('liq_tarj' + id de la
-- conciliación) se conservan y se heredan como respaldo en cada cobro.
--
-- Esta migración solo amplía el CHECK de origen_tipo. No toca datos.
-- Idempotente: se puede ejecutar varias veces.
-- Aplicar ANTES de desplegar el código (sin ella, clasificar un cobro de una
-- liquidación de tarjetas en Control Bancario falla).
-- ============================================================

ALTER TABLE control_bancario_movimientos DROP CONSTRAINT IF EXISTS chk_cbm_origen_tipo;

ALTER TABLE control_bancario_movimientos
    ADD CONSTRAINT chk_cbm_origen_tipo
    CHECK (origen_tipo IS NULL OR origen_tipo IN ('ingreso', 'egreso', 'trasp_in', 'trasp_out', 'liq_tarj', 'liq_tarj_l'));

COMMENT ON COLUMN control_bancario_movimientos.origen_tipo IS
    'ingreso|egreso|trasp_in|trasp_out|liq_tarj|liq_tarj_l: cobro/pago (ingresos_pagos/egresos_pagos), lado del traspaso (traspasos_cabecera), liquidación de tarjetas entera (conciliacion_tarjetas_cabecera, anotaciones antiguas) o un cobro de esa liquidación (conciliacion_tarjetas_lineas) al que se ancla la anotación cuando no hay línea de asiento.';
COMMENT ON COLUMN control_bancario_movimientos.origen_id IS
    'id de ingresos_pagos / egresos_pagos / traspasos_cabecera / conciliacion_tarjetas_cabecera / conciliacion_tarjetas_lineas según origen_tipo.';
