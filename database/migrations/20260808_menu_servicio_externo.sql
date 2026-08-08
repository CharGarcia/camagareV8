-- ============================================================================
--  MENÚ Y PERMISOS — Módulo Servicio Externo (ruta MVC = modulos/servicio-externo)
--
--  Registra el submódulo "Servicio Externo" en el menú. Se coloca junto a
--  "Facturas de venta", reutilizando su id_modulo e id_icono (ajustar si su
--  entorno usa otro módulo/orden). Columnas reales de submodulos_menu:
--    nombre_submodulo, ruta, id_modulo, orden, id_icono, status.
-- ============================================================================

INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Servicio Externo',
       'modulos/servicio-externo',
       s.id_modulo,
       (SELECT COALESCE(MAX(orden), 0) + 1 FROM submodulos_menu WHERE id_modulo = s.id_modulo),
       s.id_icono,
       1
FROM submodulos_menu s
WHERE s.ruta = 'modulos/factura-venta'
  AND NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/servicio-externo');

-- NOTA: tras ejecutar, obtener el id del submódulo recién creado:
--   SELECT id FROM submodulos_menu WHERE ruta = 'modulos/servicio-externo';
-- y registrarlo en config/modulos_mvc.php como id_submodulo.
-- Luego asignar permisos a los usuarios/perfiles en /config/permisos-modulos.
