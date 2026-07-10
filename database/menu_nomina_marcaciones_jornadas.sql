-- ============================================================================
--  MENÚ Y PERMISOS — Submódulos Marcaciones y Jornadas (módulo Nómina)
--  Rutas MVC: modulos/marcaciones y modulos/jornadas
--
--  Los cuelga bajo el mismo módulo (Nómina) que "Control asistencia",
--  reutilizando su id_modulo e id_icono. Idempotente.
-- ============================================================================

-- 1) Crear los submódulos bajo Nómina (mismo id_modulo que Control asistencia).
INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Marcaciones', 'modulos/marcaciones', s.id_modulo, 0, s.id_icono, 1
FROM submodulos_menu s
WHERE s.ruta = 'modulos/control-asistencia'
  AND NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/marcaciones');

INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Jornadas', 'modulos/jornadas', s.id_modulo, 0, s.id_icono, 1
FROM submodulos_menu s
WHERE s.ruta = 'modulos/control-asistencia'
  AND NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/jornadas');

-- 2) Copiar los permisos existentes de "Control asistencia" a los dos nuevos
--    submódulos (quien ya podía ver Control de Asistencia verá Marcaciones y Jornadas).
INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
SELECT ma.id_usuario, ma.id_empresa, ma.id_modulo,
       (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/marcaciones'),
       ma.r, ma.w, ma.u, ma.d, ma.t
FROM modulos_asignados ma
WHERE ma.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/control-asistencia')
  AND NOT EXISTS (
      SELECT 1 FROM modulos_asignados x
      WHERE x.id_usuario = ma.id_usuario AND x.id_empresa = ma.id_empresa
        AND x.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/marcaciones'));

INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
SELECT ma.id_usuario, ma.id_empresa, ma.id_modulo,
       (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/jornadas'),
       ma.r, ma.w, ma.u, ma.d, ma.t
FROM modulos_asignados ma
WHERE ma.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/control-asistencia')
  AND NOT EXISTS (
      SELECT 1 FROM modulos_asignados x
      WHERE x.id_usuario = ma.id_usuario AND x.id_empresa = ma.id_empresa
        AND x.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/jornadas'));

-- 3) Ver los ids resultantes para actualizar config/modulos_mvc.php
--    (claves 'modulos/marcaciones' y 'modulos/jornadas' → id_submodulo).
SELECT ruta, id AS id_submodulo
FROM submodulos_menu
WHERE ruta IN ('modulos/control-asistencia', 'modulos/marcaciones', 'modulos/jornadas')
ORDER BY ruta;
