-- ============================================================================
-- Asignación masiva de los submódulos que pasaron a exigir permiso
--   modulos/whatsapp-chat       (Bandeja de Entrada)
--   modulos/whatsapp-campanas   (Campañas masivas)
--   modulos/descargas_sri       (Cargar documentos SRI)
--
-- CONTEXTO: estos tres módulos se abrían sin validar permisos; ahora exigen el
-- submódulo asignado, igual que el resto. Los usuarios que ya los usaban no
-- tienen la fila en modulos_asignados y se quedarían fuera.
--
-- CÓMO USARLO EN pgAdmin: ejecutar bloque por bloque, en orden. Los PASOS 0 y 1
-- solo consultan; el PASO 2 escribe. NADA se inserta hasta que ejecutes el
-- PASO 2, y va dentro de una transacción para que puedas revisarlo antes de
-- confirmar.
--
-- IMPORTANTE: no se usan ids de submódulo fijos. Los ids de submodulos_menu se
-- generan por instalación, así que TODO se resuelve por la ruta. Copiar un id
-- de otra base es justo lo que provoca que el permiso quede marcado en pantalla
-- pero el usuario siga yendo al tablero.
-- ============================================================================


-- ============================================================================
-- PASO 0 — Comprobar que las tres rutas existen en ESTA base
-- ----------------------------------------------------------------------------
-- Deben salir 3 filas, todas con status = 1. Si falta alguna, su ruta está
-- escrita distinta en submodulos_menu: corrígela antes de seguir (el resto del
-- script no la encontrará).
-- ============================================================================
SELECT sm.id            AS id_submodulo,
       sm.nombre_submodulo,
       sm.ruta,
       sm.id_modulo,
       mm.nombre_modulo,
       COALESCE(sm.status, 1) AS status,
       (SELECT COUNT(*) FROM modulos_asignados ma WHERE ma.id_submodulo = sm.id) AS ya_asignado_a
  FROM submodulos_menu sm
  LEFT JOIN modulos_menu mm ON mm.id = sm.id_modulo
 WHERE replace(lower(trim(sm.ruta)), '_', '-') IN (
           'modulos/whatsapp-chat',
           'modulos/whatsapp-campanas',
           'modulos/descargas-sri'
       )
 ORDER BY sm.id;


