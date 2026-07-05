-- ============================================================================
-- Submódulo de menú: Facturación de Consignaciones (módulo independiente)
-- Ruta MVC: modulos/facturacion-cv  →  FacturacionCvController
--
-- Se coloca en el mismo módulo/menú que "Consignaciones en Ventas", reutilizando
-- su id_modulo e id_icono. Columnas reales de submodulos_menu:
--   nombre_submodulo, ruta, id_modulo, orden, id_icono, status.
-- ============================================================================

INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Facturación de consignaciones',
       'modulos/facturacion-cv',
       s.id_modulo,
       (SELECT COALESCE(MAX(orden), 0) + 1 FROM submodulos_menu WHERE id_modulo = s.id_modulo),
       s.id_icono,
       1
FROM submodulos_menu s
WHERE s.ruta = 'modulos/consignaciones-ventas'
  AND NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/facturacion-cv')
LIMIT 1;

-- Fallback: si no existe la ruta de consignaciones en el menú, anclar a Facturas de venta.
INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Facturación de consignaciones',
       'modulos/facturacion-cv',
       s.id_modulo,
       (SELECT COALESCE(MAX(orden), 0) + 1 FROM submodulos_menu WHERE id_modulo = s.id_modulo),
       s.id_icono,
       1
FROM submodulos_menu s
WHERE s.ruta = 'modulos/factura-venta'
  AND NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/facturacion-cv')
LIMIT 1;

-- Tras ejecutar, obtener el id del submódulo para asignar permisos en /config/permisos-modulos:
--   SELECT id FROM submodulos_menu WHERE ruta = 'modulos/facturacion-cv';
