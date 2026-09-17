-- =============================================================================
-- 20260917_diagnostico_busquedas.sql — SOLO LECTURA
-- -----------------------------------------------------------------------------
-- Qué hace  : mide, con datos reales de producción, cuánto tardan HOY las
--             búsquedas de los listados (y los autocompletar) para poder
--             calcular cuánto ganaría el motor de búsqueda con índices trigram.
--             Devuelve 6 secciones:
--               1. desde cuándo acumulan las estadísticas
--               2. búsquedas agrupadas por tabla principal (tiempo medio, máximo,
--                  bloques leídos por llamada = cuánto recorre cada búsqueda)
--               3. las 15 formas de consulta con búsqueda más lentas
--               4. los mismos listados SIN texto (línea base: abrir el listado)
--               5. volumen por empresa (qué tan grande es la empresa más grande)
--               6. entorno: extensiones disponibles, jit, work_mem
-- Toca datos: NO. Solo SELECT. No cambia configuración ni estadísticas.
-- Reversible: no aplica (no modifica nada).
-- Cómo      : pgAdmin → Query Tool sobre la base de producción → F5 → copiar la
--             grilla (una fila por sección, el detalle va en JSON). Si sale
--             cortado, usar "Save results to file".
-- Carga     : liviana, salvo la sección 5 (cuenta filas por empresa en 4 tablas;
--             puede tardar unos segundos en la tabla de facturas).
-- Requisito : extensión pg_stat_statements (ya instalada en producción).
-- =============================================================================
WITH st AS (
    SELECT queryid,
           sum(calls)                                   AS llamadas,
           sum(total_exec_time)                         AS total_ms,
           max(max_exec_time)                           AS max_ms,
           sum(rows)                                    AS filas,
           sum(shared_blks_hit + shared_blks_read)      AS bloques,
           sum(jit_generation_time + jit_inlining_time
             + jit_optimization_time + jit_emission_time) AS jit_ms,
           min(regexp_replace(query, '\s+', ' ', 'g'))  AS q
    FROM pg_stat_statements
    WHERE dbid = (SELECT oid FROM pg_database WHERE datname = current_database())
    GROUP BY queryid
),
clasificadas AS (
    SELECT st.*,
           -- Primera tabla después del primer FROM: identifica el módulo sin
           -- tener que enumerarlos uno por uno.
           COALESCE(lower(substring(q from '(?i)\sfrom\s+([a-z_][a-z0-9_]*)')), '(sin from)') AS tabla,
           -- Toda búsqueda de texto del sistema pasa por ILIKE (FiltrosBusqueda).
           (q ILIKE '%ilike%')                                    AS con_busqueda,
           -- Formas que usan el patrón de listado paginado (consulta única).
           (q ILIKE 'with pagina as materialized%' OR q ILIKE '%unnest(array(%') AS es_listado
    FROM st
)
SELECT 1 AS orden, 'estadisticas_desde' AS seccion,
       jsonb_build_object(
           'desde',  (SELECT stats_reset FROM pg_stat_statements_info),
           'ahora',  (now() AT TIME ZONE 'America/Guayaquil'),
           'formas_registradas', (SELECT count(*) FROM pg_stat_statements)
       ) AS detalle

UNION ALL
SELECT 2, 'busquedas_por_tabla', (
    SELECT COALESCE(jsonb_agg(x ORDER BY x.total_seg DESC), '[]'::jsonb) FROM (
        SELECT tabla,
               sum(llamadas)                                              AS llamadas,
               round((sum(total_ms) / 1000)::numeric, 1)                  AS total_seg,
               round((sum(total_ms) / NULLIF(sum(llamadas), 0))::numeric, 1) AS media_ms,
               round(max(max_ms)::numeric, 1)                             AS max_ms,
               round(sum(bloques)::numeric / NULLIF(sum(llamadas), 0), 0) AS bloques_por_llamada,
               round(sum(filas)::numeric / NULLIF(sum(llamadas), 0), 1)   AS filas_por_llamada,
               round((sum(jit_ms) / 1000)::numeric, 1)                    AS jit_seg
        FROM clasificadas
        WHERE con_busqueda
        GROUP BY tabla
        ORDER BY sum(total_ms) DESC
        LIMIT 20
    ) x
)

UNION ALL
SELECT 3, 'busquedas_mas_lentas', (
    SELECT COALESCE(jsonb_agg(x ORDER BY x.media_ms DESC), '[]'::jsonb) FROM (
        SELECT tabla,
               llamadas,
               round((total_ms / NULLIF(llamadas, 0))::numeric, 1) AS media_ms,
               round(max_ms::numeric, 1)                           AS max_ms,
               round((total_ms / 1000)::numeric, 1)                AS total_seg,
               round(bloques::numeric / NULLIF(llamadas, 0), 0)    AS bloques_por_llamada,
               round((jit_ms / NULLIF(llamadas, 0))::numeric, 1)   AS jit_ms_por_llamada,
               left(q, 300)                                        AS consulta
        FROM clasificadas
        WHERE con_busqueda AND llamadas >= 10
        ORDER BY (total_ms / NULLIF(llamadas, 0)) DESC
        LIMIT 15
    ) x
)

