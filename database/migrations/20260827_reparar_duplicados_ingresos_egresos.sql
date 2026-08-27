-- =====================================================================================
-- Reparación: ingresos y egresos NATIVOS con serie + secuencial repetidos.
--
-- CAUSA (ya corregida en el código)
--   IngresosController::guardarAjax() y EgresosController::guardarAjax() guardaban el
--   secuencial TAL COMO LLEGABA DEL NAVEGADOR. Ese número lo calculaba getSecuencialAjax()
--   al abrir el modal, sin candado y sin transacción: dos usuarios que abrían el modal a la
--   vez recibían el mismo número y ambos lo guardaban. Además, el secuencial se insertaba sin
--   relleno de ceros ('16' en vez de '000000016') mientras numero_egreso sí iba formateado,
--   así que dos documentos con el MISMO número podían verse distintos como texto
--   ('1' vs '000000001') y escapar de cualquier comparación por cadena.
--   Desde ahora el número lo reserva el servidor dentro de la transacción que inserta el
--   documento (crearConSecuencialReservado / registrarConSecuencialReservado).
--
-- QUÉ HACE ESTE SCRIPT
--   1. Normaliza el secuencial a 9 dígitos y recompone el Nº documento (EEE-PPP-SSSSSSSSS).
--   2. Renumera los duplicados: dentro de cada grupo (empresa + punto de emisión + ambiente +
--      número) conserva el número el documento MÁS ANTIGUO y los demás pasan al final de la
--      numeración de ese punto (MAX + 1, MAX + 2, …). El MAX considera también los eliminados,
--      para que un número nuevo nunca coincida con el de un documento anulado.
--   3. Propaga el número nuevo al asiento contable del documento (concepto y
--      documento_referencia), que lo guarda como texto.
--
-- QUÉ **NO** TOCA
--   * Los documentos MIGRADOS (los registrados en migracion_mysql_map). Si un grupo duplicado
--     tiene un migrado y un nativo, el migrado conserva su número y se renumera el nativo. Un
--     grupo formado solo por migrados se deja intacto y se reporta en la sección 6.
--   * El monto, la fecha, el cliente/proveedor, los pagos ni el detalle: solo cambia el número.
--
-- IDEMPOTENTE: al volver a ejecutarlo no encuentra nada que renumerar.
-- IMPORTANTE: renumerar cambia el Nº de documentos ya entregados. Revise el reporte de la
-- sección 5 y avise a contabilidad de los documentos afectados.
-- =====================================================================================

BEGIN;

CREATE TEMP TABLE _renum (
    paso    SMALLINT,   -- 1 = normalización de formato, 2 = renumeración por duplicado
    tabla   TEXT,
    id      INTEGER,
    sec_ant TEXT,
    sec_nue TEXT,
    num_ant TEXT,
    num_nue TEXT,
    motivo  TEXT
) ON COMMIT DROP;

-- Ids NATIVOS (los que no vinieron de la migración). Se calculan una vez y se reutilizan.
CREATE TEMP TABLE _nativos_ing ON COMMIT DROP AS
SELECT c.id FROM ingresos_cabecera c
 WHERE NOT EXISTS (SELECT 1 FROM migracion_mysql_map m
                    WHERE m.id_empresa = c.id_empresa AND m.entidad = 'ingresos' AND m.id_destino = c.id);

CREATE TEMP TABLE _nativos_egr ON COMMIT DROP AS
SELECT c.id FROM egresos_cabecera c
 WHERE NOT EXISTS (SELECT 1 FROM migracion_mysql_map m
                    WHERE m.id_empresa = c.id_empresa AND m.entidad = 'egresos' AND m.id_destino = c.id);


