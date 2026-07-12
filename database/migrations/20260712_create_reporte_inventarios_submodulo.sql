-- ============================================================
-- Submódulo: Reporte de Inventarios (modulos/reporte_inventarios)
-- Idempotente. Ancla el submódulo como hermano de "Reporte de
-- Ventas" (grupo Reportes) y copia los permisos de quien ya
-- tenga acceso a ese submódulo.
--
-- Después de ejecutar esta migración, actualizar el id_submodulo
-- real (ver el SELECT final) en config/modulos_mvc.php, entrada
-- 'modulos/reporte_inventarios'.
-- ============================================================

INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Reporte de Inventarios', 'modulos/reporte_inventarios', s.id_modulo,
       COALESCE((SELECT MAX(orden) + 1 FROM submodulos_menu WHERE id_modulo = s.id_modulo), 0),
       s.id_icono, 1
FROM submodulos_menu s
WHERE s.ruta = 'modulos/reporte_ventas'
  AND NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/reporte_inventarios');

INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
SELECT ma.id_usuario, ma.id_empresa, ma.id_modulo,
       (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/reporte_inventarios'),
       ma.r, ma.w, ma.u, ma.d, ma.t
FROM modulos_asignados ma
WHERE ma.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/reporte_ventas')
  AND NOT EXISTS (
      SELECT 1 FROM modulos_asignados x
      WHERE x.id_usuario = ma.id_usuario AND x.id_empresa = ma.id_empresa
        AND x.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/reporte_inventarios')
  );

-- Devuelve el id_submodulo real: copiarlo en config/modulos_mvc.php.
SELECT ruta, id AS id_submodulo FROM submodulos_menu WHERE ruta = 'modulos/reporte_inventarios';
