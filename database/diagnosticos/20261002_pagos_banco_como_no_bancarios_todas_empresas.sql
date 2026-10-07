-- ============================================================================
-- DIAGNÓSTICO (SOLO LECTURA) — Cobros/pagos que pasaron por el BANCO pero quedaron
-- registrados con una forma de pago NO bancaria (Efectivo, Caja chica, etc.).
-- Todas las empresas.
-- ============================================================================
-- Origen: caso GOLIFE (empresa 24, ver 20261002_golife_pagos_banco_migrados_como_efectivo.sql).
-- El migrador anterior a agosto-2026 grababa los pagos bancarios del sistema viejo con la
-- forma Efectivo; el ASIENTO migrado sí quedó en la cuenta del banco. Control Bancario no
-- los ve y "Comprobar con Contabilidad" los marca "Cobrado/Pagado con Efectivo".
--
-- Criterio (por documento ingreso/egreso, del ambiente de la empresa, no eliminado ni anulado):
--   * todas sus líneas de pago vigentes usan UNA misma forma NO bancaria (id_banco NULL),
--     cuya cuenta contable NO es la de un banco (eso es otro problema: ver Q3);
--   * su asiento contabilizado mueve las cuentas de bancos de la empresa por EXACTAMENTE
--     el total del pago (ingreso: entra al banco; egreso: sale del banco).
-- Clase:
--   BANCO_COMO_NO_BANCARIO   el asiento no toca la cuenta de la forma → el dinero pasó por
--                            el banco: la línea de pago debe ir a la forma bancaria.
--   TRASPASO_CON_LA_FORMA    el asiento mueve banco y, en sentido contrario, la cuenta de la
--                            forma (ingreso: Debe banco / Haber caja = depósito de caja al
--                            banco; egreso: Debe caja / Haber banco = retiro a caja). En
--                            GOLIFE los depósitos se pasaron a la forma bancaria (DEPOSITO);
--                            un retiro hay que mirarlo a mano.
--
-- TOCA DATOS  No. Solo SELECT.
-- USO         pgAdmin → Query Tool. Correr cada consulta por separado (seleccionar + F5).
--   Q1  Resumen por empresa (todas). Puede tardar: correr fuera de horas pico.
--   Q2  Detalle de UNA empresa: cambiar el id en la primera línea.
--   Q3  Configuración: formas no bancarias con la cuenta contable de un banco.
-- ============================================================================


