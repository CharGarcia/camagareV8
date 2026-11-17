-- ============================================================================
-- Copiar el MENÚ de producción (módulos y submódulos) a la base LOCAL.
--
-- PASO 1 — en PRODUCCIÓN (pgAdmin, Query Tool, F5). Es SOLO LECTURA: no cambia nada.
--   Devuelve UNA sola celda «sql» con todo el script.
--   ⚠ pgAdmin, al copiar, envuelve el texto en comillas ("BEGIN;" → error 42601). Antes de
--   copiar: Archivo → Preferencias → Query Tool → Results grid → «Result copy quoting» = None
--   (y Guardar). Luego clic en la celda, Ctrl+C, y pegar en un Query Tool de la base LOCAL.
--   Alternativa sin tocar preferencias: doble clic en la celda, se abre el texto completo;
--   Ctrl+A, Ctrl+C dentro de esa ventana (así no lleva comillas).
--
-- PASO 2 — en LOCAL: ejecuta lo pegado (F5). Ese script:
--   • guarda un respaldo de las tablas del menú local (respaldo_menu_local_*), una sola vez;
--   • crea en local los íconos que falten (por nombre, no por id);
--   • inserta o actualiza módulos y submódulos con el MISMO id que en producción;
--   • el ícono se resuelve por su NOMBRE (fa-…), por si los ids de íconos difieren;
--   • ajusta las secuencias para que los siguientes ids no choquen;
--   • NO borra nada: al final lista lo que existe en local y no en producción.
--
-- Tablas: modulos_menu (id, nombre_modulo, id_icono, orden) y
--         submodulos_menu (id, nombre_submodulo, ruta, id_modulo, orden, id_icono, status).
-- Ojo: modulos_asignados (permisos) de LOCAL apunta a ids de submódulo; si un id local
-- tenía otro submódulo que en producción, ese permiso local pasa a apuntar al de producción.
-- ============================================================================

WITH
cab AS (
    SELECT 0 AS orden, 0 AS sub, 'BEGIN;' AS sql
    UNION ALL SELECT 1, 0, '-- Menú copiado de producción el ' || to_char(now(), 'DD-MM-YYYY HH24:MI:SS')
    UNION ALL SELECT 2, 0, 'CREATE TABLE IF NOT EXISTS respaldo_menu_local_modulos AS SELECT * FROM modulos_menu;'
    UNION ALL SELECT 3, 0, 'CREATE TABLE IF NOT EXISTS respaldo_menu_local_submodulos AS SELECT * FROM submodulos_menu;'
),
iconos AS (
    -- Íconos usados por el menú de producción que podrían no existir en local.
    SELECT 10 AS orden, ROW_NUMBER() OVER (ORDER BY i.nombre_icono)::int AS sub,
           format('INSERT INTO iconos_fontawesome (nombre_icono) SELECT %L WHERE NOT EXISTS (SELECT 1 FROM iconos_fontawesome WHERE nombre_icono = %L);',
                  i.nombre_icono, i.nombre_icono) AS sql
      FROM iconos_fontawesome i
     WHERE i.id IN (SELECT id_icono FROM modulos_menu UNION SELECT id_icono FROM submodulos_menu)
       AND i.nombre_icono IS NOT NULL
),
modulos AS (
    SELECT 20 AS orden, m.id AS sub,
           format('INSERT INTO modulos_menu (id, nombre_modulo, id_icono, orden) VALUES (%s, %L, COALESCE((SELECT MIN(id) FROM iconos_fontawesome WHERE nombre_icono = %L), %s), %s) '
                  'ON CONFLICT (id) DO UPDATE SET nombre_modulo = EXCLUDED.nombre_modulo, id_icono = EXCLUDED.id_icono, orden = EXCLUDED.orden;',
                  m.id, m.nombre_modulo, i.nombre_icono, m.id_icono, m.orden) AS sql
      FROM modulos_menu m
      LEFT JOIN iconos_fontawesome i ON i.id = m.id_icono
),
submodulos AS (
    SELECT 30 AS orden, s.id AS sub,
           format('INSERT INTO submodulos_menu (id, nombre_submodulo, ruta, id_modulo, orden, id_icono, status) VALUES (%s, %L, %L, %s, %s, COALESCE((SELECT MIN(id) FROM iconos_fontawesome WHERE nombre_icono = %L), %s), %s) '
                  'ON CONFLICT (id) DO UPDATE SET nombre_submodulo = EXCLUDED.nombre_submodulo, ruta = EXCLUDED.ruta, id_modulo = EXCLUDED.id_modulo, '
                  'orden = EXCLUDED.orden, id_icono = EXCLUDED.id_icono, status = EXCLUDED.status;',
                  s.id, s.nombre_submodulo, s.ruta, s.id_modulo, s.orden, i.nombre_icono, s.id_icono, s.status) AS sql
      FROM submodulos_menu s
      LEFT JOIN iconos_fontawesome i ON i.id = s.id_icono
),
pie AS (
    SELECT 40 AS orden, 1 AS sub, 'SELECT setval(''modulos_menu_id_seq'', GREATEST((SELECT MAX(id) FROM modulos_menu), 1));' AS sql
    UNION ALL SELECT 40, 2, 'SELECT setval(''submodulos_menu_id_seq'', GREATEST((SELECT MAX(id) FROM submodulos_menu), 1));'
    UNION ALL SELECT 40, 3, 'SELECT setval(''iconos_fontawesome_id_seq'', GREATEST((SELECT MAX(id) FROM iconos_fontawesome), 1));'
    UNION ALL SELECT 50, 1, 'COMMIT;'
    -- Lo que hay en local y no en producción (no se borra; revísalo a mano).
    UNION ALL SELECT 60, 1, format('SELECT ''modulo'' AS tipo, id, nombre_modulo AS nombre, NULL::text AS ruta FROM modulos_menu WHERE id NOT IN (%s) '
                                   'UNION ALL SELECT ''submodulo'', id, nombre_submodulo, ruta FROM submodulos_menu WHERE id NOT IN (%s) ORDER BY 1, 2;',
                                   COALESCE((SELECT string_agg(id::text, ',') FROM modulos_menu), '0'),
                                   COALESCE((SELECT string_agg(id::text, ',') FROM submodulos_menu), '0'))
)
-- Todo el script en UNA sola celda (una sentencia por línea): se copia de una vez.
SELECT string_agg(sql, E'\n' ORDER BY orden, sub) AS sql FROM (
    SELECT * FROM cab UNION ALL SELECT * FROM iconos UNION ALL SELECT * FROM modulos
    UNION ALL SELECT * FROM submodulos UNION ALL SELECT * FROM pie
) x;
