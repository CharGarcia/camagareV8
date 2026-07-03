-- ============================================================
-- Empresa facturada de la suscripción (reventa) — idempotente
-- ============================================================
-- Cuando la suscripción del sistema se factura a OTRA empresa (modelo de
-- reventa: nosotros facturamos a un intermediario y este cobra al cliente
-- final), la ficha de la empresa relaciona la suscripción con el RUC de esa
-- empresa facturada (no con el RUC propio) y muestra SOLO estado, periodicidad
-- y vigencia, sin montos ni detalles (para no exponer valores al cliente final).
--
--   id_empresa_facturada → empresas.id de la empresa a la que se factura.
-- ============================================================

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS id_empresa_facturada INTEGER NULL;
