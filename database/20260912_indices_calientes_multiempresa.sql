-- ============================================================================
--  20260912_indices_calientes_multiempresa.sql
--  Los 5 índices más urgentes de las tablas multiempresa que hoy se recorren
--  completas en cada carga de página / cada documento emitido.
-- ----------------------------------------------------------------------------
--  QUÉ HACE:        crea 5 índices. NO modifica ni borra ni un solo dato.
--  TOCA DATOS:      no.
--  REVERSIBLE:      sí, al 100% — ver el bloque "REVERSIÓN" al final.
--  IMPACTO:         cada índice ocupa disco y encarece un poco las escrituras
--                   de su tabla; a cambio evita el escaneo completo en lectura.
--  VALIDADO:        12-09-2026 contra la base local (PostgreSQL 18.3) creando
--                   los 5 dentro de una transacción con ROLLBACK y comprobando
--                   con EXPLAIN que el planificador los usa y que resuelven el
--                   ORDER BY sin paso de Sort.
--
--  ⚠️  LEER ANTES DE EJECUTAR — CÓMO SE EJECUTA ESTO EN pgAdmin
--  Las sentencias del PASO 2 usan CONCURRENTLY, que NO puede ejecutarse dentro
--  de un bloque de transacción. PostgreSQL envuelve en una transacción implícita
--  cualquier envío con más de una sentencia, así que pegar todo el archivo y
--  pulsar F5 falla con:
--        ERROR: CREATE INDEX CONCURRENTLY cannot run inside a transaction block
--  Esto NO se arregla con "Auto commit ON": es comportamiento del servidor.
--
--  En el Query Tool de pgAdmin hay que enviar UNA sentencia a la vez:
--  seleccionar con el ratón solo esa línea y pulsar F5. Son 5 veces.
--
--  ¿Por qué CONCURRENTLY y no un CREATE INDEX normal? Porque un CREATE INDEX
--  normal BLOQUEA los INSERT / UPDATE / DELETE de esa tabla mientras construye
--  el índice. Con las empresas trabajando, eso congela la facturación de todas.
--  CONCURRENTLY no bloquea escrituras: tarda más, pero nadie se entera.
--
--  Si se prefiere hacerlo en una ventana de mantenimiento con el sistema
--  cerrado, al final está la VARIANTE B (sin CONCURRENTLY), que sí se ejecuta
--  de una sola vez con F5.
-- ============================================================================


-- ============================================================================
-- PASO 1 · DIAGNÓSTICO PREVIO (solo lectura — ejecutar y LEER el resultado)
-- ============================================================================
-- Objetivo: no crear un índice que ya existe con otro nombre. El IF NOT EXISTS
-- de PostgreSQL compara solo el NOMBRE: un índice equivalente creado con otro
-- nombre pasa desapercibido y quedarían dos, ocupando disco y encareciendo cada
-- escritura por duplicado.
--
-- Qué buscar en el resultado: si alguna de las 5 tablas ya tiene un índice cuya
-- PRIMERA COLUMNA y definición coincidan con lo que se va a crear abajo,
-- SALTARSE ese índice.
--
-- Lo que se esperaba encontrar en producción (medido en local el 12-09-2026):
--   modulos_asignados  → solo la PK
--   plan_cuentas       → solo la PK
--   productos_bodegas  → PK + UNIQUE (id_producto, id_bodega)   ← distinto, no estorba
--   productos_precios  → solo la PK
--   proveedores        → solo la PK

SELECT t.relname                        AS tabla,
       c.relname                        AS indice,
       a.attname                        AS primera_columna,
       i.indisunique                    AS es_unico,
       i.indisvalid                     AS valido,
       pg_size_pretty(pg_relation_size(i.indexrelid)) AS tamano,
       pg_get_indexdef(i.indexrelid)    AS definicion
FROM pg_index i
JOIN pg_class     c ON c.oid = i.indexrelid
JOIN pg_class     t ON t.oid = i.indrelid
JOIN pg_namespace n ON n.oid = t.relnamespace
JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = i.indkey[0]
WHERE n.nspname = 'public'
  AND t.relname IN ('modulos_asignados','plan_cuentas','productos_bodegas',
                    'productos_precios','proveedores')
ORDER BY t.relname, c.relname;


-- ============================================================================
-- PASO 2 · CREAR LOS ÍNDICES
--          ⚠️  UNA SENTENCIA A LA VEZ: seleccionar la línea y F5. Cinco veces.
--          Van ordenadas de la más rápida a la más lenta.
-- ============================================================================

