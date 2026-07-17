-- ============================================================
-- Aprobaciones: mensaje dirigido a otro usuario al rechazar — idempotente
-- ============================================================
-- Cuando el aprobador rechaza, puede dirigir el motivo a cualquier usuario de
-- la empresa (no necesariamente quien solicitó), quien debe resolverlo
-- (ej. eliminar la compra o aplicar el cambio pedido) y luego marcarlo como
-- atendido. Mientras no lo marque, aparece como aviso pendiente para él.
--
--   destinatario_id  → usuario al que se dirige el mensaje de rechazo.
--   atendido         → si el destinatario ya resolvió lo pedido.
--   atendido_at/by   → auditoría de cuándo y quién lo marcó atendido.
-- ============================================================

ALTER TABLE aprobaciones_solicitudes
    ADD COLUMN IF NOT EXISTS destinatario_id INTEGER;

ALTER TABLE aprobaciones_solicitudes
    ADD COLUMN IF NOT EXISTS atendido BOOLEAN NOT NULL DEFAULT false;

ALTER TABLE aprobaciones_solicitudes
    ADD COLUMN IF NOT EXISTS atendido_at TIMESTAMP;

ALTER TABLE aprobaciones_solicitudes
    ADD COLUMN IF NOT EXISTS atendido_by INTEGER;

CREATE INDEX IF NOT EXISTS idx_aprob_solicitudes_menciones
    ON aprobaciones_solicitudes (id_empresa, destinatario_id, atendido)
    WHERE destinatario_id IS NOT NULL;
