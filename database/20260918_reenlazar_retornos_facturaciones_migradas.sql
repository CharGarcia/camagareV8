-- =============================================================================
-- Re-enlazar por NUP las líneas de RETORNOS y FACTURACIONES de consignación MIGRADAS.
--
-- Problema (18-09-2026, consignación 001-101-000051712, empresa 1792708389001): la migración de
-- esos documentos (12-09) enlazaba cada línea con la línea de la consignación solo por PRODUCTO.
-- Con NUP individuales, los retornos de varias unidades del mismo producto quedaron apuntando a
-- UNA sola línea: el PDF, el Excel y el Reporte de inventarios muestran esa línea con más retornado
-- que su cantidad (saldo negativo) y las otras unidades como si siguieran en poder del cliente. El
-- total por producto está bien; lo que está mal es a qué unidad se le asigna cada retorno.
--
-- Corrección: cada línea con NUP de un retorno / facturación MIGRADO que apunta a una línea de la
-- consignación con OTRO NUP pasa a apuntar a la línea de su MISMA consignación con el mismo
-- producto y el mismo NUP, solo si esa línea es ÚNICA. No toca inventario, asientos ni totales:
-- solo `id_consignacion_detalle`. Nunca toca documentos creados en este sistema.
-- Idempotente: volver a correrlo no cambia nada.
-- Revertir: el PASO 2 guarda antes el enlace anterior de cada línea en la tabla PERMANENTE
-- respaldo_reenlace_consignaciones_20260918 (acumula, no duplica); el bloque REVERTIR del final
-- lo restaura. Borrar esa tabla cuando ya no haga falta.
--
-- Uso en pgAdmin (misma ventana, en este orden):
--   PASO 0  (opcional, solo lectura) Qué empresas tienen el problema y cuántas líneas.
--   PASO 1  Diagnóstico (solo lectura). Ajustar `ruc` e `id_consignacion` (0 = toda la empresa)
--           y ejecutar desde DROP TABLE hasta el detalle. Revisar el resumen:
--             · se_pueden_reenlazar  → lo que corrige el PASO 2;
--             · sin_linea_con_ese_nup / ambiguas → no se tocan; revisarlas a mano si importan.
--   PASO 2  Respaldo + corrección (DO block).
--   Verificación: volver a correr el PASO 1: "se_pueden_reenlazar" debe quedar en 0.
--
-- 18-09-2026: la primera versión del PASO 2 no guardaba el respaldo. Si ya se corrió así, volver a
-- correr el PASO 2 actual en la MISMA ventana (sin repetir el PASO 1) toma el respaldo desde
-- _cmg_reenlace y no re-enlaza nada más ("0 de retornos, 0 de facturaciones").
-- =============================================================================

-- ---------------------------------------------------------------------------
-- PASO 0 · ¿QUÉ EMPRESAS TIENEN EL PROBLEMA? (solo lectura, opcional)
--   Líneas con NUP de retornos / facturaciones migrados que apuntan a una línea con otro NUP.
-- ---------------------------------------------------------------------------
SELECT e.ruc, e.nombre AS empresa, t.documento_tipo, COUNT(*) AS lineas_con_otro_nup
  FROM (
        SELECT 'Retorno' AS documento_tipo, r.id_empresa
          FROM retornos_cv_detalles d
          JOIN retornos_cv r ON r.id = d.id_retorno AND r.eliminado = false
          JOIN migracion_mysql_map m ON m.id_empresa = r.id_empresa AND m.entidad = 'consignaciones_ret'
                                    AND m.id_destino = r.id AND m.vinculado = false
          LEFT JOIN consignaciones_ventas_detalles act ON act.id = d.id_consignacion_detalle
         WHERE COALESCE(d.eliminado, false) = false AND COALESCE(TRIM(d.nup), '') <> ''
           AND COALESCE(TRIM(act.nup), '') <> TRIM(d.nup)
        UNION ALL
        SELECT 'Facturación CV', cf.id_empresa
          FROM consignaciones_facturas_detalles d
          JOIN consignaciones_facturas cf ON cf.id = d.id_consignacion_factura AND cf.eliminado = false
          JOIN migracion_mysql_map m ON m.id_empresa = cf.id_empresa AND m.entidad = 'consignaciones_fact'
                                    AND m.id_destino = cf.id AND m.vinculado = false
          LEFT JOIN consignaciones_ventas_detalles act ON act.id = d.id_consignacion_detalle
         WHERE COALESCE(d.eliminado, false) = false AND COALESCE(TRIM(d.nup), '') <> ''
           AND COALESCE(TRIM(act.nup), '') <> TRIM(d.nup)
       ) t
  JOIN empresas e ON e.id = t.id_empresa
 GROUP BY e.ruc, e.nombre, t.documento_tipo
 ORDER BY lineas_con_otro_nup DESC, e.ruc, t.documento_tipo;

