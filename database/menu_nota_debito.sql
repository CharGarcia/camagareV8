-- ============================================================================
--  MENÚ Y PERMISOS — Módulo Nota de Débito (ruta MVC = modulos/nota_debito)
--
--  Emisión de Notas de Débito (SRI codDoc=05). Se cuelga bajo el mismo módulo
--  de menú que Notas de Crédito y copia sus permisos. Idempotente.
--
--  Después de correr esto, actualizar config/modulos_mvc.php:
--  'modulos/nota_debito' => ['id_submodulo' => <el id que arroja el SELECT final>, ...]
-- ============================================================================

INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Notas de Débito', 'modulos/nota_debito', s.id_modulo, s.orden + 1, s.id_icono, 1
FROM submodulos_menu s
WHERE s.ruta = 'modulos/notas_credito'
  AND NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/nota_debito');

-- Copiar permisos de Notas de Crédito a Nota de Débito.
INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
SELECT ma.id_usuario, ma.id_empresa, ma.id_modulo,
       (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/nota_debito'),
       ma.r, ma.w, ma.u, ma.d, ma.t
FROM modulos_asignados ma
WHERE ma.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/notas_credito')
  AND NOT EXISTS (
      SELECT 1 FROM modulos_asignados x
      WHERE x.id_usuario = ma.id_usuario AND x.id_empresa = ma.id_empresa
        AND x.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/nota_debito'));

-- Ver el id para config/modulos_mvc.php (clave 'modulos/nota_debito').
SELECT ruta, id AS id_submodulo FROM submodulos_menu WHERE ruta IN ('modulos/notas_credito', 'modulos/nota_debito') ORDER BY ruta;
