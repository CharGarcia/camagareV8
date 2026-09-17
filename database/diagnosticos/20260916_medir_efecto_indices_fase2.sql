-- =============================================================================
-- 20260916_medir_efecto_indices_fase2.sql — SOLO LECTURA
-- -----------------------------------------------------------------------------
-- Qué hace  : compara el estado actual contra la línea base tomada el 16-09-2026 a
--             las 22:03 (hora de Ecuador), justo antes de crear los índices de
--             20260916_indices_calientes_fase2.sql:
--               1) horas transcurridas desde la línea base,
--               2) media por llamada ANTES y DESPUÉS de las consultas que atacan
--                  los índices,
--               3) recorridos completos de tabla nuevos (deberían caer mucho en
--                  ventas_adicional, compras_adicional, ventas_pagos,
--                  liquidaciones_pagos),
--               4) usos de los 6 índices nuevos,
--               5) actividad de la base desde la línea base.
--             Mide por DIFERENCIA: no hace falta reiniciar estadísticas. Si alguien
--             las reinició después de la línea base, lo detecta y usa los valores
--             actuales tal cual.
-- Toca datos: NO.
-- Cómo      : pgAdmin → Query Tool sobre producción → F5 → copiar el resultado.
--             Se puede repetir cuantas veces se quiera; tras un día de trabajo
--             completo los números ya son confiables.
-- =============================================================================
WITH
linea_base AS (
    SELECT timestamptz '2026-09-17 03:03:17+00' AS ts
),
reinicio AS (
    SELECT COALESCE((SELECT stats_reset FROM pg_stat_statements_info) > (SELECT ts FROM linea_base), false) AS consultas,
           COALESCE((SELECT stats_reset FROM pg_stat_database WHERE datname = current_database())
                    > (SELECT ts FROM linea_base), false) AS base_datos
),
-- Valores de pg_stat_statements tomados ANTES de los índices (llamadas, tiempo total en ms)
base_consultas (orden, nombre, texto, exacta, llamadas, total_ms) AS (VALUES
    (1, 'Caducidad de consignación con lote (migración)',
        'UPDATE consignaciones_ventas_detalles SET fecha_caducidad = $1, updated_at = now() WHERE id_consignacion = $2 AND id_producto = $3 AND COALESCE(lote',
        false, 273878::numeric, 18227400::numeric),
    (2, 'Caducidad de consignación sin lote (migración)',
        'UPDATE consignaciones_ventas_detalles SET fecha_caducidad = $1, updated_at = now() WHERE id_consignacion = $2 AND id_producto = $3',
        true, 115491, 4786400),
    (3, 'Línea de consignación por producto',
        'SELECT id FROM consignaciones_ventas_detalles WHERE id_consignacion = $1 AND id_producto = $2 AND eliminado = $3 LIMIT $4',
        true, 129891, 4361700),
    (4, 'Borrar info adicional de factura', 'DELETE FROM ventas_adicional WHERE id_venta = $1', true, 98401, 3311500),
    (5, 'Borrar info adicional de compra', 'DELETE FROM compras_adicional WHERE id_compra = $1', true, 89588, 2023600),
    (6, 'Leer info adicional de factura', 'SELECT * FROM ventas_adicional WHERE id_venta = $1', true, 18317, 1426200),
    (7, 'Borrar formas de pago de factura', 'DELETE FROM ventas_pagos WHERE id_venta = $1', true, 98401, 747200),
    (8, '¿El producto tiene ventas?', 'SELECT $2 FROM ventas_detalle WHERE id_producto = $1 LIMIT $3', true, 14074, 353300)
),
st AS (
    SELECT regexp_replace(btrim(query), '\s+', ' ', 'g') AS q, calls, total_exec_time
    FROM pg_stat_statements
    WHERE dbid = (SELECT oid FROM pg_database WHERE datname = current_database())
),
actual_consultas AS (
    SELECT b.orden, b.nombre, b.llamadas AS base_llamadas, b.total_ms AS base_ms,
           COALESCE(sum(st.calls), 0)::numeric AS llamadas,
           COALESCE(sum(st.total_exec_time), 0)::numeric AS total_ms
    FROM base_consultas b
    LEFT JOIN st ON (b.exacta AND st.q = b.texto)
                 OR (NOT b.exacta AND st.q LIKE b.texto || '%')
    GROUP BY b.orden, b.nombre, b.llamadas, b.total_ms
),
-- Si la consulta ya no está en pg_stat_statements (la extensión descarta las menos
-- usadas cuando se llena), queda NULL en vez de una diferencia negativa engañosa.
nuevas_consultas AS (
    SELECT a.*,
           CASE WHEN r.consultas THEN a.llamadas
                WHEN a.llamadas >= a.base_llamadas THEN a.llamadas - a.base_llamadas END AS n_llamadas,
           CASE WHEN r.consultas THEN a.total_ms
                WHEN a.llamadas >= a.base_llamadas THEN a.total_ms - a.base_ms END AS n_ms
    FROM actual_consultas a CROSS JOIN reinicio r
),
-- Recorridos completos por tabla ANTES de los índices (seq_scan, seq_tup_read)
base_tablas (tabla, seq_scan, seq_tup_read) AS (VALUES
    ('consignaciones_ventas_detalles', 768563::bigint, 135379378848::bigint),
    ('egresos_detalle',                546069,  38933076866),
    ('ventas_adicional',               142385,  35937419989),
    ('compras_adicional',              100253,  18671896266),
    ('ventas_detalle',                 117793,  9400604414),
    ('ventas_pagos',                   116904,  8780556561),
    ('modulos_asignados',              1213192, 7336435085),
    ('ingresos_detalle',               140505,  7116678787),
    ('ventas_detalle_impuestos',       28011,   4104729491),
    ('retencion_venta_detalle',        147022,  4079952937),
    ('egresos_pagos',                  94412,   3190869748),
    ('liquidaciones_pagos',            43838,   65373806)
),
-- Contador menor que la línea base = las estadísticas se perdieron (reinicio o caída
-- de la base): se toman los valores actuales tal cual.
nuevas_tablas AS (
    SELECT b.tabla, t.n_live_tup,
           CASE WHEN r.base_datos OR t.seq_scan < b.seq_scan THEN t.seq_scan
                ELSE t.seq_scan - b.seq_scan END AS recorridos,
           CASE WHEN r.base_datos OR t.seq_tup_read < b.seq_tup_read THEN t.seq_tup_read
                ELSE t.seq_tup_read - b.seq_tup_read END AS filas_leidas
    FROM base_tablas b
    JOIN pg_stat_user_tables t ON t.relname = b.tabla
    CROSS JOIN reinicio r
)
SELECT 1 AS orden, 'horas_desde_linea_base' AS seccion,
       to_jsonb(round((extract(epoch FROM now() - (SELECT ts FROM linea_base)) / 3600)::numeric, 1)) AS detalle

