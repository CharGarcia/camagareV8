-- =====================================================================================
-- Diagnóstico (SOLO LECTURA): ingresos y egresos que comparten serie + secuencial.
--
-- Ejecutar cuando la creación del índice único falle con:
--   ERROR: could not create unique index "uq_ingresos_secuencial_activo"
--   Key (id_empresa, id_punto_emision, secuencial, tipo_ambiente)=(...) is duplicated.
--
-- Muestra, documento por documento, si es NATIVO (creado aquí) o MIGRADO, y en los migrados
-- de qué registro del sistema anterior viene. Con eso se decide qué hacer con cada grupo.
--
-- No modifica nada.
-- =====================================================================================

SELECT 'ingresos_cabecera' AS tabla,
       c.id_empresa,
       c.id_punto_emision,
       c.establecimiento || '-' || c.punto_emision AS serie,
       c.secuencial,
       c.tipo_ambiente,
       c.id,
       c.numero_ingreso                            AS numero,
       c.fecha_emision,
       c.monto_total,
       c.estado,
       COALESCE(c.recibo_de, '')                   AS referencia,
       CASE WHEN m.id_destino IS NULL THEN 'NATIVO'
            ELSE 'MIGRADO (origen #' || m.id_origen || ', nº viejo ' || COALESCE(m.clave_natural, '?') || ')'
       END                                         AS origen,
       (c.id_asiento_contable IS NOT NULL)         AS tiene_asiento
  FROM ingresos_cabecera c
  LEFT JOIN migracion_mysql_map m
         ON m.id_empresa = c.id_empresa AND m.entidad = 'ingresos' AND m.id_destino = c.id
 WHERE c.eliminado = false
   AND c.id_punto_emision IS NOT NULL
   AND EXISTS (
        SELECT 1 FROM ingresos_cabecera d
         WHERE d.eliminado = false
           AND d.id_empresa       = c.id_empresa
           AND d.id_punto_emision = c.id_punto_emision
           AND d.secuencial       = c.secuencial
           AND d.tipo_ambiente IS NOT DISTINCT FROM c.tipo_ambiente
           AND d.id <> c.id
   )
 ORDER BY c.id_empresa, c.id_punto_emision, c.secuencial, c.id;


SELECT 'egresos_cabecera' AS tabla,
       c.id_empresa,
       c.id_punto_emision,
       c.establecimiento || '-' || c.punto_emision AS serie,
       c.secuencial,
       c.tipo_ambiente,
       c.id,
       c.numero_egreso                             AS numero,
       c.fecha_emision,
       c.monto_total,
       c.estado,
       COALESCE(c.beneficiario_nombre, '')         AS referencia,
       CASE WHEN m.id_destino IS NULL THEN 'NATIVO'
            ELSE 'MIGRADO (origen #' || m.id_origen || ', nº viejo ' || COALESCE(m.clave_natural, '?') || ')'
       END                                         AS origen,
       (c.id_asiento_contable IS NOT NULL)         AS tiene_asiento
  FROM egresos_cabecera c
  LEFT JOIN migracion_mysql_map m
         ON m.id_empresa = c.id_empresa AND m.entidad = 'egresos' AND m.id_destino = c.id
 WHERE c.eliminado = false
   AND c.id_punto_emision IS NOT NULL
   AND EXISTS (
        SELECT 1 FROM egresos_cabecera d
         WHERE d.eliminado = false
           AND d.id_empresa       = c.id_empresa
           AND d.id_punto_emision = c.id_punto_emision
           AND d.secuencial       = c.secuencial
           AND d.tipo_ambiente IS NOT DISTINCT FROM c.tipo_ambiente
           AND d.id <> c.id
   )
 ORDER BY c.id_empresa, c.id_punto_emision, c.secuencial, c.id;


-- Resumen: cuántos grupos duplicados hay y de qué tipo son.
SELECT tabla, COUNT(*) AS grupos_duplicados, SUM(documentos) AS documentos_afectados,
       COUNT(*) FILTER (WHERE nativos = 0)                  AS grupos_solo_migrados,
       COUNT(*) FILTER (WHERE nativos > 0 AND migrados > 0)  AS grupos_mixtos,
       COUNT(*) FILTER (WHERE migrados = 0)                  AS grupos_solo_nativos
  FROM (
    SELECT 'ingresos_cabecera' AS tabla, c.id_empresa, c.id_punto_emision, c.secuencial, c.tipo_ambiente,
           COUNT(*) AS documentos,
           COUNT(*) FILTER (WHERE m.id_destino IS NULL)     AS nativos,
           COUNT(*) FILTER (WHERE m.id_destino IS NOT NULL) AS migrados
      FROM ingresos_cabecera c
      LEFT JOIN migracion_mysql_map m
             ON m.id_empresa = c.id_empresa AND m.entidad = 'ingresos' AND m.id_destino = c.id
     WHERE c.eliminado = false AND c.id_punto_emision IS NOT NULL
     GROUP BY 1,2,3,4,5 HAVING COUNT(*) > 1
    UNION ALL
    SELECT 'egresos_cabecera', c.id_empresa, c.id_punto_emision, c.secuencial, c.tipo_ambiente,
           COUNT(*),
           COUNT(*) FILTER (WHERE m.id_destino IS NULL),
           COUNT(*) FILTER (WHERE m.id_destino IS NOT NULL)
      FROM egresos_cabecera c
      LEFT JOIN migracion_mysql_map m
             ON m.id_empresa = c.id_empresa AND m.entidad = 'egresos' AND m.id_destino = c.id
     WHERE c.eliminado = false AND c.id_punto_emision IS NOT NULL
     GROUP BY 1,2,3,4,5 HAVING COUNT(*) > 1
  ) g
 GROUP BY tabla;
