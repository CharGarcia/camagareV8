-- ============================================================================
-- Reparación: pagos de rol (egresos) que quedaron HUÉRFANOS
-- ----------------------------------------------------------------------------
-- Causa: una regeneración previa del rol recreó las filas rol_detalle con IDs
-- nuevos (borró las viejas). Los egresos apuntaban al id viejo → quedaron sin
-- vincular y el rol mostraba "Pago pendiente" pese a estar pagado.
--
-- Este script re-vincula cada egresos_detalle 'ROL' huérfano a la línea ACTUAL
-- del rol del mismo empleado, mismo período (texto "Rol M/AAAA") y mismo monto.
--
-- 1) Revisa primero el PREVIEW (no cambia nada). 2) Si se ve correcto, ejecuta
--    el UPDATE. Idempotente: solo toca los huérfanos.
-- ============================================================================

-- ---------- PREVIEW (qué se re-vincularía) ----------
SELECT ed.id                       AS egreso_detalle_id,
       ec.numero_egreso,
       ec.id_empleado,
       ed.numero_documento,
       ed.monto_pagado,
       ed.id_referencia_documento  AS id_rol_detalle_viejo,
       rd.id                        AS id_rol_detalle_nuevo,
       rc.periodo_mes || '/' || rc.periodo_anio AS periodo
FROM egresos_detalle ed
JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
JOIN rol_cabecera rc
  ON rc.id_empresa = ec.id_empresa AND rc.eliminado = false
 AND ltrim(regexp_replace(ed.numero_documento, '\D', '', 'g'), '0')
     = ltrim(rc.periodo_mes::text || rc.periodo_anio::text, '0')
JOIN rol_detalle rd
  ON rd.id_rol = rc.id AND rd.id_empleado = ec.id_empleado
 AND abs(rd.neto - ed.monto_documento) < 0.01
WHERE ed.tipo_documento = 'ROL'
  AND ec.estado != 'anulado' AND ec.eliminado = false AND ed.eliminado = false
  AND NOT EXISTS (SELECT 1 FROM rol_detalle x WHERE x.id = ed.id_referencia_documento);

-- ---------- APLICAR (descomentar para ejecutar) ----------
-- UPDATE egresos_detalle ed
-- SET id_referencia_documento = m.new_id
-- FROM (
--     SELECT ed.id AS ed_id, rd.id AS new_id
--     FROM egresos_detalle ed
--     JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
--     JOIN rol_cabecera rc
--       ON rc.id_empresa = ec.id_empresa AND rc.eliminado = false
--      AND ltrim(regexp_replace(ed.numero_documento, '\D', '', 'g'), '0')
--          = ltrim(rc.periodo_mes::text || rc.periodo_anio::text, '0')
--     JOIN rol_detalle rd
--       ON rd.id_rol = rc.id AND rd.id_empleado = ec.id_empleado
--      AND abs(rd.neto - ed.monto_documento) < 0.01
--     WHERE ed.tipo_documento = 'ROL'
--       AND ec.estado != 'anulado' AND ec.eliminado = false AND ed.eliminado = false
--       AND NOT EXISTS (SELECT 1 FROM rol_detalle x WHERE x.id = ed.id_referencia_documento)
-- ) m
-- WHERE ed.id = m.ed_id;
