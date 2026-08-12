-- ============================================================
-- Vincular Compras (factura electrónica del proveedor) con
-- Órdenes de Compra (pedido interno previo).
-- ============================================================

ALTER TABLE compras_cabecera
    ADD COLUMN IF NOT EXISTS id_orden_compra INTEGER NULL
        REFERENCES ordenes_compra(id);

CREATE INDEX IF NOT EXISTS idx_compras_cabecera_orden_compra
    ON compras_cabecera(id_orden_compra);

-- Una orden de compra solo puede estar vinculada a UNA compra vigente a la vez.
CREATE UNIQUE INDEX IF NOT EXISTS ux_compras_cabecera_orden_compra
    ON compras_cabecera(id_orden_compra)
    WHERE id_orden_compra IS NOT NULL AND eliminado = false;