-- ---------------------------------------------------------------------------
-- PASO 1 · DIAGNÓSTICO (solo lectura)
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS _cmg_reenlace;
CREATE TEMP TABLE _cmg_reenlace AS
WITH p AS (
    SELECT '1792708389001'::text AS ruc,        -- <<< AJUSTAR: RUC de la empresa
           0::bigint             AS id_consignacion  -- <<< 0 = todas; o el id de UNA consignación (p. ej. 48181)
),
emp AS (SELECT e.id FROM empresas e JOIN p ON e.ruc = p.ruc),
lineas AS (
    -- Líneas de retornos insertados por la migración
    SELECT 'Retorno'::varchar AS documento_tipo, r.id AS id_documento,
           (r.serie || '-' || r.secuencial)::varchar AS documento, r.estado::varchar AS estado,
           d.id AS id_linea, d.id_consignacion, d.id_consignacion_detalle AS linea_actual,
           d.id_producto, TRIM(d.nup)::varchar AS nup, d.lote::varchar AS lote, d.cantidad
      FROM retornos_cv_detalles d
      JOIN retornos_cv r ON r.id = d.id_retorno AND r.eliminado = false
      JOIN migracion_mysql_map m ON m.id_empresa = r.id_empresa AND m.entidad = 'consignaciones_ret'
                                AND m.id_destino = r.id AND m.vinculado = false
     WHERE r.id_empresa IN (SELECT id FROM emp)
       AND COALESCE(d.eliminado, false) = false AND COALESCE(TRIM(d.nup), '') <> ''
    UNION ALL
    -- Líneas de facturaciones de consignación insertadas por la migración
    SELECT 'Facturación CV', cf.id,
           COALESCE(cf.numero_factura, cf.serie || '-' || cf.secuencial)::varchar, cf.estado::varchar,
           d.id, d.id_consignacion, d.id_consignacion_detalle,
           d.id_producto, TRIM(d.nup)::varchar, d.lote::varchar, d.cantidad
      FROM consignaciones_facturas_detalles d
      JOIN consignaciones_facturas cf ON cf.id = d.id_consignacion_factura AND cf.eliminado = false
      JOIN migracion_mysql_map m ON m.id_empresa = cf.id_empresa AND m.entidad = 'consignaciones_fact'
                                AND m.id_destino = cf.id AND m.vinculado = false
     WHERE cf.id_empresa IN (SELECT id FROM emp)
       AND COALESCE(d.eliminado, false) = false AND COALESCE(TRIM(d.nup), '') <> ''
)
SELECT l.documento_tipo, l.id_documento, l.documento, l.estado, l.id_linea, l.id_consignacion,
       cv.serie || '-' || cv.secuencial AS consignacion,
       pr.codigo AS producto_codigo, l.nup, l.lote, l.cantidad,
       l.linea_actual, TRIM(act.nup) AS nup_linea_actual,
       CASE WHEN cand.n = 1 THEN cand.id END                    AS linea_correcta,
       COALESCE(cand.n, 0)                                     AS n_candidatas
  FROM lineas l
 CROSS JOIN p
  JOIN consignaciones_ventas cv ON cv.id = l.id_consignacion
  LEFT JOIN productos pr ON pr.id = l.id_producto
  LEFT JOIN consignaciones_ventas_detalles act ON act.id = l.linea_actual
  LEFT JOIN LATERAL (
        SELECT MIN(e.id) AS id, COUNT(*) AS n
          FROM consignaciones_ventas_detalles e
         WHERE e.id_consignacion = l.id_consignacion AND COALESCE(e.eliminado, false) = false
           AND e.id_producto = l.id_producto AND TRIM(e.nup) = l.nup) cand ON true
 WHERE (p.id_consignacion = 0 OR l.id_consignacion = p.id_consignacion)
   AND COALESCE(TRIM(act.nup), '') <> l.nup;       -- hoy apunta a una línea con OTRO NUP

-- Resumen:
SELECT documento_tipo,
       COUNT(*)                                         AS lineas_con_otro_nup,
       COUNT(*) FILTER (WHERE n_candidatas = 1)         AS se_pueden_reenlazar,
       COUNT(*) FILTER (WHERE n_candidatas = 0)         AS sin_linea_con_ese_nup,
       COUNT(*) FILTER (WHERE n_candidatas > 1)         AS ambiguas,
       COUNT(DISTINCT id_consignacion)                  AS consignaciones
  FROM _cmg_reenlace
 GROUP BY documento_tipo
 ORDER BY documento_tipo;

