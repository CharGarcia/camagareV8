-- ============================================================================
--  ⛔ REEMPLAZADO el 16-09-2026 por 20260916_reporte_inventarios_indices_ajuste.sql
--     NO EJECUTAR ESTE ARCHIVO. Su tercer índice (idx_kardex_stock_por_bodega)
--     resultó dañino: con el código anterior al 16-09 dejaba la pestaña
--     Consignaciones en ~45 s. Quedó comentado abajo; el archivo nuevo crea los
--     otros dos y quita ese si ya existe.
-- ============================================================================
--  20260914_indices_reporte_inventarios_arranque.sql
--  Tres índices para el Reporte de Inventarios (modulos/reporte_inventarios) y el
--  módulo Inventario (modulos/inventario): dos para que la PANTALLA INICIAL deje de
--  recorrer el kardex entero solo para llenar tres <select> de filtros, y uno para
--  las consultas de stock por producto y bodega (Existencias, Valorización y
--  Auditoría), que agregan el kardex de la empresa en cada Mostrar.
-- ----------------------------------------------------------------------------
--  QUÉ HACE:        crea 3 índices. NO modifica ni borra ni un solo dato.
--  TOCA DATOS:      no.
--  REVERSIBLE:      sí, al 100% — ver el bloque "REVERSIÓN" al final.
--  IMPACTO:         ~40 MB de disco por cada 600.000 movimientos de kardex
--                   (medido: 4 MB el de usuario, 18 MB el de fecha, ~18 MB el de
--                   stock por bodega) y un poco
--                   más de coste en cada INSERT al kardex; a cambio, tres
--                   consultas que hoy escanean la tabla entera pasan a
--                   resolverse por índice.
--  VALIDADO:        15-09-2026 contra la base local (PostgreSQL 18.3): los tres
--                   creados dentro de una transacción con ROLLBACK, y medidos
--                   sobre una carga sembrada de 2.000 productos × 5 bodegas y
--                   300.000 movimientos, comprobando que los números del reporte
--                   son idénticos a un cálculo independiente en SQL elemental.
--
--  VA DE LA MANO CON EL CÓDIGO: sin los índices, las consultas reescritas de
--                   InventarioRepository::getTiposReferencia() /
--                   getUsuariosConMovimientos() y de
--                   ReporteInventarioRepository::getAniosMovimientos() siguen
--                   dando el resultado correcto, pero pierden la ventaja (el
--                   "loose index scan" necesita el índice para saltar de un
--                   valor distinto al siguiente).
--
--  POR QUÉ
--  -------
--  Al abrir el módulo, el controlador llena los combos "Origen", "Usuario" y
--  "Año" con tres consultas del tipo SELECT DISTINCT ... WHERE id_empresa = ?
--  sobre inventario_kardex. Devuelven entre 3 y 15 valores, pero para saberlo
--  leían TODOS los movimientos de la empresa. Medido en local sobre una copia
--  de la tabla real con 600.000 filas: entre 0,55 s y 1,15 s solo en esas tres
--  consultas, y creciendo cada mes. Con el código nuevo + estos índices: 1,3 ms.
--
--  LEER ANTES DE EJECUTAR - CÓMO SE EJECUTA ESTO EN pgAdmin
--  Las sentencias del PASO 2 usan CONCURRENTLY, que NO puede ejecutarse dentro
--  de un bloque de transacción. pgAdmin envuelve en una transacción implícita
--  cualquier envío con más de una sentencia, así que pegar todo el archivo y
--  pulsar F5 falla con:
--        ERROR: CREATE INDEX CONCURRENTLY cannot run inside a transaction block
--  Esto NO se arregla con "Auto commit ON": es comportamiento del servidor.
--
--  En el Query Tool hay que enviar UNA sentencia a la vez: seleccionar con el
--  ratón solo esa línea y pulsar F5. Son 3 veces.
--
--  ¿Por qué CONCURRENTLY? Porque un CREATE INDEX normal BLOQUEA los INSERT /
--  UPDATE / DELETE de inventario_kardex mientras construye el índice, y el
--  kardex se escribe en cada factura, compra y ajuste de TODAS las empresas.
--  CONCURRENTLY tarda más, pero no congela la facturación.
--
--  Al final está la VARIANTE B (sin CONCURRENTLY) para ventana de
--  mantenimiento con el sistema cerrado: esa sí se ejecuta de una sola vez.
-- ============================================================================


