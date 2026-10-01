-- ============================================================================
-- DIAGNÓSTICO (solo lectura): egresos migrados del sistema anterior SIN asiento
-- ----------------------------------------------------------------------------
-- El asiento de un egreso viejo se migra con la entidad "Contabilidad" (código
-- 'EGR' + id del egreso viejo) y se ENLAZA al egreso (id_asiento_contable) en ese
-- mismo momento, solo si el egreso ya estaba migrado. Por eso un egreso migrado
-- puede quedar sin asiento por dos motivos:
--   ASIENTO MIGRADO SIN ENLACE  → el asiento sí está, pero el egreso se migró
--                                 DESPUÉS que la Contabilidad.
--   SIN ASIENTO MIGRADO         → la Contabilidad no se volvió a migrar después de
--                                 crearse el asiento en el sistema anterior (sigue en
--                                 uso), o se migró con "Desde" por fecha del asiento y
--                                 el asiento se registró tarde; o en el viejo el
--                                 asiento está vacío (editado sin líneas) y no hay
--                                 nada que migrar.
-- No modifica nada. Ejecutar en pgAdmin.
-- ============================================================================

-- 1) Resumen por empresa
WITH eg AS (
    SELECT m.id_empresa, m.id_origen, e.id, e.numero_egreso, e.fecha_emision, e.estado, e.id_asiento_contable
      FROM migracion_mysql_map m
      JOIN egresos_cabecera e ON e.id = m.id_destino
     WHERE m.entidad = 'egresos' AND m.vinculado = false AND e.eliminado = false
),
asi AS (
    SELECT id_empresa, clave_natural, id_destino
      FROM migracion_mysql_map
     WHERE entidad = 'contabilidad'
)
SELECT eg.id_empresa,
       CASE WHEN eg.id_asiento_contable IS NOT NULL THEN '1 CON ASIENTO'
            WHEN eg.estado = 'anulado'              THEN '4 ANULADO (no lleva asiento)'
            WHEN asi.id_destino IS NOT NULL         THEN '2 ASIENTO MIGRADO SIN ENLACE'
            ELSE                                         '3 SIN ASIENTO MIGRADO' END AS situacion,
       COUNT(*)                AS egresos,
       MIN(eg.fecha_emision)   AS desde,
       MAX(eg.fecha_emision)   AS hasta
  FROM eg
  LEFT JOIN asi ON asi.id_empresa = eg.id_empresa AND asi.clave_natural = 'EGR' || eg.id_origen
 GROUP BY 1, 2
 ORDER BY 1, 2;

-- 2) Un egreso concreto (número del egreso en el sistema anterior, p. ej. 15014)
--    Cambiar :empresa y :numero. El egreso migrado conserva el número viejo como secuencial.
-- SELECT e.id, e.numero_egreso, e.fecha_emision, e.estado, e.id_asiento_contable,
--        m.id_origen AS id_egreso_viejo,
--        a.id_destino AS asiento_migrado, ac.numero_comprobante, ac.fecha_asiento, ac.total_debe
--   FROM egresos_cabecera e
--   JOIN migracion_mysql_map m ON m.entidad = 'egresos' AND m.id_destino = e.id AND m.id_empresa = e.id_empresa
--   LEFT JOIN migracion_mysql_map a ON a.entidad = 'contabilidad' AND a.id_empresa = e.id_empresa
--                                  AND a.clave_natural = 'EGR' || m.id_origen
--   LEFT JOIN asientos_contables_cabecera ac ON ac.id = a.id_destino
--  WHERE e.id_empresa = :empresa AND e.secuencial = LPAD('15014', 9, '0');
