-- =============================================================================
-- Recibos de Venta: pasar a 'emitido' los recibos en 'borrador' ya PAGADOS POR COMPLETO
-- (04-10-2026)
--
-- Por qué: el recibo nace como 'borrador' y no existía ningún paso que lo pasara a
-- 'emitido'. Desde el 12-08-2026 la contabilidad (sincronización y contabilidad automática)
-- solo genera asiento a los recibos 'emitido', así que los recibos creados en el sistema
-- quedaban sin asiento aunque estuvieran cobrados. Desde esta versión el código los emite
-- solo al quedar pagados por completo (IngresoService → ReciboVentaService::emitirSiPagados).
-- Este script pone al día los que ya estaban pagados.
--
-- Criterio (el mismo del código): estado = 'borrador', no eliminado, y lo cobrado por
-- ingresos vigentes (no anulados ni eliminados) cubre el importe total. Los recibos solo
-- bajan su saldo con cobros (no llevan NC ni retenciones).
--
-- Idempotente: una segunda corrida no encuentra nada (ya no están en 'borrador').
-- Deja una fila de auditoría en log_sistema por cada recibo (accion = 'EMITIR').
--
-- Después de ejecutarlo: los asientos que faltan los genera la contabilidad automática al
-- abrir Recibos de Venta (50 por pasada) o la sincronización manual de Asientos Contables.
--
-- Listo para pegar en el Query Tool de pgAdmin y ejecutar (F5).
-- =============================================================================

-- 1) VISTA PREVIA (opcional): qué recibos cambiarían, por empresa.
-- SELECT r.id_empresa, r.id,
--        r.establecimiento || '-' || r.punto_emision || '-' || r.secuencial AS recibo,
--        r.fecha_emision::date, r.importe_total, c.cobrado
--   FROM recibos_venta_cabecera r
--   CROSS JOIN LATERAL (
--        SELECT COALESCE(SUM(ind.monto_cobrado), 0) AS cobrado
--          FROM ingresos_detalle ind
--          JOIN ingresos_cabecera inc ON inc.id = ind.id_ingreso
--         WHERE ind.id_referencia_documento = r.id
--           AND ind.tipo_documento = 'RECIBO'
--           AND inc.estado <> 'anulado'
--           AND inc.eliminado = false
--   ) c
--  WHERE r.eliminado = false
--    AND r.estado = 'borrador'
--    AND r.importe_total - c.cobrado <= 0.005
--  ORDER BY r.id_empresa, r.fecha_emision;

-- 2) CAMBIO + AUDITORÍA (una sola sentencia: lo que se actualiza es exactamente lo que se audita).
WITH pagados AS (
    SELECT r.id
      FROM recibos_venta_cabecera r
     WHERE r.eliminado = false
       AND r.estado = 'borrador'
       AND r.importe_total - (
               SELECT COALESCE(SUM(ind.monto_cobrado), 0)
                 FROM ingresos_detalle ind
                 JOIN ingresos_cabecera inc ON inc.id = ind.id_ingreso
                WHERE ind.id_referencia_documento = r.id
                  AND ind.tipo_documento = 'RECIBO'
                  AND inc.estado <> 'anulado'
                  AND inc.eliminado = false
           ) <= 0.005
),
emitidos AS (
    UPDATE recibos_venta_cabecera r
       SET estado = 'emitido',
           updated_at = CURRENT_TIMESTAMP
      FROM pagados p
     WHERE r.id = p.id
       AND r.estado = 'borrador'
    RETURNING r.id, r.id_empresa
)
INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                         datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
SELECT NULL, e.id_empresa, 'EMITIR', 'recibos_venta_cabecera', e.id,
       '{"estado": "borrador"}'::jsonb,
       '{"estado": "emitido", "motivo": "Recibo pagado por completo (script 20261004)"}'::jsonb,
       NULL, 'script SQL 20261004_recibos_pagados_a_emitido', CURRENT_TIMESTAMP
  FROM emitidos e;

-- 3) VERIFICACIÓN (opcional): debe devolver 0.
-- SELECT COUNT(*)
--   FROM recibos_venta_cabecera r
--  WHERE r.eliminado = false AND r.estado = 'borrador'
--    AND r.importe_total - (SELECT COALESCE(SUM(ind.monto_cobrado), 0)
--                             FROM ingresos_detalle ind
--                             JOIN ingresos_cabecera inc ON inc.id = ind.id_ingreso
--                            WHERE ind.id_referencia_documento = r.id AND ind.tipo_documento = 'RECIBO'
--                              AND inc.estado <> 'anulado' AND inc.eliminado = false) <= 0.005;