-- ── 0. Diagnóstico previo ────────────────────────────────────────────────────────────
SELECT 'Duplicados encontrados' AS reporte, tabla, id_empresa, id_punto_emision, tipo_ambiente,
       numero, documentos, nativos, migrados FROM (
    SELECT 'ingresos_cabecera' AS tabla, c.id_empresa, c.id_punto_emision, c.tipo_ambiente,
           CAST(NULLIF(REGEXP_REPLACE(COALESCE(c.secuencial, ''), '[^0-9]', '', 'g'), '') AS BIGINT) AS numero,
           string_agg(c.id::text || '=' || COALESCE(c.numero_ingreso, '?'), ', ' ORDER BY c.id) AS documentos,
           COUNT(*) FILTER (WHERE n.id IS NOT NULL)  AS nativos,
           COUNT(*) FILTER (WHERE n.id IS NULL)      AS migrados
      FROM ingresos_cabecera c
      LEFT JOIN _nativos_ing n ON n.id = c.id
     WHERE c.eliminado = false AND c.id_punto_emision IS NOT NULL
       AND COALESCE(c.secuencial, '') <> ''
     GROUP BY 1,2,3,4,5 HAVING COUNT(*) > 1
    UNION ALL
    SELECT 'egresos_cabecera', c.id_empresa, c.id_punto_emision, c.tipo_ambiente,
           CAST(NULLIF(REGEXP_REPLACE(COALESCE(c.secuencial, ''), '[^0-9]', '', 'g'), '') AS BIGINT),
           string_agg(c.id::text || '=' || COALESCE(c.numero_egreso, '?'), ', ' ORDER BY c.id),
           COUNT(*) FILTER (WHERE n.id IS NOT NULL),
           COUNT(*) FILTER (WHERE n.id IS NULL)
      FROM egresos_cabecera c
      LEFT JOIN _nativos_egr n ON n.id = c.id
     WHERE c.eliminado = false AND c.id_punto_emision IS NOT NULL
       AND COALESCE(c.secuencial, '') <> ''
     GROUP BY 1,2,3,4,5 HAVING COUNT(*) > 1
) d ORDER BY tabla, id_empresa, id_punto_emision, numero;


-- ── 1. Normalizar el secuencial a 9 dígitos y recomponer el Nº documento ─────────────
-- Sin esto, '1' y '000000001' son el mismo número pero se ven distintos como texto y ninguna
-- comparación por cadena los detecta como duplicados.
WITH objetivo AS (
    SELECT c.id,
           c.numero_ingreso AS num_ant, c.secuencial AS sec_ant,
           LPAD(REGEXP_REPLACE(c.secuencial, '[^0-9]', '', 'g'), 9, '0') AS sec_nue,
           COALESCE(NULLIF(c.establecimiento, ''), '001') || '-'
        || COALESCE(NULLIF(c.punto_emision,   ''), '001') || '-'
        || LPAD(REGEXP_REPLACE(c.secuencial, '[^0-9]', '', 'g'), 9, '0') AS num_nue
      FROM ingresos_cabecera c
      INNER JOIN _nativos_ing n ON n.id = c.id
     WHERE c.eliminado = false
       AND COALESCE(c.secuencial, '') <> ''
), pendientes AS (
    SELECT o.* FROM objetivo o
      INNER JOIN ingresos_cabecera c ON c.id = o.id
     WHERE c.secuencial <> o.sec_nue OR COALESCE(c.numero_ingreso, '') <> o.num_nue
), upd AS (
    UPDATE ingresos_cabecera c
       SET secuencial = p.sec_nue, numero_ingreso = p.num_nue, updated_at = now()
      FROM pendientes p WHERE c.id = p.id
    RETURNING c.id, p.sec_ant, p.sec_nue, p.num_ant, p.num_nue
)
INSERT INTO _renum SELECT 1, 'ingresos_cabecera', id, sec_ant, sec_nue, num_ant, num_nue, 'formato normalizado' FROM upd;