-- Q1 ─ RESUMEN POR EMPRESA -----------------------------------------------------
WITH prm AS (SELECT NULL::int AS e),          -- NULL = todas las empresas
bank_formas AS (
    SELECT fp.id_empresa, fp.id AS id_forma, fp.nombre, fp.id_cuenta_contable
      FROM empresa_formas_pago fp
     WHERE fp.id_banco IS NOT NULL AND fp.eliminado = FALSE AND fp.id_cuenta_contable IS NOT NULL
),
bank_ctas AS (SELECT DISTINCT id_empresa, id_cuenta_contable FROM bank_formas),
lineas AS (
    SELECT ic.id_empresa, 'INGRESOS'::text AS tipo, ic.id AS id_doc, ic.numero_ingreso AS documento,
           ic.fecha_emision, ip.monto, fp.id AS id_forma, fp.nombre AS forma, fp.id_cuenta_contable AS cta_forma
      FROM ingresos_pagos ip
      JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso
      JOIN empresa_formas_pago fp ON fp.id = ip.id_forma_cobro
      JOIN empresas e ON e.id = ic.id_empresa, prm
     WHERE (prm.e IS NULL OR ic.id_empresa = prm.e)
       AND ic.eliminado = FALSE AND COALESCE(ic.estado, 'registrado') <> 'anulado'
       AND ic.tipo_ambiente = CAST(e.tipo_ambiente AS VARCHAR(1))
       AND fp.id_banco IS NULL
       AND EXISTS (SELECT 1 FROM bank_ctas b WHERE b.id_empresa = ic.id_empresa)
    UNION ALL
    SELECT ec.id_empresa, 'EGRESOS', ec.id, ec.numero_egreso,
           ec.fecha_emision, ep.monto, fp.id, fp.nombre, fp.id_cuenta_contable
      FROM egresos_pagos ep
      JOIN egresos_cabecera ec ON ec.id = ep.id_egreso
      JOIN empresa_formas_pago fp ON fp.id = ep.id_forma_pago
      JOIN empresas e ON e.id = ec.id_empresa, prm
     WHERE (prm.e IS NULL OR ec.id_empresa = prm.e)
       AND ec.eliminado = FALSE AND COALESCE(ec.estado, 'registrado') <> 'anulado'
       AND COALESCE(ep.eliminado, FALSE) = FALSE
       AND COALESCE(ep.estado_cheque, 'vigente') <> 'anulado'
       AND ec.tipo_ambiente = CAST(e.tipo_ambiente AS VARCHAR(1))
       AND fp.id_banco IS NULL
       AND EXISTS (SELECT 1 FROM bank_ctas b WHERE b.id_empresa = ec.id_empresa)
),
docs AS (   -- una sola forma no bancaria en todo el documento, y su cuenta no es de banco
    SELECT l.id_empresa, l.tipo, l.id_doc, MIN(l.documento) AS documento, MIN(l.fecha_emision) AS fecha,
           SUM(l.monto) AS total, COUNT(*) AS n_lineas,
           MIN(l.id_forma) AS id_forma, MIN(l.forma) AS forma, MIN(l.cta_forma) AS cta_forma
      FROM lineas l
     GROUP BY 1, 2, 3
    HAVING COUNT(DISTINCT l.id_forma) = 1
),
docs_ok AS (
    SELECT d.* FROM docs d
     WHERE NOT EXISTS (SELECT 1 FROM bank_ctas b WHERE b.id_empresa = d.id_empresa AND b.id_cuenta_contable = d.cta_forma)
       AND NOT EXISTS (SELECT 1 FROM ingresos_pagos o WHERE d.tipo = 'INGRESOS' AND o.id_ingreso = d.id_doc AND o.id_forma_cobro <> d.id_forma)
       AND NOT EXISTS (SELECT 1 FROM egresos_pagos o WHERE d.tipo = 'EGRESOS' AND o.id_egreso = d.id_doc AND o.id_forma_pago <> d.id_forma
                         AND COALESCE(o.eliminado, FALSE) = FALSE)
),
mov AS (
    SELECT d.id_empresa, d.tipo, d.id_doc,
           STRING_AGG(DISTINCT ac.numero_comprobante, ', ') AS asientos,
           SUM(CASE WHEN bc.id_cuenta_contable IS NOT NULL THEN ad.debe - ad.haber ELSE 0 END) AS mov_banco,
           SUM(CASE WHEN ad.id_cuenta_contable = d.cta_forma THEN ad.debe - ad.haber ELSE 0 END) AS mov_cta_forma,
           STRING_AGG(DISTINCT CASE WHEN bc.id_cuenta_contable IS NOT NULL THEN ad.id_cuenta_contable::text END, ',') AS ctas_banco
      FROM docs_ok d
      JOIN asientos_contables_cabecera ac
        ON ac.id_empresa = d.id_empresa AND UPPER(ac.tipo_comprobante) = d.tipo
       AND ac.id_referencia_origen = d.id_doc AND ac.estado = 'contabilizado' AND ac.eliminado = FALSE
      JOIN asientos_contables_detalle ad ON ad.id_asiento = ac.id AND ad.eliminado = FALSE
      LEFT JOIN bank_ctas bc ON bc.id_empresa = d.id_empresa AND bc.id_cuenta_contable = ad.id_cuenta_contable
     GROUP BY 1, 2, 3
),
casos AS (
    SELECT d.*, m.asientos, m.ctas_banco,
           CASE WHEN ABS(m.mov_cta_forma) < 0.005 THEN 'BANCO_COMO_NO_BANCARIO'
                WHEN ABS(m.mov_cta_forma * CASE d.tipo WHEN 'INGRESOS' THEN 1 ELSE -1 END + d.total) < 0.01
                     THEN 'TRASPASO_CON_LA_FORMA'
                ELSE 'REVISAR' END AS clase
      FROM docs_ok d
      JOIN mov m ON m.id_empresa = d.id_empresa AND m.tipo = d.tipo AND m.id_doc = d.id_doc
     WHERE ABS(m.mov_banco * CASE d.tipo WHEN 'INGRESOS' THEN 1 ELSE -1 END - d.total) < 0.01
)
SELECT c.id_empresa, e.ruc, e.nombre AS empresa, c.clase,
       COUNT(*) FILTER (WHERE c.tipo = 'INGRESOS') AS ingresos,
       COUNT(*) FILTER (WHERE c.tipo = 'EGRESOS')  AS egresos,
       SUM(c.total) AS monto_total,
       MIN(c.fecha) AS desde, MAX(c.fecha) AS hasta,
       STRING_AGG(DISTINCT c.forma, ', ') AS formas_registradas,
       (SELECT STRING_AGG(DISTINCT bf.id_forma || ' ' || bf.nombre, ', ')
          FROM bank_formas bf WHERE bf.id_empresa = c.id_empresa) AS formas_bancarias_de_la_empresa
  FROM casos c
  JOIN empresas e ON e.id = c.id_empresa
 GROUP BY c.id_empresa, e.ruc, e.nombre, c.clase
 ORDER BY c.id_empresa, c.clase;


