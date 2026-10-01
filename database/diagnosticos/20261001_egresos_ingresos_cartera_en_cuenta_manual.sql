-- =============================================================================
-- Diagnóstico (SOLO LECTURA): ingresos / egresos cuyo asiento cargó el saldo
-- de documentos de cartera SIN asiento propio a la cuenta de una línea de
-- "Otros conceptos" (2.º error corregido el 2026-10-01).
--
-- Causa: en AsientoBuilderService::contrapartidaPorCuenta(), la parte de
-- compras/liquidaciones (o facturas/recibos de venta) que no se pudo cancelar
-- contra el asiento propio del documento —porque ese documento no tiene
-- asiento— se sumaba entera a la línea manual MÁS GRANDE como si fuera un
-- "ajuste de centavos". Resultado: un gasto (o ingreso) manual inflado y la
-- Cuenta por Pagar/Cobrar sin cancelar por ese saldo.
--   Caso real: egreso 002-101-202609011 (RUC 1791400135001), 687,58 de 17
--   compras sin asiento.
--
-- Qué lista: para cada documento con líneas manuales Y documentos de cartera
-- sin asiento propio, las cuentas de las líneas manuales que en el asiento
-- vigente tienen MÁS valor que la suma de esas líneas.
--   excedente            = valor en el asiento - suma de las líneas manuales
--   cartera_sin_asiento  = lo que pagan/cobran los documentos sin asiento propio
--   coincide = true      -> el excedente es (todo o parte de) ese saldo: es el error.
--   coincide = false     -> revisar a mano (otra línea del asiento usa la misma cuenta).
--
-- Cómo se corrige cada uno: (1) generar, si corresponde, el asiento de los
-- documentos de cartera que no lo tienen; (2) abrir el ingreso/egreso y
-- guardarlo sin cambios (con el arreglo ya desplegado).
--
-- Uso: pegar en pgAdmin y ejecutar. Cambiar el id_empresa en las DOS marcas
-- "<== cambiar" (o quitar ese filtro para revisar todas las empresas).
-- =============================================================================

WITH
-- ── EGRESOS ──────────────────────────────────────────────────────────────────
eg_manual AS (
    SELECT d.id_egreso, d.id_cuenta_contable,
           SUM(d.monto_pagado) AS monto_lineas,
           COUNT(*)            AS lineas
      FROM egresos_detalle d
     WHERE d.eliminado = false
       AND d.tipo_documento = 'MANUAL'
       AND d.id_cuenta_contable IS NOT NULL
       AND d.monto_pagado > 0
     GROUP BY d.id_egreso, d.id_cuenta_contable
),
eg_cartera AS (
    SELECT d.id_egreso,
           SUM(d.monto_pagado) AS cartera_sin_asiento,
           COUNT(DISTINCT d.tipo_documento || ':' || d.id_referencia_documento) AS docs_sin_asiento
      FROM egresos_detalle d
      JOIN egresos_cabecera e ON e.id = d.id_egreso
     WHERE d.eliminado = false
       AND d.tipo_documento IN ('COMPRA', 'LIQUIDACION')
       AND d.monto_pagado > 0
       AND NOT EXISTS (
             SELECT 1
               FROM asientos_contables_cabecera a
              WHERE a.modulo_origen = CASE d.tipo_documento
                                          WHEN 'COMPRA' THEN 'compra'
                                          ELSE 'liquidacion_compra' END
                AND a.id_referencia_origen = d.id_referencia_documento
                AND a.id_empresa = e.id_empresa
                AND a.eliminado = false
                AND a.estado <> 'anulado'
           )
     GROUP BY d.id_egreso
),
eg_asiento AS (
    SELECT c.id_referencia_origen AS id_egreso, c.id AS id_asiento,
           ad.id_cuenta_contable, SUM(ad.debe) AS en_asiento
      FROM asientos_contables_cabecera c
      JOIN asientos_contables_detalle ad ON ad.id_asiento = c.id AND ad.eliminado = false
     WHERE c.modulo_origen = 'egreso'
       AND c.eliminado = false
       AND c.estado <> 'anulado'
       AND ad.debe > 0
     GROUP BY c.id_referencia_origen, c.id, ad.id_cuenta_contable
),
egresos_afectados AS (
    SELECT 'EGRESO'::text AS tipo,
           e.id_empresa,
           e.id AS id_documento,
           COALESCE(e.numero_egreso,
                    e.establecimiento || '-' || e.punto_emision || '-' || e.secuencial) AS numero,
           e.fecha_emision,
           pc.codigo || ' - ' || pc.nombre AS cuenta_manual,
           m.lineas,
           m.monto_lineas,
           a.en_asiento,
           ROUND(a.en_asiento - m.monto_lineas, 2) AS excedente,
           c.docs_sin_asiento,
           c.cartera_sin_asiento,
           (a.en_asiento - m.monto_lineas) <= c.cartera_sin_asiento + 0.05 AS coincide,
           a.id_asiento
      FROM eg_manual m
      JOIN eg_cartera c ON c.id_egreso = m.id_egreso
      JOIN eg_asiento a ON a.id_egreso = m.id_egreso
                       AND a.id_cuenta_contable = m.id_cuenta_contable
      JOIN egresos_cabecera e ON e.id = m.id_egreso
      LEFT JOIN plan_cuentas pc ON pc.id = m.id_cuenta_contable
     WHERE e.eliminado = false
       AND e.id_empresa = 106            -- <== cambiar
       AND a.en_asiento > m.monto_lineas + 0.05
),

