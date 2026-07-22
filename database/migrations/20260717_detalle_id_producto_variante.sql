-- =====================================================================
-- Variantes de producto: enlace real desde el detalle de venta
--
-- Hasta ahora, al elegir una variante (Color/Talla, con recargo opcional en
-- productos_variantes) en Factura de Venta o Recibos de Venta, lo único que
-- quedaba guardado era un texto libre en el campo "info adicional" de la
-- línea (ej. "Color: Rojo") — sin referencia real a productos_variantes.id.
-- No servía para nada más que mostrarlo; no se podía reportar "cuánto se
-- vendió de la variante Rojo" de forma confiable (era parsear texto).
--
-- Esta columna guarda el id real de la variante elegida. info_adicional NO
-- se toca (sigue siendo el texto libre que ya era, por compatibilidad); esta
-- es una fuente adicional, estructurada, para reportes.
--
-- Aplicar manualmente (dev y luego producción), como el resto de módulos.
-- =====================================================================

ALTER TABLE ventas_detalle
    ADD COLUMN IF NOT EXISTS id_producto_variante INTEGER NULL
    REFERENCES productos_variantes(id);

ALTER TABLE recibos_venta_detalle
    ADD COLUMN IF NOT EXISTS id_producto_variante INTEGER NULL
    REFERENCES productos_variantes(id);

CREATE INDEX IF NOT EXISTS ix_ventas_detalle_variante
    ON ventas_detalle (id_producto_variante) WHERE id_producto_variante IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_recibos_venta_detalle_variante
    ON recibos_venta_detalle (id_producto_variante) WHERE id_producto_variante IS NOT NULL;
