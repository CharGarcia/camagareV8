-- ============================================================================
-- Roles de Pago: empleado con valor a pagar EN CONTRA (neto negativo)
-- ----------------------------------------------------------------------------
-- Contexto:
--   Cuando en el rol MENSUAL un empleado sale en contra (sus descuentos superan
--   lo que gana), el sistema registra solo una novedad de DESCUENTO en el mes
--   siguiente por ese valor, y la mantiene al día: si el rol cambia, actualiza el
--   valor; si el empleado deja de salir en contra (o el rol se anula/elimina),
--   la elimina.
--
--   En el asiento:
--     mes en contra  → Debe  "Saldo en contra de empleados" (cuenta por cobrar)
--     mes siguiente  → Haber "Saldo en contra de empleados" (al descontarlo)
--   Si la empresa no asigna cuenta a este concepto se usa la de "Préstamos
--   Empresa por Cobrar" y, si tampoco tiene, la de "Descuentos".
--
-- Qué hace:
--   1) novedades.id_rol_origen: rol mensual que generó el descuento (NULL en
--      las novedades normales). Es la marca de "descuento automático".
--   2) Concepto de nómina SALDOCONTRANOMINA en asientos_tipo (catálogo global).
--   NO modifica novedades, roles ni asientos existentes.
--
-- Sin este SQL el código no registra los descuentos automáticos (solo avisa) y
-- el rol con un empleado en contra no se contabiliza. Por eso puede subirse el
-- código antes o después; lo ideal es aplicar el SQL primero.
--
-- Reversible:
--   UPDATE asientos_tipo SET eliminado = true WHERE codigo = 'SALDOCONTRANOMINA';
--   ALTER TABLE novedades DROP COLUMN id_rol_origen;  -- solo si no se usa
--
-- Idempotente: se puede ejecutar varias veces.
-- CÓMO EJECUTARLO EN pgAdmin: Query Tool → pegar TODO el archivo → F5.
-- ============================================================================

BEGIN;

ALTER TABLE novedades ADD COLUMN IF NOT EXISTS id_rol_origen INTEGER;

CREATE INDEX IF NOT EXISTS idx_novedades_rol_origen
    ON novedades (id_empresa, id_rol_origen)
    WHERE id_rol_origen IS NOT NULL AND eliminado = false;

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
SELECT 'nomina', 'Saldo en contra de empleados por cobrar',
       'Valor que el empleado queda debiendo cuando su rol mensual sale en contra (neto negativo): Debe en ese mes, Haber en el mes siguiente cuando se le descuenta. Opcional: sin cuenta se usa la de Préstamos Empresa por Cobrar o, en su defecto, Descuentos.',
       'SALDOCONTRANOMINA', 'activo', 'debe'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'SALDOCONTRANOMINA');

COMMIT;

-- ============================================================================
-- COMPROBAR
-- SELECT column_name FROM information_schema.columns
--  WHERE table_name = 'novedades' AND column_name = 'id_rol_origen';   -- 1 fila
-- SELECT codigo, referencia FROM asientos_tipo WHERE codigo = 'SALDOCONTRANOMINA'; -- 1 fila
-- ============================================================================
