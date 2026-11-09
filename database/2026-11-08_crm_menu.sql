-- ============================================================================
--  CRM: módulo y submódulos del menú, con sus íconos (crear o actualizar)
-- ----------------------------------------------------------------------------
--  Deja el menú así:
--    Módulo  CRM                                         fas fa-handshake
--      1  CRM                    modulos/crm             fas fa-filter-circle-dollar
--      2  Bandeja social         modulos/bandeja_social  fab fa-instagram
--      3  Configuración del CRM  modulos/crm_configuracion  fa-sliders-h
--
--  - Si el módulo «CRM» o un submódulo (buscado por su RUTA) ya existe, lo
--    actualiza (nombre, módulo, ícono, orden y lo deja activo); si no, lo crea.
--  - No usa id fijos: los de producción pueden ser distintos a los de desarrollo.
--  - Agrega al catálogo de íconos los que falten.
--  - NO asigna permisos (modulos_asignados): eso se hace en /config/permisos-modulos.
--    El superadministrador (nivel 3) los ve sin asignación.
--
--  Toca datos: sí (catálogo de íconos, modulos_menu, submodulos_menu).
--  Reversible: anote antes el estado con la consulta «Antes» de abajo. Para quitar
--  lo creado: DELETE FROM submodulos_menu WHERE ruta IN (las 3 rutas) y luego el
--  módulo, solo si no tienen permisos asignados.
--  Idempotente: se puede ejecutar varias veces.
--  Listo para pegar en el Query Tool de pgAdmin y ejecutar de una vez (F5).
-- ============================================================================

-- Antes (opcional):
-- SELECT 'modulo' AS tipo, id, nombre_modulo AS nombre, NULL AS ruta, id_icono, orden FROM modulos_menu WHERE lower(nombre_modulo) = 'crm'
-- UNION ALL
-- SELECT 'submodulo', id, nombre_submodulo, ruta, id_icono, orden FROM submodulos_menu
--  WHERE ruta IN ('modulos/crm', 'modulos/bandeja_social', 'modulos/crm_configuracion');

-- 1. Íconos que falten en el catálogo
INSERT INTO iconos_fontawesome (nombre_icono)
SELECT v FROM (VALUES ('fas fa-handshake'), ('fas fa-filter-circle-dollar'), ('fab fa-instagram'), ('fa-sliders-h')) AS n(v)
WHERE NOT EXISTS (SELECT 1 FROM iconos_fontawesome i WHERE i.nombre_icono = n.v);

-- 2. Módulo CRM: crear si no existe…
INSERT INTO modulos_menu (nombre_modulo, id_icono, orden)
SELECT 'CRM', (SELECT MIN(id) FROM iconos_fontawesome WHERE nombre_icono = 'fas fa-handshake'), 0
WHERE NOT EXISTS (SELECT 1 FROM modulos_menu WHERE lower(nombre_modulo) = 'crm');

-- …y dejar nombre e ícono (si hubiera dos «CRM», se usa el de menor id)
UPDATE modulos_menu
   SET nombre_modulo = 'CRM',
       id_icono = (SELECT MIN(id) FROM iconos_fontawesome WHERE nombre_icono = 'fas fa-handshake')
 WHERE id = (SELECT MIN(id) FROM modulos_menu WHERE lower(nombre_modulo) = 'crm');

-- 3. Submódulos: actualizar los que ya existen (por ruta)…
UPDATE submodulos_menu s
   SET nombre_submodulo = d.nombre,
       id_modulo = (SELECT MIN(id) FROM modulos_menu WHERE lower(nombre_modulo) = 'crm'),
       id_icono  = (SELECT MIN(i.id) FROM iconos_fontawesome i WHERE i.nombre_icono = d.icono),
       orden     = d.orden,
       status    = 1
  FROM (VALUES
        ('modulos/crm',               'CRM',                   'fas fa-filter-circle-dollar', 1),
        ('modulos/bandeja_social',    'Bandeja social',        'fab fa-instagram',            2),
        ('modulos/crm_configuracion', 'Configuración del CRM', 'fa-sliders-h',                3)
       ) AS d(ruta, nombre, icono, orden)
 WHERE s.ruta = d.ruta;

-- …y crear los que faltan
INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT d.nombre, d.ruta,
       (SELECT MIN(id) FROM modulos_menu WHERE lower(nombre_modulo) = 'crm'),
       d.orden,
       (SELECT MIN(i.id) FROM iconos_fontawesome i WHERE i.nombre_icono = d.icono),
       1
  FROM (VALUES
        ('modulos/crm',               'CRM',                   'fas fa-filter-circle-dollar', 1),
        ('modulos/bandeja_social',    'Bandeja social',        'fab fa-instagram',            2),
        ('modulos/crm_configuracion', 'Configuración del CRM', 'fa-sliders-h',                3)
       ) AS d(ruta, nombre, icono, orden)
 WHERE NOT EXISTS (SELECT 1 FROM submodulos_menu s WHERE s.ruta = d.ruta);

-- Comprobación: 1 módulo + 3 submódulos con sus íconos. Los id_submodulo son los
-- que van en config/modulos_mvc.php para cada ruta.
-- SELECT 'modulo' AS tipo, m.id, m.nombre_modulo AS nombre, NULL AS ruta, i.nombre_icono
--   FROM modulos_menu m JOIN iconos_fontawesome i ON i.id = m.id_icono WHERE lower(m.nombre_modulo) = 'crm'
-- UNION ALL
-- SELECT 'submodulo', s.id, s.nombre_submodulo, s.ruta, i.nombre_icono
--   FROM submodulos_menu s JOIN iconos_fontawesome i ON i.id = s.id_icono
--  WHERE s.ruta IN ('modulos/crm', 'modulos/bandeja_social', 'modulos/crm_configuracion')
--  ORDER BY tipo, nombre;