-- ── INGRESOS (espejo: líneas OTRO, cartera FACTURA/RECIBO, lado Haber) ─────
-- ingresos_detalle no tiene columna "eliminado".
in_manual AS (
    SELECT d.id_ingreso, d.id_cuenta_contable,
           SUM(d.monto_cobrado) AS monto_lineas,
           COUNT(*)             AS lineas
      FROM ingresos_detalle d
     WHERE d.tipo_documento = 'OTRO'
       AND d.id_cuenta_contable IS NOT NULL
       AND d.monto_cobrado > 0
     GROUP BY d.id_ingreso, d.id_cuenta_contable
),
in_cartera AS (
    SELECT d.id_ingreso,
           SUM(d.monto_cobrado) AS cartera_sin_asiento,
           COUNT(DISTINCT d.tipo_documento || ':' || d.id_referencia_documento) AS docs_sin_asiento
      FROM ingresos_detalle d
      JOIN ingresos_cabecera i ON i.id = d.id_ingreso
     WHERE d.tipo_documento IN ('FACTURA', 'RECIBO')
       AND d.monto_cobrado > 0
       AND NOT EXISTS (
             SELECT 1
               FROM asientos_contables_cabecera a
              WHERE a.modulo_origen = CASE d.tipo_documento
                                          WHEN 'FACTURA' THEN 'factura_venta'
                                          ELSE 'recibo_venta' END
                AND a.id_referencia_origen = d.id_referencia_documento
                AND a.id_empresa = i.id_empresa
                AND a.eliminado = false
                AND a.estado <> 'anulado'
           )
     GROUP BY d.id_ingreso
),
in_asiento AS (
    SELECT c.id_referencia_origen AS id_ingreso, c.id AS id_asiento,
           ad.id_cuenta_contable, SUM(ad.haber) AS en_asiento
      FROM asientos_contables_cabecera c
      JOIN asientos_contables_detalle ad ON ad.id_asiento = c.id AND ad.eliminado = false
     WHERE c.modulo_origen = 'ingreso'
       AND c.eliminado = false
       AND c.estado <> 'anulado'
       AND ad.haber > 0
     GROUP BY c.id_referencia_origen, c.id, ad.id_cuenta_contable
),
ingresos_afectados AS (
    SELECT 'INGRESO'::text AS tipo,
           i.id_empresa,
           i.id AS id_documento,
           COALESCE(i.numero_ingreso,
                    i.establecimiento || '-' || i.punto_emision || '-' || i.secuencial) AS numero,
           i.fecha_emision,
           pc.codigo || ' - ' || pc.nombre AS cuenta_manual,
           m.lineas,
           m.monto_lineas,
           a.en_asiento,
           ROUND(a.en_asiento - m.monto_lineas, 2) AS excedente,
           c.docs_sin_asiento,
           c.cartera_sin_asiento,
           (a.en_asiento - m.monto_lineas) <= c.cartera_sin_asiento + 0.05 AS coincide,
           a.id_asiento
      FROM in_manual m
      JOIN in_cartera c ON c.id_ingreso = m.id_ingreso
      JOIN in_asiento a ON a.id_ingreso = m.id_ingreso
                       AND a.id_cuenta_contable = m.id_cuenta_contable
      JOIN ingresos_cabecera i ON i.id = m.id_ingreso
      LEFT JOIN plan_cuentas pc ON pc.id = m.id_cuenta_contable
     WHERE i.eliminado = false
       AND i.id_empresa = 106            -- <== cambiar
       AND a.en_asiento > m.monto_lineas + 0.05
)
SELECT * FROM egresos_afectados
UNION ALL
SELECT * FROM ingresos_afectados
ORDER BY tipo, fecha_emision DESC, id_documento DESC;
