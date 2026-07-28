-- ---------------------------------------------------------------------------
-- MENÚ Y PERMISOS del módulo Videollamadas
--
-- Ejecutar DESPUÉS de database/migrations/20260728_create_videollamadas.sql
--
-- Este script NO se ejecuta de corrido: los pasos 1 y 2 son para decidir dónde
-- colgar el submódulo. Vaya uno por uno.
-- ---------------------------------------------------------------------------


-- ═══ PASO 1 ═══ Ver los módulos padre disponibles y elegir uno.
--
--   SELECT id, nombre_modulo, orden
--   FROM modulos_menu
--   WHERE status = 1
--   ORDER BY orden, nombre_modulo;
--
-- Sugerencias de dónde colgarlo, en orden de preferencia:
--   1. El módulo donde ya vive "WhatsApp / Chat" (agrupa las comunicaciones).
--   2. Un módulo tipo "Herramientas", "Utilidades" o "General".
--   3. Si no encaja en ninguno, crear un módulo padre nuevo (ver PASO 1-B).


-- ═══ PASO 1-B (OPCIONAL) ═══ Solo si quiere un módulo padre nuevo.
--
--   INSERT INTO modulos_menu (nombre_modulo, orden, id_icono, status)
--   SELECT 'Comunicaciones',
--          (SELECT COALESCE(MAX(orden), 0) + 1 FROM modulos_menu),
--          (SELECT id_icono FROM modulos_menu WHERE status = 1 LIMIT 1),
--          1
--   WHERE NOT EXISTS (SELECT 1 FROM modulos_menu WHERE nombre_modulo = 'Comunicaciones');


-- ═══ PASO 2 ═══ Crear el submódulo.
--
-- Variante A (RECOMENDADA): lo cuelga del mismo módulo padre y con el mismo
-- ícono que un submódulo que ya existe. Cambie 'modulos/whatsapp-chat' por la
-- ruta del submódulo junto al que quiera que aparezca.

INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Videollamadas',
       'modulos/videollamadas',
       s.id_modulo,
       (SELECT COALESCE(MAX(orden), 0) + 1 FROM submodulos_menu WHERE id_modulo = s.id_modulo),
       s.id_icono,
       1
FROM submodulos_menu s
WHERE s.ruta = 'modulos/whatsapp-chat'
  AND NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/videollamadas')
LIMIT 1;

-- Variante B: indicando el id del módulo padre a mano (el que vio en el PASO 1).
-- Descomente y reemplace el 99 por el id real.
--
--   INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
--   SELECT 'Videollamadas',
--          'modulos/videollamadas',
--          99,
--          (SELECT COALESCE(MAX(orden), 0) + 1 FROM submodulos_menu WHERE id_modulo = 99),
--          (SELECT id_icono FROM submodulos_menu WHERE id_modulo = 99 LIMIT 1),
--          1
--   WHERE NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/videollamadas');


-- ═══ PASO 3 ═══ Permisos.
--
-- Nivel 3 (superadministrador) NO necesita registro: ya tiene acceso total.
-- Este paso es para los usuarios de nivel 1 y 2.
--
-- Copia los permisos desde un submódulo que esa gente ya use. Cambie la ruta de
-- origen por la que corresponda; 'modulos/whatsapp-chat' es solo un ejemplo.

INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
SELECT ma.id_usuario,
       ma.id_empresa,
       (SELECT id_modulo FROM submodulos_menu WHERE ruta = 'modulos/videollamadas'),
       (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/videollamadas'),
       ma.r, ma.w, ma.u, ma.d, ma.t
FROM modulos_asignados ma
WHERE ma.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/whatsapp-chat')
  AND NOT EXISTS (
        SELECT 1 FROM modulos_asignados x
        WHERE x.id_usuario = ma.id_usuario
          AND x.id_empresa = ma.id_empresa
          AND x.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/videollamadas')
      );

-- Ajuste fino: los permisos también se administran desde /config/permisos-modulos.
--
-- Recordatorio sobre el permiso 't' (acceso total) en este módulo:
--   con 't' → el usuario ve las reuniones de toda la empresa y puede iniciar o
--             finalizar las de otros.
--   sin 't' → solo ve las reuniones que él mismo creó.


-- ═══ PASO 4 ═══ Obtener el id y registrarlo en el código.

SELECT id AS id_submodulo, nombre_submodulo, ruta, id_modulo, orden
FROM submodulos_menu
WHERE ruta = 'modulos/videollamadas';

-- Con ese número, editar config/modulos_mvc.php:
--
--   'modulos/videollamadas' => [
--       'id_submodulo' => <el número que devolvió la consulta>,
--       'legacy_rutas' => [],
--   ],
--
-- Si se deja en 0 igual funciona (se resuelve por la ruta), pero dejarlo puesto
-- ahorra una consulta en cada verificación de permisos.
