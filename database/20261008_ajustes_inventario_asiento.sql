-- Asiento contable automático de los AJUSTES de inventario (/modulos/inventario).
--
-- 1) inventario_kardex.contabiliza_ajuste: marca los movimientos que deben generar asiento.
--    Solo la ponen en true los ajustes hechos desde el módulo Inventario (modal, importación
--    CSV) y el ajuste de la pestaña Existencias del Reporte de Inventarios. Los anteriores a este cambio
--    quedan en false: no se contabilizan (fecha de corte = despliegue). Los demás flujos que
--    también graban 'ajuste_manual' (ficha de Producto, cargas, importaciones, saldos
--    iniciales) tampoco la marcan.
-- 2) inventario_kardex.id_asiento_contable: enlace al asiento generado (como en cada documento).
-- 3) asientos_tipo: concepto 'ajuste_inventario' con sus tres cuentas, para la sección
--    «Ajustes de Inventario» de Configuración Contable.
--
-- Idempotente: se puede ejecutar más de una vez.

ALTER TABLE inventario_kardex ADD COLUMN IF NOT EXISTS contabiliza_ajuste BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE inventario_kardex ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER NULL;

-- Detección de pendientes (SincronizadorAsientosService / generación automática): índice parcial,
-- solo ocupa las filas marcadas.
CREATE INDEX IF NOT EXISTS idx_kardex_ajuste_contable
    ON inventario_kardex (id_empresa)
    WHERE contabiliza_ajuste = true AND eliminado = false;

ALTER TABLE asientos_tipo ADD COLUMN IF NOT EXISTS debe_haber VARCHAR(10) NOT NULL DEFAULT 'debe';

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, debe_haber)
SELECT 'ajuste_inventario',
       'Inventario',
       'Cuenta de inventario/mercadería que sube con una entrada y baja con una salida de ajuste, a costo. Si no se configura, se usa la de Ventas con Factura.',
       'AJUSTE_INVENTARIO',
       'debe'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'AJUSTE_INVENTARIO');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, debe_haber)
SELECT 'ajuste_inventario',
       'Sobrante de inventario',
       'Contrapartida de las ENTRADAS por ajuste (sobrantes de conteo físico, etc.). Normalmente una cuenta de otros ingresos.',
       'AJUSTE_SOBRANTE',
       'haber'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'AJUSTE_SOBRANTE');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, debe_haber)
SELECT 'ajuste_inventario',
       'Faltante / merma de inventario',
       'Contrapartida de las SALIDAS por ajuste (faltantes, mermas, daños). Normalmente una cuenta de gasto.',
       'AJUSTE_FALTANTE',
       'debe'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'AJUSTE_FALTANTE');