WITH objetivo AS (
    SELECT c.id,
           c.numero_egreso AS num_ant, c.secuencial AS sec_ant,
           LPAD(REGEXP_REPLACE(c.secuencial, '[^0-9]', '', 'g'), 9, '0') AS sec_nue,
           COALESCE(NULLIF(c.establecimiento, ''), '001') || '-'
        || COALESCE(NULLIF(c.punto_emision,   ''), '001') || '-'
        || LPAD(REGEXP_REPLACE(c.secuencial, '[^0-9]', '', 'g'), 9, '0') AS num_nue
      FROM egresos_cabecera c
      INNER JOIN _nativos_egr n ON n.id = c.id
     WHERE c.eliminado = false
       AND COALESCE(c.secuencial, '') <> ''
), pendientes AS (
    SELECT o.* FROM objetivo o
      INNER JOIN egresos_cabecera c ON c.id = o.id
     WHERE c.secuencial <> o.sec_nue OR COALESCE(c.numero_egreso, '') <> o.num_nue
), upd AS (
    UPDATE egresos_cabecera c
       SET secuencial = p.sec_nue, numero_egreso = p.num_nue, updated_at = now()
      FROM pendientes p WHERE c.id = p.id
    RETURNING c.id, p.sec_ant, p.sec_nue, p.num_ant, p.num_nue
)
INSERT INTO _renum SELECT 1, 'egresos_cabecera', id, sec_ant, sec_nue, num_ant, num_nue, 'formato normalizado' FROM upd;


-- ── 2. Renumerar los duplicados nativos: INGRESOS ────────────────────────────────────
-- Orden dentro del grupo: primero el MIGRADO (si lo hay, conserva su número), luego el más
-- antiguo por fecha de creación. El resto se renumera al final de la numeración del punto.
WITH grupos AS (
    SELECT c.id, c.id_empresa, c.id_punto_emision, c.tipo_ambiente, c.secuencial,
           (n.id IS NOT NULL) AS es_nativo,
           ROW_NUMBER() OVER (
               PARTITION BY c.id_empresa, c.id_punto_emision, c.tipo_ambiente, c.secuencial
               ORDER BY (n.id IS NOT NULL), c.created_at, c.id
           ) AS rn
      FROM ingresos_cabecera c
      LEFT JOIN _nativos_ing n ON n.id = c.id
     WHERE c.eliminado = false AND c.id_punto_emision IS NOT NULL
       AND COALESCE(c.secuencial, '') <> ''
), a_renumerar AS (
    SELECT g.id, g.id_empresa, g.id_punto_emision, g.tipo_ambiente,
           ROW_NUMBER() OVER (PARTITION BY g.id_empresa, g.id_punto_emision, g.tipo_ambiente
                              ORDER BY g.secuencial, g.id) AS orden
      FROM grupos g WHERE g.rn > 1 AND g.es_nativo
), maximos AS (
    -- Incluye los eliminados a propósito: un número nuevo no debe coincidir con el de un anulado.
    SELECT id_empresa, id_punto_emision, tipo_ambiente,
           MAX(CAST(NULLIF(REGEXP_REPLACE(COALESCE(secuencial, ''), '[^0-9]', '', 'g'), '') AS BIGINT)) AS max_sec
      FROM ingresos_cabecera
     WHERE id_punto_emision IS NOT NULL AND COALESCE(secuencial, '') <> ''
     GROUP BY 1,2,3
), objetivo AS (
    SELECT c.id, c.numero_ingreso AS num_ant, c.secuencial AS sec_ant,
           LPAD((mx.max_sec + r.orden)::text, 9, '0') AS sec_nue,
           COALESCE(NULLIF(c.establecimiento, ''), '001') || '-'
        || COALESCE(NULLIF(c.punto_emision,   ''), '001') || '-'
        || LPAD((mx.max_sec + r.orden)::text, 9, '0') AS num_nue
      FROM a_renumerar r
      INNER JOIN ingresos_cabecera c ON c.id = r.id
      INNER JOIN maximos mx ON mx.id_empresa = r.id_empresa
                           AND mx.id_punto_emision = r.id_punto_emision
                           AND mx.tipo_ambiente IS NOT DISTINCT FROM r.tipo_ambiente
), upd AS (
    UPDATE ingresos_cabecera c
       SET secuencial = o.sec_nue, numero_ingreso = o.num_nue, updated_at = now()
      FROM objetivo o WHERE c.id = o.id
    RETURNING c.id, o.sec_ant, o.sec_nue, o.num_ant, o.num_nue
)
INSERT INTO _renum SELECT 2, 'ingresos_cabecera', id, sec_ant, sec_nue, num_ant, num_nue, 'número repetido' FROM upd;


