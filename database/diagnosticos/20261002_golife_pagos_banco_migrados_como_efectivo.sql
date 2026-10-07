-- ============================================================================
-- CORRECCIÓN — GOLIFE (empresa 24, RUC 0993384802001): cobros/pagos por BANCO
-- migrados con la forma "Efectivo"
-- ============================================================================
-- Causa: GOLIFE se migró el 15-07-2026, antes del fix del migrador "pagos
-- bancarios como Efectivo" (agosto 2026). En el sistema viejo un pago por banco
-- guarda id_cuenta > 0 y codigo_forma_pago = '0'; el migrador de entonces solo
-- miraba el código y caía a Efectivo. El ASIENTO migrado sí quedó en el banco
-- (cuenta 2271 BANCO BOLIVARIANO) — su glosa lo dice ("cobrado con: Depósito
-- BOLIVARIANO…") —, pero la línea de pago del ingreso/egreso quedó con la forma
-- 51 Efectivo (cuenta 2268 CAJA GENERAL). Control Bancario no los ve y la
-- comprobación con contabilidad los marca como "otra cuenta".
--
-- Qué cambia: id_forma_cobro / id_forma_pago 51 (Efectivo) → 214 (Bolivariano)
-- y tipo_operacion_bancaria, SOLO en líneas que cumplen TODO esto:
--   * el documento no está eliminado (y en egresos, la línea no está eliminada
--     ni el cheque anulado);
--   * todas sus líneas de pago son Efectivo (51) — un documento mixto no se toca;
--   * su asiento contabilizado mueve la cuenta del banco (2271) por el MISMO
--     monto que esas líneas, y no toca la caja (2268).
-- tipo_operacion_bancaria: se conserva si ya tenía; si no, CHEQUE si la glosa
-- dice cheque o hay número de cheque; DEPOSITO si la glosa dice depósito; si no,
-- TRANSFERENCIA (mismo fallback del migrador).
--
-- No cambia ids de línea: no se pierden clasificaciones de Control Bancario
-- (que se anclan a origen_id). Tampoco toca asientos.
-- Auditoría: una fila en log_sistema por línea corregida (antes / después).
--
-- USO   pgAdmin → Query Tool.
--   1. Correr Q1 (solo lectura) y revisar la lista y el total.
--   2. Si está bien, correr el bloque Q2 completo (DO $$ … $$). Es atómico: o
--      corrige todo, o nada. Al final muestra cuántas líneas cambió.
--   3. Correr Q1 otra vez: debe salir vacía. Y Q3 para ver el resultado.
-- v_user = 2 (usuario de producción que ejecuta, solo para auditoría).
-- ============================================================================


-- Q1 ─ VISTA PREVIA (solo lectura): líneas que se corregirían ----------------
WITH asi AS (
    SELECT UPPER(ac.tipo_comprobante) AS tipo,
           ac.id_referencia_origen    AS id_doc,
           STRING_AGG(DISTINCT ac.numero_comprobante, ', ') AS asiento,
           MIN(ac.concepto)           AS glosa,
           SUM(CASE WHEN ad.id_cuenta_contable = 2271 THEN ad.debe - ad.haber ELSE 0 END) AS mov_banco,
           BOOL_OR(ad.id_cuenta_contable = 2268) AS toca_caja
      FROM asientos_contables_cabecera ac
      JOIN asientos_contables_detalle ad ON ad.id_asiento = ac.id AND ad.eliminado = FALSE
     WHERE ac.id_empresa = 24 AND ac.estado = 'contabilizado' AND ac.eliminado = FALSE
       AND UPPER(ac.tipo_comprobante) IN ('INGRESOS', 'EGRESOS')
     GROUP BY 1, 2
),
lineas AS (
    SELECT 'ingreso'::text AS origen, ip.id AS id_pago, ic.id AS id_doc, ic.numero_ingreso AS documento,
           ic.fecha_emision, ip.monto, ip.tipo_operacion_bancaria AS tipo_ant, ip.numero_cheque,
           SUM(ip.monto) OVER (PARTITION BY ic.id) AS total_doc,
           (SELECT COUNT(*) FROM ingresos_pagos o WHERE o.id_ingreso = ic.id AND o.id_forma_cobro <> 51) AS otras_formas,
           a.asiento, a.glosa, a.mov_banco, a.toca_caja
      FROM ingresos_pagos ip
      JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso
      JOIN asi a ON a.tipo = 'INGRESOS' AND a.id_doc = ic.id
     WHERE ic.id_empresa = 24 AND ic.eliminado = FALSE AND ip.id_forma_cobro = 51
    UNION ALL
    SELECT 'egreso', ep.id, ec.id, ec.numero_egreso,
           ec.fecha_emision, ep.monto, ep.tipo_operacion_bancaria, ep.numero_cheque,
           SUM(ep.monto) OVER (PARTITION BY ec.id),
           (SELECT COUNT(*) FROM egresos_pagos o WHERE o.id_egreso = ec.id AND o.id_forma_pago <> 51
                AND COALESCE(o.eliminado, FALSE) = FALSE),
           a.asiento, a.glosa, -a.mov_banco, a.toca_caja
      FROM egresos_pagos ep
      JOIN egresos_cabecera ec ON ec.id = ep.id_egreso
      JOIN asi a ON a.tipo = 'EGRESOS' AND a.id_doc = ec.id
     WHERE ec.id_empresa = 24 AND ec.eliminado = FALSE AND ep.id_forma_pago = 51
       AND COALESCE(ep.eliminado, FALSE) = FALSE
       AND COALESCE(ep.estado_cheque, 'vigente') <> 'anulado'
)
SELECT origen, id_pago, documento, fecha_emision, monto, asiento, glosa,
       tipo_ant AS tipo_actual,
       CASE WHEN NULLIF(tipo_ant, '') IS NOT NULL THEN UPPER(tipo_ant)
            WHEN glosa ILIKE '%cheque%' OR NULLIF(numero_cheque, '') IS NOT NULL THEN 'CHEQUE'
            WHEN glosa ILIKE '%dep_sito%' THEN 'DEPOSITO'
            ELSE 'TRANSFERENCIA' END AS tipo_nuevo
  FROM lineas
 WHERE otras_formas = 0
   AND NOT toca_caja
   AND ABS(mov_banco - total_doc) < 0.01
 ORDER BY fecha_emision, origen, documento;

-- Totales de la vista previa (misma lógica, resumida):
--   descomentar y correr si se quiere solo el conteo.
-- SELECT origen, COUNT(*), SUM(monto) FROM (<Q1 sin ORDER BY>) x GROUP BY origen;


-- Q2 ─ CORRECCIÓN (escribe; atómico) ------------------------------------------
DO $$
DECLARE
    v_emp         INT := 24;
    v_user        INT := 2;
    v_forma_ef    INT := 51;    -- Efectivo
    v_forma_banco INT := 214;   -- Bolivariano (forma bancaria activa)
    v_cta_banco   INT := 2271;  -- 1.1.1.02.001 BANCO BOLIVARIANO
    v_cta_caja    INT := 2268;  -- 1.1.1.01.001 CAJA GENERAL
    v_ing INT;
    v_egr INT;
BEGIN
    -- Defensa: la forma destino debe ser la bancaria activa de esa cuenta.
    IF NOT EXISTS (SELECT 1 FROM empresa_formas_pago
                    WHERE id = v_forma_banco AND id_empresa = v_emp AND eliminado = FALSE
                      AND id_banco IS NOT NULL AND id_cuenta_contable = v_cta_banco) THEN
        RAISE EXCEPTION 'La forma % no es la forma bancaria activa de la cuenta % en la empresa %.',
            v_forma_banco, v_cta_banco, v_emp;
    END IF;

    CREATE TEMP TABLE tmp_fix_golife ON COMMIT DROP AS
    WITH asi AS (
        SELECT UPPER(ac.tipo_comprobante) AS tipo, ac.id_referencia_origen AS id_doc,
               MIN(ac.concepto) AS glosa,
               SUM(CASE WHEN ad.id_cuenta_contable = v_cta_banco THEN ad.debe - ad.haber ELSE 0 END) AS mov_banco,
               BOOL_OR(ad.id_cuenta_contable = v_cta_caja) AS toca_caja
          FROM asientos_contables_cabecera ac
          JOIN asientos_contables_detalle ad ON ad.id_asiento = ac.id AND ad.eliminado = FALSE
         WHERE ac.id_empresa = v_emp AND ac.estado = 'contabilizado' AND ac.eliminado = FALSE
           AND UPPER(ac.tipo_comprobante) IN ('INGRESOS', 'EGRESOS')
         GROUP BY 1, 2
    ),
    lineas AS (
        SELECT 'ingreso'::text AS origen, ip.id AS id_pago, ic.id AS id_doc, ic.numero_ingreso AS documento,
               ip.monto, ip.tipo_operacion_bancaria AS tipo_ant, ip.numero_cheque,
               SUM(ip.monto) OVER (PARTITION BY ic.id) AS total_doc,
               (SELECT COUNT(*) FROM ingresos_pagos o WHERE o.id_ingreso = ic.id AND o.id_forma_cobro <> v_forma_ef) AS otras_formas,
               a.glosa, a.mov_banco, a.toca_caja
          FROM ingresos_pagos ip
          JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso
          JOIN asi a ON a.tipo = 'INGRESOS' AND a.id_doc = ic.id
         WHERE ic.id_empresa = v_emp AND ic.eliminado = FALSE AND ip.id_forma_cobro = v_forma_ef
        UNION ALL
        SELECT 'egreso', ep.id, ec.id, ec.numero_egreso,
               ep.monto, ep.tipo_operacion_bancaria, ep.numero_cheque,
               SUM(ep.monto) OVER (PARTITION BY ec.id),
               (SELECT COUNT(*) FROM egresos_pagos o WHERE o.id_egreso = ec.id AND o.id_forma_pago <> v_forma_ef
                    AND COALESCE(o.eliminado, FALSE) = FALSE),
               a.glosa, -a.mov_banco, a.toca_caja
          FROM egresos_pagos ep
          JOIN egresos_cabecera ec ON ec.id = ep.id_egreso
          JOIN asi a ON a.tipo = 'EGRESOS' AND a.id_doc = ec.id
         WHERE ec.id_empresa = v_emp AND ec.eliminado = FALSE AND ep.id_forma_pago = v_forma_ef
           AND COALESCE(ep.eliminado, FALSE) = FALSE
           AND COALESCE(ep.estado_cheque, 'vigente') <> 'anulado'
    )
    SELECT origen, id_pago, id_doc, documento, monto, tipo_ant,
           CASE WHEN NULLIF(tipo_ant, '') IS NOT NULL THEN UPPER(tipo_ant)
                WHEN glosa ILIKE '%cheque%' OR NULLIF(numero_cheque, '') IS NOT NULL THEN 'CHEQUE'
                WHEN glosa ILIKE '%dep_sito%' THEN 'DEPOSITO'
                ELSE 'TRANSFERENCIA' END AS tipo_nuevo
      FROM lineas
     WHERE otras_formas = 0
       AND NOT toca_caja
       AND ABS(mov_banco - total_doc) < 0.01;

    -- Auditoría (antes de cambiar, para guardar el estado anterior).
    INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                             datos_anteriores, datos_nuevos, ip_usuario, user_agent)
    SELECT v_user, v_emp, 'actualizar',
           CASE t.origen WHEN 'ingreso' THEN 'ingresos_pagos' ELSE 'egresos_pagos' END,
           t.id_pago,
           jsonb_build_object('forma', v_forma_ef, 'tipo_operacion_bancaria', t.tipo_ant, 'documento', t.documento),
           jsonb_build_object('forma', v_forma_banco, 'tipo_operacion_bancaria', t.tipo_nuevo, 'documento', t.documento,
                              'motivo', 'Pago bancario migrado como Efectivo (fix 20261002_golife_pagos_banco_migrados_como_efectivo.sql)'),
           'sql-manual', 'pgAdmin'
      FROM tmp_fix_golife t;

    UPDATE ingresos_pagos ip
       SET id_forma_cobro = v_forma_banco, tipo_operacion_bancaria = t.tipo_nuevo
      FROM tmp_fix_golife t
     WHERE t.origen = 'ingreso' AND ip.id = t.id_pago AND ip.id_forma_cobro = v_forma_ef;
    GET DIAGNOSTICS v_ing = ROW_COUNT;

    UPDATE egresos_pagos ep
       SET id_forma_pago = v_forma_banco, tipo_operacion_bancaria = t.tipo_nuevo
      FROM tmp_fix_golife t
     WHERE t.origen = 'egreso' AND ep.id = t.id_pago AND ep.id_forma_pago = v_forma_ef;
    GET DIAGNOSTICS v_egr = ROW_COUNT;

    RAISE NOTICE 'Corregidas: % líneas de ingresos y % líneas de egresos.', v_ing, v_egr;
