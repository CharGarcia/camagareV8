-- ------------------------------------------------------------
-- El precio_unitario del detalle de suscripciones estaba en NUMERIC(14,2),
-- lo que truncaba a 2 decimales e impedía respetar la configuración de
-- decimales del establecimiento (igual que la factura de venta, que usa
-- NUMERIC(18,6)). Se amplía la escala. cantidad ya es NUMERIC(18,6).
-- ------------------------------------------------------------
ALTER TABLE suscripciones_detalle
    ALTER COLUMN precio_unitario TYPE NUMERIC(18,6);
