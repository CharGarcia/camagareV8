-- =====================================================================================
-- Diagnóstico: ¿por qué estos EGRESOS no generan su asiento contable?
-- =====================================================================================
-- Solo lectura (no toca datos). Lista los egresos que el aviso de asientos pendientes
-- revisa (vigentes, sin asiento, no anulados, no migrados) y, por cada uno, revisa lo mismo
-- que revisa el sistema al armar el asiento. La columna «diagnostico» dice la causa probable.
--
-- Uso en pgAdmin: cambie el 0 de la línea marcada por el id de la empresa (0 = todas) y F5.
-- =====================================================================================

WITH parametros AS (
    SELECT 0 AS id_empresa   -- ← id de la empresa (0 = todas)
),
pendientes AS (
    SELECT e.*
      FROM egresos_cabecera e, parametros prm
     WHERE (prm.id_empresa = 0 OR e.id_empresa = prm.id_empresa)
       AND e.eliminado = false
       AND e.id_asiento_contable IS NULL
       AND UPPER(TRIM(COALESCE(e.estado, ''))) <> 'ANULADO'
       AND NOT EXISTS (SELECT 1 FROM migracion_mysql_map mm
                        WHERE mm.entidad = 'egresos' AND mm.id_destino = e.id AND mm.vinculado IS NOT TRUE)
),
pagos AS (
    SELECT p.id_egreso,
           COUNT(*) AS pagos,
           COALESCE(SUM(p.monto) FILTER (WHERE COALESCE(p.estado_cheque, 'vigente') <> 'anulado' AND p.monto > 0), 0) AS monto_vigente,
           STRING_AGG(DISTINCT f.nombre, ', ')
               FILTER (WHERE COALESCE(p.estado_cheque, 'vigente') <> 'anulado' AND p.monto > 0
                         AND COALESCE(ap.id_cuenta, f.id_cuenta_contable) IS NULL) AS formas_sin_cuenta
      FROM egresos_pagos p
      JOIN pendientes e ON e.id = p.id_egreso
      JOIN empresa_formas_pago f ON f.id = p.id_forma_pago
      LEFT JOIN asientos_programados ap ON ap.id_referencia = f.id
                                       AND ap.tipo_referencia = 'forma_pago'
                                       AND ap.id_empresa = e.id_empresa
                                       AND ap.eliminado = false
     WHERE p.eliminado = false
     GROUP BY p.id_egreso
),
detalle AS (
SELECT e.id_empresa,
       emp.nombre                              AS empresa,
       e.id                                    AS id_egreso,
       e.numero_egreso,
       e.fecha_emision,
       o.nombre                                AS concepto,
       o.comportamiento,
       COALESCE(pg.pagos, 0)                   AS pagos,
       COALESCE(pg.monto_vigente, 0)           AS monto_vigente,
       pg.formas_sin_cuenta,
       COALESCE(apo.id_cuenta, o.id_cuenta_contable) AS cuenta_concepto,
       EXISTS (SELECT 1 FROM periodos_contables pc
                WHERE pc.id_empresa = e.id_empresa AND pc.eliminado = false AND pc.status = 0
                  AND e.fecha_emision::date BETWEEN pc.fecha_inicial AND pc.fecha_final) AS periodo_cerrado,
       CASE
           WHEN COALESCE(pg.pagos, 0) = 0
               THEN 'Sin formas de pago registradas: no hay nada que contabilizar'
           WHEN COALESCE(pg.monto_vigente, 0) = 0
               THEN 'Pagos anulados o en cero: no hay nada que contabilizar'
           WHEN EXISTS (SELECT 1 FROM periodos_contables pc
                         WHERE pc.id_empresa = e.id_empresa AND pc.eliminado = false AND pc.status = 0
                           AND e.fecha_emision::date BETWEEN pc.fecha_inicial AND pc.fecha_final)
               THEN 'Fecha en un período contable cerrado'
           WHEN pg.formas_sin_cuenta IS NOT NULL
               THEN 'Forma de pago sin cuenta contable: ' || pg.formas_sin_cuenta
           WHEN o.id IS NULL
               THEN 'El egreso no tiene concepto (o el concepto ya no existe)'
           WHEN UPPER(COALESCE(o.comportamiento, '')) IN ('COMPRA', 'LIQUIDACION')
               THEN 'Paga compras: revise la Cuenta por Pagar (Adquisiciones de Compras) y que esas compras tengan asiento'
           WHEN UPPER(COALESCE(o.comportamiento, '')) = 'ROL'
               THEN 'Paga roles: revise Sueldos por Pagar / Anticipos y Descuentos (Nómina)'
           WHEN COALESCE(apo.id_cuenta, o.id_cuenta_contable) IS NULL
               THEN 'El concepto «' || o.nombre || '» no tiene cuenta contable (Ingresos y Egresos)'
           ELSE 'Configuración aparentemente completa: revisar las cuentas por línea del egreso'
       END AS diagnostico
  FROM pendientes e
  LEFT JOIN empresas emp ON emp.id = e.id_empresa
  LEFT JOIN pagos pg     ON pg.id_egreso = e.id
  LEFT JOIN empresa_opciones_ingreso_egreso o ON o.id = e.id_egreso_concepto
  LEFT JOIN asientos_programados apo ON apo.id_referencia = o.id
                                    AND apo.tipo_referencia = 'opcion_egreso'
                                    AND apo.id_empresa = e.id_empresa
                                    AND apo.eliminado = false
 )
-- Resumen: una fila por empresa y causa. Para ver egreso por egreso, cambie esta última
-- consulta por:  SELECT * FROM detalle ORDER BY id_empresa, diagnostico, fecha_emision;
SELECT id_empresa,
       empresa,
       diagnostico,
       COUNT(*)                                             AS egresos,
       SUM(monto_vigente)                                   AS monto,
       MIN(fecha_emision)                                   AS desde,
       MAX(fecha_emision)                                   AS hasta,
       (ARRAY_AGG(numero_egreso ORDER BY fecha_emision))[1:5] AS ejemplos
  FROM detalle
 GROUP BY id_empresa, empresa, diagnostico
 ORDER BY id_empresa, egresos DESC;