-- ============================================================================
-- PASO 1 — Previsualizar a quién se le asignaría (NO escribe nada)
-- ----------------------------------------------------------------------------
-- Se combinan dos criterios; cada fila dice cuál lo propuso:
--
--   'uso'      El usuario TIENE actividad registrada en el módulo. Solo aplica
--              a Descargas SRI: whatsapp_mensajes y whatsapp_chats guardan
--              created_by = 0, así que en WhatsApp no hay forma de saber por
--              los datos quién lo usaba.
--   'familia'  El usuario ya tiene OTRO submódulo del mismo módulo del menú
--              (p. ej. quien tiene Plantillas de WhatsApp seguramente también
--              usaba la Bandeja). Es una suposición, no un hecho: revisa la
--              lista antes de insertar.
--
-- Revisa el resultado. Si alguien no debería tener acceso, quítalo en el
-- PASO 2 con el filtro que está comentado al final de la consulta.
-- ============================================================================
WITH destino AS (
    SELECT sm.id AS id_submodulo,
           sm.id_modulo,
           replace(lower(trim(sm.ruta)), '_', '-') AS ruta_norm
      FROM submodulos_menu sm
     WHERE replace(lower(trim(sm.ruta)), '_', '-') IN (
               'modulos/whatsapp-chat',
               'modulos/whatsapp-campanas',
               'modulos/descargas-sri'
           )
       AND COALESCE(sm.status, 1) = 1
),
-- ── Criterio 'uso': actividad real registrada ───────────────────────────────
por_uso AS (
    SELECT DISTINCT l.created_by AS id_usuario, l.id_empresa, 'modulos/descargas-sri' AS ruta_norm
      FROM sri_descarga_auto_log l
     WHERE COALESCE(l.created_by, 0) > 0
    UNION
    SELECT DISTINCT d.created_by, d.id_empresa, 'modulos/descargas-sri'
      FROM documentos_ignorados_sri d
     WHERE COALESCE(d.created_by, 0) > 0
    UNION
    -- Estas dos quedan por si en tu base whatsapp SÍ guardó el usuario
    -- (en la base de desarrollo created_by es 0 y no devuelven nada).
    SELECT DISTINCT m.created_by, m.id_empresa, 'modulos/whatsapp-chat'
      FROM whatsapp_mensajes m
     WHERE COALESCE(m.created_by, 0) > 0
       AND m.direccion = 'OUT'
       AND m.tipo_mensaje <> 'template'
    UNION
    SELECT DISTINCT m.created_by, m.id_empresa, 'modulos/whatsapp-campanas'
      FROM whatsapp_mensajes m
     WHERE COALESCE(m.created_by, 0) > 0
       AND m.direccion = 'OUT'
       AND m.tipo_mensaje = 'template'
),
-- ── Criterio 'familia': ya tiene otro submódulo del mismo módulo del menú ───
por_familia AS (
    SELECT DISTINCT ma.id_usuario, ma.id_empresa, d.ruta_norm
      FROM modulos_asignados ma
      JOIN submodulos_menu sm_tiene ON sm_tiene.id = ma.id_submodulo
      JOIN destino d                ON d.id_modulo = sm_tiene.id_modulo
     WHERE COALESCE(ma.r, 0) = 1
       AND sm_tiene.id <> d.id_submodulo
       -- Descargas SRI queda FUERA de este criterio a propósito: con permiso de
       -- crear se puede abrir la ventana de login que entrega las credenciales
       -- del SRI de la empresa a la extensión. Además cuelga del módulo
       -- "Documentos", que agrupa muchos submódulos sin relación (saldos
       -- iniciales, cargas, conciliación…), así que "familia" repartiría el
       -- acceso a demasiada gente. Ese módulo se asigna solo por uso real o con
       -- la lista manual del ANEXO. Para incluirlo igual, borra esta línea:
       AND d.ruta_norm <> 'modulos/descargas-sri'
),
candidatos AS (
    SELECT id_usuario, id_empresa, ruta_norm, 'uso'     AS origen FROM por_uso
    UNION ALL
    SELECT id_usuario, id_empresa, ruta_norm, 'familia'          FROM por_familia
)
SELECT c.ruta_norm                       AS modulo,
       c.id_usuario,
       u.nombre                          AS usuario,
       u.nivel,
       c.id_empresa,
       e.nombre                          AS empresa,
       string_agg(DISTINCT c.origen, ' + ' ORDER BY c.origen) AS propuesto_por
  FROM candidatos c
  JOIN destino  d ON d.ruta_norm = c.ruta_norm
  JOIN usuarios u ON u.id = c.id_usuario
  LEFT JOIN empresas e ON e.id = c.id_empresa
 WHERE u.nivel < 3                    -- nivel 3 no necesita asignación
   AND COALESCE(u.eliminado, false) = false
   AND COALESCE(u.estado, 1) = 1
   AND NOT EXISTS (                   -- omitir los que ya lo tienen
           SELECT 1 FROM modulos_asignados ma
            WHERE ma.id_usuario   = c.id_usuario
              AND ma.id_empresa   = c.id_empresa
              AND ma.id_submodulo = d.id_submodulo
       )
 GROUP BY c.ruta_norm, c.id_usuario, u.nombre, u.nivel, c.id_empresa, e.nombre
 ORDER BY c.ruta_norm, u.nombre;


-- ============================================================================
-- PASO 2 — Insertar (ESTE ES EL QUE ESCRIBE)
-- ----------------------------------------------------------------------------
-- Va dentro de una transacción: ejecuta todo el bloque, revisa lo que devuelve
-- y recién entonces ejecuta COMMIT (o ROLLBACK para descartarlo).
--
-- PERMISOS QUE SE OTORGAN: r/w/u/d/t = 1, es decir acceso completo — el mismo
-- que estos usuarios tenían de hecho cuando el módulo no validaba nada. Si
-- prefieres algo más restrictivo, cambia los valores en la línea marcada
-- "PERMISOS A OTORGAR" (p. ej. d = 0 para que no puedan eliminar).
-- ============================================================================
BEGIN;

