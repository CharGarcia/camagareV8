-- ============================================================================
--  MENÚ Y PERMISOS — Módulo Factura de Reembolso (ruta MVC = modulos/factura-reembolso)
--
--  Emisión de Facturas de Reembolso (SRI codDoc=01, ATS código 41 "Comprobante
--  de venta emitido por reembolso"). Se cuelga bajo el mismo módulo de menú que
--  Factura de Venta y copia sus permisos. Idempotente.
--
--  Después de correr esto, actualizar config/modulos_mvc.php:
--  'modulos/factura-reembolso' => ['id_submodulo' => <el id que arroja el SELECT final>, ...]
-- ============================================================================

INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Factura de Reembolso', 'modulos/factura-reembolso', s.id_modulo, s.orden + 1, s.id_icono, 1
FROM submodulos_menu s
WHERE s.ruta = 'modulos/factura-venta'
  AND NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/factura-reembolso');

-- Copiar permisos de Factura de Venta a Factura de Reembolso.
INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
SELECT ma.id_usuario, ma.id_empresa, ma.id_modulo,
       (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/factura-reembolso'),
       ma.r, ma.w, ma.u, ma.d, ma.t
FROM modulos_asignados ma
WHERE ma.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/factura-venta')
  AND NOT EXISTS (
      SELECT 1 FROM modulos_asignados x
      WHERE x.id_usuario = ma.id_usuario AND x.id_empresa = ma.id_empresa
        AND x.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/factura-reembolso'));

-- Ver el id para config/modulos_mvc.php (clave 'modulos/factura-reembolso').
SELECT ruta, id AS id_submodulo FROM submodulos_menu WHERE ruta IN ('modulos/factura-venta', 'modulos/factura-reembolso') ORDER BY ruta;
