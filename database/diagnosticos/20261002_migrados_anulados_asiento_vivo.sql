-- =====================================================================================
-- Ingresos / Egresos MIGRADOS anulados cuyo asiento migrado sigue vivo: ¿se puede anular?
-- =====================================================================================
-- Solo lectura. No modifica nada.
--
-- Para cada documento responde dos preguntas antes de tocar su asiento:
--   1. ¿Se anuló en ESTE sistema (hay ANULAR en log_sistema) o llegó ya anulado de la migración?
--   2. ¿Existe otro asiento vivo de la misma empresa que sea su ESPEJO exacto (mismas cuentas,
--      montos al revés)? Eso sería un reverso: el documento ya está compensado y anular su
--      asiento dejaría el saldo al revés.
--
-- Columna `diagnostico`:
--   TIENE REVERSO → NO anular (ya está compensado).
--   ANULADO EN EL SISTEMA → anular el asiento (es el defecto corregido el 02-10-2026).
--   LLEGÓ ANULADO → revisar en el sistema anterior antes de decidir.
-- =====================================================================================
WITH docs AS (
    SELECT 'ingreso'::text AS tipo, 'ingresos_cabecera'::text AS tabla, 'ingresos'::text AS entidad,
           d.id_empresa, d.id AS id_doc, d.numero_ingreso::text AS numero, d.fecha_emision, d.id_asiento_contable AS id_asiento
      FROM ingresos_cabecera d
     WHERE d.id_asiento_contable IS NOT NULL AND d.eliminado = false AND LOWER(d.estado::text) = 'anulado'
    UNION ALL
    SELECT 'egreso', 'egresos_cabecera', 'egresos',
           d.id_empresa, d.id, d.numero_egreso::text, d.fecha_emision, d.id_asiento_contable
      FROM egresos_cabecera d
     WHERE d.id_asiento_contable IS NOT NULL AND d.eliminado = false AND LOWER(d.estado::text) = 'anulado'
),
vivos AS (   -- solo los que apuntan a un asiento MIGRADO todavía vivo
    SELECT x.*, a.numero_comprobante, a.fecha_asiento, a.total_debe
      FROM docs x
      JOIN asientos_contables_cabecera a ON a.id = x.id_asiento
     WHERE a.modulo_origen = 'migracion' AND a.eliminado = false AND a.estado <> 'anulado'
),
candidatos AS (   -- otros asientos vivos de la empresa con el mismo total: posibles reversos
    SELECT v.id_asiento AS id_original, c.id AS id_candidato
      FROM vivos v
      JOIN asientos_contables_cabecera c
        ON c.id_empresa = v.id_empresa AND c.total_debe = v.total_debe AND c.id <> v.id_asiento
       AND c.eliminado = false AND c.estado <> 'anulado'
),
saldos AS (   -- saldo neto por cuenta de cada asiento involucrado
    SELECT ad.id_asiento, ad.id_cuenta_contable, ROUND(SUM(ad.debe - ad.haber), 2) AS s
      FROM asientos_contables_detalle ad
     WHERE ad.eliminado = false
       AND ad.id_asiento IN (SELECT id_asiento FROM vivos UNION SELECT id_candidato FROM candidatos)
     GROUP BY ad.id_asiento, ad.id_cuenta_contable
    HAVING ROUND(SUM(ad.debe - ad.haber), 2) <> 0
),
firmas AS (
    SELECT id_asiento,
           STRING_AGG(id_cuenta_contable || ':' || s,    ',' ORDER BY id_cuenta_contable) AS firma,
           STRING_AGG(id_cuenta_contable || ':' || (-s), ',' ORDER BY id_cuenta_contable) AS firma_invertida
      FROM saldos GROUP BY id_asiento
),
reversos AS (
    SELECT c.id_original,
           STRING_AGG(ac.numero_comprobante || ' (' || ac.fecha_asiento || ')', '; ' ORDER BY ac.fecha_asiento) AS reversos
      FROM candidatos c
      JOIN firmas fo ON fo.id_asiento = c.id_original
      JOIN firmas fc ON fc.id_asiento = c.id_candidato AND fc.firma = fo.firma_invertida
      JOIN asientos_contables_cabecera ac ON ac.id = c.id_candidato
     GROUP BY c.id_original
)
SELECT e.ruc, e.nombre AS empresa,
       v.tipo, v.id_doc, v.numero, v.fecha_emision,
       v.id_asiento, v.numero_comprobante, v.fecha_asiento, v.total_debe AS monto,
       mm.created_at AS migrado_el,
       an.created_at AS anulado_el,
       COALESCE(u.nombre, CASE WHEN an.id IS NOT NULL THEN 'usuario ' || an.id_usuario END) AS anulado_por,
       r.reversos AS asiento_reverso,
       CASE WHEN r.reversos IS NOT NULL THEN 'TIENE REVERSO: no anular'
            WHEN an.id IS NOT NULL      THEN 'ANULADO EN EL SISTEMA: anular el asiento'
            ELSE 'LLEGÓ ANULADO de la migración: revisar' END AS diagnostico
  FROM vivos v
  JOIN empresas e ON e.id = v.id_empresa
  LEFT JOIN migracion_mysql_map mm
         ON mm.entidad = v.entidad AND mm.id_destino = v.id_doc AND mm.id_empresa = v.id_empresa
  LEFT JOIN LATERAL (
        SELECT l.id, l.created_at, l.id_usuario FROM log_sistema l
         WHERE l.tabla_afectada = v.tabla AND l.id_registro = v.id_doc AND l.accion = 'ANULAR'
         ORDER BY l.created_at DESC LIMIT 1) an ON true
  LEFT JOIN usuarios u ON u.id = an.id_usuario
  LEFT JOIN reversos r ON r.id_original = v.id_asiento
 WHERE true
   -- AND e.ruc = '0993408339001'        -- ← descomente para una sola empresa
 ORDER BY e.nombre, v.tipo, v.fecha_emision;