-- ── 3. Renumerar los duplicados nativos: EGRESOS ─────────────────────────────────────
WITH grupos AS (
    SELECT c.id, c.id_empresa, c.id_punto_emision, c.tipo_ambiente, c.secuencial,
           (n.id IS NOT NULL) AS es_nativo,
           ROW_NUMBER() OVER (
               PARTITION BY c.id_empresa, c.id_punto_emision, c.tipo_ambiente, c.secuencial
               ORDER BY (n.id IS NOT NULL), c.created_at, c.id
           ) AS rn
      FROM egresos_cabecera c
      LEFT JOIN _nativos_egr n ON n.id = c.id
     WHERE c.eliminado = false AND c.id_punto_emision IS NOT NULL
       AND COALESCE(c.secuencial, '') <> ''
), a_renumerar AS (
    SELECT g.id, g.id_empresa, g.id_punto_emision, g.tipo_ambiente,
           ROW_NUMBER() OVER (PARTITION BY g.id_empresa, g.id_punto_emision, g.tipo_ambiente
                              ORDER BY g.secuencial, g.id) AS orden
      FROM grupos g WHERE g.rn > 1 AND g.es_nativo
), maximos AS (
    SELECT id_empresa, id_punto_emision, tipo_ambiente,
           MAX(CAST(NULLIF(REGEXP_REPLACE(COALESCE(secuencial, ''), '[^0-9]', '', 'g'), '') AS BIGINT)) AS max_sec
      FROM egresos_cabecera
     WHERE id_punto_emision IS NOT NULL AND COALESCE(secuencial, '') <> ''
     GROUP BY 1,2,3
), objetivo AS (
    SELECT c.id, c.numero_egreso AS num_ant, c.secuencial AS sec_ant,
           LPAD((mx.max_sec + r.orden)::text, 9, '0') AS sec_nue,
           COALESCE(NULLIF(c.establecimiento, ''), '001') || '-'
        || COALESCE(NULLIF(c.punto_emision,   ''), '001') || '-'
        || LPAD((mx.max_sec + r.orden)::text, 9, '0') AS num_nue
      FROM a_renumerar r
      INNER JOIN egresos_cabecera c ON c.id = r.id
      INNER JOIN maximos mx ON mx.id_empresa = r.id_empresa
                           AND mx.id_punto_emision = r.id_punto_emision
                           AND mx.tipo_ambiente IS NOT DISTINCT FROM r.tipo_ambiente
), upd AS (
    UPDATE egresos_cabecera c
       SET secuencial = o.sec_nue, numero_egreso = o.num_nue, updated_at = now()
      FROM objetivo o WHERE c.id = o.id
    RETURNING c.id, o.sec_ant, o.sec_nue, o.num_ant, o.num_nue
)
INSERT INTO _renum SELECT 2, 'egresos_cabecera', id, sec_ant, sec_nue, num_ant, num_nue, 'número repetido' FROM upd;


-- ── 4. Consolidar los cambios por documento ──────────────────────────────────────────
-- Un mismo documento puede haber pasado por los dos pasos (se normalizó su formato y además
-- se renumeró por duplicado), dejando DOS filas en _renum. Al asiento hay que llevarle el
-- salto completo — número ORIGINAL → número FINAL —, no el intermedio del primer paso.
CREATE TEMP TABLE _cambios ON COMMIT DROP AS
SELECT tabla,
       id,
       (array_agg(sec_ant ORDER BY paso ASC))[1]  AS sec_ant,
       (array_agg(sec_nue ORDER BY paso DESC))[1] AS sec_nue,
       (array_agg(num_ant ORDER BY paso ASC))[1]  AS num_ant,
       (array_agg(num_nue ORDER BY paso DESC))[1] AS num_nue,
       string_agg(DISTINCT motivo, ' + ' ORDER BY motivo) AS motivo
  FROM _renum
 GROUP BY tabla, id;


-- ── 4b. Propagar el número nuevo al asiento contable ─────────────────────────────────
-- El asiento guarda el número como TEXTO ("Ingreso 001-101-000000007") en el concepto de la
-- cabecera y en documento_referencia de cada línea. El enlace real es por id
-- (ingresos_cabecera.id_asiento_contable), así que solo hay que refrescar esos textos.
UPDATE asientos_contables_cabecera a
   SET concepto = REPLACE(a.concepto, r.num_ant, r.num_nue), updated_at = now()
  FROM _cambios r
  INNER JOIN ingresos_cabecera c ON c.id = r.id
 WHERE r.tabla = 'ingresos_cabecera'
   AND a.id = c.id_asiento_contable
   AND COALESCE(r.num_ant, '') <> '' AND r.num_ant <> r.num_nue
   AND a.concepto LIKE '%' || r.num_ant || '%';