-- Detalle (lo que se reenlaza y lo que no):
SELECT * FROM _cmg_reenlace ORDER BY id_consignacion, documento_tipo, id_documento, id_linea;


-- ---------------------------------------------------------------------------
-- PASO 1b · VISTA PREVIA (solo lectura, opcional): saldos por línea antes y después del PASO 2
--   Mismo cálculo del PDF (retornos Emitidos y facturaciones facturadas; no incluye cambios de
--   productos), solo en las consignaciones con algo que re-enlazar. Deja _cmg_saldos.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS _cmg_saldos;
CREATE TEMP TABLE _cmg_saldos AS
WITH cons AS (SELECT DISTINCT id_consignacion FROM _cmg_reenlace WHERE linea_correcta IS NOT NULL),
ret AS (
    SELECT d.id_consignacion_detalle AS antes,
           COALESCE(x.linea_correcta, d.id_consignacion_detalle) AS despues, d.cantidad
      FROM retornos_cv_detalles d
      JOIN retornos_cv r ON r.id = d.id_retorno AND r.eliminado = false AND r.estado = 'Emitida'
      LEFT JOIN _cmg_reenlace x ON x.documento_tipo = 'Retorno' AND x.id_linea = d.id
     WHERE COALESCE(d.eliminado, false) = false AND d.id_consignacion IN (SELECT id_consignacion FROM cons)
),
fac AS (
    SELECT d.id_consignacion_detalle AS antes,
           COALESCE(x.linea_correcta, d.id_consignacion_detalle) AS despues, d.cantidad
      FROM consignaciones_facturas_detalles d
      JOIN consignaciones_facturas cf ON cf.id = d.id_consignacion_factura AND cf.eliminado = false AND cf.estado = 'facturada'
      LEFT JOIN _cmg_reenlace x ON x.documento_tipo = 'Facturación CV' AND x.id_linea = d.id
     WHERE COALESCE(d.eliminado, false) = false AND d.id_consignacion IN (SELECT id_consignacion FROM cons)
),
ra AS (SELECT antes   AS id, SUM(cantidad) AS c FROM ret GROUP BY 1),
rd AS (SELECT despues AS id, SUM(cantidad) AS c FROM ret GROUP BY 1),
fa AS (SELECT antes   AS id, SUM(cantidad) AS c FROM fac GROUP BY 1),
fd AS (SELECT despues AS id, SUM(cantidad) AS c FROM fac GROUP BY 1)
SELECT cvd.id AS id_linea, cvd.id_consignacion, cv.serie || '-' || cv.secuencial AS consignacion,
       pr.codigo AS producto_codigo, cvd.nup, cvd.cantidad,
       COALESCE(ra.c, 0) AS retornado_antes,  COALESCE(rd.c, 0) AS retornado_despues,
       COALESCE(fa.c, 0) AS facturado_antes,  COALESCE(fd.c, 0) AS facturado_despues,
       cvd.cantidad - COALESCE(ra.c, 0) - COALESCE(fa.c, 0) AS saldo_antes,
       cvd.cantidad - COALESCE(rd.c, 0) - COALESCE(fd.c, 0) AS saldo_despues
  FROM consignaciones_ventas_detalles cvd
  JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
  LEFT JOIN productos pr ON pr.id = cvd.id_producto
  LEFT JOIN ra ON ra.id = cvd.id
  LEFT JOIN rd ON rd.id = cvd.id
  LEFT JOIN fa ON fa.id = cvd.id
  LEFT JOIN fd ON fd.id = cvd.id
 WHERE cvd.id_consignacion IN (SELECT id_consignacion FROM cons) AND COALESCE(cvd.eliminado, false) = false;

-- Resumen de la vista previa:
SELECT COUNT(*)                                      AS lineas_revisadas,
       COUNT(*) FILTER (WHERE saldo_antes < 0)       AS lineas_negativas_antes,
       COUNT(*) FILTER (WHERE saldo_despues < 0)     AS lineas_negativas_despues,
       COUNT(DISTINCT id_consignacion) FILTER (WHERE saldo_antes < 0)   AS consignaciones_negativas_antes,
       COUNT(DISTINCT id_consignacion) FILTER (WHERE saldo_despues < 0) AS consignaciones_negativas_despues
  FROM _cmg_saldos;