-- Q2 ─ DETALLE DE UNA EMPRESA (cambiar el id de la primera línea) ----------------
WITH prm AS (SELECT 24::int AS e),
bank_formas AS (
    SELECT fp.id_empresa, fp.id AS id_forma, fp.nombre, fp.id_cuenta_contable
      FROM empresa_formas_pago fp, prm
     WHERE fp.id_empresa = prm.e
       AND fp.id_banco IS NOT NULL AND fp.eliminado = FALSE AND fp.id_cuenta_contable IS NOT NULL
),
bank_ctas AS (SELECT DISTINCT id_empresa, id_cuenta_contable FROM bank_formas),
lineas AS (
    SELECT ic.id_empresa, 'INGRESOS'::text AS tipo, ic.id AS id_doc, ic.numero_ingreso AS documento,
           ic.fecha_emision, ip.monto, fp.id AS id_forma, fp.nombre AS forma, fp.id_cuenta_contable AS cta_forma
      FROM ingresos_pagos ip
      JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso
      JOIN empresa_formas_pago fp ON fp.id = ip.id_forma_cobro
      JOIN empresas e ON e.id = ic.id_empresa, prm
     WHERE ic.id_empresa = prm.e
       AND ic.eliminado = FALSE AND COALESCE(ic.estado, 'registrado') <> 'anulado'
       AND ic.tipo_ambiente = CAST(e.tipo_ambiente AS VARCHAR(1))
       AND fp.id_banco IS NULL
    UNION ALL
    SELECT ec.id_empresa, 'EGRESOS', ec.id, ec.numero_egreso,
           ec.fecha_emision, ep.monto, fp.id, fp.nombre, fp.id_cuenta_contable
      FROM egresos_pagos ep
      JOIN egresos_cabecera ec ON ec.id = ep.id_egreso
      JOIN empresa_formas_pago fp ON fp.id = ep.id_forma_pago
      JOIN empresas e ON e.id = ec.id_empresa, prm
     WHERE ec.id_empresa = prm.e
       AND ec.eliminado = FALSE AND COALESCE(ec.estado, 'registrado') <> 'anulado'
       AND COALESCE(ep.eliminado, FALSE) = FALSE
       AND COALESCE(ep.estado_cheque, 'vigente') <> 'anulado'
       AND ec.tipo_ambiente = CAST(e.tipo_ambiente AS VARCHAR(1))
       AND fp.id_banco IS NULL
),
docs AS (
    SELECT l.id_empresa, l.tipo, l.id_doc, MIN(l.documento) AS documento, MIN(l.fecha_emision) AS fecha,
           SUM(l.monto) AS total, COUNT(*) AS n_lineas,
           MIN(l.id_forma) AS id_forma, MIN(l.forma) AS forma, MIN(l.cta_forma) AS cta_forma
      FROM lineas l
     GROUP BY 1, 2, 3
    HAVING COUNT(DISTINCT l.id_forma) = 1
),
docs_ok AS (
    SELECT d.* FROM docs d
     WHERE NOT EXISTS (SELECT 1 FROM bank_ctas b WHERE b.id_empresa = d.id_empresa AND b.id_cuenta_contable = d.cta_forma)
       AND NOT EXISTS (SELECT 1 FROM ingresos_pagos o WHERE d.tipo = 'INGRESOS' AND o.id_ingreso = d.id_doc AND o.id_forma_cobro <> d.id_forma)
       AND NOT EXISTS (SELECT 1 FROM egresos_pagos o WHERE d.tipo = 'EGRESOS' AND o.id_egreso = d.id_doc AND o.id_forma_pago <> d.id_forma
                         AND COALESCE(o.eliminado, FALSE) = FALSE)
),
mov AS (
    SELECT d.id_empresa, d.tipo, d.id_doc,
           STRING_AGG(DISTINCT ac.numero_comprobante, ', ') AS asientos,
           MIN(ac.concepto) AS glosa,
           SUM(CASE WHEN bc.id_cuenta_contable IS NOT NULL THEN ad.debe - ad.haber ELSE 0 END) AS mov_banco,
           SUM(CASE WHEN ad.id_cuenta_contable = d.cta_forma THEN ad.debe - ad.haber ELSE 0 END) AS mov_cta_forma,
           STRING_AGG(DISTINCT CASE WHEN bc.id_cuenta_contable IS NOT NULL THEN ad.id_cuenta_contable::text END, ',') AS ctas_banco
      FROM docs_ok d
      JOIN asientos_contables_cabecera ac
        ON ac.id_empresa = d.id_empresa AND UPPER(ac.tipo_comprobante) = d.tipo
       AND ac.id_referencia_origen = d.id_doc AND ac.estado = 'contabilizado' AND ac.eliminado = FALSE
      JOIN asientos_contables_detalle ad ON ad.id_asiento = ac.id AND ad.eliminado = FALSE
      LEFT JOIN bank_ctas bc ON bc.id_empresa = d.id_empresa AND bc.id_cuenta_contable = ad.id_cuenta_contable
     GROUP BY 1, 2, 3
)
SELECT d.tipo, d.documento, d.fecha, d.total, d.n_lineas, d.forma AS forma_registrada,
       m.asientos, m.glosa, m.ctas_banco,
       (SELECT STRING_AGG(bf.id_forma || ' ' || bf.nombre, ', ')
          FROM bank_formas bf WHERE bf.id_cuenta_contable::text = m.ctas_banco) AS forma_bancaria_sugerida,
       CASE WHEN ABS(m.mov_cta_forma) < 0.005 THEN 'BANCO_COMO_NO_BANCARIO'
            WHEN ABS(m.mov_cta_forma * CASE d.tipo WHEN 'INGRESOS' THEN 1 ELSE -1 END + d.total) < 0.01
                 THEN 'TRASPASO_CON_LA_FORMA'
            ELSE 'REVISAR' END AS clase
  FROM docs_ok d
  JOIN mov m ON m.id_empresa = d.id_empresa AND m.tipo = d.tipo AND m.id_doc = d.id_doc
 WHERE ABS(m.mov_banco * CASE d.tipo WHEN 'INGRESOS' THEN 1 ELSE -1 END - d.total) < 0.01
 ORDER BY d.fecha, d.tipo, d.documento;


