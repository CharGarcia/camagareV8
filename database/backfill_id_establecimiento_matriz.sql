-- ============================================================================
-- Backfill de id_establecimiento = MATRIZ para documentos ya registrados.
--
-- Asigna la sucursal propia (id_establecimiento) a TODOS los registros del
-- sistema que la tengan vacía, usando la MATRIZ de cada empresa (el
-- establecimiento de menor código). Evita tener que re-migrar.
--
-- Es IDEMPOTENTE y NO destructivo: solo toca filas con id_establecimiento IS NULL,
-- así que NO pisa reasignaciones hechas a mano ni los documentos nativos que ya
-- traen su establecimiento. Correr las veces que haga falta.
--
-- Después, usar el módulo "Reasignar establecimiento" para mover las excepciones.
-- ============================================================================

-- 1) RETENCIONES DE VENTA ----------------------------------------------------
--    La columna es nueva (la emite el cliente; su establecimiento/punto/secuencial
--    son el número del CLIENTE, no una sucursal nuestra). Se agrega y se atribuye
--    a la matriz.
ALTER TABLE retencion_venta_cabecera
    ADD COLUMN IF NOT EXISTS id_establecimiento integer;

UPDATE retencion_venta_cabecera r
   SET id_establecimiento = (
        SELECT ee.id FROM empresa_establecimiento ee
         WHERE ee.id_empresa = r.id_empresa AND ee.eliminado = false
         ORDER BY ee.codigo ASC LIMIT 1)
 WHERE r.id_establecimiento IS NULL;

-- 2) COMPRAS -----------------------------------------------------------------
--    La columna ya existe. Las compras MIGRADAS quedaron con id_establecimiento
--    NULL (la migración no lo fijaba). Se atribuyen a la matriz; las nativas y las
--    ya reasignadas (no NULL) no se tocan.
UPDATE compras_cabecera c
   SET id_establecimiento = (
        SELECT ee.id FROM empresa_establecimiento ee
         WHERE ee.id_empresa = c.id_empresa AND ee.eliminado = false
         ORDER BY ee.codigo ASC LIMIT 1)
 WHERE c.id_establecimiento IS NULL
   AND c.eliminado = false;

-- Verificación (opcional): cuántos quedaron aún sin establecimiento (debería ser 0
-- salvo empresas sin ningún establecimiento activo).
-- SELECT 'retencion_venta' AS doc, COUNT(*) FROM retencion_venta_cabecera WHERE id_establecimiento IS NULL
-- UNION ALL
-- SELECT 'compras', COUNT(*) FROM compras_cabecera WHERE id_establecimiento IS NULL AND eliminado = false;