END $$;


-- Q3 ─ Después de corregir: líneas de la forma bancaria por mes ---------------
SELECT to_char(x.fecha, 'YYYY-MM') AS mes, x.origen, COUNT(*) AS lineas, SUM(x.monto) AS monto
  FROM (
    SELECT ic.fecha_emision AS fecha, 'ingreso' AS origen, ip.monto
      FROM ingresos_pagos ip JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso
     WHERE ic.id_empresa = 24 AND ic.eliminado = FALSE AND ip.id_forma_cobro = 214
    UNION ALL
    SELECT ec.fecha_emision, 'egreso', ep.monto
      FROM egresos_pagos ep JOIN egresos_cabecera ec ON ec.id = ep.id_egreso
     WHERE ec.id_empresa = 24 AND ec.eliminado = FALSE AND ep.id_forma_pago = 214
       AND COALESCE(ep.eliminado, FALSE) = FALSE
  ) x
 GROUP BY 1, 2
 ORDER BY 1, 2;


-- ============================================================================
-- Q4 ─ CASOS ESPECIALES: depósitos de caja al banco (3 ingresos de junio 2026)
-- ============================================================================
-- Q2 los saltó a propósito porque su asiento también toca la caja. Revisados a
-- mano (02-10-2026): los tres son Debe 2271 BANCO / Haber 2268 CAJA por el mismo
-- monto = dinero de caja depositado en el Bolivariano. El asiento está bien; la
-- línea de pago debe ser la forma bancaria 214 con tipo DEPOSITO.
--   ING182293  ingreso 001-001-000000004  01-06-2026   140,75
--   ING182410  ingreso 001-001-000000005  30-06-2026  4.980,02
--   ING182414  ingreso 001-001-000000006  30-06-2026    86,00
-- Igual que Q2: atómico, solo líneas que sigan en Efectivo y cuyo asiento sea
-- exactamente banco/caja por el monto del pago; deja log_sistema por línea.
DO $$
DECLARE
    v_emp         INT := 24;
    v_user        INT := 2;
    v_forma_ef    INT := 51;
    v_forma_banco INT := 214;
    v_cta_banco   INT := 2271;
    v_cta_caja    INT := 2268;
    v_n INT;
