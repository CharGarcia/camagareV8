-- ============================================================================
--  RENOMBRE: "Control asistencia" → "Puntos de servicio"
--  El módulo quedó solo con Puntos de servicio + Horarios/turnos + Config;
--  las marcaciones, jornadas y credenciales se separaron. Se actualiza el
--  nombre visible y la ruta MVC del submódulo (mismo id, mismos permisos).
-- ============================================================================

UPDATE submodulos_menu
SET nombre_submodulo = 'Puntos de servicio',
    ruta            = 'modulos/puntos-servicio'
WHERE ruta = 'modulos/control-asistencia';

-- Verificar
SELECT id, nombre_submodulo, ruta FROM submodulos_menu WHERE ruta = 'modulos/puntos-servicio';
