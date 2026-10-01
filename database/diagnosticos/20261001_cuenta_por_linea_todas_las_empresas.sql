-- =============================================================================
-- Diagnóstico (SOLO LECTURA), TODAS LAS EMPRESAS: ingresos y egresos con
-- asientos afectados por los dos errores corregidos el 2026-10-01 en
-- AsientoBuilderService::contrapartidaPorCuenta().
--
--   ERROR 1 — La cuenta elegida a mano en una línea de "Otros conceptos" NO
--             está en el asiento (se usó la del concepto, p. ej. Cuentas por
--             Pagar). Misma lógica que 20261001_egresos_ingresos_cuenta_por_linea_no_aplicada.sql
--   ERROR 2 — El saldo de documentos de cartera SIN asiento propio se sumó a la
--             cuenta de una línea manual. Misma lógica que
--             20261001_egresos_ingresos_cartera_en_cuenta_manual.sql
--
-- Son 2 consultas. En pgAdmin seleccione UNA (desde su comentario "-- N." hasta
-- el punto y coma) y ejecútela con F5.
--   1. RESUMEN por empresa: cuántos documentos y cuánto dinero afecta cada error.
--   2. DETALLE: un renglón por documento y cuenta afectada.
--
-- Corrección de cada documento (con el arreglo ya desplegado): generar, si
-- corresponde, el asiento de los documentos de cartera que no lo tienen, y luego
-- abrir el ingreso/egreso y guardarlo sin cambios.
-- =============================================================================


