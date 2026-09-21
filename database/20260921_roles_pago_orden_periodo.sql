-- ============================================================================
-- Roles de Pago — orden por defecto: período más reciente primero
-- Fecha: 21-09-2026
--
-- OPCIONAL. El cambio de código ya deja el listado ordenado por período
-- descendente para quien NUNCA haya tocado un encabezado de la tabla. Quien sí
-- lo hizo tiene su propio orden guardado en usuarios_preferencias y ese manda:
-- seguirá viendo el listado como lo dejó (puede cambiarlo con un clic en
-- "Período").
--
-- Ejecutar esto SOLO si se quiere que el nuevo orden por defecto se aplique
-- también a esos usuarios, borrando la preferencia de orden que guardaron.
-- No borra las demás preferencias del módulo (columnas ocultas y anchos).
-- ============================================================================

-- PASO 1 — Ver a quién afectaría (no modifica nada).
SELECT id_usuario,
       id_empresa,
       preferencias -> '__vista__' ->> '__ordenCol__'   AS orden_col,
       preferencias -> '__vista__' ->> '__ordenDir__'   AS orden_dir,
       preferencias -> '__vista__' ->  '__ordenMulti__' AS orden_multi
  FROM usuarios_preferencias
 WHERE modulo = 'roles_pago'
   AND jsonb_exists_any(preferencias -> '__vista__', ARRAY['__ordenCol__', '__ordenDir__', '__ordenMulti__'])
 ORDER BY id_empresa, id_usuario;

-- PASO 2 — Borrar solo las tres claves de orden dentro de __vista__.
-- (Revisar el resultado del PASO 1 antes de ejecutar.)
UPDATE usuarios_preferencias
   SET preferencias = jsonb_set(
           preferencias,
           '{__vista__}',
           (preferencias -> '__vista__') - '__ordenCol__' - '__ordenDir__' - '__ordenMulti__'
       )
 WHERE modulo = 'roles_pago'
   AND jsonb_exists_any(preferencias -> '__vista__', ARRAY['__ordenCol__', '__ordenDir__', '__ordenMulti__']);

-- PASO 3 — Comprobar que ya no queda ninguna (debe devolver 0 filas).
SELECT id_usuario, id_empresa, preferencias -> '__vista__' AS vista
  FROM usuarios_preferencias
 WHERE modulo = 'roles_pago'
   AND jsonb_exists_any(preferencias -> '__vista__', ARRAY['__ordenCol__', '__ordenDir__', '__ordenMulti__']);