-- ============================================================================
-- PASO 1 - DIAGNÓSTICO PREVIO (solo lectura - ejecutar y LEER el resultado)
-- ============================================================================
-- Objetivo doble:
--   a) No crear un índice que ya exista con otro nombre. El IF NOT EXISTS de
--      PostgreSQL compara solo el NOMBRE: un índice equivalente con otro nombre
--      pasa desapercibido y quedarían dos, ocupando disco y encareciendo cada
--      escritura por duplicado.
--   b) Confirmar que idx_kardex_referencia YA EXISTE. Ese tercer índice no se
--      crea aquí porque ya está en database/indices_reporte_consignaciones.sql,
--      pero el combo "Origen" lo necesita. Si NO aparece en el resultado,
--      ejecutar antes aquel archivo.
--
-- Lo que se esperaba encontrar (medido en local el 14-09-2026):
--   inventario_kardex_pkey        (id)
--   idx_kardex_empresa_producto   (id_empresa, id_producto)
--   idx_kardex_fecha              (fecha_movimiento)          <- sin id_empresa
--   idx_kardex_lote               (numero_lote) WHERE ...
--   idx_kardex_referencia         (id_empresa, referencia_tipo, ...) WHERE ...

SELECT c.relname                        AS indice,
       i.indisunique                    AS es_unico,
       i.indisvalid                     AS valido,
       pg_size_pretty(pg_relation_size(i.indexrelid)) AS tamano,
       pg_get_indexdef(i.indexrelid)    AS definicion
FROM pg_index i
JOIN pg_class     c ON c.oid = i.indexrelid
JOIN pg_class     t ON t.oid = i.indrelid
JOIN pg_namespace n ON n.oid = t.relnamespace
WHERE n.nspname = 'public'
  AND t.relname = 'inventario_kardex'
ORDER BY c.relname;


-- ============================================================================
-- PASO 2 - CREAR LOS ÍNDICES
--          UNA SENTENCIA A LA VEZ: seleccionar la línea y F5. Tres veces.
--          Van ordenadas de la más rápida a la más lenta.
-- ============================================================================

-- 1) Combo "Usuario" (quién registró el movimiento), en Reporte de Inventarios
--    y en Inventario. Patrón real, tras la reescritura del repositorio:
--      WHERE id_empresa = ? AND eliminado = false AND created_by > ?
--      ORDER BY created_by LIMIT 1     -- una vez por usuario distinto
--    created_by es además una clave foránea que hoy no tiene ningún índice.
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_kardex_empresa_usuario ON public.inventario_kardex (id_empresa, created_by) WHERE eliminado = false;

-- 2) Combo "Año" de la pestaña Movimientos. Patrón real, tras la reescritura:
--      WHERE id_empresa = ? AND eliminado = false AND fecha_movimiento >= ?
--      ORDER BY fecha_movimiento LIMIT 1   -- una vez por año del histórico
--    El idx_kardex_fecha que ya existe NO sirve para esto: empieza por
--    fecha_movimiento y no lleva id_empresa, así que en una base multiempresa
--    obliga a recorrer las fechas de todas las empresas. Este índice sirve
--    además a cualquier listado de kardex filtrado por empresa y rango de
--    fechas (la pestaña Movimientos entera).
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_kardex_empresa_fecha ON public.inventario_kardex (id_empresa, fecha_movimiento) WHERE eliminado = false;

-- 3) Stock por producto y bodega. Lo usan las pestañas Existencias, Valorización
--    y Auditoría: las tres agregan el kardex completo de la empresa
--    (SUM(cantidad) y último costo, agrupados por id_producto + id_bodega) en cada
--    Mostrar. El idx_kardex_empresa_producto que ya existe se queda a medias: no
--    lleva id_bodega, así que obliga a reordenar todo el resultado.
--    Las columnas del INCLUDE evitan bajar a la tabla (index-only scan).
--    Medido: Auditoría 4,9 s -> 1,3 s. Es el más grande de los tres.
--    ⛔ DESACTIVADO el 16-09-2026: le quitaba el plan a idx_kardex_referencia en la
--    pestaña Consignaciones (1,1 s -> 45 s). Ver 20260916_reporte_inventarios_indices_ajuste.sql.
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_kardex_stock_por_bodega ON public.inventario_kardex (id_empresa, id_producto, id_bodega) INCLUDE (cantidad, costo_unitario, fecha_movimiento, id) WHERE eliminado = false;


