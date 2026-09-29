-- =====================================================================================
-- Sugerir la cuenta contable de las FORMAS DE COBRO/PAGO que no tienen cuenta
-- =====================================================================================
-- Solo lectura (no toca datos). Para cada forma de pago sin cuenta, busca los asientos que
-- ya existen de ingresos/egresos pagados SOLO con esa forma (sobre todo el histórico
-- migrado del sistema anterior) y mira qué cuenta de banco/caja se usó:
--   - egreso  → la línea del HABER (sale dinero de esa cuenta)
--   - ingreso → la línea del DEBE  (entra dinero a esa cuenta)
-- Prefiere la línea cuyo valor coincide con lo pagado con la forma. La cuenta que más se
-- repite es la sugerida; «confianza» = % de documentos que la respaldan.
--
-- Cómo se encuentra el asiento de un documento (cualquiera de las tres):
--   1. el documento apunta a su asiento (id_asiento_contable);
--   2. asiento migrado cuyo detalle lleva el número del documento (documento_referencia);
--   3. asiento migrado con id_referencia_origen = id del documento (mismo tipo).
--
-- Lectura del resultado:
--   - confianza alta (≥ 90 %) y varios documentos: casi seguro es la cuenta correcta.
--   - confianza baja o segunda opción cercana: la forma se usó con varias cuentas; decidir.
--   - «sin historial»: no hay asientos previos; la cuenta la decide el contador.
--   Un contador debe revisar la sugerencia antes de asignarla.
--
-- Uso en pgAdmin: cambie el 0 de la línea marcada por el id de la empresa (0 = todas) y F5.
-- =====================================================================================

