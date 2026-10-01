-- =============================================================================
-- Diagnóstico (SOLO LECTURA) del egreso 002-101-202609011
-- Empresa RUC 1791400135001
--
-- Son 6 consultas independientes. En pgAdmin, seleccione UNA (desde su
-- comentario "-- N." hasta el punto y coma) y ejecútela con F5; repita con las
-- siguientes. Todas ubican el egreso por empresa + número, no hace falta
-- conocer ningún id.
-- =============================================================================


-- 1. CABECERA: empresa, egreso, concepto y su comportamiento ------------------
SELECT emp.id                    AS id_empresa,
       e.id                      AS id_egreso,
       e.numero_egreso,
       e.establecimiento || '-' || e.punto_emision || '-' || e.secuencial AS numero,
       e.fecha_emision,
       e.tipo_egreso,
       e.estado,
       e.eliminado,
       o.id                      AS id_concepto,
       o.nombre                  AS concepto,
       o.comportamiento,
       o.estado                  AS estado_concepto,
       e.created_at,
       e.updated_at
  FROM egresos_cabecera e
  JOIN empresas emp ON emp.id = e.id_empresa
  LEFT JOIN empresa_opciones_ingreso_egreso o ON o.id = e.id_egreso_concepto
 WHERE emp.ruc = '1791400135001'
   AND (e.numero_egreso = '002-101-202609011'
        OR e.establecimiento || '-' || e.punto_emision || '-' || e.secuencial = '002-101-202609011');


-- 2. DETALLE: cada línea con la cuenta elegida a mano ------------------------
--    En las líneas MANUAL, "cuenta_elegida" es la que el asiento DEBE usar.
SELECT d.id,
       d.tipo_documento,
       d.numero_documento,
       d.descripcion,
       d.monto_pagado,
       d.id_cuenta_contable,
       pc.codigo || ' - ' || pc.nombre AS cuenta_elegida,
       d.eliminado
  FROM egresos_cabecera e
  JOIN empresas emp ON emp.id = e.id_empresa
  JOIN egresos_detalle d ON d.id_egreso = e.id
  LEFT JOIN plan_cuentas pc ON pc.id = d.id_cuenta_contable
 WHERE emp.ruc = '1791400135001'
   AND (e.numero_egreso = '002-101-202609011'
        OR e.establecimiento || '-' || e.punto_emision || '-' || e.secuencial = '002-101-202609011')
 ORDER BY d.eliminado, d.id;


-- 3. PAGOS: formas de pago y la cuenta con la que se contabilizan ------------
SELECT p.id,
       f.nombre                  AS forma,
       f.activo                  AS forma_activa,
       p.monto,
       p.estado_cheque,
       p.eliminado,
       COALESCE(ap.id_cuenta, f.id_cuenta_contable) AS id_cuenta_forma,
       pc.codigo || ' - ' || pc.nombre AS cuenta_forma
  FROM egresos_cabecera e
  JOIN empresas emp ON emp.id = e.id_empresa
  JOIN egresos_pagos p ON p.id_egreso = e.id
  LEFT JOIN empresa_formas_pago f ON f.id = p.id_forma_pago
  LEFT JOIN asientos_programados ap ON ap.id_referencia = f.id
                                   AND ap.tipo_referencia = 'forma_pago'
                                   AND ap.id_empresa = e.id_empresa
                                   AND ap.eliminado = false
  LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap.id_cuenta, f.id_cuenta_contable)
 WHERE emp.ruc = '1791400135001'
   AND (e.numero_egreso = '002-101-202609011'
        OR e.establecimiento || '-' || e.punto_emision || '-' || e.secuencial = '002-101-202609011')
 ORDER BY p.eliminado, p.id;


