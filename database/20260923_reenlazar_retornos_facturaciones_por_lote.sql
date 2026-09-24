-- =============================================================================
-- Re-enlazar por PRODUCTO + LOTE + NUP las líneas de RETORNOS y FACTURACIONES de consignación
-- MIGRADAS que quedaron apuntando a una línea de la consignación de OTRO LOTE.
--
-- Problema (23-09-2026, consignación 001-101-000041821, empresa 1792708389001, "Anillo para
-- Fijador de Guia 4,00mm"): el retorno migrado 001-101-000018023 trae dos líneas con NUP "1":
-- lote X030484385 (1 u.) y lote X110524902 (5 u.). Las dos quedaron enlazadas a la línea 186980
-- (lote X030484385, 1 u.) → esa línea sale con saldo −5 y la del lote X110524902 con +5.
-- La migración del 12-09 enlazaba solo por producto, y el re-enlace del 18-09
-- (20260918_reenlazar_retornos_facturaciones_migradas.sql) compara el NUP pero NO el lote: con NUP
-- genéricos ("1", "2"…) que se repiten en varios lotes, el lote es lo único que distingue la línea.
--
-- Corrección: cada línea con lote de un retorno / facturación MIGRADO que apunta a una línea de
-- OTRO lote (o de otro NUP) pasa a apuntar a la línea de su MISMA consignación con el mismo
-- producto + lote + NUP, solo si esa línea es ÚNICA. Solo cambia `id_consignacion_detalle`: no
-- toca inventario, kardex, asientos ni totales. Nunca toca documentos creados en este sistema.
-- Idempotente: volver a correrlo no cambia nada.
-- Revertir: el PASO 2 guarda antes el enlace anterior en la tabla PERMANENTE
-- respaldo_reenlace_lote_20260923 (acumula, no duplica); el bloque REVERTIR del final lo restaura.
--
-- Uso en pgAdmin (MISMA ventana, en este orden; cada paso por separado: seleccionar y F5):
--   PASO 0  (opcional, solo lectura) Qué empresas lo tienen y cuántas líneas.
--   PASO 1  Diagnóstico (solo lectura). Ajustar `ruc` e `id_consignacion` (0 = toda la empresa).
--           Seleccionar desde DROP TABLE hasta el SELECT del resumen y F5; luego el detalle solo.
--             · se_pueden_reenlazar → lo que corrige el PASO 2;
--             · sin_linea / ambiguas → no se tocan; revisarlas a mano si importan.
--   PASO 2  Respaldo + corrección (DO block).
--   Verificación: volver a correr el PASO 1: "se_pueden_reenlazar" debe quedar en 0.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- PASO 0 · ¿QUÉ EMPRESAS LO TIENEN? (solo lectura, opcional)
-- ---------------------------------------------------------------------------
SELECT e.ruc, e.nombre AS empresa, t.documento_tipo, COUNT(*) AS lineas_en_otro_lote
  FROM (
        SELECT 'Retorno' AS documento_tipo, r.id_empresa
          FROM retornos_cv_detalles d
          JOIN retornos_cv r ON r.id = d.id_retorno AND r.eliminado = false
          JOIN migracion_mysql_map m ON m.id_empresa = r.id_empresa AND m.entidad = 'consignaciones_ret'
                                    AND m.id_destino = r.id AND m.vinculado IS NOT TRUE
          JOIN consignaciones_ventas_detalles act ON act.id = d.id_consignacion_detalle
         WHERE COALESCE(d.eliminado, false) = false AND COALESCE(TRIM(d.lote), '') <> ''
           AND (UPPER(TRIM(d.lote)) <> UPPER(TRIM(COALESCE(act.lote, '')))
                OR UPPER(TRIM(COALESCE(d.nup, ''))) <> UPPER(TRIM(COALESCE(act.nup, ''))))
        UNION ALL
        SELECT 'Facturación CV', cf.id_empresa
          FROM consignaciones_facturas_detalles d
          JOIN consignaciones_facturas cf ON cf.id = d.id_consignacion_factura AND cf.eliminado = false
          JOIN migracion_mysql_map m ON m.id_empresa = cf.id_empresa AND m.entidad = 'consignaciones_fact'
                                    AND m.id_destino = cf.id AND m.vinculado IS NOT TRUE
          JOIN consignaciones_ventas_detalles act ON act.id = d.id_consignacion_detalle
         WHERE COALESCE(d.eliminado, false) = false AND COALESCE(TRIM(d.lote), '') <> ''
           AND (UPPER(TRIM(d.lote)) <> UPPER(TRIM(COALESCE(act.lote, '')))
                OR UPPER(TRIM(COALESCE(d.nup, ''))) <> UPPER(TRIM(COALESCE(act.nup, ''))))
       ) t
  JOIN empresas e ON e.id = t.id_empresa
 GROUP BY e.ruc, e.nombre, t.documento_tipo
 ORDER BY lineas_en_otro_lote DESC, e.ruc, t.documento_tipo;

-- ---------------------------------------------------------------------------
-- PASO 1 · DIAGNÓSTICO (solo lectura). Deja la tabla temporal _cmg_reenlace_lote.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS _cmg_reenlace_lote;
CREATE TEMP TABLE _cmg_reenlace_lote AS
WITH p AS (
    SELECT '1792708389001'::text AS ruc,             -- <<< AJUSTAR: RUC de la empresa
           0::bigint             AS id_consignacion  -- <<< 0 = toda la empresa; o el id de UNA consignación (p. ej. 38290)
),
emp AS (SELECT e.id FROM empresas e JOIN p ON e.ruc = p.ruc),
lineas AS (
    SELECT 'Retorno'::varchar AS documento_tipo, r.id AS id_documento,
           (r.serie || '-' || r.secuencial)::varchar AS documento, r.estado::varchar AS estado,
           d.id AS id_linea, act.id_consignacion, d.id_producto,
           TRIM(COALESCE(d.nup, ''))::varchar AS nup, TRIM(d.lote)::varchar AS lote, d.cantidad,
           d.id_consignacion_detalle AS linea_actual
      FROM retornos_cv_detalles d
      JOIN retornos_cv r ON r.id = d.id_retorno AND r.eliminado = false
      JOIN migracion_mysql_map m ON m.id_empresa = r.id_empresa AND m.entidad = 'consignaciones_ret'
                                AND m.id_destino = r.id AND m.vinculado IS NOT TRUE
      JOIN consignaciones_ventas_detalles act ON act.id = d.id_consignacion_detalle
     WHERE r.id_empresa IN (SELECT id FROM emp)
       AND COALESCE(d.eliminado, false) = false AND COALESCE(TRIM(d.lote), '') <> ''
    UNION ALL
    SELECT 'Facturación CV', cf.id, COALESCE(cf.numero_factura, cf.serie || '-' || cf.secuencial)::varchar,
           cf.estado::varchar, d.id, act.id_consignacion, act.id_producto,
           TRIM(COALESCE(d.nup, ''))::varchar, TRIM(d.lote)::varchar, d.cantidad, d.id_consignacion_detalle
      FROM consignaciones_facturas_detalles d
      JOIN consignaciones_facturas cf ON cf.id = d.id_consignacion_factura AND cf.eliminado = false
      JOIN migracion_mysql_map m ON m.id_empresa = cf.id_empresa AND m.entidad = 'consignaciones_fact'
                                AND m.id_destino = cf.id AND m.vinculado IS NOT TRUE
      JOIN consignaciones_ventas_detalles act ON act.id = d.id_consignacion_detalle
     WHERE cf.id_empresa IN (SELECT id FROM emp)
       AND COALESCE(d.eliminado, false) = false AND COALESCE(TRIM(d.lote), '') <> ''
)
SELECT l.documento_tipo, l.id_documento, l.documento, l.estado, l.id_linea, l.id_consignacion,
       cv.serie || '-' || cv.secuencial AS consignacion, pr.codigo AS producto_codigo,
       l.lote, l.nup, l.cantidad,
       l.linea_actual, TRIM(act.lote) AS lote_linea_actual, TRIM(act.nup) AS nup_linea_actual,
       cand.n AS n_candidatas,
       CASE WHEN cand.n = 1 THEN cand.id END AS linea_correcta
  FROM lineas l
  JOIN consignaciones_ventas cv ON cv.id = l.id_consignacion
  LEFT JOIN productos pr ON pr.id = l.id_producto
  JOIN consignaciones_ventas_detalles act ON act.id = l.linea_actual
 CROSS JOIN p
  LEFT JOIN LATERAL (
        SELECT MIN(c.id) AS id, COUNT(*) AS n
          FROM consignaciones_ventas_detalles c
         WHERE c.id_consignacion = l.id_consignacion AND c.eliminado = false
           AND c.id_producto = l.id_producto
           AND UPPER(TRIM(COALESCE(c.lote, ''))) = UPPER(l.lote)
           AND UPPER(TRIM(COALESCE(c.nup, '')))  = UPPER(l.nup)) cand ON true
 WHERE (p.id_consignacion = 0 OR l.id_consignacion = p.id_consignacion)
   AND (UPPER(l.lote) <> UPPER(TRIM(COALESCE(act.lote, '')))          -- hoy apunta a OTRO lote
        OR UPPER(l.nup) <> UPPER(TRIM(COALESCE(act.nup, ''))));        -- … o a otro NUP

-- Resumen
SELECT documento_tipo,
       COUNT(*)                                  AS lineas_en_otra_linea,
       COUNT(*) FILTER (WHERE n_candidatas = 1)  AS se_pueden_reenlazar,
       COUNT(*) FILTER (WHERE n_candidatas = 0)  AS sin_linea,
       COUNT(*) FILTER (WHERE n_candidatas > 1)  AS ambiguas,
       COUNT(DISTINCT id_consignacion)           AS consignaciones
  FROM _cmg_reenlace_lote
 GROUP BY documento_tipo;

-- Detalle (correr solo)
-- SELECT * FROM _cmg_reenlace_lote ORDER BY consignacion, producto_codigo, lote, nup, id_linea;

-- ---------------------------------------------------------------------------
-- PASO 2 · RESPALDO + CORRECCIÓN (DO block, idempotente). Requiere el PASO 1 en esta misma ventana.
-- ---------------------------------------------------------------------------
DO $$
DECLARE
  n_resp INT := 0;
  n_ret  INT := 0;
  n_fac  INT := 0;
BEGIN
  IF to_regclass('pg_temp._cmg_reenlace_lote') IS NULL THEN
    RAISE EXCEPTION 'Corra primero el PASO 1 en esta misma ventana: falta la tabla temporal _cmg_reenlace_lote.';
  END IF;

  CREATE TABLE IF NOT EXISTS respaldo_reenlace_lote_20260923 (
      documento_tipo  varchar   NOT NULL,
      id_linea        bigint    NOT NULL,
      id_consignacion bigint,
      lote            varchar,
      nup             varchar,
      linea_anterior  bigint,
      linea_nueva     bigint    NOT NULL,
      respaldado_at   timestamp NOT NULL DEFAULT now(),
      PRIMARY KEY (documento_tipo, id_linea)
  );
  INSERT INTO respaldo_reenlace_lote_20260923
         (documento_tipo, id_linea, id_consignacion, lote, nup, linea_anterior, linea_nueva)
  SELECT documento_tipo, id_linea, id_consignacion, lote, nup, linea_actual, linea_correcta
    FROM _cmg_reenlace_lote
   WHERE linea_correcta IS NOT NULL
  ON CONFLICT (documento_tipo, id_linea) DO NOTHING;
  GET DIAGNOSTICS n_resp = ROW_COUNT;

  UPDATE retornos_cv_detalles d
     SET id_consignacion_detalle = x.linea_correcta, updated_at = now()
    FROM _cmg_reenlace_lote x
   WHERE x.documento_tipo = 'Retorno' AND x.linea_correcta IS NOT NULL
     AND d.id = x.id_linea
     AND d.id_consignacion_detalle = x.linea_actual;   -- solo si sigue como estaba
  GET DIAGNOSTICS n_ret = ROW_COUNT;

  UPDATE consignaciones_facturas_detalles d
     SET id_consignacion_detalle = x.linea_correcta
    FROM _cmg_reenlace_lote x
   WHERE x.documento_tipo = 'Facturación CV' AND x.linea_correcta IS NOT NULL
     AND d.id = x.id_linea
     AND d.id_consignacion_detalle = x.linea_actual;
  GET DIAGNOSTICS n_fac = ROW_COUNT;

  RAISE NOTICE 'Respaldo: % líneas nuevas en respaldo_reenlace_lote_20260923.', n_resp;
  RAISE NOTICE 'Líneas re-enlazadas: % de retornos, % de facturaciones de consignación.', n_ret, n_fac;
END $$;

-- ---------------------------------------------------------------------------
-- VERIFICACIÓN (caso 41821): la línea del lote X030484385 debe quedar con retornado 1 y la del
-- lote X110524902 con retornado 5.
-- ---------------------------------------------------------------------------
-- SELECT d.id AS id_linea_retorno, d.lote, d.nup, d.cantidad, d.id_consignacion_detalle,
--        cvd.lote AS lote_linea, cvd.nup AS nup_linea, cvd.cantidad AS cantidad_linea
--   FROM retornos_cv_detalles d
--   JOIN consignaciones_ventas_detalles cvd ON cvd.id = d.id_consignacion_detalle
--  WHERE d.id IN (75676, 75677);

-- ---------------------------------------------------------------------------
-- REVERTIR (solo si hiciera falta): restaura el enlace anterior guardado en el respaldo.
-- ---------------------------------------------------------------------------
-- UPDATE retornos_cv_detalles d
--    SET id_consignacion_detalle = r.linea_anterior, updated_at = now()
--   FROM respaldo_reenlace_lote_20260923 r
--  WHERE r.documento_tipo = 'Retorno' AND d.id = r.id_linea AND d.id_consignacion_detalle = r.linea_nueva;
-- UPDATE consignaciones_facturas_detalles d
--    SET id_consignacion_detalle = r.linea_anterior
--   FROM respaldo_reenlace_lote_20260923 r
--  WHERE r.documento_tipo = 'Facturación CV' AND d.id = r.id_linea AND d.id_consignacion_detalle = r.linea_nueva;
