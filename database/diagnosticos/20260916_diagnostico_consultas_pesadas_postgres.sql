-- =============================================================================
-- 20260916_diagnostico_consultas_pesadas_postgres.sql — SOLO LECTURA
-- -----------------------------------------------------------------------------
-- Segunda parte de 20260916_diagnostico_rendimiento_postgres.sql.
-- Qué hace  : con pg_stat_statements (instalada en producción) lista las
--             consultas que más tiempo de base consumen, las que escriben a
--             disco temporal y las que pagan compilación JIT. Además, los
--             índices que YA existen en las tablas más recorridas (para no
--             proponer duplicados) y los errores más repetidos de la semana.
-- Toca datos: NO. Solo SELECT.
-- Cómo      : pgAdmin → Query Tool sobre la base de producción → F5 → copiar
--             el resultado. Si al pegarlo sale cortado, usar
--             "Save results to file".
-- Carga     : liviana.
-- =============================================================================
WITH
ahora_local AS (
    SELECT (now() AT TIME ZONE 'America/Guayaquil') AS ts
),
st AS (
    SELECT queryid,
           sum(calls) AS llamadas,
           sum(total_exec_time) AS total_ms,
           max(max_exec_time) AS max_ms,
           sum(rows) AS filas,
           sum(shared_blks_hit) AS blq_cache,
           sum(shared_blks_read) AS blq_disco,
           sum(temp_blks_written) AS blq_temp,
           sum(jit_generation_time + jit_inlining_time + jit_optimization_time + jit_emission_time) AS jit_ms,
           min(regexp_replace(query, '\s+', ' ', 'g')) AS q
    FROM pg_stat_statements
    WHERE dbid = (SELECT oid FROM pg_database WHERE datname = current_database())
    GROUP BY queryid
),
st_fmt AS (
    SELECT st.*,
           round((total_ms / 1000)::numeric, 1) AS total_seg,
           round((total_ms / NULLIF(llamadas, 0))::numeric, 1) AS media_ms,
           -- Consultas largas: el inicio y luego desde el FROM, que es donde está el WHERE
           CASE WHEN length(q) <= 700 THEN q
                ELSE left(q, 350) || ' … ' || substr(q, greatest(strpos(lower(q), ' from '), 351), 350)
           END AS consulta
    FROM st
)
SELECT 1 AS orden, 'estadisticas_desde' AS seccion,
       to_jsonb((SELECT stats_reset FROM pg_stat_statements_info)) AS detalle

UNION ALL
SELECT 2, 'consultas_mas_costosas', (
    SELECT jsonb_agg(x ORDER BY x.total_seg DESC) FROM (
        SELECT total_seg,
               llamadas,
               media_ms,
               round(max_ms::numeric, 1) AS max_ms,
               filas,
               round(100.0 * blq_cache / NULLIF(blq_cache + blq_disco, 0), 1) AS cache_pct,
               pg_size_pretty(blq_temp * 8192) AS temporal,
               round((jit_ms / 1000)::numeric, 1) AS jit_seg,
               consulta
        FROM st_fmt
        ORDER BY total_ms DESC
        LIMIT 40
    ) x
)

UNION ALL
SELECT 3, 'consultas_con_disco_temporal', (
    SELECT COALESCE(jsonb_agg(x ORDER BY x.blq_temp DESC), '[]'::jsonb) FROM (
        SELECT blq_temp, pg_size_pretty(blq_temp * 8192) AS temporal, llamadas, total_seg, media_ms, consulta
        FROM st_fmt
        WHERE blq_temp > 0
        ORDER BY blq_temp DESC
        LIMIT 10
    ) x
)

UNION ALL
SELECT 4, 'consultas_con_mas_jit', (
    SELECT COALESCE(jsonb_agg(x ORDER BY x.jit_seg DESC), '[]'::jsonb) FROM (
        SELECT round((jit_ms / 1000)::numeric, 1) AS jit_seg, total_seg, llamadas, media_ms, consulta
        FROM st_fmt
        WHERE jit_ms > 0
        ORDER BY jit_ms DESC
        LIMIT 10
    ) x
)

UNION ALL
SELECT 5, 'indices_existentes_tablas_calientes', (
    SELECT COALESCE(jsonb_agg(x ORDER BY x.tabla, x.indice), '[]'::jsonb) FROM (
        SELECT s.relname AS tabla,
               s.indexrelname AS indice,
               s.idx_scan AS usos,
               pg_size_pretty(pg_relation_size(s.indexrelid)) AS tamano,
               pg_get_indexdef(s.indexrelid) AS definicion
        FROM pg_stat_user_indexes s
        WHERE s.relname IN ('consignaciones_ventas_detalles', 'egresos_detalle', 'ventas_adicional',
                            'compras_adicional', 'ventas_detalle', 'ventas_pagos', 'modulos_asignados',
                            'ingresos_detalle', 'ventas_detalle_impuestos', 'retencion_venta_detalle',
                            'egresos_pagos', 'consignaciones_ventas', 'liquidaciones_pagos',
                            'inventario_kardex', 'pedidos_cabecera', 'asientos_contables_detalle',
                            'tareas', 'tareas_responsables', 'empresa_casilleros_iva_sri')
    ) x
)

UNION ALL
SELECT 6, 'errores_mas_repetidos_7d', (
    SELECT COALESCE(jsonb_agg(x ORDER BY x.veces DESC), '[]'::jsonb) FROM (
        SELECT count(*) AS veces,
               count(DISTINCT e.id_empresa) AS empresas,
               max(e.created_at) AS ultima,
               e.tipo,
               e.clase,
               e.ruta,
               regexp_replace(left(e.mensaje, 180), '\d+', 'N', 'g') AS mensaje
        FROM errores_sistema e, ahora_local a
        WHERE e.created_at >= a.ts - interval '7 days'
        GROUP BY e.tipo, e.clase, e.ruta, regexp_replace(left(e.mensaje, 180), '\d+', 'N', 'g')
        ORDER BY count(*) DESC
        LIMIT 30
    ) x
)

ORDER BY orden;
