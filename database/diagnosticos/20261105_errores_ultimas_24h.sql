-- =============================================================================
-- Diagnóstico (SOLO LECTURA): errores del sistema de las últimas 24 horas, agrupados
-- -----------------------------------------------------------------------------
-- Junta los errores repetidos (mismo origen y mismo mensaje, con los números
-- reemplazados por # para que "id 123" e "id 456" cuenten como el mismo error) y los
-- ordena del más frecuente al menos. Normalmente unos pocos grupos explican casi todo.
-- No modifica nada. Pegar en pgAdmin y ejecutar (F5); copiar la grilla completa.
-- =============================================================================

SELECT COUNT(*)                                   AS veces,
       MIN(e.tipo)                                AS tipo,
       COALESCE(NULLIF(e.ruta, ''), '(sin ruta)') AS ruta,
       MAX(e.accion)                              AS accion,
       MAX(e.clase)                               AS clase,
       MAX(e.sql_state)                           AS sql_state,
       left(regexp_replace(e.mensaje, '[0-9]+', '#', 'g'), 250) AS mensaje,
       COUNT(DISTINCT e.id_usuario)               AS usuarios,
       COUNT(DISTINCT e.id_empresa)               AS empresas,
       MAX(e.archivo || ':' || e.linea)           AS donde,
       MIN(e.created_at)                          AS primero,
       MAX(e.created_at)                          AS ultimo,
       MAX(e.id)                                  AS id_ejemplo     -- para abrirlo en Errores del sistema
FROM errores_sistema e
WHERE e.created_at >= NOW() - INTERVAL '24 hours'
GROUP BY 3, 7
ORDER BY veces DESC
LIMIT 40;