UNION ALL
SELECT 2, 'consultas_antes_y_despues', (
    SELECT jsonb_agg(jsonb_build_object(
               'consulta', nombre,
               'antes_media_ms', round(base_ms / NULLIF(base_llamadas, 0), 1),
               'despues_llamadas', n_llamadas,
               'despues_media_ms', round(n_ms / NULLIF(n_llamadas, 0), 2),
               'veces_mas_rapida', round((base_ms / NULLIF(base_llamadas, 0)) / NULLIF(n_ms / NULLIF(n_llamadas, 0), 0), 1)
           ) ORDER BY orden)
    FROM nuevas_consultas
)

UNION ALL
SELECT 3, 'recorridos_completos_nuevos', (
    SELECT jsonb_agg(jsonb_build_object(
               'tabla', tabla,
               'recorridos_completos', recorridos,
               'filas_leidas_asi', filas_leidas,
               'filas_de_la_tabla', n_live_tup
           ) ORDER BY filas_leidas DESC)
    FROM nuevas_tablas
)

UNION ALL
SELECT 4, 'indices_nuevos_usos', (
    SELECT COALESCE(jsonb_object_agg(indexrelname, idx_scan), '{}'::jsonb)
    FROM pg_stat_user_indexes
    WHERE indexrelname IN ('idx_ventas_adicional_venta', 'idx_ventas_pagos_venta',
                           'idx_compras_adicional_compra', 'idx_liquidaciones_pagos_cabecera',
                           'idx_cons_ventas_det_consignacion_producto', 'idx_ventas_detalle_producto')
)

UNION ALL
SELECT 5, 'base_datos_desde_linea_base', (
    SELECT jsonb_build_object(
               'commits',             CASE WHEN perdidas THEN d.xact_commit  ELSE d.xact_commit  - 36042096     END,
               'filas_devueltas',     CASE WHEN perdidas THEN d.tup_returned ELSE d.tup_returned - 289973493116 END,
               'filas_por_indice',    CASE WHEN perdidas THEN d.tup_fetched  ELSE d.tup_fetched  - 7803407989   END,
               'archivos_temporales', CASE WHEN perdidas THEN d.temp_files   ELSE d.temp_files   - 16989        END,
               'estadisticas_reiniciadas', perdidas)
    FROM pg_stat_database d
    CROSS JOIN reinicio r
    CROSS JOIN LATERAL (SELECT r.base_datos OR d.xact_commit < 36042096 AS perdidas) p
    WHERE d.datname = current_database()
)

ORDER BY orden;
