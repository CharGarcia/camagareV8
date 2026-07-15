-- ============================================================
-- Catálogo de conceptos contables para Recibos de Venta
-- (tipo_asiento = 'recibos_venta'), independiente del de Facturas
-- de Venta (tipo_asiento = 'ventas_factura'). asientos_tipo es una
-- tabla GLOBAL (sin id_empresa) — las cuentas por empresa se
-- asignan aparte en asientos_programados vía Configuración Contable.
-- Espejo exacto de las 8 filas reales de ventas_factura.
-- Idempotente: no falla si ya se corrió antes.
-- ============================================================

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'recibos_venta', 'Cuenta por cobrar', 'Cuenta por cobrar del recibo de venta', 'PORCOBRARRECIBOVENTA', 'activo,ingreso', 'debe', false, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'PORCOBRARRECIBOVENTA');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'recibos_venta', 'Subtotal', 'Subtotal del recibo de venta', 'SUBTOTALRECIBOVENTA', 'ingreso', 'haber', false, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'SUBTOTALRECIBOVENTA');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'recibos_venta', 'Propina en venta', 'Propina del recibo de venta', 'PROPINARECIBOVENTA', 'ingreso', 'haber', false, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'PROPINARECIBOVENTA');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'recibos_venta', 'Ice en recibo de venta', 'ICE del recibo de venta', 'ICERECIBOVENTA', 'pasivo', 'haber', false, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'ICERECIBOVENTA');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'recibos_venta', 'Descuento en el recibo de venta', 'Descuento del recibo de venta', 'DESCUENTORECIBOVENTA', 'ingreso', 'debe', false, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'DESCUENTORECIBOVENTA');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'recibos_venta', 'Costo de Ventas', 'Costo de ventas del recibo (kardex referencia_tipo=recibo_venta)', 'COSTORECIBOVENTA', 'costo', 'debe', false, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'COSTORECIBOVENTA');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'recibos_venta', 'Inventario de Mercaderías', 'Contrapartida del costo de ventas del recibo', 'INVENTARIORECIBOVENTA', 'activo', 'haber', false, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'INVENTARIORECIBOVENTA');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'recibos_venta', 'Ajuste por redondeo', 'Ajuste por redondeo del recibo de venta', 'AJUSTEREDONDEORECIBOVENTA', 'ingreso,costo,gasto', 'debe', false, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'AJUSTEREDONDEORECIBOVENTA');

-- Devuelve las filas insertadas para confirmar.
SELECT id, tipo_asiento, referencia, codigo FROM asientos_tipo WHERE tipo_asiento = 'recibos_venta' ORDER BY id;
