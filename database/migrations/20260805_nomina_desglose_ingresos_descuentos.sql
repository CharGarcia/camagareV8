-- ============================================================================
-- Desglosa el asiento de Nómina en cuentas más finas (tipo_asiento = 'nomina').
--
-- ANTES: "Gasto Sueldos y Salarios" (GASTOSUELDOSNOMINA) acumulaba TODO ingreso
-- del rol (sueldo + horas extra + otros ingresos + rubros fijos + vacaciones +
-- fondos + décimos mensualizados), y "Anticipos y Descuentos"
-- (ANTICIPOSDESCUENTOSNOMINA) acumulaba TODO descuento (anticipo, préstamos,
-- descuentos, IR, días no laborados, neteo de quincena/semana).
--
-- AHORA:
--   - GASTOSUELDOSNOMINA (existente, se reutiliza) → SOLO sueldo base.
--   - GASTOHORASEXTRASNOMINA (nuevo) → horas nocturnas/suplementarias/extraordinarias.
--   - INGRESOSGRAVADOSNOMINA (nuevo) → otros ingresos (rubros fijos, vacaciones)
--     que SÍ aportan a IESS.
--   - INGRESOSNOGRAVADOSNOMINA (nuevo) → otros ingresos (otros ingresos, rubros
--     fijos, fondos de reserva, décimos mensualizados) que NO aportan a IESS.
--   - ANTICIPOSDESCUENTOSNOMINA (existente, se reutiliza y se renombra a
--     "Anticipos") → anticipo de sueldo pagado + lo ya pagado en quincena/semana
--     del mes (neteo).
--   - DESCUENTOSNOMINA (nuevo) → descuento directo, préstamos (quirografario,
--     hipotecario, empresa), días no laborados, retención IR, rubros fijos tipo
--     descuento, y los descuentos ya aplicados en quincena/semana del mes.
--
-- IMPORTANTE: tras correr esto, hay que asignar cuenta contable a los 4 conceptos
-- NUEVOS en /modulos/configuracion-contable → tipo de asiento "Nómina", antes de
-- volver a contabilizar cualquier rol mensual — si falta alguna, "Contabilizar"
-- lanza el mismo error de siempre ("Configure las cuentas de nómina...") en vez
-- de contabilizar mal. Las cuentas ya asignadas a GASTOSUELDOSNOMINA y
-- ANTICIPOSDESCUENTOSNOMINA se conservan (mismo código, alcance más angosto).
-- Idempotente.
-- ============================================================================

ALTER TABLE asientos_tipo ADD COLUMN IF NOT EXISTS debe_haber VARCHAR(10) NOT NULL DEFAULT 'debe';
ALTER TABLE asientos_tipo ADD COLUMN IF NOT EXISTS tipo_cuenta VARCHAR(20);

-- Renombrar el alcance de las dos cuentas existentes (mismo id/código, no se toca
-- la cuenta ya asignada por cada empresa).
UPDATE asientos_tipo
SET referencia = 'Gasto Sueldos y Salarios (sueldo base)',
    detalle = 'Gasto por el sueldo base del rol (sin horas extra, sin otros ingresos). Antes incluía todo el ingreso del rol.'
WHERE tipo_asiento = 'nomina' AND codigo = 'GASTOSUELDOSNOMINA' AND eliminado = false;

UPDATE asientos_tipo
SET referencia = 'Anticipos',
    detalle = 'Anticipo de sueldo pagado al empleado y lo ya pagado en quincena/semana del mes (neteo). Antes incluía también los descuentos (ver DESCUENTOSNOMINA).'
WHERE tipo_asiento = 'nomina' AND codigo = 'ANTICIPOSDESCUENTOSNOMINA' AND eliminado = false;

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
SELECT v.tipo_asiento, v.referencia, v.detalle, v.codigo, v.tipo_cuenta, v.debe_haber
FROM (VALUES
    ('nomina', 'Gasto Horas Extras',
     'Gasto por horas nocturnas, suplementarias y extraordinarias del rol.',
     'GASTOHORASEXTRASNOMINA', 'gasto', 'debe'),
    ('nomina', 'Ingresos Gravados',
     'Otros ingresos del rol que SÍ aportan a IESS (rubros fijos gravados, vacaciones).',
     'INGRESOSGRAVADOSNOMINA', 'gasto', 'debe'),
    ('nomina', 'Ingresos No Gravados',
     'Otros ingresos del rol que NO aportan a IESS (otros ingresos, rubros fijos no gravados, fondos de reserva, décimo tercero/cuarto mensualizados).',
     'INGRESOSNOGRAVADOSNOMINA', 'gasto', 'debe'),
    ('nomina', 'Descuentos',
     'Descuento directo, préstamos (quirografario, hipotecario, empresa), días no laborados, retención IR, rubros fijos tipo descuento, y descuentos ya aplicados en quincena/semana del mes.',
     'DESCUENTOSNOMINA', 'pasivo', 'haber')
) AS v(tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = v.codigo);