WITH parametros AS (
    SELECT 0 AS id_empresa   -- ← id de la empresa (0 = todas)
),
-- Formas sin cuenta para pagos: ni regla en Configuración Contable → Pagos ni cuenta en la ficha.
formas AS (
    SELECT f.id, f.id_empresa, f.nombre, f.tipo
      FROM empresa_formas_pago f, parametros prm
     WHERE (prm.id_empresa = 0 OR f.id_empresa = prm.id_empresa)
       AND f.eliminado = false
       AND f.id_cuenta_contable IS NULL
       AND NOT EXISTS (SELECT 1 FROM asientos_programados ap
                        WHERE ap.id_referencia = f.id AND ap.tipo_referencia = 'forma_pago'
                          AND ap.id_empresa = f.id_empresa AND ap.eliminado = false
                          AND ap.id_cuenta IS NOT NULL)
),
-- Documentos pagados/cobrados con UNA sola forma (así la línea de banco/caja es inequívoca).
pagos AS (
    SELECT 'EGR' AS flujo, e.id_empresa, e.id AS id_doc, e.numero_egreso AS numero,
           e.id_asiento_contable, p.id_forma_pago AS id_forma, p.monto
      FROM egresos_pagos p
      JOIN egresos_cabecera e ON e.id = p.id_egreso AND e.eliminado = false
     WHERE p.eliminado = false
       AND COALESCE(p.estado_cheque, 'vigente') <> 'anulado'
       AND p.monto > 0
    UNION ALL
    SELECT 'ING', i.id_empresa, i.id, i.numero_ingreso,
           i.id_asiento_contable, p.id_forma_cobro, p.monto
      FROM ingresos_pagos p
      JOIN ingresos_cabecera i ON i.id = p.id_ingreso AND i.eliminado = false
     WHERE p.monto > 0
),
docs AS (
    SELECT pg.flujo, pg.id_empresa, pg.id_doc, pg.numero, pg.id_asiento_contable,
           MIN(pg.id_forma) AS id_forma, ROUND(SUM(pg.monto), 2) AS monto
      FROM pagos pg
     WHERE pg.id_empresa IN (SELECT id_empresa FROM formas)
     GROUP BY pg.flujo, pg.id_empresa, pg.id_doc, pg.numero, pg.id_asiento_contable
    HAVING COUNT(DISTINCT pg.id_forma) = 1
       AND MIN(pg.id_forma) IN (SELECT id FROM formas)
),
-- Asientos migrados de ingresos/egresos con el número de documento de su detalle.
migrados AS (
    SELECT DISTINCT a.id, a.id_empresa, a.id_referencia_origen,
           CASE WHEN UPPER(a.tipo_comprobante) LIKE 'EGRESO%' THEN 'EGR' ELSE 'ING' END AS flujo,
           x.documento_referencia
      FROM asientos_contables_cabecera a
      LEFT JOIN asientos_contables_detalle x ON x.id_asiento = a.id AND x.eliminado = false
     WHERE a.modulo_origen = 'migracion'
       AND a.eliminado = false
       AND COALESCE(a.estado, '') <> 'anulado'
       AND (UPPER(a.tipo_comprobante) LIKE 'EGRESO%' OR UPPER(a.tipo_comprobante) LIKE 'INGRESO%')
       AND a.id_empresa IN (SELECT id_empresa FROM formas)
),
vinculo AS (
    SELECT d.*, a.id AS id_asiento
      FROM docs d
      JOIN asientos_contables_cabecera a ON a.id = d.id_asiento_contable
                                        AND a.eliminado = false AND COALESCE(a.estado, '') <> 'anulado'
    UNION
    SELECT d.*, m.id
      FROM docs d
      JOIN migrados m ON m.id_empresa = d.id_empresa AND m.flujo = d.flujo
                     AND m.documento_referencia = d.numero
    UNION
    SELECT d.*, m.id
      FROM docs d
      JOIN migrados m ON m.id_empresa = d.id_empresa AND m.flujo = d.flujo
                     AND m.id_referencia_origen = d.id_doc
),
-- Por documento, la línea de banco/caja: la del lado que corresponde y, si hay varias, la que
-- coincide con el monto pagado; si ninguna coincide, la mayor.
linea AS (
    SELECT DISTINCT ON (v.flujo, v.id_doc)
           v.id_empresa, v.id_forma, v.flujo, v.id_doc, x.id_cuenta_contable AS id_cuenta
      FROM vinculo v
      JOIN asientos_contables_detalle x ON x.id_asiento = v.id_asiento AND x.eliminado = false
     WHERE (v.flujo = 'EGR' AND x.haber > 0) OR (v.flujo = 'ING' AND x.debe > 0)
     ORDER BY v.flujo, v.id_doc,
              (ABS(CASE WHEN v.flujo = 'EGR' THEN x.haber ELSE x.debe END - v.monto) < 0.015) DESC,
              CASE WHEN v.flujo = 'EGR' THEN x.haber ELSE x.debe END DESC
),
votos AS (
    SELECT l.id_empresa, l.id_forma, l.id_cuenta, COUNT(*) AS docs,
           ROW_NUMBER() OVER (PARTITION BY l.id_forma ORDER BY COUNT(*) DESC, l.id_cuenta) AS puesto,
           SUM(COUNT(*)) OVER (PARTITION BY l.id_forma) AS docs_con_asiento
      FROM linea l
     GROUP BY l.id_empresa, l.id_forma, l.id_cuenta
),
pendientes AS (
    SELECT p.id_forma_pago AS id_forma, COUNT(DISTINCT e.id) AS egresos
      FROM egresos_pagos p
      JOIN egresos_cabecera e ON e.id = p.id_egreso AND e.eliminado = false
                             AND e.id_asiento_contable IS NULL
                             AND UPPER(TRIM(COALESCE(e.estado, ''))) <> 'ANULADO'
     WHERE p.eliminado = false
       AND NOT EXISTS (SELECT 1 FROM migracion_mysql_map mm
                        WHERE mm.entidad = 'egresos' AND mm.id_destino = e.id AND mm.vinculado IS NOT TRUE)
     GROUP BY p.id_forma_pago
)
SELECT f.id_empresa,
       emp.nombre                                        AS empresa,
       f.id                                              AS id_forma,
       f.nombre                                          AS forma,
       f.tipo,
       COALESCE(pe.egresos, 0)                           AS egresos_pendientes,
       CASE WHEN v1.id_cuenta IS NULL THEN 'sin historial'
            ELSE c1.codigo || ' - ' || c1.nombre END     AS cuenta_sugerida,
       v1.id_cuenta                                      AS id_cuenta_sugerida,
       v1.docs                                           AS docs_a_favor,
       v1.docs_con_asiento,
       ROUND(100.0 * v1.docs / NULLIF(v1.docs_con_asiento, 0), 0) AS confianza_pct,
       c2.codigo || ' - ' || c2.nombre || ' (' || v2.docs || ')' AS segunda_opcion,
       CASE WHEN v1.id_cuenta IS NOT NULL AND (c1.id IS NULL OR c1.eliminado)
            THEN 'la cuenta sugerida ya no existe o está eliminada' END AS aviso
  FROM formas f
  LEFT JOIN empresas emp     ON emp.id = f.id_empresa
  LEFT JOIN pendientes pe    ON pe.id_forma = f.id
  LEFT JOIN votos v1         ON v1.id_forma = f.id AND v1.puesto = 1
  LEFT JOIN plan_cuentas c1  ON c1.id = v1.id_cuenta
  LEFT JOIN votos v2         ON v2.id_forma = f.id AND v2.puesto = 2
  LEFT JOIN plan_cuentas c2  ON c2.id = v2.id_cuenta
 WHERE COALESCE(pe.egresos, 0) > 0
    OR v1.id_cuenta IS NOT NULL
 ORDER BY f.id_empresa, egresos_pendientes DESC, f.nombre;
