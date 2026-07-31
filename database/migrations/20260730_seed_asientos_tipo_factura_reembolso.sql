-- Tipos de asiento para el concepto "Factura de Reembolso" (catálogo global).
-- Habilitan la sección en Configuración de Asientos Contables para mapear las
-- cuentas del asiento de "cuenta puente" (el reembolso NO es ingreso propio):
--   Debe : Cuentas por Cobrar Cliente        (total de la factura)
--   Haber: Reembolso a Terceros (puente)     (base + IVA reembolsado — sin fallback,
--                                              es el único concepto genuinamente nuevo)
--   Haber: Ingresos por Honorarios            (solo si hay línea propia de honorarios)
--   Haber: IVA Ventas (honorarios)            (solo si esa línea lleva IVA)
-- Cada empresa asigna la cuenta real en asientos_programados desde esa pantalla.
-- Si CXC_CLIENTE / INGRESO_HONORARIOS / IVA_VENTAS_HONORARIOS no se configuran
-- aquí, el builder reutiliza las mismas cuentas ya configuradas para
-- 'ventas_factura' (son el mismo tipo de cuenta que cualquier venta normal).

ALTER TABLE asientos_tipo ADD COLUMN IF NOT EXISTS debe_haber VARCHAR(10) NOT NULL DEFAULT 'debe';

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, debe_haber)
SELECT 'factura_reembolso',
       'Cuentas por Cobrar Cliente',
       'Lo que el cliente debe pagar por la factura de reembolso completa (gasto reembolsado + honorario propio si aplica). Si no se configura, se reutiliza la cuenta de Cuentas por Cobrar de Facturas de Venta.',
       'FACTREEMB_CXC_CLIENTE',
       'debe'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'FACTREEMB_CXC_CLIENTE');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, debe_haber)
SELECT 'factura_reembolso',
       'Reembolso a Terceros (cuenta puente)',
       'NO es ingreso propio. Representa el gasto pagado a proveedores terceros en nombre del cliente; reconciliar manualmente contra el asiento de la Compra/Egreso original que hizo ese pago (fuera de alcance automático de este módulo).',
       'FACTREEMB_PUENTE_TERCEROS',
       'haber'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'FACTREEMB_PUENTE_TERCEROS');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, debe_haber)
SELECT 'factura_reembolso',
       'Ingresos por Honorarios/Intermediación',
       'Solo si la factura incluye una línea propia de honorarios (no marcada como reembolso). Si no se configura, se reutiliza la cuenta de Ventas de Facturas de Venta.',
       'FACTREEMB_INGRESO_HONORARIOS',
       'haber'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'FACTREEMB_INGRESO_HONORARIOS');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, debe_haber)
SELECT 'factura_reembolso',
       'IVA Ventas (honorarios)',
       'IVA propio generado únicamente por la línea de honorarios, si existe. Si no se configura, se reutiliza la cuenta de IVA en Ventas por tarifa ya configurada para Facturas de Venta.',
       'FACTREEMB_IVA_VENTAS_HONORARIOS',
       'haber'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'FACTREEMB_IVA_VENTAS_HONORARIOS');
