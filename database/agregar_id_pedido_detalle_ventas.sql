-- ============================================================================
-- Vincula el detalle de Facturas de Venta con el detalle del Pedido de origen
-- ============================================================================
-- PARA QUÉ
--   Cuando se genera una Factura de Venta desde un Pedido (botón "Facturar" del
--   módulo de Pedidos), cada línea de la factura queda enlazada a la línea del
--   pedido que la originó. Con esto:
--     - Pedidos puede saber cuánta cantidad de cada línea ya fue facturada
--       (App\repositories\modulos\PedidoRepository::getCantidadConsumidaPorDetalle())
--       y bloquear que se elimine o reduzca esa línea (PedidoService).
--     - El historial de un ítem del pedido (clic en la línea) puede mostrar qué
--       factura(s) lo consumieron.
--
--   Mismo patrón ya usado en consignaciones_ventas_detalles.id_pedido_detalle.
--
-- DEGRADACIÓN SEGURA
--   El código ya revisa si esta columna existe (information_schema) antes de
--   usarla — mientras este script no se despliegue, todo sigue funcionando
--   igual que antes, solo que sin el vínculo a Factura (sí funciona ya el
--   vínculo a Consignación, que no depende de este script).
--
-- USO
--   Ejecutar una sola vez. No destructivo, no afecta datos existentes.
-- ============================================================================

ALTER TABLE ventas_detalle ADD COLUMN IF NOT EXISTS id_pedido_detalle INTEGER NULL;

CREATE INDEX IF NOT EXISTS idx_ventas_detalle_pedido_detalle
    ON ventas_detalle (id_pedido_detalle)
    WHERE id_pedido_detalle IS NOT NULL;
