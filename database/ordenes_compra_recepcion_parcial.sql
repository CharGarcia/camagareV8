-- ============================================================================
-- Órdenes de Compra: entregas parciales (varias compras por orden)
-- ============================================================================
-- Hasta ahora una orden solo se podía vincular a UNA compra (índice único en
-- compras_cabecera.id_orden_compra). Para soportar entregas parciales del
-- proveedor (varias facturas contra el mismo pedido) hay que permitir que
-- VARIAS compras apunten a la misma orden — cada compra sigue apuntando a
-- una sola orden (eso no cambia), pero una orden ahora puede recibir varias
-- compras a lo largo del tiempo.
-- ============================================================================

-- Quitar la restricción "una orden = una sola compra vinculada".
DROP INDEX IF EXISTS ux_compras_cabecera_orden_compra;

-- El índice normal (no único) para buscar por id_orden_compra ya existe
-- (idx_compras_cabecera_orden_compra, de alter_compras_orden_compra.sql) y
-- sigue sirviendo igual con múltiples compras por orden.

-- Marca si el cierre a "Recibido" fue forzado manualmente (el proveedor no
-- va a entregar el saldo pendiente) en vez de completarse por cantidades.
ALTER TABLE ordenes_compra
    ADD COLUMN IF NOT EXISTS cierre_forzado BOOLEAN NOT NULL DEFAULT false;