-- 1) modulos_asignados — PERMISOS. Es la consulta más repetida de todo el
--    sistema: se resuelve en CADA petición de CADA usuario. Hoy la tabla solo
--    tiene la PK, así que cada carga de página la recorre completa (todas las
--    empresas, todos los usuarios, todos los submódulos).
--    Patrón real en el código: WHERE id_usuario = ? AND id_empresa = ?
--    [AND id_submodulo = ?]. Va liderado por id_usuario porque está en todas
--    las consultas y es la columna más selectiva.
--    (Esta tabla no tiene columna `eliminado`.)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_modulos_asignados_usuario_empresa ON public.modulos_asignados (id_usuario, id_empresa, id_submodulo);

-- 2) productos_precios — se consulta al armar cada línea de factura.
--    Patrón real: WHERE id_empresa = ? AND eliminado = false
--    [AND id_producto = ? | AND id_producto IN (...)].
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_productos_precios_empresa_producto ON public.productos_precios (id_empresa, eliminado, id_producto);

-- 3) productos_bodegas — STOCK. El UNIQUE que ya existe (id_producto, id_bodega)
--    cubre la búsqueda puntual de un producto en una bodega, pero NO cubre el
--    listado de stock de una bodega ni el de la empresa, que hoy recorren la
--    tabla entera. Además id_bodega es una clave foránea sin índice.
--    Patrón real: WHERE id_empresa = ? [AND id_bodega = ?] [AND eliminado = false].
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_productos_bodegas_empresa_bodega ON public.productos_bodegas (id_empresa, id_bodega, id_producto);

-- 4) plan_cuentas — CONTABILIDAD. Se consulta en cada asiento y en cada
--    selector de cuentas. Patrón real: WHERE id_empresa = ? AND eliminado = false
--    ORDER BY codigo. Con este índice el ORDER BY se resuelve leyendo el índice
--    en orden, sin paso de Sort (verificado con EXPLAIN). El orden de columnas
--    también sirve a la consulta que filtra solo por id_empresa.
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_plan_cuentas_empresa_codigo ON public.plan_cuentas (id_empresa, eliminado, codigo);

-- 5) proveedores — listados de Compras, selectores y validación de RUC
--    duplicado. Hoy solo tiene la PK y es de las tablas que más crecen.
--    Patrón real: WHERE id_empresa = ? AND eliminado = false
--    ORDER BY razon_social ASC [LIMIT n]. Igual que el anterior: el ORDER BY
--    sale del índice, sin Sort.
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_proveedores_empresa_razon_social ON public.proveedores (id_empresa, eliminado, razon_social);


-- ============================================================================
-- PASO 3 · VERIFICAR QUE NINGUNO QUEDÓ INVÁLIDO  ← NO SALTARSE ESTE PASO
-- ============================================================================
-- CONCURRENTLY puede fallar a mitad de camino (por un bloqueo, un timeout o una
-- desconexión) y deja el índice CREADO pero MARCADO COMO INVÁLIDO: existe,
-- ocupa disco, encarece las escrituras... y el planificador NO lo usa. Y no
-- avisa de nada.
--
-- Resultado esperado: 5 filas, todas con valido = true.
-- Si alguna sale con valido = false, borrarla y volver a crearla:
--     DROP INDEX CONCURRENTLY IF EXISTS <nombre_del_indice>;
--     (y repetir su CREATE del PASO 2)

SELECT c.relname AS indice, i.indisvalid AS valido
FROM pg_index i
JOIN pg_class c ON c.oid = i.indexrelid
WHERE c.relname IN ('idx_modulos_asignados_usuario_empresa',
                    'idx_productos_precios_empresa_producto',
                    'idx_productos_bodegas_empresa_bodega',
                    'idx_plan_cuentas_empresa_codigo',
                    'idx_proveedores_empresa_razon_social')
ORDER BY c.relname;


-- ============================================================================
-- PASO 4 · ACTUALIZAR ESTADÍSTICAS
-- ============================================================================
-- Sin esto el planificador puede tardar en darse cuenta de que los índices
-- existen. ANALYZE sí admite ejecutarse todo junto: este bloque se puede pegar
-- completo y ejecutar con F5. No bloquea el uso normal del sistema.

ANALYZE public.modulos_asignados;
ANALYZE public.productos_precios;
ANALYZE public.productos_bodegas;
ANALYZE public.plan_cuentas;
ANALYZE public.proveedores;


-- ============================================================================
-- PASO 5 · COMPROBACIÓN FINAL (deben salir 5 filas)
-- ============================================================================

SELECT indexname, tablename, pg_size_pretty(pg_relation_size(indexname::regclass)) AS tamano
FROM pg_indexes
WHERE schemaname = 'public'
  AND indexname IN ('idx_modulos_asignados_usuario_empresa',
                    'idx_productos_precios_empresa_producto',
                    'idx_productos_bodegas_empresa_bodega',
                    'idx_plan_cuentas_empresa_codigo',
                    'idx_proveedores_empresa_razon_social')
ORDER BY indexname;