-- 1. RESUMEN POR EMPRESA ------------------------------------------------------
WITH
error1 AS (
    -- Egresos: línea MANUAL cuya cuenta no está en el Debe del asiento vigente
    SELECT e.id_empresa, 'EGRESO'::text AS tipo, e.id AS id_doc, d.monto_pagado AS monto
      FROM egresos_cabecera e
      JOIN egresos_detalle d ON d.id_egreso = e.id AND d.eliminado = false
                            AND d.tipo_documento = 'MANUAL'
                            AND d.id_cuenta_contable IS NOT NULL AND d.monto_pagado > 0
      JOIN asientos_contables_cabecera a ON a.modulo_origen = 'egreso' AND a.id_referencia_origen = e.id
                                        AND a.id_empresa = e.id_empresa
                                        AND a.eliminado = false AND a.estado <> 'anulado'
     WHERE e.eliminado = false
       AND NOT EXISTS (SELECT 1 FROM asientos_contables_detalle ad
                        WHERE ad.id_asiento = a.id AND ad.eliminado = false AND ad.debe > 0
                          AND ad.id_cuenta_contable = d.id_cuenta_contable)
    UNION ALL
    -- Ingresos: línea OTRO cuya cuenta no está en el Haber del asiento vigente
    SELECT i.id_empresa, 'INGRESO', i.id, d.monto_cobrado
      FROM ingresos_cabecera i
      JOIN ingresos_detalle d ON d.id_ingreso = i.id
                             AND d.tipo_documento = 'OTRO'
                             AND d.id_cuenta_contable IS NOT NULL AND d.monto_cobrado > 0
      JOIN asientos_contables_cabecera a ON a.modulo_origen = 'ingreso' AND a.id_referencia_origen = i.id
                                        AND a.id_empresa = i.id_empresa
                                        AND a.eliminado = false AND a.estado <> 'anulado'
     WHERE i.eliminado = false
       AND NOT EXISTS (SELECT 1 FROM asientos_contables_detalle ad
                        WHERE ad.id_asiento = a.id AND ad.eliminado = false AND ad.haber > 0
                          AND ad.id_cuenta_contable = d.id_cuenta_contable)
),
error2 AS (
    -- Egresos: cuenta manual con más valor en el asiento que la suma de sus líneas,
    -- habiendo compras/liquidaciones sin asiento propio
    SELECT e.id_empresa, 'EGRESO'::text AS tipo, e.id AS id_doc,
           a.en_asiento - m.monto_lineas AS monto
      FROM (SELECT id_egreso, id_cuenta_contable, SUM(monto_pagado) AS monto_lineas
              FROM egresos_detalle
             WHERE eliminado = false AND tipo_documento = 'MANUAL'
               AND id_cuenta_contable IS NOT NULL AND monto_pagado > 0
             GROUP BY id_egreso, id_cuenta_contable) m
      JOIN egresos_cabecera e ON e.id = m.id_egreso AND e.eliminado = false
      JOIN (SELECT c.id_referencia_origen AS id_egreso, ad.id_cuenta_contable, SUM(ad.debe) AS en_asiento
              FROM asientos_contables_cabecera c
              JOIN asientos_contables_detalle ad ON ad.id_asiento = c.id AND ad.eliminado = false AND ad.debe > 0
             WHERE c.modulo_origen = 'egreso' AND c.eliminado = false AND c.estado <> 'anulado'
             GROUP BY c.id_referencia_origen, ad.id_cuenta_contable) a
        ON a.id_egreso = m.id_egreso AND a.id_cuenta_contable = m.id_cuenta_contable
     WHERE a.en_asiento > m.monto_lineas + 0.05
       AND EXISTS (SELECT 1 FROM egresos_detalle dc
                    WHERE dc.id_egreso = e.id AND dc.eliminado = false
                      AND dc.tipo_documento IN ('COMPRA', 'LIQUIDACION') AND dc.monto_pagado > 0
                      AND NOT EXISTS (SELECT 1 FROM asientos_contables_cabecera ac
                                       WHERE ac.modulo_origen = CASE dc.tipo_documento WHEN 'COMPRA' THEN 'compra' ELSE 'liquidacion_compra' END
                                         AND ac.id_referencia_origen = dc.id_referencia_documento
                                         AND ac.id_empresa = e.id_empresa
                                         AND ac.eliminado = false AND ac.estado <> 'anulado'))
    UNION ALL
    -- Ingresos: espejo con facturas/recibos de venta sin asiento propio (lado Haber)
    SELECT i.id_empresa, 'INGRESO', i.id, a.en_asiento - m.monto_lineas
      FROM (SELECT id_ingreso, id_cuenta_contable, SUM(monto_cobrado) AS monto_lineas
              FROM ingresos_detalle
             WHERE tipo_documento = 'OTRO' AND id_cuenta_contable IS NOT NULL AND monto_cobrado > 0
             GROUP BY id_ingreso, id_cuenta_contable) m
      JOIN ingresos_cabecera i ON i.id = m.id_ingreso AND i.eliminado = false
      JOIN (SELECT c.id_referencia_origen AS id_ingreso, ad.id_cuenta_contable, SUM(ad.haber) AS en_asiento
              FROM asientos_contables_cabecera c
              JOIN asientos_contables_detalle ad ON ad.id_asiento = c.id AND ad.eliminado = false AND ad.haber > 0
             WHERE c.modulo_origen = 'ingreso' AND c.eliminado = false AND c.estado <> 'anulado'
             GROUP BY c.id_referencia_origen, ad.id_cuenta_contable) a
        ON a.id_ingreso = m.id_ingreso AND a.id_cuenta_contable = m.id_cuenta_contable
     WHERE a.en_asiento > m.monto_lineas + 0.05
       AND EXISTS (SELECT 1 FROM ingresos_detalle dc
                    WHERE dc.id_ingreso = i.id
                      AND dc.tipo_documento IN ('FACTURA', 'RECIBO') AND dc.monto_cobrado > 0
                      AND NOT EXISTS (SELECT 1 FROM asientos_contables_cabecera ac
                                       WHERE ac.modulo_origen = CASE dc.tipo_documento WHEN 'FACTURA' THEN 'factura_venta' ELSE 'recibo_venta' END
                                         AND ac.id_referencia_origen = dc.id_referencia_documento
                                         AND ac.id_empresa = i.id_empresa
                                         AND ac.eliminado = false AND ac.estado <> 'anulado'))
),
todos AS (
    SELECT id_empresa, 1 AS error, tipo, id_doc, monto FROM error1
    UNION ALL
    SELECT id_empresa, 2, tipo, id_doc, monto FROM error2
)
SELECT t.id_empresa,
       emp.ruc,
       COALESCE(NULLIF(emp.nombre_comercial, ''), emp.nombre)    AS empresa,
       COUNT(DISTINCT t.id_doc) FILTER (WHERE t.error = 1 AND t.tipo = 'EGRESO')  AS err1_egresos,
       COUNT(DISTINCT t.id_doc) FILTER (WHERE t.error = 1 AND t.tipo = 'INGRESO') AS err1_ingresos,
       ROUND(COALESCE(SUM(t.monto) FILTER (WHERE t.error = 1), 0), 2)            AS err1_monto_mal_contabilizado,
       COUNT(DISTINCT t.id_doc) FILTER (WHERE t.error = 2 AND t.tipo = 'EGRESO')  AS err2_egresos,
       COUNT(DISTINCT t.id_doc) FILTER (WHERE t.error = 2 AND t.tipo = 'INGRESO') AS err2_ingresos,
       ROUND(COALESCE(SUM(t.monto) FILTER (WHERE t.error = 2), 0), 2)            AS err2_monto_excedente
  FROM todos t
  LEFT JOIN empresas emp ON emp.id = t.id_empresa
 GROUP BY t.id_empresa, emp.ruc, emp.nombre_comercial, emp.nombre
 ORDER BY COALESCE(SUM(t.monto), 0) DESC, emp.ruc;


