-- ¿Por qué Cuentas por Pagar a una fecha no cuadra con el Mayor de Proveedores a esa fecha?
-- CxP se arma con DOCUMENTOS (compras, liquidaciones, saldos iniciales menos egresos, retenciones
-- y NC/ND); el Mayor se arma con ASIENTOS contabilizados sobre la(s) cuenta(s) configurada(s)
-- como "Cuenta por pagar" en Configuración contable → compras. Esta consulta desglosa el Mayor
-- por el módulo que originó cada asiento, para ver de qué lado viene la diferencia.
-- Solo lectura. Cambiar empresa y fecha de corte en la primera línea.

WITH p AS (SELECT 33::int AS id_empresa, DATE '2026-05-31' AS corte),
cta AS (   -- todas las cuentas usadas como Cuenta por pagar (general y por proveedor/ítem/categoría/marca)
    SELECT DISTINCT ap.id_cuenta
      FROM p
      JOIN asientos_programados ap ON ap.id_empresa = p.id_empresa AND ap.eliminado = false
      JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo AND at.codigo = 'PORPAGARFACTURACOMPRA'
     WHERE ap.id_cuenta IS NOT NULL
)
-- 1) Mayor de proveedores al corte, desglosado por origen del asiento
SELECT pc.codigo AS cuenta, ac.modulo_origen,
       COUNT(DISTINCT ac.id)        AS asientos,
       SUM(ad.debe)                 AS debe,
       SUM(ad.haber)                AS haber,
       SUM(ad.haber) - SUM(ad.debe) AS saldo_acreedor
  FROM p
  JOIN asientos_contables_cabecera ac ON ac.id_empresa = p.id_empresa
                                     AND ac.fecha_asiento <= p.corte
                                     AND ac.estado = 'contabilizado' AND ac.eliminado = false
  JOIN asientos_contables_detalle ad ON ad.id_asiento = ac.id AND ad.eliminado = false
  JOIN plan_cuentas pc ON pc.id = ad.id_cuenta_contable
 WHERE ad.id_cuenta_contable IN (SELECT id_cuenta FROM cta)
 GROUP BY pc.codigo, ac.modulo_origen
 ORDER BY pc.codigo, ac.modulo_origen;

-- 2) Asientos sobre la cuenta de proveedores cuyo documento de compra ya no cuenta en CxP
--    (anulado, rechazado, pendiente de aprobación o eliminado) → inflan el Mayor.
-- WITH p AS (...), cta AS (...)
-- SELECT ac.id AS asiento, ac.fecha_asiento, ac.modulo_origen, c.id AS compra,
--        c.establecimiento_prov||'-'||c.punto_emision_prov||'-'||c.secuencial_prov AS numero,
--        c.estado, c.eliminado, SUM(ad.haber - ad.debe) AS efecto
--   FROM p
--   JOIN asientos_contables_cabecera ac ON ac.id_empresa = p.id_empresa AND ac.fecha_asiento <= p.corte
--    AND ac.estado = 'contabilizado' AND ac.eliminado = false AND ac.modulo_origen = 'compra'
--   JOIN asientos_contables_detalle ad ON ad.id_asiento = ac.id AND ad.eliminado = false
--    AND ad.id_cuenta_contable IN (SELECT id_cuenta FROM cta)
--   JOIN compras_cabecera c ON c.id = ac.id_referencia_origen AND c.id_empresa = p.id_empresa
--  WHERE c.eliminado = true OR UPPER(TRIM(COALESCE(c.estado,''))) IN ('ANULADO','ANULADA','RECHAZADA','PENDIENTE_APROBACION')
--  GROUP BY 1,2,3,4,5,6,7 ORDER BY ac.fecha_asiento;

-- 3) Compras y liquidaciones al corte SIN asiento contabilizado → están en CxP pero no en el Mayor.
-- WITH p AS (...)
-- SELECT 'compra' AS tipo, c.id, c.establecimiento_prov||'-'||c.punto_emision_prov||'-'||c.secuencial_prov AS numero,
--        c.fecha_emision, c.estado, c.importe_total, c.id_asiento_contable, a.estado AS asiento_estado
--   FROM p JOIN compras_cabecera c ON c.id_empresa = p.id_empresa
--   LEFT JOIN asientos_contables_cabecera a ON a.id = c.id_asiento_contable
--  WHERE c.eliminado = false AND c.fecha_emision <= p.corte AND c.tipo_comprobante = '01'
--    AND UPPER(TRIM(COALESCE(c.estado,''))) NOT IN ('ANULADO','ANULADA','RECHAZADA','PENDIENTE_APROBACION')
--    AND (a.id IS NULL OR a.estado <> 'contabilizado' OR a.eliminado = true)
--  ORDER BY c.fecha_emision;

-- 4) Egresos al corte que pagan compras pero cuyo asiento NO toca la cuenta de proveedores
--    (pagos contabilizados contra la cuenta del concepto, no contra CxP) → CxP baja, el Mayor no.
-- WITH p AS (...), cta AS (...)
-- SELECT e.id, e.numero_egreso, e.fecha_emision, e.estado, e.id_asiento_contable,
--        SUM(ed.monto_pagado) AS pagado_a_compras
--   FROM p JOIN egresos_cabecera e ON e.id_empresa = p.id_empresa
--   JOIN egresos_detalle ed ON ed.id_egreso = e.id AND ed.eliminado = false AND ed.tipo_documento IN ('COMPRA','LIQUIDACION','SALDO_INICIAL')
--  WHERE e.eliminado = false AND e.fecha_emision <= p.corte AND UPPER(TRIM(COALESCE(e.estado,''))) <> 'ANULADO'
--    AND NOT EXISTS (SELECT 1 FROM asientos_contables_detalle ad
--                     WHERE ad.id_asiento = e.id_asiento_contable AND ad.eliminado = false
--                       AND ad.id_cuenta_contable IN (SELECT id_cuenta FROM cta))
--  GROUP BY 1,2,3,4,5 ORDER BY e.fecha_emision;
