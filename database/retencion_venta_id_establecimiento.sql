-- Atribución de establecimiento (sucursal propia) para las retenciones de venta.
--
-- La retención de venta la emite el CLIENTE (agente de retención); sus columnas
-- establecimiento/punto_emision/secuencial son el NÚMERO del documento del cliente,
-- NO una sucursal nuestra. Para poder atribuir/reasignar la retención a uno de
-- NUESTROS establecimientos (reportes/ATS) se agrega id_establecimiento, análogo a
-- compras_cabecera.id_establecimiento. No cambia el número del documento.
--
-- El módulo "Reasignar establecimiento" (modulos/reasignar-establecimiento) también
-- aplica este ALTER de forma automática (ADD COLUMN IF NOT EXISTS + backfill). Este
-- archivo existe para el despliegue manual y como registro del cambio.

ALTER TABLE retencion_venta_cabecera
    ADD COLUMN IF NOT EXISTS id_establecimiento integer;

COMMENT ON COLUMN retencion_venta_cabecera.id_establecimiento IS
    'Sucursal propia a la que se atribuye la retención de venta (FK empresa_establecimiento). No es el serie del cliente.';

-- Backfill: las existentes se atribuyen a la MATRIZ (establecimiento de menor código) de su empresa.
UPDATE retencion_venta_cabecera r
   SET id_establecimiento = (
        SELECT ee.id FROM empresa_establecimiento ee
         WHERE ee.id_empresa = r.id_empresa AND ee.eliminado = false
         ORDER BY ee.codigo ASC LIMIT 1
   )
 WHERE r.id_establecimiento IS NULL;
