-- ============================================================
-- Aprobaciones: quitar "notificar por correo" — idempotente
-- ============================================================
-- Se decidió no enviar correos; los avisos de aprobaciones pendientes se
-- muestran solo dentro del sistema (badge del navbar).
-- ============================================================

ALTER TABLE aprobaciones_config
    DROP COLUMN IF EXISTS notificar_correo;
