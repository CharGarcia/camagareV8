-- ============================================================================
-- POS Restaurantes — QR por mesa (Fase 1: pedir + enviar a preparación)
--
-- Cada mesa tiene un token único (no correlativo, no adivinable) que resuelve
-- a un link público /pedido/{token}. Regenerar el token invalida el QR
-- impreso anterior (útil si se filtra o se reimprime).
-- ============================================================================

ALTER TABLE mesas ADD COLUMN IF NOT EXISTS qr_token VARCHAR(64);

CREATE UNIQUE INDEX IF NOT EXISTS uq_mesas_qr_token ON mesas (qr_token) WHERE qr_token IS NOT NULL;
