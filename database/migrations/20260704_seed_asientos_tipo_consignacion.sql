-- Tipos de asiento para el concepto "Consignaciones en Ventas" (catálogo global).
-- Habilitan la sección en Configuración de Asientos Contables para mapear las dos
-- cuentas de la reclasificación de inventario a costo:
--   Debe : Mercadería en Consignación (poder de terceros)
--   Haber: Inventario (contrapartida)
-- Cada empresa asigna la cuenta real en asientos_programados desde esa pantalla.

-- Asegurar la columna debe_haber (la crea el repositorio en runtime; aquí por si acaso).
ALTER TABLE asientos_tipo ADD COLUMN IF NOT EXISTS debe_haber VARCHAR(10) NOT NULL DEFAULT 'debe';

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, debe_haber)
SELECT 'consignacion_venta',
       'Mercadería en Consignación',
       'Cuenta de activo donde se traslada la mercadería entregada en consignación (poder de terceros), a costo.',
       'CONSIGNACION_MERCADERIA',
       'debe'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'CONSIGNACION_MERCADERIA');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, debe_haber)
SELECT 'consignacion_venta',
       'Inventario (contrapartida)',
       'Cuenta de inventario/mercadería de la que sale la mercadería consignada, a costo.',
       'CONSIGNACION_INVENTARIO',
       'haber'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'CONSIGNACION_INVENTARIO');
