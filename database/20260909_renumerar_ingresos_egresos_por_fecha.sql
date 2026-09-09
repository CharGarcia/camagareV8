-- =====================================================================================
-- Renumerar ingresos y egresos ya emitidos, al formato de "numeración por fecha".
--
-- PARA QUÉ
--   Un punto de emisión que se pasa a numeración por fecha de emisión (Empresa →
--   Secuenciales) deja los documentos ANTERIORES con la numeración corrida (000000017).
--   Este script los renumera al formato del periodo que les corresponde —202609001 en
--   mensual, 202600017 en anual— respetando el orden cronológico.
--
-- QUÉ TOCA
--   - `secuencial` y `numero_egreso` / `numero_ingreso` de los documentos elegidos.
--   - El texto del asiento contable de cada uno: el `concepto` de la cabecera y el
--     `documento_referencia` de sus líneas guardan el número como texto. El vínculo
--     asiento↔documento es por id, así que no se rompe en ningún caso; solo se corrige
--     el texto para que no siga mostrando un número que ya no existe.
--
-- QUÉ NO TOCA
--   - Documentos eliminados (eliminado = true): no ocupan número.
--   - Otros establecimientos, otro ambiente o fechas fuera del rango.
--   - El `numero_documento` de los detalles: ese es el de la factura o compra que se
--     cobró o pagó, no el de este documento.
--
-- CÓMO SE USA
--   1. Ajuste los parámetros. Son los mismos cuatro valores en el PASO 1 y en el PASO 2:
--      cámbielos en ambos sitios.
--   2. Ejecute el PASO 1, que NO cambia nada: lista cada documento con su número actual
--      y el que recibiría. Confirme que la lista es exactamente la que espera.
--   3. Si cuadra, ejecute el PASO 2. Va en una sola transacción: o entra todo o nada.
--
--   Ojo: el número nuevo sale también en los comprobantes que se vuelvan a imprimir. Si
--   alguno de estos documentos ya se entregó impreso, el papel deja de coincidir.
-- =====================================================================================