-- 4. ASIENTO(S) del egreso, con sus líneas -----------------------------------
--    Si sale más de una cabecera, la vigente es la no eliminada / no anulada.
SELECT c.id                      AS id_asiento,
       c.numero_comprobante,
       c.fecha_asiento,
       c.estado,
       c.eliminado               AS asiento_eliminado,
       c.created_at,
       c.updated_at,
       pc.codigo || ' - ' || pc.nombre AS cuenta,
       ad.debe,
       ad.haber,
       ad.referencia_detalle
  FROM egresos_cabecera e
  JOIN empresas emp ON emp.id = e.id_empresa
  JOIN asientos_contables_cabecera c ON c.modulo_origen = 'egreso'
                                    AND c.id_referencia_origen = e.id
                                    AND c.id_empresa = e.id_empresa
  JOIN asientos_contables_detalle ad ON ad.id_asiento = c.id AND ad.eliminado = false
  LEFT JOIN plan_cuentas pc ON pc.id = ad.id_cuenta_contable
 WHERE emp.ruc = '1791400135001'
   AND (e.numero_egreso = '002-101-202609011'
        OR e.establecimiento || '-' || e.punto_emision || '-' || e.secuencial = '002-101-202609011')
 ORDER BY c.eliminado, c.id DESC, ad.haber, ad.id;


-- 5. VEREDICTO: ¿cada línea MANUAL tiene su cuenta en el Debe del asiento? ---
--    "en_asiento = false" = esa cuenta elegida a mano NO llegó al asiento.
SELECT d.descripcion,
       d.monto_pagado,
       pc.codigo || ' - ' || pc.nombre AS cuenta_elegida,
       EXISTS (
           SELECT 1
             FROM asientos_contables_cabecera c
             JOIN asientos_contables_detalle ad ON ad.id_asiento = c.id
            WHERE c.modulo_origen = 'egreso'
              AND c.id_referencia_origen = e.id
              AND c.id_empresa = e.id_empresa
              AND c.eliminado = false
              AND c.estado <> 'anulado'
              AND ad.eliminado = false
              AND ad.debe > 0
              AND ad.id_cuenta_contable = d.id_cuenta_contable
       ) AS en_asiento
  FROM egresos_cabecera e
  JOIN empresas emp ON emp.id = e.id_empresa
  JOIN egresos_detalle d ON d.id_egreso = e.id
                        AND d.eliminado = false
                        AND d.tipo_documento = 'MANUAL'
  LEFT JOIN plan_cuentas pc ON pc.id = d.id_cuenta_contable
 WHERE emp.ruc = '1791400135001'
   AND (e.numero_egreso = '002-101-202609011'
        OR e.establecimiento || '-' || e.punto_emision || '-' || e.secuencial = '002-101-202609011')
 ORDER BY d.id;


-- 6. COMPRAS que paga el egreso SIN asiento propio ---------------------------
--    Su monto no se puede cancelar contra la Cuenta por Pagar que usó la compra
--    (no hay asiento de dónde leerla) y va a la cuenta del concepto. Revise por
--    qué no se contabilizaron (estado, período cerrado, módulo apagado...).
SELECT d.numero_documento,
       d.monto_pagado,
       cc.id                     AS id_compra,
       cc.fecha_emision,
       cc.estado                 AS estado_compra,
       cc.eliminado              AS compra_eliminada
  FROM egresos_cabecera e
  JOIN empresas emp ON emp.id = e.id_empresa
  JOIN egresos_detalle d ON d.id_egreso = e.id
                        AND d.eliminado = false
                        AND d.tipo_documento = 'COMPRA'
  LEFT JOIN compras_cabecera cc ON cc.id = d.id_referencia_documento
 WHERE emp.ruc = '1791400135001'
   AND (e.numero_egreso = '002-101-202609011'
        OR e.establecimiento || '-' || e.punto_emision || '-' || e.secuencial = '002-101-202609011')
   AND NOT EXISTS (
         SELECT 1
           FROM asientos_contables_cabecera a
          WHERE a.modulo_origen = 'compra'
            AND a.id_referencia_origen = d.id_referencia_documento
            AND a.id_empresa = e.id_empresa
            AND a.eliminado = false
            AND a.estado <> 'anulado'
       )
 ORDER BY cc.fecha_emision, d.numero_documento;
