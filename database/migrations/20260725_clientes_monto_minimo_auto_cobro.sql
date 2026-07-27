-- ============================================================
-- Clientes › pestaña Cobros
-- Rango de monto para auto generar el cobro (ingreso) de una venta.
--
--   monto_minimo_auto_cobro → solo se auto genera el cobro si el saldo del
--                             documento es MAYOR O IGUAL a este valor.
--   monto_maximo_auto_cobro → (ya existía) solo se auto genera si el saldo
--                             es MENOR O IGUAL a este valor.
--
-- Ambos opcionales: NULL o 0 = sin restricción por ese lado, de modo que se
-- puede configurar solo "mayor a", solo "menor a", o un rango cerrado.
--
-- Espejo de 20260725_proveedores_monto_minimo_auto_pago.sql.
-- Idempotente y no destructiva.
-- ============================================================

ALTER TABLE clientes
    ADD COLUMN IF NOT EXISTS monto_minimo_auto_cobro NUMERIC(14,2) NULL;

COMMENT ON COLUMN clientes.monto_minimo_auto_cobro IS
    'Monto mínimo (mayor o igual a) del documento para auto generar el cobro. NULL/0 = sin restricción.';

COMMENT ON COLUMN clientes.monto_maximo_auto_cobro IS
    'Monto máximo (menor o igual a) del documento para auto generar el cobro. NULL/0 = sin restricción.';