-- Marca de dónde arranca lo insertado, para poder deshacerlo (PASO 4).
CREATE TEMP TABLE _antes_de_asignar AS
SELECT COALESCE(MAX(id), 0) AS max_id FROM modulos_asignados;

WITH destino AS (
    SELECT sm.id AS id_submodulo,
           sm.id_modulo,
           replace(lower(trim(sm.ruta)), '_', '-') AS ruta_norm
      FROM submodulos_menu sm
     WHERE replace(lower(trim(sm.ruta)), '_', '-') IN (
               'modulos/whatsapp-chat',
               'modulos/whatsapp-campanas',
               'modulos/descargas-sri'
           )
       AND COALESCE(sm.status, 1) = 1
),
por_uso AS (
    SELECT DISTINCT l.created_by AS id_usuario, l.id_empresa, 'modulos/descargas-sri' AS ruta_norm
      FROM sri_descarga_auto_log l
     WHERE COALESCE(l.created_by, 0) > 0
    UNION
    SELECT DISTINCT d.created_by, d.id_empresa, 'modulos/descargas-sri'
      FROM documentos_ignorados_sri d
     WHERE COALESCE(d.created_by, 0) > 0
    UNION
    SELECT DISTINCT m.created_by, m.id_empresa, 'modulos/whatsapp-chat'
      FROM whatsapp_mensajes m
     WHERE COALESCE(m.created_by, 0) > 0
       AND m.direccion = 'OUT'
       AND m.tipo_mensaje <> 'template'
    UNION
    SELECT DISTINCT m.created_by, m.id_empresa, 'modulos/whatsapp-campanas'
      FROM whatsapp_mensajes m
     WHERE COALESCE(m.created_by, 0) > 0
       AND m.direccion = 'OUT'
       AND m.tipo_mensaje = 'template'
),
por_familia AS (
    SELECT DISTINCT ma.id_usuario, ma.id_empresa, d.ruta_norm
      FROM modulos_asignados ma
      JOIN submodulos_menu sm_tiene ON sm_tiene.id = ma.id_submodulo
      JOIN destino d                ON d.id_modulo = sm_tiene.id_modulo
     WHERE COALESCE(ma.r, 0) = 1
       AND sm_tiene.id <> d.id_submodulo
       -- Descargas SRI queda FUERA de este criterio a propósito: con permiso de
       -- crear se puede abrir la ventana de login que entrega las credenciales
       -- del SRI de la empresa a la extensión. Además cuelga del módulo
       -- "Documentos", que agrupa muchos submódulos sin relación (saldos
       -- iniciales, cargas, conciliación…), así que "familia" repartiría el
       -- acceso a demasiada gente. Ese módulo se asigna solo por uso real o con
       -- la lista manual del ANEXO. Para incluirlo igual, borra esta línea:
       AND d.ruta_norm <> 'modulos/descargas-sri'
),
candidatos AS (
    SELECT DISTINCT id_usuario, id_empresa, ruta_norm FROM (
        SELECT id_usuario, id_empresa, ruta_norm FROM por_uso
        UNION ALL
        SELECT id_usuario, id_empresa, ruta_norm FROM por_familia
    ) x
)
INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
SELECT c.id_usuario,
       c.id_empresa,
       d.id_modulo,          -- el módulo del menú al que pertenece el submódulo
       d.id_submodulo,
       1, 1, 1, 1, 1         -- PERMISOS A OTORGAR: r, w, u, d, t
  FROM candidatos c
  JOIN destino  d ON d.ruta_norm = c.ruta_norm
  JOIN usuarios u ON u.id = c.id_usuario
 WHERE u.nivel < 3
   AND COALESCE(u.eliminado, false) = false
   AND COALESCE(u.estado, 1) = 1
   AND NOT EXISTS (
           SELECT 1 FROM modulos_asignados ma
            WHERE ma.id_usuario   = c.id_usuario
              AND ma.id_empresa   = c.id_empresa
              AND ma.id_submodulo = d.id_submodulo
       )
   -- Para excluir usuarios concretos, descomenta y pon sus ids:
   -- AND c.id_usuario NOT IN (11, 27)
