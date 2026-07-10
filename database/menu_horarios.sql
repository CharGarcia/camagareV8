-- ============================================================================
--  MENÚ Y PERMISOS — Módulo Horarios y turnos (ruta MVC = modulos/horarios)
--
--  Turnos + asignaciones empleado→turno→punto. Separado de Puntos de servicio.
--  Se cuelga bajo Nómina (mismo id_modulo que Puntos de servicio), reutilizando
--  su id_icono. Idempotente. Copia los permisos de Puntos de servicio.
-- ============================================================================

INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Horarios y turnos', 'modulos/horarios', s.id_modulo, 0, s.id_icono, 1
FROM submodulos_menu s
WHERE s.ruta = 'modulos/puntos-servicio'
  AND NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/horarios');

-- Copiar permisos de Puntos de servicio a Horarios.
INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
SELECT ma.id_usuario, ma.id_empresa, ma.id_modulo,
       (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/horarios'),
       ma.r, ma.w, ma.u, ma.d, ma.t
FROM modulos_asignados ma
WHERE ma.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/puntos-servicio')
  AND NOT EXISTS (
      SELECT 1 FROM modulos_asignados x
      WHERE x.id_usuario = ma.id_usuario AND x.id_empresa = ma.id_empresa
        AND x.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/horarios'));

-- Ver el id para config/modulos_mvc.php (clave 'modulos/horarios').
SELECT ruta, id AS id_submodulo FROM submodulos_menu WHERE ruta IN ('modulos/puntos-servicio', 'modulos/horarios') ORDER BY ruta;