-- =====================================================================================
-- PASO 1 · COMPROBACIÓN — solo lectura, no cambia nada
-- =====================================================================================
WITH param AS (
    SELECT '1791400135001'::text AS ruc,                 -- RUC de la empresa
           '002'::text           AS cod_estab,           -- código del establecimiento
           DATE '2026-09-01'     AS desde,               -- primer día a renumerar
           DATE '2026-09-30'     AS hasta                -- último día (inclusive)
),
emp AS (
    SELECT e.id AS id_empresa, CAST(e.tipo_ambiente AS VARCHAR(1)) AS ambiente
      FROM empresas e CROSS JOIN param p
     WHERE REGEXP_REPLACE(e.ruc, '[^0-9]', '', 'g') = p.ruc
       AND e.eliminado = false
),
pts AS (
    SELECT pe.id AS id_punto
      FROM empresa_punto_emision pe
      JOIN empresa_establecimiento es ON es.id = pe.id_establecimiento
      JOIN emp ON emp.id_empresa = es.id_empresa
      CROSS JOIN param p
     WHERE LPAD(REGEXP_REPLACE(es.codigo, '[^0-9]', '', 'g'), 3, '0') = p.cod_estab
       AND es.eliminado = false
       AND pe.eliminado = false
),
-- Todos los documentos vivos de esos puntos (sin filtro de fecha): sirven para saber qué
-- correlativos ya están ocupados en cada periodo.
todos AS (
    SELECT 'Egresos'::text AS tipo, ec.id, ec.id_punto_emision, ec.fecha_emision,
           TRIM(ec.secuencial) AS secuencial, ec.establecimiento, ec.punto_emision
      FROM egresos_cabecera ec
      JOIN emp ON emp.id_empresa = ec.id_empresa
      JOIN pts ON pts.id_punto = ec.id_punto_emision
     WHERE ec.eliminado = false AND ec.tipo_ambiente = emp.ambiente
    UNION ALL
    SELECT 'Ingresos', ic.id, ic.id_punto_emision, ic.fecha_emision,
           TRIM(ic.secuencial), ic.establecimiento, ic.punto_emision
      FROM ingresos_cabecera ic
      JOIN emp ON emp.id_empresa = ic.id_empresa
      JOIN pts ON pts.id_punto = ic.id_punto_emision
     WHERE ic.eliminado = false AND ic.tipo_ambiente = emp.ambiente
),
-- Configuración de numeración de cada punto + tipo.
cfg AS (
    SELECT s.id_punto_emision, s.tipo_documento,
           s.modo_numeracion, s.periodo_reinicio,
           GREATEST(1, COALESCE(s.secuencial_inicial, 1)) AS inicial
      FROM empresa_secuencial s
      JOIN pts ON pts.id_punto = s.id_punto_emision
     WHERE s.eliminado = false
       AND s.tipo_documento IN ('Ingresos', 'Egresos')
),
-- Los que entran en el rango, ya con el prefijo del periodo que les toca.
base AS (
    SELECT t.*, c.modo_numeracion, c.inicial,
           CASE WHEN c.periodo_reinicio = 'mensual'
                THEN to_char(t.fecha_emision, 'YYYYMM')
                ELSE to_char(t.fecha_emision, 'YYYY')
           END AS prefijo
      FROM todos t
      JOIN cfg c ON c.id_punto_emision = t.id_punto_emision AND c.tipo_documento = t.tipo
      CROSS JOIN param p
     WHERE t.fecha_emision BETWEEN p.desde AND p.hasta
),
-- Correlativo más alto ya ocupado en ese periodo por documentos que NO se renumeran.
-- En mensual renumerando el mes entero da 0; en anual renumerando un solo mes, evita
-- pisar la numeración del resto del año.
ocupados AS (
    SELECT b.id_punto_emision, b.tipo, b.prefijo,
           COALESCE(MAX(CAST(SUBSTRING(t.secuencial FROM length(b.prefijo) + 1) AS BIGINT)), 0) AS max_ocupado
      FROM (SELECT DISTINCT id_punto_emision, tipo, prefijo FROM base) b
      LEFT JOIN todos t
             ON t.id_punto_emision = b.id_punto_emision
            AND t.tipo             = b.tipo
            AND t.secuencial ~ '^[0-9]+$'
            AND t.secuencial LIKE b.prefijo || '%'
            AND length(t.secuencial) > length(b.prefijo)
            AND NOT EXISTS (SELECT 1 FROM base bb WHERE bb.tipo = t.tipo AND bb.id = t.id)
     GROUP BY 1, 2, 3
),
plan AS (
    SELECT b.tipo, b.id, b.fecha_emision, b.establecimiento, b.punto_emision,
           b.secuencial AS secuencial_actual, b.modo_numeracion, b.prefijo,
           GREATEST(1, 9 - length(b.prefijo)) AS ancho,
           GREATEST(b.inicial, o.max_ocupado + 1)
             + ROW_NUMBER() OVER (PARTITION BY b.id_punto_emision, b.tipo, b.prefijo
                                      ORDER BY b.fecha_emision, b.id) - 1 AS correlativo
      FROM base b
      JOIN ocupados o
        ON o.id_punto_emision = b.id_punto_emision AND o.tipo = b.tipo AND o.prefijo = b.prefijo
)
SELECT tipo,
       fecha_emision AS fecha,
       establecimiento || '-' || punto_emision || '-' || secuencial_actual AS numero_actual,
       establecimiento || '-' || punto_emision || '-' ||
           (prefijo || LPAD(correlativo::text, ancho, '0')) AS numero_nuevo,
       modo_numeracion AS modo_configurado,
       CASE WHEN modo_numeracion <> 'por_fecha'
            THEN '*** ESTE TIPO NO ESTA EN MODO POR FECHA: el PASO 2 se detendra ***'
            WHEN secuencial_actual = prefijo || LPAD(correlativo::text, ancho, '0')
            THEN 'ya estaba correcto'
            ELSE 'se renumera'
       END AS observacion
  FROM plan
 ORDER BY tipo, fecha_emision, id;


-- =====================================================================================
-- PASO 2 · RENUMERACIÓN — ejecutar solo después de revisar el PASO 1
-- =====================================================================================
BEGIN;

DO $$
DECLARE
    -- ── PARÁMETROS (los mismos del PASO 1) ──────────────────────────────────────────
    v_ruc       TEXT := '1791400135001';
    v_cod_estab TEXT := '002';
    v_desde     DATE := DATE '2026-09-01';
    v_hasta     DATE := DATE '2026-09-30';
    -- ────────────────────────────────────────────────────────────────────────────────
    v_id_empresa INT;
    v_ambiente   VARCHAR(1);
    v_malmodo    TEXT;
    v_n_egresos  INT := 0;
    v_n_ingresos INT := 0;
    r            RECORD;
