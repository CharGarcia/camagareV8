-- =============================================================================
-- 20260916_diagnostico_rendimiento_postgres.sql — SOLO LECTURA
-- -----------------------------------------------------------------------------
-- Qué hace  : lee las estadísticas de PostgreSQL (tamaño, caché, conexiones,
--             tablas que se recorren completas, índices, vacuum) y un resumen
--             del uso real del ERP (empresas, sesiones, actividad por hora y
--             por día, errores).
-- Toca datos: NO. Solo SELECT sobre catálogos y tablas del sistema.
-- Reversible: no aplica, no cambia nada.
-- Cómo      : pgAdmin → Query Tool sobre la base de producción → F5: devuelve
--             una fila por sección → copiar el resultado o "Save results to
--             file" (CSV).
-- Carga     : liviana. log_sistema se limita a sus últimas 300.000 filas por id
--             (índice de la PK), así que no recorre la tabla completa.
-- Si falla  : por una tabla que no exista en esa base, borrar esa sección
--             (su bloque UNION ALL) y volver a ejecutar.
-- =============================================================================


-- ── CONSULTA 1 ───────────────────────────────────────────────────────────────
WITH
db AS (
    SELECT * FROM pg_stat_database WHERE datname = current_database()
),
-- Las columnas de fecha del sistema son TIMESTAMP sin zona, guardadas en hora de
-- Guayaquil; pgAdmin puede estar en UTC, así que se compara contra la hora local.
ahora_local AS (
    SELECT (now() AT TIME ZONE 'America/Guayaquil') AS ts
)
SELECT 1 AS orden, 'servidor' AS seccion, jsonb_build_object(
    'version', version(),
    'base', current_database(),
    'tamano_base', pg_size_pretty(pg_database_size(current_database())),
    'arranque_servidor', pg_postmaster_start_time(),
    'estadisticas_desde', (SELECT stats_reset FROM db),
    'ahora', now()
) AS detalle

UNION ALL
SELECT 2, 'configuracion', (
    SELECT jsonb_object_agg(name, CASE unit
               WHEN '8kB' THEN pg_size_pretty(setting::bigint * 8192)
               WHEN 'kB'  THEN pg_size_pretty(setting::bigint * 1024)
               WHEN 'MB'  THEN pg_size_pretty(setting::bigint * 1048576)
               ELSE setting || COALESCE(' ' || unit, '') END)
    FROM pg_settings
    WHERE name IN ('max_connections', 'shared_buffers', 'effective_cache_size', 'work_mem',
                   'maintenance_work_mem', 'random_page_cost', 'effective_io_concurrency',
                   'max_parallel_workers_per_gather', 'jit', 'shared_preload_libraries',
                   'track_io_timing', 'log_min_duration_statement', 'statement_timeout',
                   'idle_in_transaction_session_timeout', 'TimeZone', 'autovacuum')
)

UNION ALL
SELECT 3, 'uso_base', (
    SELECT jsonb_build_object(
        'conexiones_ahora', numbackends,
        'commits', xact_commit,
        'rollbacks', xact_rollback,
        'cache_hit_pct', round(100.0 * blks_hit / NULLIF(blks_hit + blks_read, 0), 2),
        'filas_devueltas', tup_returned,
        'filas_por_indice', tup_fetched,
        'insertadas', tup_inserted,
        'actualizadas', tup_updated,
        'borradas', tup_deleted,
        'archivos_temporales', temp_files,
        'bytes_temporales', pg_size_pretty(temp_bytes),
        'deadlocks', deadlocks)
    FROM db
)

UNION ALL
SELECT 4, 'conexiones_por_estado', (
    SELECT jsonb_agg(x ORDER BY x.total DESC) FROM (
        SELECT usename AS usuario,
               application_name AS app,
               client_addr::text AS origen,
               COALESCE(state, backend_type) AS estado,
               count(*) AS total,
               max(now() - state_change)::text AS mas_antigua_en_ese_estado
        FROM pg_stat_activity
        WHERE datname = current_database()
        GROUP BY 1, 2, 3, 4
    ) x
)

UNION ALL
SELECT 5, 'consultas_lentas_ahora', (
    SELECT COALESCE(jsonb_agg(x), '[]'::jsonb) FROM (
        SELECT pid,
               usename AS usuario,
               state AS estado,
               (now() - query_start)::text AS duracion,
               wait_event_type AS esperando,
               left(regexp_replace(query, '\s+', ' ', 'g'), 250) AS consulta
        FROM pg_stat_activity
        WHERE datname = current_database()
          AND pid <> pg_backend_pid()
          AND state IS DISTINCT FROM 'idle'
          AND now() - query_start > interval '3 seconds'
        ORDER BY query_start
        LIMIT 15
    ) x
)

UNION ALL
SELECT 6, 'tablas_mas_grandes', (
    SELECT jsonb_agg(x) FROM (
        SELECT relname AS tabla,
               pg_size_pretty(pg_total_relation_size(relid)) AS total,
               pg_size_pretty(pg_relation_size(relid)) AS datos,
               pg_size_pretty(pg_indexes_size(relid)) AS indices,
               n_live_tup AS filas,
               n_dead_tup AS muertas
        FROM pg_stat_user_tables
        ORDER BY pg_total_relation_size(relid) DESC
        LIMIT 25
    ) x
)

