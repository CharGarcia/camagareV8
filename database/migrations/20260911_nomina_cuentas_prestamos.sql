-- ============================================================================
-- Nómina: cuenta contable propia para las cuotas de préstamo (tipo_asiento = 'nomina')
-- ----------------------------------------------------------------------------
-- Contexto:
--   Al contabilizar el rol MENSUAL, las cuotas de préstamo descontadas al
--   empleado (novedades 7 Préstamo Quirografario, 8 Préstamo Hipotecario y
--   9 Préstamo Empresa) se sumaban dentro del concepto "Descuentos"
--   (DESCUENTOSNOMINA), mezcladas con el descuento directo, los días no
--   laborados, la retención de IR, etc.
--
--   Este archivo agrega tres conceptos al catálogo de Nómina para que cada
--   empresa les asigne su propia cuenta en Configuración Contable → Nómina
--   (en General y, si quiere, en las Reglas por Empleado):
--     PRESTAMOQUIROGRAFARIONOMINA  Préstamos Quirografarios por Pagar  pasivo  Haber
--     PRESTAMOHIPOTECARIONOMINA    Préstamos Hipotecarios por Pagar    pasivo  Haber
--     PRESTAMOEMPRESANOMINA        Préstamos Empresa por Cobrar        activo  Haber
--
--   Los tres son OPCIONALES: si una empresa no les asigna cuenta, la cuota sigue
--   yendo a "Descuentos" y su asiento sale igual que antes. Por eso este SQL y
--   el código pueden subirse en cualquier orden.
--
-- Qué hace:
--   1) Inserta los 3 conceptos en asientos_tipo (catálogo GLOBAL, sin id_empresa).
--   2) Actualiza la descripción de "Descuentos" para reflejar el cambio.
--   NO toca asientos ya generados, ni cuentas ya configuradas, ni datos de
--   ninguna empresa.
--
-- Reversible: sí.
--   Mientras ninguna empresa les haya asignado cuenta:
--     DELETE FROM asientos_tipo
--     WHERE codigo IN ('PRESTAMOQUIROGRAFARIONOMINA', 'PRESTAMOHIPOTECARIONOMINA', 'PRESTAMOEMPRESANOMINA');
--   Si alguna ya les asignó cuenta, en lugar de borrar:
--     UPDATE asientos_tipo SET eliminado = true
--     WHERE codigo IN ('PRESTAMOQUIROGRAFARIONOMINA', 'PRESTAMOHIPOTECARIONOMINA', 'PRESTAMOEMPRESANOMINA');
--   (el código vuelve a mandar esas cuotas a "Descuentos").
--
-- Idempotente: se puede ejecutar varias veces sin duplicar nada.
-- ----------------------------------------------------------------------------
-- CÓMO EJECUTARLO EN pgAdmin
--   Query Tool → pegar TODO el archivo → ejecutar (F5). Es instantáneo y no
--   bloquea a los usuarios.
-- ============================================================================

BEGIN;

-- 1) Conceptos nuevos -------------------------------------------------------
INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
SELECT v.tipo_asiento, v.referencia, v.detalle, v.codigo, v.tipo_cuenta, v.debe_haber
FROM (VALUES
    ('nomina', 'Préstamos Quirografarios por Pagar',
     'Cuota del préstamo quirografario del IESS descontada en el rol mensual; la empresa la retiene y la paga al IESS. Opcional: sin cuenta, la cuota va a Descuentos.',
     'PRESTAMOQUIROGRAFARIONOMINA', 'pasivo', 'haber'),
    ('nomina', 'Préstamos Hipotecarios por Pagar',
     'Cuota del préstamo hipotecario (IESS/BIESS) descontada en el rol mensual; la empresa la retiene y la paga. Opcional: sin cuenta, la cuota va a Descuentos.',
     'PRESTAMOHIPOTECARIONOMINA', 'pasivo', 'haber'),
    ('nomina', 'Préstamos Empresa por Cobrar',
     'Cuota de un préstamo que la empresa dio al empleado, recuperada en el rol mensual (reduce su cuenta por cobrar). Opcional: sin cuenta, la cuota va a Descuentos.',
     'PRESTAMOEMPRESANOMINA', 'activo', 'haber')
) AS v(tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = v.codigo);

-- 2) Descripción de "Descuentos" (el código y la cuenta asignada no cambian) --
UPDATE asientos_tipo
SET detalle = 'Descuento directo, días no laborados, retención IR, rubros fijos tipo descuento, descuentos ya aplicados en quincena/semana del mes y cuotas de préstamo cuyo concepto no tenga cuenta.'
WHERE tipo_asiento = 'nomina' AND codigo = 'DESCUENTOSNOMINA' AND eliminado = false;

COMMIT;

-- ============================================================================
-- COMPROBAR QUE QUEDÓ APLICADO (pegar en el Query Tool después de ejecutar)
-- ----------------------------------------------------------------------------
-- SELECT codigo, referencia, tipo_cuenta, debe_haber
-- FROM asientos_tipo
-- WHERE tipo_asiento = 'nomina'
--   AND codigo IN ('PRESTAMOQUIROGRAFARIONOMINA', 'PRESTAMOHIPOTECARIONOMINA', 'PRESTAMOEMPRESANOMINA')
--   AND eliminado = false
-- ORDER BY codigo;
-- Deben salir 3 filas.
-- ============================================================================
