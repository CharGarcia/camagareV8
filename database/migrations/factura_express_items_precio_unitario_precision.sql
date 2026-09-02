-- factura_express_items.precio_unitario era NUMERIC(14,2): solo 2 decimales de
-- escala. El resto de las tablas de origen de Factura de Venta (proformas,
-- taller, car_wash, servicio_externo, consignaciones, ventas_detalle, etc.)
-- usan NUMERIC(x,6). Con solo 2 decimales, aunque el precio del producto en
-- catálogo tenga más precisión (necesaria para que el precio con IVA cuadre
-- exacto, ej. 109.5650), Postgres lo redondeaba silenciosamente al guardar la
-- plantilla QR, reintroduciendo el descuadre de 1 centavo en la factura que
-- se genera desde ahí.
ALTER TABLE factura_express_items
    ALTER COLUMN precio_unitario TYPE NUMERIC(14,6);
