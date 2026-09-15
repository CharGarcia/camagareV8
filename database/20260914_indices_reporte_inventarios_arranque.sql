-- ============================================================================
--  20260914_indices_reporte_inventarios_arranque.sql
--  Dos índices para que la PANTALLA INICIAL de Reporte de Inventarios
--  (modulos/reporte_inventarios) y de Inventario (modulos/inventario) dejen de
--  recorrer el kardex completo solo para llenar tres <select> de filtros.
-- ----------------------------------------------------------------------------
--  QUÉ HACE:        crea 2 índices. NO modifica ni borra ni un solo dato.
--  TOCA DATOS:      no.
--  REVERSIBLE:      sí, al 100% — ver el bloque "REVERSIÓN" al final.
--  IMPACTO:         ~22 MB de disco por cada 600.000 movimientos de kardex
--                   (medido: 4 MB el de usuario + 18 MB el de fecha) y un poco
--                   más de coste en cada INSERT al kardex; a cambio, tres
--                   consultas que hoy escanean la tabla entera pasan a
--                   resolverse por índice.
--  VALIDADO:        14-09-2026 contra la base local (PostgreSQL 18.3) sobre una
--                   copia temporal de inventario_kardex con 600.000 filas,
--                   comprobando resultado idéntico al de las consultas viejas.
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
--  ratón solo esa línea y pulsar F5. Son 2 veces.
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
--          UNA SENTENCIA A LA VEZ: seleccionar la línea y F5. Dos veces.
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


-- ============================================================================
-- PASO 3 - VERIFICAR QUE NINGUNO QUEDÓ INVÁLIDO  <- NO SALTARSE ESTE PASO
-- ============================================================================
-- CONCURRENTLY puede fallar a mitad (por un bloqueo, un timeout o una
-- desconexión) y deja el índice CREADO pero MARCADO COMO INVÁLIDO: existe,
-- ocupa disco, encarece las escrituras... y el planificador NO lo usa. Y no
-- avisa de nada.
--
-- Resultado esperado: 2 filas, ambas con valido = true.
-- Si alguna sale con valido = false, borrarla y volver a crearla:
--     DROP INDEX CONCURRENTLY IF EXISTS <nombre_del_indice>;
--     (y repetir su CREATE del PASO 2)

SELECT c.relname AS indice, i.indisvalid AS valido
FROM pg_index i
JOIN pg_class c ON c.oid = i.indexrelid
WHERE c.relname IN ('idx_kardex_empresa_usuario',
                    'idx_kardex_empresa_fecha')
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


-- ============================================================================
-- VARIANTE B - VENTANA DE MANTENIMIENTO (sistema cerrado)
--              Estas SÍ se pueden ejecutar todas juntas con F5, pero BLOQUEAN
--              las escrituras de inventario_kardex mientras construyen.
-- ============================================================================
-- CREATE INDEX IF NOT EXISTS idx_kardex_empresa_usuario ON public.inventario_kardex (id_empresa, created_by) WHERE eliminado = false;
-- CREATE INDEX IF NOT EXISTS idx_kardex_empresa_fecha   ON public.inventario_kardex (id_empresa, fecha_movimiento) WHERE eliminado = false;
-- ANALYZE public.inventario_kardex;