-- 2. DETALLE POR DOCUMENTO ----------------------------------------------------
--    Para ver una sola empresa, descomente el filtro "AND emp.ruc = ..." del final.
WITH
error1 AS (
    SELECT e.id_empresa, 1 AS error, 'EGRESO'::text AS tipo, e.id AS id_doc,
           COALESCE(e.numero_egreso, e.establecimiento || '-' || e.punto_emision || '-' || e.secuencial) AS numero,
           e.fecha_emision, d.id_cuenta_contable,
           SUM(d.monto_pagado) AS monto
      FROM egresos_cabecera e
      JOIN egresos_detalle d ON d.id_egreso = e.id AND d.eliminado = false
                            AND d.tipo_documento = 'MANUAL'
                            AND d.id_cuenta_contable IS NOT NULL AND d.monto_pagado > 0
      JOIN asientos_contables_cabecera a ON a.modulo_origen = 'egreso' AND a.id_referencia_origen = e.id
                                        AND a.id_empresa = e.id_empresa
                                        AND a.eliminado = false AND a.estado <> 'anulado'
     WHERE e.eliminado = false
       AND NOT EXISTS (SELECT 1 FROM asientos_contables_detalle ad
                        WHERE ad.id_asiento = a.id AND ad.eliminado = false AND ad.debe > 0
                          AND ad.id_cuenta_contable = d.id_cuenta_contable)
     GROUP BY e.id_empresa, e.id, e.numero_egreso, e.establecimiento, e.punto_emision, e.secuencial,
              e.fecha_emision, d.id_cuenta_contable
    UNION ALL
    SELECT i.id_empresa, 1, 'INGRESO', i.id,
           COALESCE(i.numero_ingreso, i.establecimiento || '-' || i.punto_emision || '-' || i.secuencial),
           i.fecha_emision, d.id_cuenta_contable,
           SUM(d.monto_cobrado)
      FROM ingresos_cabecera i
      JOIN ingresos_detalle d ON d.id_ingreso = i.id
                             AND d.tipo_documento = 'OTRO'
                             AND d.id_cuenta_contable IS NOT NULL AND d.monto_cobrado > 0
      JOIN asientos_contables_cabecera a ON a.modulo_origen = 'ingreso' AND a.id_referencia_origen = i.id
                                        AND a.id_empresa = i.id_empresa
                                        AND a.eliminado = false AND a.estado <> 'anulado'
     WHERE i.eliminado = false
       AND NOT EXISTS (SELECT 1 FROM asientos_contables_detalle ad
                        WHERE ad.id_asiento = a.id AND ad.eliminado = false AND ad.haber > 0
                          AND ad.id_cuenta_contable = d.id_cuenta_contable)
     GROUP BY i.id_empresa, i.id, i.numero_ingreso, i.establecimiento, i.punto_emision, i.secuencial,
              i.fecha_emision, d.id_cuenta_contable
),
error2 AS (
    SELECT e.id_empresa, 2 AS error, 'EGRESO'::text AS tipo, e.id AS id_doc,
           COALESCE(e.numero_egreso, e.establecimiento || '-' || e.punto_emision || '-' || e.secuencial) AS numero,
           e.fecha_emision, m.id_cuenta_contable,
           a.en_asiento - m.monto_lineas AS monto
      FROM (SELECT id_egreso, id_cuenta_contable, SUM(monto_pagado) AS monto_lineas
              FROM egresos_detalle
             WHERE eliminado = false AND tipo_documento = 'MANUAL'
               AND id_cuenta_contable IS NOT NULL AND monto_pagado > 0
             GROUP BY id_egreso, id_cuenta_contable) m
      JOIN egresos_cabecera e ON e.id = m.id_egreso AND e.eliminado = false
      JOIN (SELECT c.id_referencia_origen AS id_egreso, ad.id_cuenta_contable, SUM(ad.debe) AS en_asiento
              FROM asientos_contables_cabecera c
              JOIN asientos_contables_detalle ad ON ad.id_asiento = c.id AND ad.eliminado = false AND ad.debe > 0
             WHERE c.modulo_origen = 'egreso' AND c.eliminado = false AND c.estado <> 'anulado'
             GROUP BY c.id_referencia_origen, ad.id_cuenta_contable) a
        ON a.id_egreso = m.id_egreso AND a.id_cuenta_contable = m.id_cuenta_contable
     WHERE a.en_asiento > m.monto_lineas + 0.05
       AND EXISTS (SELECT 1 FROM egresos_detalle dc
                    WHERE dc.id_egreso = e.id AND dc.eliminado = false
                      AND dc.tipo_documento IN ('COMPRA', 'LIQUIDACION') AND dc.monto_pagado > 0
                      AND NOT EXISTS (SELECT 1 FROM asientos_contables_cabecera ac
                                       WHERE ac.modulo_origen = CASE dc.tipo_documento WHEN 'COMPRA' THEN 'compra' ELSE 'liquidacion_compra' END
                                         AND ac.id_referencia_origen = dc.id_referencia_documento
                                         AND ac.id_empresa = e.id_empresa
                                         AND ac.eliminado = false AND ac.estado <> 'anulado'))
    UNION ALL
    SELECT i.id_empresa, 2, 'INGRESO', i.id,
           COALESCE(i.numero_ingreso, i.establecimiento || '-' || i.punto_emision || '-' || i.secuencial),
           i.fecha_emision, m.id_cuenta_contable,
           a.en_asiento - m.monto_lineas
      FROM (SELECT id_ingreso, id_cuenta_contable, SUM(monto_cobrado) AS monto_lineas
              FROM ingresos_detalle
             WHERE tipo_documento = 'OTRO' AND id_cuenta_contable IS NOT NULL AND monto_cobrado > 0
             GROUP BY id_ingreso, id_cuenta_contable) m
      JOIN ingresos_cabecera i ON i.id = m.id_ingreso AND i.eliminado = false
      JOIN (SELECT c.id_referencia_origen AS id_ingreso, ad.id_cuenta_contable, SUM(ad.haber) AS en_asiento
              FROM asientos_contables_cabecera c
              JOIN asientos_contables_detalle ad ON ad.id_asiento = c.id AND ad.eliminado = false AND ad.haber > 0
             WHERE c.modulo_origen = 'ingreso' AND c.eliminado = false AND c.estado <> 'anulado'
             GROUP BY c.id_referencia_origen, ad.id_cuenta_contable) a
        ON a.id_ingreso = m.id_ingreso AND a.id_cuenta_contable = m.id_cuenta_contable
     WHERE a.en_asiento > m.monto_lineas + 0.05
       AND EXISTS (SELECT 1 FROM ingresos_detalle dc
                    WHERE dc.id_ingreso = i.id
                      AND dc.tipo_documento IN ('FACTURA', 'RECIBO') AND dc.monto_cobrado > 0
                      AND NOT EXISTS (SELECT 1 FROM asientos_contables_cabecera ac
                                       WHERE ac.modulo_origen = CASE dc.tipo_documento WHEN 'FACTURA' THEN 'factura_venta' ELSE 'recibo_venta' END
                                         AND ac.id_referencia_origen = dc.id_referencia_documento
                                         AND ac.id_empresa = i.id_empresa
                                         AND ac.eliminado = false AND ac.estado <> 'anulado'))
)
SELECT t.id_empresa,
       emp.ruc,
       COALESCE(NULLIF(emp.nombre_comercial, ''), emp.nombre) AS empresa,
       t.error,
       CASE t.error WHEN 1 THEN 'Cuenta manual no está en el asiento'
                    ELSE 'Saldo de cartera sin asiento sumado a cuenta manual' END AS descripcion_error,
       t.tipo,
       t.numero,
       t.fecha_emision,
       pc.codigo || ' - ' || pc.nombre AS cuenta_manual,
       ROUND(t.monto, 2)               AS monto
  FROM (SELECT * FROM error1 UNION ALL SELECT * FROM error2) t
  LEFT JOIN empresas emp ON emp.id = t.id_empresa
  LEFT JOIN plan_cuentas pc ON pc.id = t.id_cuenta_contable
 WHERE true
   -- AND emp.ruc = '1791400135001'
 ORDER BY emp.ruc, t.error, t.tipo, t.fecha_emision DESC, t.numero;
