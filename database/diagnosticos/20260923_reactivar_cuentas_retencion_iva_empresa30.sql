-- =============================================================================
-- Empresa 30 (RUC 1711548931001): el balance no cuadra por 472.91
--
-- Causa: las cuentas 1.1.2.04.003 RETENCIONES DE IVA 30 (id 3322) y
-- 1.1.2.04.004 RETENCIONES DE IVA 70 (id 3323) están ELIMINADAS en el plan de
-- cuentas, pero 97 asientos las usan (116.97 + 355.94 = 472.91 neto al debe).
-- El balance solo cuenta cuentas activas, así que el Activo queda 472.91 corto.
--
-- Siguen recibiendo movimientos porque la configuración de retenciones
-- (asientos_programados, tipo 'retenciones_venta_debe') apunta a esas cuentas
-- por id y AsientoBuilderService::generarAsientoRetencionVenta() no revisa si
-- la cuenta está eliminada.
--
-- Ejecutar en pgAdmin UN bloque a la vez. Si algo falla: ROLLBACK;
-- =============================================================================


-- A) REVISIÓN (solo lectura) ---------------------------------------------------

-- A1. Las dos cuentas, su padre y quién/cuándo las eliminó
SELECT pc.id, pc.codigo, pc.nombre, pc.nivel, pc.eliminado, pc.deleted_at,
       pc.deleted_by, u.nombre AS eliminada_por
FROM plan_cuentas pc
LEFT JOIN usuarios u ON u.id = pc.deleted_by
WHERE pc.id_empresa = 30
  AND pc.codigo IN ('1.1.2.04', '1.1.2.04.003', '1.1.2.04.004')
ORDER BY pc.codigo, pc.id;

-- A2. ¿Existe OTRA cuenta ACTIVA que las reemplace (mismo código o nombre)?
--     Si esta consulta devuelve filas, NO ejecutar el bloque B: avisar, porque
--     entonces lo correcto es mover las líneas a esa cuenta, no reactivar.
SELECT pc.id, pc.codigo, pc.nombre, pc.nivel, pc.created_at
FROM plan_cuentas pc
WHERE pc.id_empresa = 30
  AND pc.eliminado = false
  AND (pc.codigo IN ('1.1.2.04.003', '1.1.2.04.004')
       OR pc.nombre ILIKE '%RETENCION%IVA%30%'
       OR pc.nombre ILIKE '%RETENCION%IVA%70%');

-- A3. Configuración que sigue apuntando a esas cuentas
SELECT ap.id, ap.tipo_referencia, ap.id_referencia, ap.referencia_texto, ap.id_cuenta
FROM asientos_programados ap
WHERE ap.id_empresa = 30
  AND ap.eliminado = false
  AND ap.id_cuenta IN (3322, 3323);


-- B) CORRECCIÓN: reactivar las dos cuentas (solo si A2 no devolvió filas) ------
--    Cambiar el 1 de "usr" por TU id_usuario (queda en log_sistema y updated_by).

BEGIN;

WITH usr AS (SELECT 1 AS id),                                   -- ← tu id_usuario
antes AS (
    SELECT pc.* FROM plan_cuentas pc
    WHERE pc.id_empresa = 30 AND pc.id IN (3322, 3323) AND pc.eliminado = true
    FOR UPDATE
),
upd AS (
    UPDATE plan_cuentas pc
       SET eliminado  = false,
           deleted_at = NULL,
           deleted_by = NULL,
           updated_at = NOW(),
           updated_by = (SELECT id FROM usr)
      FROM antes a
     WHERE pc.id = a.id
    RETURNING pc.*
)
INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                         datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
SELECT (SELECT id FROM usr), 30, 'reactivar', 'plan_cuentas', u.id,
       to_jsonb(a), to_jsonb(u), 'sql-manual',
       'Reactivación: cuenta eliminada con asientos (balance descuadrado 472.91)', NOW()
FROM upd u
JOIN antes a ON a.id = u.id;

-- Debe mostrar las 2 cuentas con eliminado = false
SELECT id, codigo, nombre, eliminado, updated_at FROM plan_cuentas WHERE id IN (3322, 3323);

COMMIT;   -- o ROLLBACK; si algo no se ve bien


-- C) VERIFICACIÓN: repetir la consulta 4 de 20260923_balance_descuadrado.sql.
--    diferencia_esperada_del_balance debe dar 0.00, y el aviso del
--    Estado de Situación Financiera debe desaparecer.
