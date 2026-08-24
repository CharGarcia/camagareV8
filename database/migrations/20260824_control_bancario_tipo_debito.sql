-- ============================================================
-- Control Bancario: aceptar el tipo DEBITO en la clasificación
-- (modulos/control-bancario)
--
-- Un egreso puede registrarse con Operación Bancaria = "Débito". Al conciliar
-- ese movimiento, el tipo llega tal cual desde el documento (el usuario ya no
-- lo elige a mano en el modal), y el CHECK original no lo contemplaba, así que
-- el guardado fallaba.
--
-- No destructiva e idempotente: solo amplía la lista de valores permitidos.
-- ============================================================

ALTER TABLE control_bancario_movimientos
    DROP CONSTRAINT IF EXISTS control_bancario_movimientos_tipo_transaccion_check;

ALTER TABLE control_bancario_movimientos
    ADD CONSTRAINT control_bancario_movimientos_tipo_transaccion_check
    CHECK (tipo_transaccion IN ('DEPOSITO','CHEQUE','TRANSFERENCIA','DEBITO','NOTA_DEBITO','NOTA_CREDITO','OTRO'));
