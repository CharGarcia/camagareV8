-- ============================================================================
-- Fix: agrega el concepto "Sueldos por Pagar" (tipo_asiento = 'nomina'),
-- código SUELDOSPORPAGARNOMINA — catálogo global.
--
-- RolAsientoService::contabilizar() (app/Services/modulos/RolAsientoService.php)
-- ya usa este código para acreditar el neto del rol como pasivo (Debe: Gasto
-- Sueldos / Haber: Sueldos por Pagar), y AsientoBuilderService::generarAsientoEgreso()
-- ya lo usa para cancelar ese mismo pasivo cuando se paga un rol MENSUAL vía
-- Egresos. El código nunca se sembró en asientos_tipo, así que HOY:
--   1. Contabilizar cualquier rol de pago falla siempre con la excepción
--      "Configure las cuentas de nómina en Configuración Contable:
--       SUELDOSPORPAGARNOMINA."
--   2. El egreso de pago de un rol MENSUAL nunca cancela el pasivo: cae al
--      fallback (la cuenta libre del concepto "Nómina" en Ingresos/Egresos).
--
-- Con este seed, cada empresa asigna la cuenta real en Configuración Contable
-- → tipo de asiento "Nómina" → renglón "Sueldos por Pagar" (asientos_programados).
-- Idempotente.
-- ============================================================================

ALTER TABLE asientos_tipo ADD COLUMN IF NOT EXISTS debe_haber VARCHAR(10) NOT NULL DEFAULT 'debe';
ALTER TABLE asientos_tipo ADD COLUMN IF NOT EXISTS tipo_cuenta VARCHAR(20);

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
SELECT 'nomina',
       'Sueldos por Pagar',
       'Neto a pagar al empleado, ya provisionado al contabilizar el rol. Se cancela contra Bancos/Caja cuando se paga el rol MENSUAL desde Egresos.',
       'SUELDOSPORPAGARNOMINA',
       'pasivo',
       'haber'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'SUELDOSPORPAGARNOMINA');