-- Líneas que seguirían negativas (para revisar a mano):
SELECT * FROM _cmg_saldos WHERE saldo_despues < 0 ORDER BY id_consignacion, id_linea;


-- ---------------------------------------------------------------------------
-- PASO 2 · RESPALDO + CORRECCIÓN (DO block, idempotente). Requiere el PASO 1 en esta misma sesión.
-- ---------------------------------------------------------------------------
DO $$
DECLARE
  n_resp INT := 0;
  n_ret  INT := 0;
  n_fac  INT := 0;
BEGIN
  IF to_regclass('pg_temp._cmg_reenlace') IS NULL THEN
    RAISE EXCEPTION 'Corra primero el PASO 1 (diagnóstico) en esta misma sesión: falta la tabla temporal _cmg_reenlace.';
  END IF;

  -- Respaldo permanente del enlace anterior (para el bloque REVERTIR). Acumula y no duplica.
  CREATE TABLE IF NOT EXISTS respaldo_reenlace_consignaciones_20260918 (
      documento_tipo  varchar   NOT NULL,
      id_linea        bigint    NOT NULL,
      id_consignacion bigint,
      nup             varchar,
      linea_anterior  bigint,
      linea_nueva     bigint    NOT NULL,
      respaldado_at   timestamp NOT NULL DEFAULT now(),
      PRIMARY KEY (documento_tipo, id_linea)
  );
  INSERT INTO respaldo_reenlace_consignaciones_20260918
         (documento_tipo, id_linea, id_consignacion, nup, linea_anterior, linea_nueva)
  SELECT documento_tipo, id_linea, id_consignacion, nup, linea_actual, linea_correcta
    FROM _cmg_reenlace
   WHERE linea_correcta IS NOT NULL
  ON CONFLICT (documento_tipo, id_linea) DO NOTHING;
  GET DIAGNOSTICS n_resp = ROW_COUNT;

  UPDATE retornos_cv_detalles d
     SET id_consignacion_detalle = x.linea_correcta, updated_at = now()
    FROM _cmg_reenlace x
   WHERE x.documento_tipo = 'Retorno' AND x.linea_correcta IS NOT NULL
     AND d.id = x.id_linea
     AND d.id_consignacion_detalle = x.linea_actual;   -- solo si sigue como estaba
  GET DIAGNOSTICS n_ret = ROW_COUNT;

  UPDATE consignaciones_facturas_detalles d
     SET id_consignacion_detalle = x.linea_correcta
    FROM _cmg_reenlace x
   WHERE x.documento_tipo = 'Facturación CV' AND x.linea_correcta IS NOT NULL
     AND d.id = x.id_linea
     AND d.id_consignacion_detalle = x.linea_actual;
  GET DIAGNOSTICS n_fac = ROW_COUNT;

  RAISE NOTICE 'Respaldo: % líneas nuevas en respaldo_reenlace_consignaciones_20260918.', n_resp;
  RAISE NOTICE 'Líneas re-enlazadas: % de retornos, % de facturaciones de consignación.', n_ret, n_fac;
END $$;

-- Verificación: volver a correr el PASO 1 ("se_pueden_reenlazar" en 0) y las consultas 1 y 4 de
-- database/diagnosticos/20260918_consignacion_retornos_saldo_negativo.sql.
--   SELECT documento_tipo, COUNT(*) FROM respaldo_reenlace_consignaciones_20260918 GROUP BY 1;
--   -- debe dar las líneas re-enlazadas por el PASO 2 (p. ej. Retorno 43151).


-- ---------------------------------------------------------------------------
-- REVERTIR (solo si hiciera falta; quitar los comentarios). Devuelve cada línea a su enlace
-- anterior, solo si sigue como la dejó el PASO 2.
-- ---------------------------------------------------------------------------
-- UPDATE retornos_cv_detalles d
--    SET id_consignacion_detalle = b.linea_anterior, updated_at = now()
--   FROM respaldo_reenlace_consignaciones_20260918 b
--  WHERE b.documento_tipo = 'Retorno' AND d.id = b.id_linea AND d.id_consignacion_detalle = b.linea_nueva;
-- UPDATE consignaciones_facturas_detalles d
--    SET id_consignacion_detalle = b.linea_anterior
--   FROM respaldo_reenlace_consignaciones_20260918 b
--  WHERE b.documento_tipo = 'Facturación CV' AND d.id = b.id_linea AND d.id_consignacion_detalle = b.linea_nueva;
--
-- Cuando ya no haga falta revertir:  DROP TABLE IF EXISTS respaldo_reenlace_consignaciones_20260918;
