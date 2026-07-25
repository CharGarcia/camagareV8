-- ============================================================
-- Proveedores › pestaña Pagos
-- Rango de monto para auto generar el pago (egreso) de una compra.
--
--   monto_minimo_auto_pago  → solo se auto genera el pago si el total
--                             del documento es MAYOR O IGUAL a este valor.
--   monto_maximo_auto_pago  → (ya existía) solo se auto genera si el total
--                             es MENOR O IGUAL a este valor.
--
-- Ambos son opcionales: NULL o 0 significa "sin restricción" por ese lado,
-- por lo que se puede configurar solo "mayor a", solo "menor a" o un rango.
--
-- Idempotente y no destructiva.
-- ============================================================

ALTER TABLE proveedores
    ADD COLUMN IF NOT EXISTS monto_minimo_auto_pago NUMERIC(14,2) NULL;

COMMENT ON COLUMN proveedores.monto_minimo_auto_pago IS
    'Monto mínimo (mayor o igual a) del documento para auto generar el pago. NULL/0 = sin restricción.';

COMMENT ON COLUMN proveedores.monto_maximo_auto_pago IS
    'Monto máximo (menor o igual a) del documento para auto generar el pago. NULL/0 = sin restricción.';