BEGIN
    -- Sin las columnas del modo, este script no tiene nada que hacer.
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'public' AND table_name = 'empresa_secuencial'
                      AND column_name = 'modo_numeracion') THEN
        RAISE EXCEPTION 'Falta ejecutar antes 20260909_secuencial_modo_periodo.sql: empresa_secuencial todavia no tiene modo_numeracion.';
    END IF;

    SELECT e.id, CAST(e.tipo_ambiente AS VARCHAR(1)) INTO v_id_empresa, v_ambiente
      FROM empresas e
     WHERE REGEXP_REPLACE(e.ruc, '[^0-9]', '', 'g') = v_ruc AND e.eliminado = false;

    IF v_id_empresa IS NULL THEN
        RAISE EXCEPTION 'No existe ninguna empresa activa con RUC %.', v_ruc;
    END IF;
    RAISE NOTICE 'Empresa % (id %), ambiente %.', v_ruc, v_id_empresa, v_ambiente;

    -- Puntos de emisión del establecimiento indicado.
    CREATE TEMP TABLE _pts ON COMMIT DROP AS
    SELECT pe.id AS id_punto
      FROM empresa_punto_emision pe
      JOIN empresa_establecimiento es ON es.id = pe.id_establecimiento
     WHERE es.id_empresa = v_id_empresa
       AND LPAD(REGEXP_REPLACE(es.codigo, '[^0-9]', '', 'g'), 3, '0') = v_cod_estab
       AND es.eliminado = false AND pe.eliminado = false;

    IF NOT EXISTS (SELECT 1 FROM _pts) THEN
        RAISE EXCEPTION 'El establecimiento % de la empresa % no tiene puntos de emision activos.', v_cod_estab, v_ruc;
    END IF;

    -- Documentos vivos de esos puntos (sin filtro de fecha).
    CREATE TEMP TABLE _todos ON COMMIT DROP AS
    SELECT 'Egresos'::text AS tipo, ec.id, ec.id_punto_emision, ec.fecha_emision,
           TRIM(ec.secuencial) AS secuencial, ec.establecimiento, ec.punto_emision
      FROM egresos_cabecera ec JOIN _pts ON _pts.id_punto = ec.id_punto_emision
     WHERE ec.id_empresa = v_id_empresa AND ec.eliminado = false AND ec.tipo_ambiente = v_ambiente
    UNION ALL
    SELECT 'Ingresos', ic.id, ic.id_punto_emision, ic.fecha_emision,
           TRIM(ic.secuencial), ic.establecimiento, ic.punto_emision
      FROM ingresos_cabecera ic JOIN _pts ON _pts.id_punto = ic.id_punto_emision
     WHERE ic.id_empresa = v_id_empresa AND ic.eliminado = false AND ic.tipo_ambiente = v_ambiente;

    -- Plan de renumeración.
    CREATE TEMP TABLE _plan ON COMMIT DROP AS
    WITH cfg AS (
        SELECT s.id_punto_emision, s.tipo_documento, s.modo_numeracion, s.periodo_reinicio,
               GREATEST(1, COALESCE(s.secuencial_inicial, 1)) AS inicial
          FROM empresa_secuencial s JOIN _pts ON _pts.id_punto = s.id_punto_emision
         WHERE s.eliminado = false AND s.tipo_documento IN ('Ingresos', 'Egresos')
    ),
    base AS (
        SELECT t.*, c.modo_numeracion, c.inicial,
               CASE WHEN c.periodo_reinicio = 'mensual'
                    THEN to_char(t.fecha_emision, 'YYYYMM')
                    ELSE to_char(t.fecha_emision, 'YYYY')
               END AS prefijo
          FROM _todos t
          JOIN cfg c ON c.id_punto_emision = t.id_punto_emision AND c.tipo_documento = t.tipo
         WHERE t.fecha_emision BETWEEN v_desde AND v_hasta
    ),
    ocupados AS (
        SELECT b.id_punto_emision, b.tipo, b.prefijo,
               COALESCE(MAX(CAST(SUBSTRING(t.secuencial FROM length(b.prefijo) + 1) AS BIGINT)), 0) AS max_ocupado
          FROM (SELECT DISTINCT id_punto_emision, tipo, prefijo FROM base) b
          LEFT JOIN _todos t
                 ON t.id_punto_emision = b.id_punto_emision AND t.tipo = b.tipo
                AND t.secuencial ~ '^[0-9]+$'
                AND t.secuencial LIKE b.prefijo || '%'
                AND length(t.secuencial) > length(b.prefijo)
                AND NOT EXISTS (SELECT 1 FROM base bb WHERE bb.tipo = t.tipo AND bb.id = t.id)
         GROUP BY 1, 2, 3
    )
    SELECT b.tipo, b.id, b.establecimiento, b.punto_emision, b.fecha_emision,
           b.secuencial AS secuencial_actual, b.modo_numeracion,
           b.prefijo || LPAD((
               GREATEST(b.inicial, o.max_ocupado + 1)
               + ROW_NUMBER() OVER (PARTITION BY b.id_punto_emision, b.tipo, b.prefijo
                                        ORDER BY b.fecha_emision, b.id) - 1
           )::text, GREATEST(1, 9 - length(b.prefijo)), '0') AS secuencial_nuevo
      FROM base b
      JOIN ocupados o ON o.id_punto_emision = b.id_punto_emision AND o.tipo = b.tipo AND o.prefijo = b.prefijo;

    -- Ningún tipo puede estar en modo consecutivo: renumerar ahí no tendría sentido.
    SELECT string_agg(DISTINCT tipo, ', ') INTO v_malmodo
      FROM _plan WHERE modo_numeracion <> 'por_fecha';
    IF v_malmodo IS NOT NULL THEN
        RAISE EXCEPTION 'Estos tipos no estan en modo "por fecha" (%): configurelos en Empresa - Secuenciales antes de renumerar.', v_malmodo;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM _plan) THEN
        RAISE NOTICE 'No hay documentos que renumerar en ese rango. Nada que hacer.';
        RETURN;
    END IF;

    -- Fase 1: a un valor temporal, para que dos documentos no choquen entre si al
    -- intercambiar numeros (el indice unico de secuencial se evalua fila a fila).
    UPDATE egresos_cabecera ec
       SET secuencial = 'TMP' || LPAD(ec.id::text, 6, '0')
      FROM _plan p WHERE p.tipo = 'Egresos' AND p.id = ec.id;
    UPDATE ingresos_cabecera ic
       SET secuencial = 'TMP' || LPAD(ic.id::text, 6, '0')
      FROM _plan p WHERE p.tipo = 'Ingresos' AND p.id = ic.id;

    -- Fase 2: numero definitivo, y el numero visible que se arma con el.
    UPDATE egresos_cabecera ec
       SET secuencial    = p.secuencial_nuevo,
           numero_egreso = p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial_nuevo,
           updated_at    = NOW()
      FROM _plan p WHERE p.tipo = 'Egresos' AND p.id = ec.id;
    GET DIAGNOSTICS v_n_egresos = ROW_COUNT;

    UPDATE ingresos_cabecera ic
       SET secuencial     = p.secuencial_nuevo,
           numero_ingreso = p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial_nuevo,
           updated_at     = NOW()
      FROM _plan p WHERE p.tipo = 'Ingresos' AND p.id = ic.id;
    GET DIAGNOSTICS v_n_ingresos = ROW_COUNT;

    -- Texto del asiento contable: cabecera y lineas. El vinculo es por id; esto solo
    -- corrige el texto que ve el usuario ("Egreso 002-001-000000017").
    UPDATE asientos_contables_cabecera ac
       SET concepto = CASE WHEN p.tipo = 'Egresos' THEN 'Egreso ' ELSE 'Ingreso ' END
                      || p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial_nuevo
      FROM _plan p
     WHERE ac.id_referencia_origen = p.id
       AND ac.modulo_origen = CASE WHEN p.tipo = 'Egresos' THEN 'egreso' ELSE 'ingreso' END
       AND ac.id_empresa = v_id_empresa;

    UPDATE asientos_contables_detalle ad
       SET documento_referencia = CASE WHEN p.tipo = 'Egresos' THEN 'Egreso ' ELSE 'Ingreso ' END
                                  || p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial_nuevo
      FROM _plan p, asientos_contables_cabecera ac
     WHERE ac.id_referencia_origen = p.id
       AND ac.modulo_origen = CASE WHEN p.tipo = 'Egresos' THEN 'egreso' ELSE 'ingreso' END
       AND ac.id_empresa = v_id_empresa
       AND ad.id_asiento = ac.id;

    RAISE NOTICE 'Renumerados: % egresos y % ingresos.', v_n_egresos, v_n_ingresos;
    FOR r IN SELECT tipo, secuencial_actual, secuencial_nuevo, fecha_emision
               FROM _plan ORDER BY tipo, fecha_emision LOOP
        RAISE NOTICE '  % : % -> %  (%)', r.tipo, r.secuencial_actual, r.secuencial_nuevo, r.fecha_emision;
    END LOOP;
END $$;

COMMIT;