-- ============================================================================
-- PASO 3 - VERIFICAR QUE NINGUNO QUEDÓ INVÁLIDO  <- NO SALTARSE ESTE PASO
-- ============================================================================
-- CONCURRENTLY puede fallar a mitad (por un bloqueo, un timeout o una
-- desconexión) y deja el índice CREADO pero MARCADO COMO INVÁLIDO: existe,
-- ocupa disco, encarece las escrituras... y el planificador NO lo usa. Y no
-- avisa de nada.
--
-- Resultado esperado: 3 filas, todas con valido = true.
-- Si alguna sale con valido = false, borrarla y volver a crearla:
--     DROP INDEX CONCURRENTLY IF EXISTS <nombre_del_indice>;
--     (y repetir su CREATE del PASO 2)

SELECT c.relname AS indice, i.indisvalid AS valido
FROM pg_index i
JOIN pg_class c ON c.oid = i.indexrelid
WHERE c.relname IN ('idx_kardex_empresa_usuario',
                    'idx_kardex_empresa_fecha',
                    'idx_kardex_stock_por_bodega')
ORDER BY c.relname;


-- ============================================================================
-- PASO 4 - REFRESCAR ESTADÍSTICAS
-- ============================================================================
-- Para que el planificador empiece a usar lo nuevo de inmediato.
ANALYZE public.inventario_kardex;


-- ============================================================================
-- COMPROBACIÓN FINAL (opcional) - que el plan ya no escanea la tabla
-- ============================================================================
-- Reemplazar el 8 por el id de una empresa con bastante kardex. En el plan NO
-- debe aparecer "Seq Scan on inventario_kardex"; deben verse "Index Only Scan"
-- / "Index Scan" sobre los índices de arriba.
--
-- EXPLAIN ANALYZE
-- WITH RECURSIVE saltos AS (
--     (SELECT fecha_movimiento AS f FROM inventario_kardex
--       WHERE id_empresa = 8 AND eliminado = false AND fecha_movimiento IS NOT NULL
--       ORDER BY fecha_movimiento LIMIT 1)
--     UNION ALL
--     SELECT (SELECT k.fecha_movimiento FROM inventario_kardex k
--              WHERE k.id_empresa = 8 AND k.eliminado = false
--                AND k.fecha_movimiento >= date_trunc('year', s.f) + interval '1 year'
--              ORDER BY k.fecha_movimiento LIMIT 1)
--       FROM saltos s WHERE s.f IS NOT NULL)
-- SELECT EXTRACT(YEAR FROM f)::int AS anio FROM saltos WHERE f IS NOT NULL ORDER BY anio DESC;


-- ============================================================================
-- REVERSIÓN (si hiciera falta deshacer) - también UNA SENTENCIA A LA VEZ
-- ============================================================================
-- DROP INDEX CONCURRENTLY IF EXISTS public.idx_kardex_empresa_usuario;
-- DROP INDEX CONCURRENTLY IF EXISTS public.idx_kardex_empresa_fecha;
-- DROP INDEX CONCURRENTLY IF EXISTS public.idx_kardex_stock_por_bodega;


-- ============================================================================
-- VARIANTE B - VENTANA DE MANTENIMIENTO (sistema cerrado)
--              Estas SÍ se pueden ejecutar todas juntas con F5, pero BLOQUEAN
--              las escrituras de inventario_kardex mientras construyen.
-- ============================================================================
-- CREATE INDEX IF NOT EXISTS idx_kardex_empresa_usuario ON public.inventario_kardex (id_empresa, created_by) WHERE eliminado = false;
-- CREATE INDEX IF NOT EXISTS idx_kardex_empresa_fecha   ON public.inventario_kardex (id_empresa, fecha_movimiento) WHERE eliminado = false;
-- (idx_kardex_stock_por_bodega desactivado el 16-09-2026: ver el encabezado)
-- ANALYZE public.inventario_kardex;
