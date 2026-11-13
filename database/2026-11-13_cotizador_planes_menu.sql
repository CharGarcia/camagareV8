-- ============================================================================
-- MENÚ — Cotizador de Planes (ruta MVC = modulos/cotizador-planes)
--
-- Ejecutar DESPUÉS de database/2026-11-13_cotizador_planes.sql.
-- Registra el submódulo «Cotizador de Planes» dentro del módulo CRM del menú
-- (creado por 2026-11-08_crm_menu.sql). Si no existe el módulo CRM, lo cuelga
-- del mismo módulo que Proformas.
-- Columnas reales de submodulos_menu: nombre_submodulo, ruta, id_modulo, orden, id_icono, status.
--
-- No hace falta tocar config/modulos_mvc.php: su id_submodulo queda en 0 y el
-- permiso se resuelve por submodulos_menu.ruta.
-- Después: asignar permisos a los vendedores en /config/permisos-modulos.
--
-- Idempotente: se puede ejecutar más de una vez.
-- ============================================================================

INSERT INTO iconos_fontawesome (nombre_icono)
SELECT 'fas fa-calculator'
WHERE NOT EXISTS (SELECT 1 FROM iconos_fontawesome WHERE nombre_icono = 'fas fa-calculator');

INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Cotizador de Planes',
       'modulos/cotizador-planes',
       m.id_modulo,
       (SELECT COALESCE(MAX(orden), 0) + 1 FROM submodulos_menu WHERE id_modulo = m.id_modulo),
       (SELECT MIN(id) FROM iconos_fontawesome WHERE nombre_icono = 'fas fa-calculator'),
       1
  FROM (SELECT COALESCE(
               (SELECT MIN(id) FROM modulos_menu WHERE lower(nombre_modulo) = 'crm'),
               (SELECT MIN(id_modulo) FROM submodulos_menu WHERE ruta = 'modulos/proformas')
       ) AS id_modulo) m
 WHERE m.id_modulo IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/cotizador-planes');

-- Comprobar:
--   SELECT s.id, s.nombre_submodulo, s.ruta, mm.nombre_modulo
--     FROM submodulos_menu s JOIN modulos_menu mm ON mm.id = s.id_modulo
--    WHERE s.ruta = 'modulos/cotizador-planes';
