-- ============================================================================
--  20260916_reporte_inventarios_indices_ajuste.sql
--  Índices del kardex para el Reporte de Inventarios (modulos/reporte_inventarios)
--  y el módulo Inventario. REEMPLAZA a 20260914_indices_reporte_inventarios_arranque.sql.
-- ----------------------------------------------------------------------------
--  QUÉ HACE:        crea 2 índices en inventario_kardex (si no existen) y BORRA
--                   1 índice que resultó dañino (si existe). NO modifica ni
--                   borra ni un solo dato.
--  TOCA DATOS:      no.
--  REVERSIBLE:      sí, al 100% — ver el bloque "REVERSIÓN" al final.
--  IMPACTO:         ~4 MB (usuario) y ~18 MB (fecha) por cada 600.000
--                   movimientos, y un poco más de coste en cada INSERT al kardex.
--                   Borrar el tercero libera ~18 MB y abarata esos INSERT.
--  VALIDADO:        16-09-2026 contra la base local (PostgreSQL 18.3), dentro de
--                   transacciones con ROLLBACK, con 1,8 millones de movimientos
--                   (una empresa con 300.000 + 100 empresas intercaladas en orden
--                   cronológico) y 20.000 líneas de consignación.
--
--  RELACIÓN CON 20260914_indices_reporte_inventarios_arranque.sql
--   - Si NO se ejecutó: no ejecutarlo. Basta con este archivo.
--   - Si SÍ se ejecutó completo: el PASO 2 de este archivo no hará nada (los dos
--     índices ya existen) y el PASO 3 quita el tercero.
--
--  POR QUÉ
--  -------
--  1) idx_kardex_empresa_usuario e idx_kardex_empresa_fecha: el combo "Usuario"
--     y el combo "Año" del reporte los necesitan para cargarse en ~1 ms. El código
--     del 16-09 detecta si existen: sin ellos usa una consulta de una sola pasada
--     (correcta, pero ~0,5-1 s más lenta al entrar al módulo con mucho kardex).
--  2) idx_kardex_stock_por_bodega se BORRA: con él creado, el costo por línea de
--     la pestaña Consignaciones dejaba de usar idx_kardex_referencia y recorría
--     todos los movimientos del producto por cada línea. Medido con el código
--     anterior al 16-09: el listado de Consignaciones pasaba de 1,1 s a 45 s.
--     Con el código nuevo ya no estorba, pero tampoco aporta nada medible y
--     encarece cada movimiento de kardex (cada factura, compra y ajuste).
--
--  ORDEN RECOMENDADO: primero este SQL, después el código. En cualquier otro orden
--  no se rompe nada: el código funciona con o sin estos índices.
--
--  ⚠️  LEER ANTES DE EJECUTAR — CÓMO SE EJECUTA ESTO EN pgAdmin
--  Las sentencias de los PASOS 2 y 3 usan CONCURRENTLY, que NO puede ejecutarse
--  dentro de un bloque de transacción. PostgreSQL envuelve en una transacción
--  implícita cualquier envío con más de una sentencia, así que pegar todo el
--  archivo y pulsar F5 falla con:
--        ERROR: CREATE INDEX CONCURRENTLY cannot run inside a transaction block
--  Esto NO se arregla con "Auto commit ON": es comportamiento del servidor.
--
--  En el Query Tool hay que enviar UNA sentencia a la vez: seleccionar con el
--  ratón solo esa línea y pulsar F5. Son 3 veces (2 CREATE + 1 DROP).
--
--  ¿Por qué CONCURRENTLY? Porque un CREATE / DROP INDEX normal BLOQUEA los
--  INSERT / UPDATE / DELETE de inventario_kardex mientras trabaja, y el kardex se
--  escribe en cada factura, compra y ajuste de TODAS las empresas.
--
--  Al final está la VARIANTE B (sin CONCURRENTLY), para una ventana de
--  mantenimiento con el sistema cerrado: esa sí se ejecuta de una sola vez.
-- ============================================================================


-- ============================================================================
-- PASO 1 · DIAGNÓSTICO PREVIO (solo lectura — ejecutar con F5 y LEER el resultado)
-- ============================================================================
-- Todos los índices actuales de inventario_kardex. Qué mirar:
--   a) Si ya hay un índice con otro nombre sobre (id_empresa, created_by) o
--      (id_empresa, fecha_movimiento) WHERE eliminado = false, saltarse su CREATE:
--      IF NOT EXISTS compara solo el NOMBRE y quedarían dos iguales.
--   b) Si aparece idx_kardex_stock_por_bodega, el PASO 3 lo quita.
--   c) idx_kardex_referencia debe existir (lo crea
--      database/indices_reporte_consignaciones.sql). Si no aparece, ejecutar
--      antes aquel archivo: el combo "Origen" y Consignaciones lo usan.

SELECT c.relname                                AS indice,
       i.indisvalid                             AS valido,
       pg_size_pretty(pg_relation_size(c.oid))  AS tamano,
       pg_get_indexdef(i.indexrelid)            AS definicion
FROM pg_index i
JOIN pg_class     c ON c.oid = i.indexrelid
JOIN pg_class     t ON t.oid = i.indrelid
JOIN pg_namespace n ON n.oid = t.relnamespace
WHERE n.nspname = 'public'
  AND t.relname = 'inventario_kardex'
