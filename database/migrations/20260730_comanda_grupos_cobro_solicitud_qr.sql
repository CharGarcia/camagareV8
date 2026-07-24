-- ============================================================================
-- POS Restaurantes — QR Fase 2: el cliente arma su "cuenta" y deja sus datos
--
-- El cliente, desde el portal público (/pedido/{token}), elige los ítems ya
-- ENTREGADOS que quiere pagar y deja sus datos de facturación. Eso arma un
-- comanda_grupos_cobro (igual que cuando el mesero divide la cuenta), pero
-- con el cliente y el tipo de documento ya resueltos — el mesero solo tiene
-- que confirmar la forma de pago física (efectivo/tarjeta) para cerrarlo, sin
-- pedirle de nuevo cédula/correo al cliente. Todavía NO hay cobro/pago desde
-- el QR en esta fase — el grupo queda 'pendiente' hasta que el mesero cobra.
-- ============================================================================

ALTER TABLE comanda_grupos_cobro ADD COLUMN IF NOT EXISTS id_cliente INTEGER;
ALTER TABLE comanda_grupos_cobro ADD COLUMN IF NOT EXISTS tipo_documento_solicitado VARCHAR(10); -- 'FACTURA' | 'RECIBO', lo que pidió el cliente
ALTER TABLE comanda_grupos_cobro ADD COLUMN IF NOT EXISTS origen VARCHAR(10) NOT NULL DEFAULT 'mesero'; -- 'mesero' | 'qr'

-- "Llamar al mesero" desde el portal QR — aviso visible en el tablero de
-- mesas y en la comanda hasta que alguien del staff lo marca como atendido.
ALTER TABLE comandas ADD COLUMN IF NOT EXISTS solicita_asistencia BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE comandas ADD COLUMN IF NOT EXISTS asistencia_solicitada_at TIMESTAMP;
