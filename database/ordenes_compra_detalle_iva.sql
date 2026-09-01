-- ============================================================================
-- Órdenes de Compra: tarifa de IVA por línea + subtotales con impuestos
-- ============================================================================
-- Hasta ahora el detalle de la orden solo guardaba cantidad y precio unitario,
-- así que el modal, el PDF, el Excel y la página de aprobación del proveedor
-- mostraban un único TOTAL = suma de cantidad × precio, SIN impuestos.
--
-- Se agrega la tarifa de IVA por línea (mismo criterio que compras_detalle):
--   - codigo_iva     = código SRI de la tarifa (tarifa_iva.codigo). Es el que
--                      distingue 0% / Exento / No objeto, que comparten
--                      porcentaje 0 y por eso no se pueden separar solo con el
--                      porcentaje.
--   - porcentaje_iva = porcentaje aplicado (15.00, 0.00…). Se guarda junto al
--                      código para que el documento conserve la tarifa con la
--                      que se emitió aunque el catálogo cambie después.
-- ============================================================================

ALTER TABLE ordenes_compra_detalle
    ADD COLUMN IF NOT EXISTS codigo_iva     VARCHAR(2)   DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS porcentaje_iva NUMERIC(5,2) NOT NULL DEFAULT 0;

-- Backfill SOLO de las órdenes en Borrador: toman la tarifa del producto
-- vinculado. Las órdenes ya enviadas/aprobadas/recibidas NO se tocan a
-- propósito — el proveedor las aprobó con el total que vio en su momento, y
-- recalcularles el IVA ahora cambiaría el importe de un documento cerrado.
UPDATE ordenes_compra_detalle d
   SET codigo_iva     = ti.codigo,
       porcentaje_iva = COALESCE(ti.porcentaje_iva, 0)
  FROM productos  p
  JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
 WHERE p.id = d.id_producto
   AND d.id_producto IS NOT NULL
   AND d.codigo_iva IS NULL
   AND EXISTS (SELECT 1
                 FROM ordenes_compra oc
                WHERE oc.id = d.id_orden
                  AND oc.estado = 'borrador'
                  AND oc.eliminado = false);

-- Si además se quisiera recalcular el IVA de las órdenes ya enviadas/aprobadas
-- (cambia el TOTAL de documentos cerrados: hacerlo solo a conciencia), ejecutar
-- el mismo UPDATE quitando la condición "oc.estado = 'borrador'" del EXISTS.
