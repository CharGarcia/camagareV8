-- ============================================================
-- Migración: Registro del submódulo Unidades de Medida en el menú
-- Ruta MVC: modulos/unidades-medida
-- Ejecutar este script y anotar el id retornado para
-- actualizar el valor 'id_submodulo' en config/modulos_mvc.php
-- ============================================================

-- 1. Insertar el submódulo en submodulos_menu
--    Ajusta id_modulo al id del módulo padre "Configuración" de tu empresa
--    y el campo 'orden' según la posición deseada en el menú.
INSERT INTO submodulos_menu (id_modulo, nombre, ruta, icono, orden, status)
VALUES (
    (SELECT id FROM modulos_menu WHERE nombre ILIKE '%configuraci%' LIMIT 1),
    'Unidades de Medida',
    'modulos/unidades-medida',
    'bi bi-rulers',
    999,
    true
)
RETURNING id;

-- 2. Después de ejecutar, actualiza config/modulos_mvc.php con el id retornado:
--    'modulos/unidades-medida' => ['id_submodulo' => <ID_RETORNADO>, 'legacy_rutas' => []],
