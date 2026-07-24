-- ============================================================================
-- POS Restaurantes — Comandas: soporte de lote/caducidad/NUP por línea
--
-- Contexto: los productos de "Stock General" agregados desde modulos/comandas
-- ya respetan el IVA y el límite de Consumidor Final de Facturación al
-- cobrar, pero NO capturaban lote/caducidad/NUP — si la empresa los exige
-- (empresa → Facturación), la comanda se podía llenar sin problema pero el
-- cobro fallaba al final (FacturaVentaRules/ReciboVentaRules ya validan
-- estos campos), dejando la cuenta atascada sin forma de corregirlo desde
-- comandas. Esta migración agrega dónde guardarlos; la captura en pantalla
-- y el envío a PosVentaService::cobrar() van en el código.
-- ============================================================================

ALTER TABLE comanda_detalle ADD COLUMN IF NOT EXISTS lote VARCHAR(50);
ALTER TABLE comanda_detalle ADD COLUMN IF NOT EXISTS caducidad DATE;
ALTER TABLE comanda_detalle ADD COLUMN IF NOT EXISTS nup VARCHAR(50);