-- Q3 ─ CONFIGURACIÓN: formas NO bancarias con la cuenta contable de un banco ----
-- No es el mismo error (aquí el asiento va al banco porque la forma lo pide), pero
-- mezcla en contabilidad la cuenta del banco con algo que no pasó por él. Los
-- ANTICIPO pueden ser intencionales; Efectivo/Caja casi nunca.
SELECT e.id AS id_empresa, e.ruc, e.nombre AS empresa,
       fp.id AS id_forma, fp.nombre AS forma, fp.tipo, fp.activo,
       pc.codigo, pc.nombre AS cuenta_contable,
       STRING_AGG(DISTINCT bf.id || ' ' || bf.nombre, ', ') AS formas_bancarias_misma_cuenta
  FROM empresa_formas_pago fp
  JOIN empresa_formas_pago bf
    ON bf.id_empresa = fp.id_empresa AND bf.id_cuenta_contable = fp.id_cuenta_contable
   AND bf.id_banco IS NOT NULL AND bf.eliminado = FALSE
  JOIN empresas e ON e.id = fp.id_empresa AND e.eliminado = FALSE
  LEFT JOIN plan_cuentas pc ON pc.id = fp.id_cuenta_contable
 WHERE fp.id_banco IS NULL AND fp.eliminado = FALSE
 GROUP BY e.id, e.ruc, e.nombre, fp.id, fp.nombre, fp.tipo, fp.activo, pc.codigo, pc.nombre
 ORDER BY e.id, fp.tipo, fp.id;
