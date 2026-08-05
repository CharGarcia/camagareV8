-- ============================================================================
-- Elimina (soft-delete) el concepto "Bancos / Líquido a Pagar" (BANCOSNOMINA)
-- del catálogo global asientos_tipo (tipo_asiento = 'nomina').
--
-- Es un residuo del diseño anterior de contabilización de rol (Debe Gasto /
-- Haber Banco directo, sin devengo). Desde que el rol mensual se contabiliza en
-- base devengado (RolAsientoService::contabilizar()), el neto queda como pasivo
-- en "Sueldos por Pagar" (SUELDOSPORPAGARNOMINA) y el banco/caja real se resuelve
-- después, al pagar, con la Forma de Pago elegida en el Egreso (Cobros y Pagos) —
-- no con ninguna cuenta de este catálogo de Nómina.
--
-- Ningún código (RolAsientoService.php, AsientoBuilderService.php) lee el código
-- BANCOSNOMINA; solo queda referenciado en un comentario de
-- MigracionConfigContableService.php documentando que quedó obsoleto. Es seguro
-- eliminarlo aunque alguna empresa ya le haya asignado una cuenta en
-- asientos_programados: esa fila queda huérfana, sin efecto, igual que ahora.
-- Idempotente.
-- ============================================================================

UPDATE asientos_tipo
SET eliminado = true,
    deleted_at = NOW()
WHERE tipo_asiento = 'nomina'
  AND codigo = 'BANCOSNOMINA'
  AND eliminado = false;
