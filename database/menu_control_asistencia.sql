-- ============================================================================
--  MENÚ Y PERMISOS — Módulo Control de Asistencia (ruta MVC = modulos/control-asistencia)
--
--  Registra el submódulo "Control de Asistencia" en el menú, junto al submódulo
--  "Novedades" (módulo Nómina), reutilizando su id_modulo e id_icono.
--  Columnas reales de submodulos_menu:
--    nombre_submodulo, ruta, id_modulo, orden, id_icono, status.
--
--  Nota: mientras no se registre el id en config/modulos_mvc.php, el super admin
--  (Nivel 3) igual puede entrar por URL a /modulos/control-asistencia.
-- ============================================================================

INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Control de Asistencia',
       'modulos/control-asistencia',
       s.id_modulo,
       (SELECT COALESCE(MAX(orden), 0) + 1 FROM submodulos_menu WHERE id_modulo = s.id_modulo),
       s.id_icono,
       1
FROM submodulos_menu s
WHERE s.ruta = 'modulos/novedades'
  AND NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/control-asistencia');

-- Tras ejecutar, obtener el id del submódulo creado y registrarlo en
-- config/modulos_mvc.php (clave 'modulos/control-asistencia' → id_submodulo):
--   SELECT id FROM submodulos_menu WHERE ruta = 'modulos/control-asistencia';
-- Luego asignar permisos (r,w,u,d,t) a los perfiles en /config/permisos-modulos.