UNION ALL
SELECT 7, 'tablas_recorridas_completas', (
    SELECT jsonb_agg(x) FROM (
        SELECT relname AS tabla,
               seq_scan AS recorridos_completos,
               seq_tup_read AS filas_leidas_asi,
               seq_tup_read / NULLIF(seq_scan, 0) AS filas_por_recorrido,
               COALESCE(idx_scan, 0) AS usos_de_indice,
               n_live_tup AS filas
        FROM pg_stat_user_tables
        WHERE seq_scan > 0
        ORDER BY seq_tup_read DESC
        LIMIT 30
    ) x
)

UNION ALL
SELECT 8, 'tablas_leidas_de_disco', (
    SELECT jsonb_agg(x) FROM (
        SELECT relname AS tabla,
               heap_blks_read AS bloques_de_disco,
               heap_blks_hit AS bloques_de_cache,
               round(100.0 * heap_blks_hit / NULLIF(heap_blks_hit + heap_blks_read, 0), 2) AS hit_pct,
               COALESCE(idx_blks_read, 0) AS bloques_indice_de_disco
        FROM pg_statio_user_tables
        ORDER BY heap_blks_read + COALESCE(idx_blks_read, 0) DESC
        LIMIT 15
    ) x
)

UNION ALL
SELECT 9, 'indices_sin_uso', (
    SELECT COALESCE(jsonb_agg(x), '[]'::jsonb) FROM (
        SELECT s.relname AS tabla,
               s.indexrelname AS indice,
               pg_size_pretty(pg_relation_size(s.indexrelid)) AS tamano
        FROM pg_stat_user_indexes s
        JOIN pg_index i ON i.indexrelid = s.indexrelid
        WHERE s.idx_scan = 0 AND NOT i.indisunique AND NOT i.indisprimary
        ORDER BY pg_relation_size(s.indexrelid) DESC
        LIMIT 25
    ) x
)

UNION ALL
SELECT 10, 'indices_invalidos', (
    SELECT COALESCE(jsonb_agg(c.relname), '[]'::jsonb)
    FROM pg_index i
    JOIN pg_class c ON c.oid = i.indexrelid
    WHERE NOT i.indisvalid
)

UNION ALL
SELECT 11, 'vacuum', (
    SELECT jsonb_agg(x) FROM (
        SELECT relname AS tabla,
               n_live_tup AS filas,
               n_dead_tup AS muertas,
               round(100.0 * n_dead_tup / NULLIF(n_live_tup + n_dead_tup, 0), 1) AS muertas_pct,
               last_autovacuum AS ultimo_autovacuum,
               last_autoanalyze AS ultimo_autoanalyze
        FROM pg_stat_user_tables
        ORDER BY n_dead_tup DESC
        LIMIT 15
    ) x
)

UNION ALL
SELECT 12, 'extensiones', jsonb_build_object(
    'instaladas', (SELECT jsonb_agg(extname || ' ' || extversion) FROM pg_extension),
    'pg_stat_statements_disponible', EXISTS (SELECT 1 FROM pg_available_extensions WHERE name = 'pg_stat_statements'),
    'pg_stat_statements_instalada', EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_stat_statements')
)

UNION ALL
SELECT 13, 'uso_sistema', jsonb_build_object(
    'empresas_activas', (SELECT count(*) FROM empresas WHERE estado = '1' AND eliminado = false),
    'usuarios_activos_24h', (SELECT count(DISTINCT s.id_usuario) FROM sesiones_activas s, ahora_local a
                             WHERE s.ultima_actividad >= a.ts - interval '1 day'),
    'usuarios_activos_7d', (SELECT count(DISTINCT s.id_usuario) FROM sesiones_activas s, ahora_local a
                            WHERE s.ultima_actividad >= a.ts - interval '7 days'),
    'sesiones_abiertas_15min', (SELECT count(*) FROM sesiones_activas s, ahora_local a
                                WHERE s.activa AND s.ultima_actividad >= a.ts - interval '15 minutes')
)

UNION ALL
SELECT 14, 'acciones_por_hora_14d', (
    SELECT jsonb_object_agg(lpad(hora::text, 2, '0'), acciones) FROM (
        SELECT extract(hour FROM l.created_at)::int AS hora, count(*) AS acciones
        FROM log_sistema l, ahora_local a
        WHERE l.id > (SELECT max(id) - 300000 FROM log_sistema)
          AND l.created_at >= a.ts - interval '14 days'
        GROUP BY 1
    ) x
)

UNION ALL
SELECT 15, 'actividad_por_dia_14d', (
    SELECT jsonb_object_agg(dia, jsonb_build_object('acciones', acciones, 'empresas', empresas, 'usuarios', usuarios)) FROM (
        SELECT l.created_at::date::text AS dia,
               count(*) AS acciones,
               count(DISTINCT l.id_empresa) AS empresas,
               count(DISTINCT l.id_usuario) AS usuarios
        FROM log_sistema l, ahora_local a
        WHERE l.id > (SELECT max(id) - 300000 FROM log_sistema)
          AND l.created_at >= a.ts - interval '14 days'
        GROUP BY 1
    ) x
)

UNION ALL
SELECT 16, 'errores_por_dia_14d', (
    SELECT COALESCE(jsonb_object_agg(dia, jsonb_build_object('total', total, 'de_base_datos', de_bd)), '{}'::jsonb) FROM (
        SELECT e.created_at::date::text AS dia,
               count(*) AS total,
               count(*) FILTER (WHERE e.sql_state IS NOT NULL) AS de_bd
        FROM errores_sistema e, ahora_local a
        WHERE e.created_at >= a.ts - interval '14 days'
        GROUP BY 1
    ) x
)

ORDER BY orden;

-- Siguiente paso: 20260916_diagnostico_consultas_pesadas_postgres.sql
-- (necesita pg_stat_statements; ver la fila "extensiones").