ORDER BY c.relname;


-- ============================================================================
-- PASO 2 · CREAR LOS DOS ÍNDICES — UNA SENTENCIA A LA VEZ (seleccionar la línea y F5)
-- ============================================================================

-- 1) Combo "Usuario" (quién registró el movimiento), en Reporte de Inventarios e
--    Inventario. created_by es además una clave foránea que no tenía índice.
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_kardex_empresa_usuario ON public.inventario_kardex (id_empresa, created_by) WHERE eliminado = false;

-- 2) Combo "Año" de la pestaña Movimientos, y los recorridos del kardex de una
--    empresa por rango de fechas. El idx_kardex_fecha que ya existe no sirve para
--    esto: no lleva id_empresa y obliga a pasar por los movimientos de todas las
--    empresas.
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_kardex_empresa_fecha ON public.inventario_kardex (id_empresa, fecha_movimiento) WHERE eliminado = false;


-- ============================================================================
-- PASO 3 · QUITAR EL ÍNDICE DAÑINO — UNA SENTENCIA (seleccionar la línea y F5)
-- ============================================================================
-- Si no existe, no hace nada.
DROP INDEX CONCURRENTLY IF EXISTS public.idx_kardex_stock_por_bodega;


-- ============================================================================
-- PASO 4 · VERIFICAR  ← NO SALTARSE ESTE PASO
-- ============================================================================
-- CONCURRENTLY puede fallar a mitad (un bloqueo, un timeout, una desconexión) y
-- deja el índice CREADO pero INVÁLIDO: ocupa disco, encarece las escrituras y el
-- planificador NO lo usa, sin avisar. El código del reporte trata un índice
-- inválido como inexistente.
--
-- Resultado esperado: 3 filas —
--   idx_kardex_empresa_fecha     existe = true   valido = true
--   idx_kardex_empresa_usuario   existe = true   valido = true
--   idx_kardex_stock_por_bodega  existe = false  valido = false
-- Si alguno de los dos primeros sale con valido = false, borrarlo y volver a
-- crearlo (una sentencia a la vez):
--     DROP INDEX CONCURRENTLY IF EXISTS public.<nombre_del_indice>;
--     (y repetir su CREATE del PASO 2)

SELECT n.indice,
       c.oid IS NOT NULL               AS existe,
       COALESCE(i.indisvalid, false)   AS valido
FROM (VALUES ('idx_kardex_empresa_usuario'),
             ('idx_kardex_empresa_fecha'),
             ('idx_kardex_stock_por_bodega')) AS n(indice)
LEFT JOIN pg_class c ON c.relname = n.indice AND c.relkind = 'i'
LEFT JOIN pg_index i ON i.indexrelid = c.oid
ORDER BY n.indice;


-- ============================================================================
-- PASO 5 · ACTUALIZAR ESTADÍSTICAS (F5)
-- ============================================================================
ANALYZE public.inventario_kardex;


-- ============================================================================
-- PASO 6 · A LOS 2-3 DÍAS: ¿SE ESTÁN USANDO?  (solo lectura, opcional)
-- ============================================================================
-- idx_scan debe ir creciendo con el uso del reporte. Un índice en 0 tras varios
-- días de uso no se está aprovechando: avisar antes de tocar nada.
--
-- SELECT indexrelname AS indice, idx_scan AS veces_usado,
--        pg_size_pretty(pg_relation_size(indexrelid)) AS tamano
-- FROM pg_stat_user_indexes
-- WHERE relname = 'inventario_kardex'
--   AND indexrelname IN ('idx_kardex_empresa_usuario', 'idx_kardex_empresa_fecha')
-- ORDER BY indexrelname;


-- ============================================================================
-- VARIANTE B · SIN CONCURRENTLY (solo con el sistema cerrado)
-- ============================================================================
-- Se ejecuta todo junto con F5, pero BLOQUEA las escrituras del kardex (facturas,
-- compras, ajustes de todas las empresas) mientras dura.
--
-- CREATE INDEX IF NOT EXISTS idx_kardex_empresa_usuario ON public.inventario_kardex (id_empresa, created_by) WHERE eliminado = false;
-- CREATE INDEX IF NOT EXISTS idx_kardex_empresa_fecha   ON public.inventario_kardex (id_empresa, fecha_movimiento) WHERE eliminado = false;
-- DROP INDEX IF EXISTS public.idx_kardex_stock_por_bodega;
-- ANALYZE public.inventario_kardex;


-- ============================================================================
-- REVERSIÓN (si hiciera falta deshacer) — también UNA SENTENCIA A LA VEZ
-- ============================================================================
-- Quitar los dos índices (el código sigue funcionando: vuelve a la consulta de una
-- sola pasada):
-- DROP INDEX CONCURRENTLY IF EXISTS public.idx_kardex_empresa_usuario;
-- DROP INDEX CONCURRENTLY IF EXISTS public.idx_kardex_empresa_fecha;
--
-- Volver a crear el que se quitó en el PASO 3 (NO recomendado; ver "POR QUÉ"):
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_kardex_stock_por_bodega ON public.inventario_kardex (id_empresa, id_producto, id_bodega) INCLUDE (cantidad, costo_unitario, fecha_movimiento, id) WHERE eliminado = false;