;

-- Qué quedó insertado
SELECT sm.ruta        AS modulo,
       u.nombre       AS usuario,
       ma.id_empresa,
       ma.r, ma.w, ma.u, ma.d, ma.t
  FROM modulos_asignados ma
  JOIN submodulos_menu sm ON sm.id = ma.id_submodulo
  JOIN usuarios u         ON u.id  = ma.id_usuario
 WHERE ma.id > (SELECT max_id FROM _antes_de_asignar)
 ORDER BY sm.ruta, u.nombre;

-- Revisa la lista de arriba y ejecuta UNA de estas dos:
--   COMMIT;     -- confirmar
--   ROLLBACK;   -- descartar todo


-- ============================================================================
-- PASO 3 — Comprobar el resultado (después del COMMIT)
-- ----------------------------------------------------------------------------
-- Cuántos usuarios quedaron con cada módulo, por empresa.
-- ============================================================================
SELECT sm.ruta                AS modulo,
       ma.id_empresa,
       e.nombre               AS empresa,
       COUNT(*)               AS usuarios,
       COUNT(*) FILTER (WHERE COALESCE(ma.r, 0) = 1) AS con_permiso_ver
  FROM modulos_asignados ma
  JOIN submodulos_menu sm ON sm.id = ma.id_submodulo
  LEFT JOIN empresas e    ON e.id = ma.id_empresa
 WHERE replace(lower(trim(sm.ruta)), '_', '-') IN (
           'modulos/whatsapp-chat',
           'modulos/whatsapp-campanas',
           'modulos/descargas-sri'
       )
 GROUP BY sm.ruta, ma.id_empresa, e.nombre
 ORDER BY sm.ruta, e.nombre;


-- ============================================================================
-- PASO 4 — Deshacer (solo si ya hiciste COMMIT y te arrepentiste)
-- ----------------------------------------------------------------------------
-- Sustituye <MAX_ID> por el valor que tenía _antes_de_asignar.max_id en el
-- PASO 2 (si cerraste la sesión, esa tabla temporal ya no existe).
-- Borra ÚNICAMENTE filas de estos tres submódulos creadas después de esa marca.
-- ============================================================================
-- DELETE FROM modulos_asignados ma
--  USING submodulos_menu sm
--  WHERE sm.id = ma.id_submodulo
--    AND ma.id > <MAX_ID>
--    AND replace(lower(trim(sm.ruta)), '_', '-') IN (
--            'modulos/whatsapp-chat',
--            'modulos/whatsapp-campanas',
--            'modulos/descargas-sri'
--        );


-- ============================================================================
-- ANEXO — Alternativa: asignar a una lista concreta de usuarios
-- ----------------------------------------------------------------------------
-- Si prefieres decidir tú a quién, reemplaza la lista de pares
-- (id_usuario, id_empresa) y ejecuta este bloque en lugar del PASO 2.
-- ============================================================================
-- BEGIN;
-- WITH destino AS (
--     SELECT sm.id AS id_submodulo, sm.id_modulo,
--            replace(lower(trim(sm.ruta)), '_', '-') AS ruta_norm
--       FROM submodulos_menu sm
--      WHERE replace(lower(trim(sm.ruta)), '_', '-') IN (
--                'modulos/whatsapp-chat',
--                'modulos/whatsapp-campanas',
--                'modulos/descargas-sri'   -- deja solo los módulos que quieras
--            )
--        AND COALESCE(sm.status, 1) = 1
-- ),
-- lista(id_usuario, id_empresa) AS (
--     VALUES (9, 1), (11, 1), (11, 2)      -- <<< tus pares usuario/empresa
-- )
-- INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
-- SELECT l.id_usuario, l.id_empresa, d.id_modulo, d.id_submodulo, 1, 1, 1, 1, 1
--   FROM lista l CROSS JOIN destino d
--  WHERE NOT EXISTS (
--            SELECT 1 FROM modulos_asignados ma
--             WHERE ma.id_usuario   = l.id_usuario
--               AND ma.id_empresa   = l.id_empresa
--               AND ma.id_submodulo = d.id_submodulo
--        );
-- COMMIT;
