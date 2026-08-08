-- ============================================================
-- Empresas: marca "es_demo" para bloquear el cambio a ambiente
-- de Producción (usada en la empresa de demostración para el
-- revisor de Google Play / Apple — nunca debe poder emitir
-- documentos electrónicos reales).
-- Idempotente.
-- ============================================================

ALTER TABLE empresas ADD COLUMN IF NOT EXISTS es_demo BOOLEAN NOT NULL DEFAULT false;

COMMENT ON COLUMN empresas.es_demo IS
    'true = empresa de demostración; EmpresaService::saveEmisor() rechaza cambiar tipo_ambiente a Producción (2) mientras esta marca esté activa.';