BEGIN
    CREATE TEMP TABLE tmp_fix_golife_dep ON COMMIT DROP AS
    SELECT ip.id AS id_pago, ic.numero_ingreso AS documento, ip.monto, ip.tipo_operacion_bancaria AS tipo_ant
      FROM ingresos_pagos ip
      JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso
      JOIN asientos_contables_cabecera ac
        ON ac.id_empresa = ic.id_empresa AND UPPER(ac.tipo_comprobante) = 'INGRESOS'
       AND ac.id_referencia_origen = ic.id AND ac.estado = 'contabilizado' AND ac.eliminado = FALSE
     WHERE ic.id_empresa = v_emp AND ic.eliminado = FALSE
       AND ac.numero_comprobante IN ('ING182293', 'ING182410', 'ING182414')
       AND ip.id_forma_cobro = v_forma_ef
       AND NOT EXISTS (SELECT 1 FROM ingresos_pagos o WHERE o.id_ingreso = ic.id AND o.id_forma_cobro <> v_forma_ef)
       -- El asiento es exactamente: Debe banco = Haber caja = monto del pago, sin otras cuentas.
       AND (SELECT SUM(ad.debe)  FROM asientos_contables_detalle ad WHERE ad.id_asiento = ac.id AND ad.eliminado = FALSE AND ad.id_cuenta_contable = v_cta_banco) = ip.monto
       AND (SELECT SUM(ad.haber) FROM asientos_contables_detalle ad WHERE ad.id_asiento = ac.id AND ad.eliminado = FALSE AND ad.id_cuenta_contable = v_cta_caja)  = ip.monto
       AND NOT EXISTS (SELECT 1 FROM asientos_contables_detalle ad WHERE ad.id_asiento = ac.id AND ad.eliminado = FALSE
                         AND ad.id_cuenta_contable NOT IN (v_cta_banco, v_cta_caja));

    INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                             datos_anteriores, datos_nuevos, ip_usuario, user_agent)
    SELECT v_user, v_emp, 'actualizar', 'ingresos_pagos', t.id_pago,
           jsonb_build_object('forma', v_forma_ef, 'tipo_operacion_bancaria', t.tipo_ant, 'documento', t.documento),
           jsonb_build_object('forma', v_forma_banco, 'tipo_operacion_bancaria', 'DEPOSITO', 'documento', t.documento,
                              'motivo', 'Depósito de caja al banco migrado como Efectivo (fix 20261002_golife…, Q4)'),
           'sql-manual', 'pgAdmin'
      FROM tmp_fix_golife_dep t;

    UPDATE ingresos_pagos ip
       SET id_forma_cobro = v_forma_banco, tipo_operacion_bancaria = 'DEPOSITO'
      FROM tmp_fix_golife_dep t
     WHERE ip.id = t.id_pago AND ip.id_forma_cobro = v_forma_ef;
    GET DIAGNOSTICS v_n = ROW_COUNT;

    IF v_n <> 3 THEN
        RAISE EXCEPTION 'Se esperaban 3 líneas y cumplen las condiciones %. No se cambió nada.', v_n;
    END IF;
    RAISE NOTICE 'Corregidas % líneas (depósitos de caja al banco).', v_n;
END $$;
