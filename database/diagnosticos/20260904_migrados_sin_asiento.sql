-- Documentos traídos por la migración desde MySQL (migracion_mysql_map, vinculado = false)
-- que siguen SIN asiento contable enlazado. Es el mismo universo que cuenta el aviso azul
-- "documentos migrados sin asiento" de Asientos Contables / Estados Financieros, pero aquí
-- se ven TODOS, con estado, fecha, total y si están eliminados, para decidir qué hacer con
-- cada uno (anulados o eliminados no necesitan asiento; el resto: re-migrar contabilidad
-- o registrar el asiento desde el documento).
--
-- Uso en pgAdmin: cambiar el id de empresa en la primera línea y ejecutar todo.
-- Solo lectura. La última consulta es el resumen por módulo y estado.

WITH p AS (SELECT 8::int AS id_empresa),
docs AS (
    SELECT 'Facturas de Venta' AS modulo, v.id, v.establecimiento||'-'||v.punto_emision||'-'||v.secuencial AS numero,
           v.fecha_emision::date AS fecha, v.estado, v.importe_total AS total, v.eliminado
      FROM ventas_cabecera v
      CROSS JOIN p
      JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'facturas' AND mm.id_destino = v.id AND mm.vinculado IS NOT TRUE
     WHERE v.id_empresa = p.id_empresa AND v.id_asiento_contable IS NULL
  UNION ALL
    SELECT 'Recibos de Venta', r.id, r.establecimiento||'-'||r.punto_emision||'-'||r.secuencial,
           r.fecha_emision::date, r.estado, r.importe_total, r.eliminado
      FROM recibos_venta_cabecera r
      CROSS JOIN p
      JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'recibos' AND mm.id_destino = r.id AND mm.vinculado IS NOT TRUE
     WHERE r.id_empresa = p.id_empresa AND r.id_asiento_contable IS NULL
  UNION ALL
    SELECT 'Facturas de Compra', c.id, c.establecimiento_prov||'-'||c.punto_emision_prov||'-'||c.secuencial_prov,
           c.fecha_emision::date, c.estado, c.importe_total, c.eliminado
      FROM compras_cabecera c
      CROSS JOIN p
      JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'compras' AND mm.id_destino = c.id AND mm.vinculado IS NOT TRUE
     WHERE c.id_empresa = p.id_empresa AND c.id_asiento_contable IS NULL
  UNION ALL
    SELECT 'Liquidaciones de Compra', l.id, l.establecimiento||'-'||l.punto_emision||'-'||l.secuencial,
           l.fecha_emision::date, l.estado, l.importe_total, l.eliminado
      FROM liquidaciones_cabecera l
      CROSS JOIN p
      JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'liquidaciones' AND mm.id_destino = l.id AND mm.vinculado IS NOT TRUE
     WHERE l.id_empresa = p.id_empresa AND l.id_asiento_contable IS NULL
  UNION ALL
    SELECT 'Notas de Crédito', n.id, n.establecimiento||'-'||n.punto_emision||'-'||n.secuencial,
           n.fecha_emision::date, n.estado, n.importe_total, n.eliminado
      FROM notas_credito_cabecera n
      CROSS JOIN p
      JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'notas_credito' AND mm.id_destino = n.id AND mm.vinculado IS NOT TRUE
     WHERE n.id_empresa = p.id_empresa AND n.id_asiento_contable IS NULL
  UNION ALL
    SELECT 'Retenciones en Ventas', rv.id, rv.establecimiento||'-'||rv.punto_emision||'-'||rv.secuencial,
           rv.fecha_emision::date, NULL::text, NULL::numeric, rv.eliminado
      FROM retencion_venta_cabecera rv
      CROSS JOIN p
      JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'retenciones_venta' AND mm.id_destino = rv.id AND mm.vinculado IS NOT TRUE
     WHERE rv.id_empresa = p.id_empresa AND rv.id_asiento_contable IS NULL
  UNION ALL
    SELECT 'Retenciones en Compras', rc.id, rc.establecimiento||'-'||rc.punto_emision||'-'||rc.secuencial,
           rc.fecha_emision::date, rc.estado, NULL::numeric, rc.eliminado
      FROM retencion_compra_cabecera rc
      CROSS JOIN p
      JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'retenciones_compra' AND mm.id_destino = rc.id AND mm.vinculado IS NOT TRUE
     WHERE rc.id_empresa = p.id_empresa AND rc.id_asiento_contable IS NULL
  UNION ALL
    SELECT 'Ingresos', i.id, i.numero_ingreso::text,
           i.fecha_emision::date, i.estado, NULL::numeric, i.eliminado
      FROM ingresos_cabecera i
      CROSS JOIN p
      JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'ingresos' AND mm.id_destino = i.id AND mm.vinculado IS NOT TRUE
     WHERE i.id_empresa = p.id_empresa AND i.id_asiento_contable IS NULL
  UNION ALL
    SELECT 'Egresos', e.id, e.numero_egreso::text,
           e.fecha_emision::date, e.estado, NULL::numeric, e.eliminado
      FROM egresos_cabecera e
      CROSS JOIN p
      JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'egresos' AND mm.id_destino = e.id AND mm.vinculado IS NOT TRUE
     WHERE e.id_empresa = p.id_empresa AND e.id_asiento_contable IS NULL
  UNION ALL
    SELECT 'Consignaciones en Ventas', cv.id, cv.serie||'-'||cv.secuencial,
           cv.fecha_emision::date, cv.estado, cv.total, cv.eliminado
      FROM consignaciones_ventas cv
      CROSS JOIN p
      JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'consignaciones' AND mm.id_destino = cv.id AND mm.vinculado IS NOT TRUE
     WHERE cv.id_empresa = p.id_empresa AND cv.id_asiento_contable IS NULL
)
-- 1) Detalle: un documento por fila
SELECT modulo, id, numero, fecha, estado, total, eliminado,
       CASE
         WHEN eliminado THEN 'eliminado: no necesita asiento'
         WHEN UPPER(TRIM(COALESCE(estado,''))) IN ('ANULADO','ANULADA','RECHAZADA','PENDIENTE_APROBACION','BORRADOR') THEN 'estado sin efecto contable: no necesita asiento'
         ELSE 'sin asiento: re-migrar contabilidad o registrar desde el documento'
       END AS situacion
  FROM docs
 ORDER BY modulo, fecha, id;

-- 2) Resumen por módulo y estado (ejecutar por separado si pgAdmin muestra solo la última grilla)
-- WITH ... (repetir el CTE de arriba) ...
-- SELECT modulo, COALESCE(estado,'(sin estado)') AS estado, eliminado, COUNT(*) AS documentos, SUM(total) AS total
--   FROM docs GROUP BY modulo, estado, eliminado ORDER BY modulo, estado;
