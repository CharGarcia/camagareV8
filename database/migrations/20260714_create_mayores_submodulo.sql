-- ============================================================
-- Submódulo: Mayores (modulos/mayores)
-- Idempotente. Ancla el submódulo como hermano de "Auditoría
-- Contable" (grupo CONTABILIDAD, id_modulo = 314) y copia SOLO
-- el permiso de lectura (r) de quien ya tenga acceso a ese
-- submódulo — Mayores es un reporte de solo lectura, sin
-- creación/edición/eliminación.
--
-- Después de ejecutar esta migración, actualizar el id_submodulo
-- real (ver el SELECT final) en config/modulos_mvc.php, entrada
-- 'modulos/mayores'.
-- ============================================================

INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Mayores', 'modulos/mayores', s.id_modulo,
       COALESCE((SELECT MAX(orden) + 1 FROM submodulos_menu WHERE id_modulo = s.id_modulo), 0),
       s.id_icono, 1
FROM submodulos_menu s
WHERE s.ruta = 'modulos/auditoria_contable'
  AND NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/mayores');

INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
SELECT ma.id_usuario, ma.id_empresa, ma.id_modulo,
       (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/mayores'),
       ma.r, 0, 0, 0, ma.t
FROM modulos_asignados ma
WHERE ma.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/auditoria_contable')
  AND NOT EXISTS (
      SELECT 1 FROM modulos_asignados x
      WHERE x.id_usuario = ma.id_usuario AND x.id_empresa = ma.id_empresa
        AND x.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/mayores')
  );

-- Devuelve el id_submodulo real: copiarlo en config/modulos_mvc.php.
SELECT ruta, id AS id_submodulo FROM submodulos_menu WHERE ruta = 'modulos/mayores';
