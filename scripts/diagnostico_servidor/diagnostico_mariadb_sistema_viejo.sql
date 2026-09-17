-- =============================================================================
-- diagnostico_mariadb_sistema_viejo.sql — SOLO LECTURA
-- -----------------------------------------------------------------------------
-- Qué hace : lee el estado de la MariaDB/MySQL del sistema viejo (memoria,
--            conexiones, tamaño de las bases) y cuánto se sigue usando ese
--            sistema (empresas que todavía facturan ahí).
-- Toca datos: NO. Solo SELECT / SHOW.
-- Cómo      : lo ejecuta solo recolectar_servidor.sh. Si el script no pudo
--            entrar a la base sin contraseña, ejecútalo tú desde HeidiSQL,
--            phpMyAdmin o Workbench y guarda los resultados en un .txt.
-- Carga     : las dos últimas consultas recorren encabezado_factura (no tiene
--            índice por fecha). Correr en horario tranquilo.
-- Supuesto  : la base del sistema viejo se llama `sistema`.
-- =============================================================================

SELECT VERSION() AS version, NOW() AS ahora, @@hostname AS servidor;

SHOW GLOBAL STATUS WHERE Variable_name IN (
    'Uptime', 'Threads_connected', 'Threads_running', 'Max_used_connections',
    'Connections', 'Aborted_connects', 'Questions', 'Slow_queries',
    'Com_select', 'Com_insert', 'Com_update', 'Com_delete',
    'Created_tmp_tables', 'Created_tmp_disk_tables',
    'Innodb_buffer_pool_pages_total', 'Innodb_buffer_pool_pages_free',
    'Innodb_buffer_pool_read_requests', 'Innodb_buffer_pool_reads',
    'Bytes_received', 'Bytes_sent'
);

SHOW GLOBAL VARIABLES WHERE Variable_name IN (
    'innodb_buffer_pool_size', 'max_connections', 'key_buffer_size',
    'query_cache_type', 'query_cache_size', 'tmp_table_size', 'max_heap_table_size',
    'table_open_cache', 'thread_cache_size', 'wait_timeout',
    'innodb_flush_log_at_trx_commit', 'slow_query_log', 'long_query_time',
    'performance_schema', 'bind_address', 'skip_networking'
);

-- Tamaño por base
SELECT table_schema AS base,
       COUNT(*) AS tablas,
       ROUND(SUM(data_length) / 1048576, 1)  AS datos_mb,
       ROUND(SUM(index_length) / 1048576, 1) AS indices_mb
FROM information_schema.tables
WHERE table_schema NOT IN ('information_schema', 'performance_schema', 'mysql', 'sys')
GROUP BY table_schema
ORDER BY SUM(data_length + index_length) DESC;

-- Las 20 tablas más grandes
SELECT table_schema AS base, table_name AS tabla, engine AS motor, table_rows AS filas_aprox,
       ROUND((data_length + index_length) / 1048576, 1) AS total_mb
FROM information_schema.tables
WHERE table_schema NOT IN ('information_schema', 'performance_schema', 'mysql', 'sys')
ORDER BY data_length + index_length DESC
LIMIT 20;

-- Conexiones abiertas ahora, por usuario y origen
-- (sirve para ver si la herramienta de migración del ERP nuevo sigue conectada)
SELECT user AS usuario, SUBSTRING_INDEX(host, ':', 1) AS origen, db AS base, command AS estado,
       COUNT(*) AS conexiones, MAX(time) AS max_segundos
FROM information_schema.processlist
GROUP BY user, SUBSTRING_INDEX(host, ':', 1), db, command
ORDER BY conexiones DESC;

-- Uso real: empresas que siguen facturando en el sistema viejo, mes a mes
SELECT DATE_FORMAT(fecha_factura, '%Y-%m') AS mes,
       COUNT(DISTINCT LEFT(ruc_empresa, 10)) AS empresas,
       COUNT(*) AS facturas
FROM sistema.encabezado_factura
WHERE fecha_factura >= CURDATE() - INTERVAL 6 MONTH
GROUP BY DATE_FORMAT(fecha_factura, '%Y-%m')
ORDER BY mes;

-- Uso real: detalle por empresa en los últimos 90 días
SELECT LEFT(ruc_empresa, 10) AS ruc_base,
       COUNT(*) AS facturas_90d,
       SUM(fecha_factura >= CURDATE() - INTERVAL 30 DAY) AS facturas_30d,
       SUM(fecha_factura >= CURDATE() - INTERVAL 7 DAY)  AS facturas_7d,
       MAX(fecha_factura) AS ultima_factura
FROM sistema.encabezado_factura
WHERE fecha_factura >= CURDATE() - INTERVAL 90 DAY
GROUP BY LEFT(ruc_empresa, 10)
ORDER BY facturas_30d DESC, facturas_90d DESC;
