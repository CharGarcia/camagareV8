-- =============================================================================
-- Migración: vincular pedidos con su proforma de origen
-- Fecha: 2026-09-21
-- Descripción: Agrega pedidos_cabecera.id_proforma para listar todos los
--              pedidos generados desde una proforma (relación 1:N), igual que
--              ya se hace con las facturas (ventas_cabecera.id_proforma).
--
-- El código degrada solo si esta migración todavía no se aplicó: el INSERT de
-- pedidos omite la columna cuando no existe (PedidoService::guardarPedido) y la
-- pestaña "Pedidos" del modal de proforma devuelve una lista vacía. Aplicar este
-- SQL ANTES de desplegar el código.
-- =============================================================================

ALTER TABLE pedidos_cabecera ADD COLUMN IF NOT EXISTS id_proforma INTEGER;

COMMENT ON COLUMN pedidos_cabecera.id_proforma IS 'FK lógica a proformas_cabecera cuando el pedido se generó desde una proforma';

CREATE INDEX IF NOT EXISTS idx_pedidos_id_proforma ON pedidos_cabecera(id_proforma);
