-- ============================================================================
-- Conceptos de asiento para NÓMINA (tipo_asiento = 'nomina') — catálogo global.
-- Habilitan la sección "Nómina" en Configuración Contable para que cada empresa
-- asigne la cuenta real (plan_cuentas) a cada renglón vía asientos_programados.
-- El asiento del rol cuadra por construcción (débitos = créditos).
-- ============================================================================

ALTER TABLE asientos_tipo ADD COLUMN IF NOT EXISTS debe_haber VARCHAR(10) NOT NULL DEFAULT 'debe';
ALTER TABLE asientos_tipo ADD COLUMN IF NOT EXISTS tipo_cuenta VARCHAR(20);

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
SELECT v.tipo_asiento, v.referencia, v.detalle, v.codigo, v.tipo_cuenta, v.debe_haber
FROM (VALUES
    -- DEBE (gastos)
    ('nomina', 'Gasto Sueldos y Salarios',   'Gasto por las remuneraciones (ingresos) del rol.',                 'GASTOSUELDOSNOMINA',          'gasto',  'debe'),
    ('nomina', 'Gasto Aporte Patronal IESS',  'Gasto por el aporte patronal al IESS.',                            'GASTOAPORTEPATRONALNOMINA',   'gasto',  'debe'),
    ('nomina', 'Gasto Décimo Tercero',        'Provisión mensual del décimo tercero (gasto).',                    'GASTODECIMOTERCERONOMINA',    'gasto',  'debe'),
    ('nomina', 'Gasto Décimo Cuarto',         'Provisión mensual del décimo cuarto (gasto).',                     'GASTODECIMOCUARTONOMINA',     'gasto',  'debe'),
    ('nomina', 'Gasto Vacaciones',            'Provisión mensual de vacaciones (gasto).',                         'GASTOVACACIONESNOMINA',       'gasto',  'debe'),
    ('nomina', 'Gasto Fondos de Reserva',     'Provisión / gasto de fondos de reserva.',                          'GASTOFONDOSRESERVANOMINA',    'gasto',  'debe'),
    ('nomina', 'Gasto Desahucio',             'Provisión mensual del desahucio (gasto).',                         'GASTODESAHUCIONOMINA',        'gasto',  'debe'),
    -- HABER (pasivos / cuentas por pagar-cobrar)
    ('nomina', 'IESS por Pagar',              'Aporte personal + patronal del IESS por pagar.',                   'IESSPORPAGARNOMINA',          'pasivo', 'haber'),
    ('nomina', 'Décimo Tercero por Pagar',    'Provisión del décimo tercero por pagar.',                          'DECIMOTERCEROPORPAGARNOMINA', 'pasivo', 'haber'),
    ('nomina', 'Décimo Cuarto por Pagar',     'Provisión del décimo cuarto por pagar.',                           'DECIMOCUARTOPORPAGARNOMINA',  'pasivo', 'haber'),
    ('nomina', 'Vacaciones por Pagar',        'Provisión de vacaciones por pagar.',                               'VACACIONESPORPAGARNOMINA',    'pasivo', 'haber'),
    ('nomina', 'Fondos de Reserva por Pagar', 'Fondos de reserva por pagar.',                                     'FONDOSRESERVAPORPAGARNOMINA', 'pasivo', 'haber'),
    ('nomina', 'Desahucio por Pagar',         'Provisión del desahucio por pagar.',                               'DESAHUCIOPORPAGARNOMINA',     'pasivo', 'haber'),
    ('nomina', 'Anticipos y Descuentos',      'Anticipos, préstamos y descuentos recuperados del empleado.',      'ANTICIPOSDESCUENTOSNOMINA',   'activo', 'haber'),
    ('nomina', 'Bancos / Líquido a Pagar',    'Líquido a pagar al empleado (banco o caja).',                      'BANCOSNOMINA',                'activo', 'haber')
) AS v(tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = v.codigo);
