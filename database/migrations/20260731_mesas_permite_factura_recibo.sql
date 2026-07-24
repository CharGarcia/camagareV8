-- ============================================================================
-- POS Restaurantes — Documentos que puede pedir el cliente en el portal QR,
-- configurado por mesa (no por empresa)
--
-- Al crear/editar una mesa (modulos/mesas) se elige si esa mesa deja pedir
-- Factura, Recibo, o ambos, al "Pedir mi cuenta" desde el QR. Si se permiten
-- los dos, Factura es la opción principal (preseleccionada). Por defecto,
-- toda mesa nace solo con Factura — no cambia nada del flujo hasta que el
-- usuario habilite también Recibo para una mesa puntual.
-- ============================================================================

ALTER TABLE mesas ADD COLUMN IF NOT EXISTS permite_factura BOOLEAN NOT NULL DEFAULT true;
ALTER TABLE mesas ADD COLUMN IF NOT EXISTS permite_recibo  BOOLEAN NOT NULL DEFAULT false;