UNION ALL
SELECT 4, 'listados_sin_texto', (
    SELECT COALESCE(jsonb_agg(x ORDER BY x.total_seg DESC), '[]'::jsonb) FROM (
        SELECT tabla,
               sum(llamadas)                                              AS llamadas,
               round((sum(total_ms) / 1000)::numeric, 1)                  AS total_seg,
               round((sum(total_ms) / NULLIF(sum(llamadas), 0))::numeric, 1) AS media_ms,
               round(sum(bloques)::numeric / NULLIF(sum(llamadas), 0), 0) AS bloques_por_llamada
        FROM clasificadas
        WHERE es_listado AND NOT con_busqueda
        GROUP BY tabla
        ORDER BY sum(total_ms) DESC
        LIMIT 15
    ) x
)

UNION ALL
SELECT 5, 'volumen_por_empresa', (
    SELECT jsonb_build_object(
        'facturas',  (SELECT COALESCE(jsonb_agg(x ORDER BY x.filas DESC), '[]'::jsonb) FROM (
                          SELECT id_empresa, count(*) AS filas FROM ventas_cabecera WHERE eliminado = false
                          GROUP BY id_empresa ORDER BY count(*) DESC LIMIT 8) x),
        'compras',   (SELECT COALESCE(jsonb_agg(x ORDER BY x.filas DESC), '[]'::jsonb) FROM (
                          SELECT id_empresa, count(*) AS filas FROM compras_cabecera WHERE eliminado = false
                          GROUP BY id_empresa ORDER BY count(*) DESC LIMIT 8) x),
        'clientes',  (SELECT COALESCE(jsonb_agg(x ORDER BY x.filas DESC), '[]'::jsonb) FROM (
                          SELECT id_empresa, count(*) AS filas FROM clientes WHERE eliminado = false
                          GROUP BY id_empresa ORDER BY count(*) DESC LIMIT 8) x),
        'productos', (SELECT COALESCE(jsonb_agg(x ORDER BY x.filas DESC), '[]'::jsonb) FROM (
                          SELECT id_empresa, count(*) AS filas FROM productos WHERE eliminado = false
                          GROUP BY id_empresa ORDER BY count(*) DESC LIMIT 8) x),
        'tamano_tablas', (SELECT COALESCE(jsonb_agg(x ORDER BY x.filas_aprox DESC), '[]'::jsonb) FROM (
                          SELECT relname AS tabla,
                                 reltuples::bigint AS filas_aprox,
                                 pg_size_pretty(pg_total_relation_size(oid)) AS tamano
                          FROM pg_class
                          WHERE relname IN ('ventas_cabecera','ventas_detalle','compras_cabecera','compras_detalle',
                                            'clientes','productos','proveedores','ingresos_cabecera','egresos_cabecera',
                                            'consignaciones_ventas','consignaciones_ventas_detalles','pedidos_cabecera')
                            AND relkind = 'r') x)
    )
)

UNION ALL
SELECT 6, 'entorno_busqueda', (
    SELECT jsonb_build_object(
        'extensiones', (SELECT COALESCE(jsonb_agg(jsonb_build_object(
                             'nombre', name, 'disponible', default_version, 'instalada', installed_version)), '[]'::jsonb)
                        FROM pg_available_extensions
                        WHERE name IN ('pg_trgm', 'unaccent', 'btree_gin', 'pg_stat_statements')),
        'funcion_f_unaccent', (SELECT count(*) FROM pg_proc WHERE proname = 'f_unaccent'),
        'ajustes', (SELECT jsonb_object_agg(name, setting)
                    FROM pg_settings
                    WHERE name IN ('jit', 'jit_above_cost', 'work_mem', 'hash_mem_multiplier',
                                   'shared_buffers', 'effective_cache_size', 'max_connections',
                                   'default_statistics_target', 'server_version')),
        -- El costo de ILIKE depende de la intercalación de la base
        'intercalacion', (SELECT datcollate || ' / ' || datctype FROM pg_database WHERE datname = current_database()),
        'indices_gin_existentes', (SELECT COALESCE(jsonb_agg(jsonb_build_object(
                                        'tabla', t.relname, 'indice', i.relname,
                                        'tamano', pg_size_pretty(pg_relation_size(i.oid)))), '[]'::jsonb)
                                   FROM pg_index x
                                   JOIN pg_class i ON i.oid = x.indexrelid
                                   JOIN pg_class t ON t.oid = x.indrelid
                                   JOIN pg_am am ON am.oid = i.relam
                                   WHERE am.amname = 'gin' AND t.relnamespace = 'public'::regnamespace)
    )
)

ORDER BY orden;
