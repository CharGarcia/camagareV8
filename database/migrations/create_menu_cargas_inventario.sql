-- ============================================================
-- Menú + submódulo: Cargas de Inventario (grupo Documentos, id_modulo = 11)
-- Idempotente: inserta solo si la ruta no existe.
-- ============================================================
INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Cargas de Inventario', 'modulos/cargas-inventario', 11, 90, 58, 1
WHERE NOT EXISTS (
    SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/cargas-inventario'
);

-- El id resultante debe registrarse en config/modulos_mvc.php ('modulos/cargas-inventario' => id_submodulo)
-- y asignarse permisos en modulos_asignados a los usuarios/roles correspondientes.
-- Para obtenerlo:  SELECT id FROM submodulos_menu WHERE ruta = 'modulos/cargas-inventario';