-- ============================================================================
-- PASO 6 · A LOS 2-3 DÍAS: ¿SE ESTÁN USANDO DE VERDAD?  (solo lectura)
-- ============================================================================
-- Un índice que nunca se usa es puro costo: ocupa disco y ralentiza cada
-- escritura sin dar nada. Lo normal es que idx_scan crezca rápido en
-- modulos_asignados (cada petición) y plan_cuentas.
-- Si alguno sigue en 0 después de una semana de uso normal, sobra: borrarlo con
-- el bloque de REVERSIÓN.

SELECT indexrelname AS indice, relname AS tabla, idx_scan AS veces_usado,
       pg_size_pretty(pg_relation_size(indexrelid)) AS tamano
FROM pg_stat_user_indexes
WHERE indexrelname IN ('idx_modulos_asignados_usuario_empresa',
                       'idx_productos_precios_empresa_producto',
                       'idx_productos_bodegas_empresa_bodega',
                       'idx_plan_cuentas_empresa_codigo',
                       'idx_proveedores_empresa_razon_social')
ORDER BY idx_scan DESC;


-- ============================================================================
-- VARIANTE B · SIN CONCURRENTLY (solo con el sistema cerrado)
-- ============================================================================
-- Este bloque SÍ se puede pegar completo y ejecutar de una vez con F5, porque
-- no usa CONCURRENTLY. En cambio BLOQUEA las escrituras de cada tabla mientras
-- construye su índice: nadie podrá guardar nada en esas tablas durante ese
-- rato. Usarlo únicamente en una ventana de mantenimiento con los usuarios
-- fuera. En tablas pequeñas es cuestión de segundos; en `proveedores` de una
-- base grande puede ser bastante más.
--
-- Descomentar para usar:
--
-- CREATE INDEX IF NOT EXISTS idx_modulos_asignados_usuario_empresa  ON public.modulos_asignados  (id_usuario, id_empresa, id_submodulo);
-- CREATE INDEX IF NOT EXISTS idx_productos_precios_empresa_producto ON public.productos_precios  (id_empresa, eliminado, id_producto);
-- CREATE INDEX IF NOT EXISTS idx_productos_bodegas_empresa_bodega   ON public.productos_bodegas  (id_empresa, id_bodega, id_producto);
-- CREATE INDEX IF NOT EXISTS idx_plan_cuentas_empresa_codigo        ON public.plan_cuentas       (id_empresa, eliminado, codigo);
-- CREATE INDEX IF NOT EXISTS idx_proveedores_empresa_razon_social   ON public.proveedores        (id_empresa, eliminado, razon_social);
-- ANALYZE public.modulos_asignados;
-- ANALYZE public.productos_precios;
-- ANALYZE public.productos_bodegas;
-- ANALYZE public.plan_cuentas;
-- ANALYZE public.proveedores;


-- ============================================================================
-- REVERSIÓN
-- ============================================================================
-- Borrar un índice no toca ni un dato: el sistema vuelve exactamente al estado
-- anterior (más lento, nada más). DROP INDEX CONCURRENTLY tampoco admite
-- transacción: también va UNA SENTENCIA A LA VEZ.
--
-- DROP INDEX CONCURRENTLY IF EXISTS idx_modulos_asignados_usuario_empresa;
-- DROP INDEX CONCURRENTLY IF EXISTS idx_productos_precios_empresa_producto;
-- DROP INDEX CONCURRENTLY IF EXISTS idx_productos_bodegas_empresa_bodega;
-- DROP INDEX CONCURRENTLY IF EXISTS idx_plan_cuentas_empresa_codigo;
-- DROP INDEX CONCURRENTLY IF EXISTS idx_proveedores_empresa_razon_social;


-- ============================================================================
-- NOTA · DOS TABLAS QUE PARECÍAN NECESITARLO Y NO LO NECESITAN
-- ============================================================================
-- Al revisar las consultas reales antes de escribir este archivo, dos de las
-- candidatas iniciales se descartaron. Se deja constancia para no volver a
-- proponerlas:
--
-- · usuarios_preferencias: aparece en la lista de tablas «sin índice liderado
--   por id_empresa», pero ya tiene el UNIQUE
--   usuarios_preferencias_id_usuario_id_empresa_modulo_key
--   (id_usuario, id_empresa, modulo), y la consulta del repositorio es
--   exactamente WHERE id_usuario = ? AND id_empresa = ? AND modulo = ?.
--   Ya está cubierta: un índice nuevo solo añadiría costo.
--
-- · empresa_secuencial: ya tiene uq_secuencial_punto_tipo
--   (id_punto_emision, tipo_documento) WHERE eliminado = false, que es
--   justamente cómo se busca el secuencial al emitir. Cubierta.
--
-- La lección general: «tener id_empresa sin índice que lo lidere» es una señal
-- para ir a mirar, no una conclusión. Lo que decide es la consulta real.
-- ============================================================================
