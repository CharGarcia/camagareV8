-- ============================================================================
-- Fecha de cobro de los cobros/pagos bancarios que NO son cheque (28-09-2026)
-- ----------------------------------------------------------------------------
-- Desde este cambio, al guardar un ingreso o egreso con una cuenta bancaria, las
-- transferencias, depósitos y débitos toman como fecha de cobro la fecha de emisión
-- del documento (ControlBancarioRules::fijarFechaCobroPagos). Solo el cheque conserva
-- su propia fecha y queda pendiente de confirmar la Fecha Banco en Control Bancario.
--
-- Este script completa los registros ANTERIORES que quedaron con fecha_cobro vacía.
-- Solo toca filas con fecha_cobro NULL (no pisa fechas ya escritas) y nunca cheques.
-- Idempotente: se puede ejecutar más de una vez.
-- ============================================================================

BEGIN;

-- Cobros (ingresos)
UPDATE ingresos_pagos ip
   SET fecha_cobro = ic.fecha_emision
  FROM ingresos_cabecera ic, empresa_formas_pago fp
 WHERE ic.id = ip.id_ingreso
   AND fp.id = ip.id_forma_cobro
   AND fp.id_banco IS NOT NULL
   AND ip.fecha_cobro IS NULL
   AND ic.fecha_emision IS NOT NULL
   AND COALESCE(UPPER(NULLIF(ip.tipo_operacion_bancaria, '')),
                CASE fp.tipo WHEN 'CHEQUE' THEN 'CHEQUE' END, '') <> 'CHEQUE'
   AND NOT EXISTS (SELECT 1 FROM control_bancario_movimientos cbm
                    WHERE cbm.origen_tipo = 'ingreso' AND cbm.origen_id = ip.id
                      AND cbm.eliminado = FALSE AND cbm.tipo_transaccion = 'CHEQUE');

-- Pagos (egresos)
UPDATE egresos_pagos ep
   SET fecha_cobro = ec.fecha_emision
  FROM egresos_cabecera ec, empresa_formas_pago fp
 WHERE ec.id = ep.id_egreso
   AND fp.id = ep.id_forma_pago
   AND fp.id_banco IS NOT NULL
   AND ep.fecha_cobro IS NULL
   AND ec.fecha_emision IS NOT NULL
   AND COALESCE(UPPER(NULLIF(ep.tipo_operacion_bancaria, '')),
                CASE fp.tipo WHEN 'CHEQUE' THEN 'CHEQUE' END, '') <> 'CHEQUE'
   AND NOT EXISTS (SELECT 1 FROM control_bancario_movimientos cbm
                    WHERE cbm.origen_tipo = 'egreso' AND cbm.origen_id = ep.id
                      AND cbm.eliminado = FALSE AND cbm.tipo_transaccion = 'CHEQUE');

COMMIT;
