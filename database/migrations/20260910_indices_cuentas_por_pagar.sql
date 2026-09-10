-- ============================================================================
-- Índices para el listado de Cuentas por Pagar (y de paso el de Compras)
-- Fecha: 2026-09-10
--
-- CONTEXTO
--   El listado de CxP calcula el saldo de CADA documento de la empresa (pagos,
--   retenciones, notas de crédito/débito) y solo al final filtra los que tienen
--   saldo pendiente. Por eso mostrar 4 facturas cuesta lo mismo que recorrer
--   toda la historia: el trabajo es proporcional al total de documentos, no al
--   resultado. Medido en producción (empresa 30, RUC 1711548931001):
--   6.259 compras propias y 91.424 líneas de egreso en todo el sistema.
--
--   egresos_detalle solo tenía su PRIMARY KEY, así que el JOIN con
--   egresos_cabecera obligaba a recorrer las 91.424 filas en cada carga.
--
-- QUÉ HACE
--   Crea índices. NO modifica ni borra datos. Es idempotente.
--
-- REVERSIBLE, sin pérdida de datos:
--   DROP INDEX IF EXISTS idx_egresos_detalle_egreso;
--   DROP INDEX IF EXISTS idx_egresos_detalle_documento;
--   DROP INDEX IF EXISTS idx_ret_compra_id_compra;
--   DROP INDEX IF EXISTS idx_compras_listado;
--   DROP INDEX IF EXISTS idx_compras_nc_documento_modificado;
--
-- CÓMO EJECUTARLO
--   Los CREATE INDEX van CONCURRENTLY para no bloquear las escrituras mientras
--   se construyen, y eso EXIGE ejecutarlos FUERA de una transacción. En pgAdmin:
--   ejecutar cada sentencia POR SEPARADO, o desactivar el modo transacción
--   (menú Query → Auto commit ON). Si aparece "CREATE INDEX CONCURRENTLY cannot
--   run inside a transaction block", es que quedó envuelto en un BEGIN.
--
--   Si un CONCURRENTLY falla a medio camino deja el índice inválido: se limpia
--   con DROP INDEX IF EXISTS <nombre>; y se vuelve a lanzar.
-- ============================================================================


-- 1) JOIN egresos_detalle → egresos_cabecera -------------------------------
--    El más importante: sin él, cada carga del listado recorre la tabla entera.
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_egresos_detalle_egreso
    ON egresos_detalle (id_egreso)
    WHERE eliminado = false;

-- 2) Búsqueda de lo pagado por documento ------------------------------------
--    El CTE agrupa por (tipo_documento, id_referencia_documento); este índice
--    permite resolver esa agrupación sin ordenar la tabla completa.
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_egresos_detalle_documento
    ON egresos_detalle (tipo_documento, id_referencia_documento)
    WHERE eliminado = false;

-- 3) Retenciones por compra -------------------------------------------------
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ret_compra_id_compra
    ON retencion_compra_cabecera (id_compra)
    WHERE eliminado = false;

-- 4) y 5) Listado de Compras y cruce de notas de crédito --------------------
--    Vienen de database/compras_indices_listado.sql, que nunca se ejecutó.
--    Se repiten aquí para no depender de correr dos archivos.
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_compras_listado
    ON compras_cabecera (id_empresa, tipo_ambiente, fecha_emision DESC, id DESC)
    WHERE eliminado = false;

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_compras_nc_documento_modificado
    ON compras_cabecera (id_empresa, id_proveedor, documento_modificado)
    WHERE tipo_comprobante = '04' AND eliminado = false;


-- 6) Refrescar estadísticas para que el planificador use los índices nuevos --
ANALYZE egresos_detalle;
ANALYZE retencion_compra_cabecera;
ANALYZE compras_cabecera;
