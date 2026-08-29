-- ============================================================================
-- Comandas: aviso al mesero de que el cliente pidió desde el QR de la mesa.
--
-- El tablero ya avisa cuando hay ítems por enviar a preparación, pero eso no
-- distingue quién los pidió. Cuando el cliente confirma su pedido desde el
-- celular no queda nadie enterado en el salón: sus ítems se van solos al KDS (o
-- quedan entregados, si no pasan por estación) y el mesero puede no darse cuenta
-- de que esa mesa pidió.
--
-- Esta marca se enciende cuando el cliente confirma un pedido desde el QR y se
-- apaga cuando un mesero entra a esa comanda desde el tablero — o sea, cuando
-- alguien del salón ya lo vio.
--
-- Va en `comandas` y no en `comanda_detalle` a propósito: es un aviso por mesa,
-- no un atributo de cada línea, y así el tablero lo lee sin sumar detalles.
-- ============================================================================

BEGIN;

ALTER TABLE comandas
    ADD COLUMN IF NOT EXISTS pedido_qr_pendiente BOOLEAN NOT NULL DEFAULT false;

ALTER TABLE comandas
    ADD COLUMN IF NOT EXISTS pedido_qr_at TIMESTAMP NULL;

COMMENT ON COLUMN comandas.pedido_qr_pendiente IS
    'true = el cliente confirmó un pedido desde el QR y todavía nadie del salón entró a la comanda. Se apaga al entrar el mesero.';
COMMENT ON COLUMN comandas.pedido_qr_at IS
    'Cuándo confirmó el cliente su último pedido desde el QR (para ordenar/priorizar el aviso en el tablero).';

COMMIT;

-- Verificación:
-- SELECT id, numero_comanda, pedido_qr_pendiente, pedido_qr_at FROM comandas WHERE estado = 'abierta';
