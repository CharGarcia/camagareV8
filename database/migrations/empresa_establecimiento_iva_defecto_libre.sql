-- Tarifa de IVA que se preselecciona automáticamente al ingresar un ítem
-- "libre" (servicio no catalogado) en Factura de Venta / Recibo de Venta,
-- cuando el establecimiento tiene activo "facturacion_libre".
-- NULL = sin configurar, se mantiene el comportamiento anterior (se
-- selecciona la primera tarifa de la lista).
ALTER TABLE empresa_establecimiento
    ADD COLUMN IF NOT EXISTS id_tarifa_iva_defecto_libre INTEGER REFERENCES tarifa_iva(id);