UPDATE asientos_contables_detalle d
   SET documento_referencia = REPLACE(d.documento_referencia, r.num_ant, r.num_nue), updated_at = now()
  FROM _cambios r
  INNER JOIN ingresos_cabecera c ON c.id = r.id
 WHERE r.tabla = 'ingresos_cabecera'
   AND d.id_asiento = c.id_asiento_contable
   AND COALESCE(r.num_ant, '') <> '' AND r.num_ant <> r.num_nue
   AND d.documento_referencia LIKE '%' || r.num_ant || '%';

UPDATE asientos_contables_cabecera a
   SET concepto = REPLACE(a.concepto, r.num_ant, r.num_nue), updated_at = now()
  FROM _cambios r
  INNER JOIN egresos_cabecera c ON c.id = r.id
 WHERE r.tabla = 'egresos_cabecera'
   AND a.id = c.id_asiento_contable
   AND COALESCE(r.num_ant, '') <> '' AND r.num_ant <> r.num_nue
   AND a.concepto LIKE '%' || r.num_ant || '%';

UPDATE asientos_contables_detalle d
   SET documento_referencia = REPLACE(d.documento_referencia, r.num_ant, r.num_nue), updated_at = now()
  FROM _cambios r
  INNER JOIN egresos_cabecera c ON c.id = r.id
 WHERE r.tabla = 'egresos_cabecera'
   AND d.id_asiento = c.id_asiento_contable
   AND COALESCE(r.num_ant, '') <> '' AND r.num_ant <> r.num_nue
   AND d.documento_referencia LIKE '%' || r.num_ant || '%';


-- ── 5. Qué cambió ────────────────────────────────────────────────────────────────────
SELECT 'Documentos modificados' AS reporte, tabla, id,
       num_ant AS numero_anterior, num_nue AS numero_nuevo,
       sec_ant AS secuencial_anterior, sec_nue AS secuencial_nuevo,
       CASE WHEN num_ant IS DISTINCT FROM num_nue THEN motivo
            ELSE motivo || ' (el Nº visible no cambia)' END AS motivo
  FROM _cambios ORDER BY (num_ant IS NOT DISTINCT FROM num_nue), tabla, id;


-- ── 6. Qué queda duplicado (solo migrados: fuera del alcance de este script) ─────────
SELECT 'Duplicado no resuelto (todos migrados)' AS reporte, tabla, id_empresa,
       id_punto_emision, numero, documentos FROM (
    SELECT 'ingresos_cabecera' AS tabla, c.id_empresa, c.id_punto_emision,
           c.secuencial AS numero,
           string_agg(c.id::text, ', ' ORDER BY c.id) AS documentos
      FROM ingresos_cabecera c
     WHERE c.eliminado = false AND c.id_punto_emision IS NOT NULL AND COALESCE(c.secuencial,'') <> ''
     GROUP BY 1,2,3,4 HAVING COUNT(*) > 1
    UNION ALL
    SELECT 'egresos_cabecera', c.id_empresa, c.id_punto_emision, c.secuencial,
           string_agg(c.id::text, ', ' ORDER BY c.id)
      FROM egresos_cabecera c
     WHERE c.eliminado = false AND c.id_punto_emision IS NOT NULL AND COALESCE(c.secuencial,'') <> ''
     GROUP BY 1,2,3,4 HAVING COUNT(*) > 1
) x ORDER BY tabla, id_empresa, id_punto_emision, numero;

COMMIT;

-- =====================================================================================
-- DESPUÉS: cuando la sección 6 no devuelva ninguna fila, ejecutar
-- 20260827_unique_secuencial_ingresos_egresos.sql para que la base rechace por sí misma
-- cualquier número repetido que se cuele en el futuro.
-- =====================================================================================
