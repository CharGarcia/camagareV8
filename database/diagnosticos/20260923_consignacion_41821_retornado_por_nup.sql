-- =============================================================================
-- Reporte de inventarios › Consignaciones: línea con saldo negativo (retornado de más).
-- ¿Los retornos / facturaciones / cambios están enlazados a la línea correcta (por NUP + lote)?
-- SOLO LECTURA. Caso: empresa 1792708389001, consignación 41821,
-- producto "Anillo para Fijador de Guia 4,00mm", lote X030484385 (23-09-2026).
--
-- Cómo cuenta el reporte: cada retorno / facturación / cambio suma a la línea que dice su
-- columna id_consignacion_detalle (retornos_cv_detalles, consignaciones_facturas_detalles,
-- cambios_producto_cv_detalles.id_origen_detalle). NO vuelve a cruzar por NUP ni por lote.
-- Si varios retornos de NUP distintos apuntan a UNA sola línea, esa línea queda negativa y
-- las demás (las de esos NUP) quedan con saldo de más.
--
-- Uso en pgAdmin: ajustar la línea "p" de CADA consulta y correrlas por separado
-- (seleccionar la consulta y F5). "consignacion" acepta el id o el secuencial.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- 1) Líneas de la consignación para el producto: lo que el reporte cuenta (enlace por
--    id_consignacion_detalle) frente a lo que le correspondería por NUP + lote.
--    Si retornado_enlazado ≠ retornado_por_nup, la hipótesis queda confirmada.
-- ---------------------------------------------------------------------------
WITH p AS (SELECT '1792708389001'::text AS ruc, '41821'::text AS consignacion,
                  'Anillo para Fijador de Guia 4,00'::text AS producto, 'X030484385'::text AS lote),
cons AS (
    SELECT cv.id, cv.id_empresa, cv.serie || '-' || cv.secuencial AS numero, cv.estado
      FROM consignaciones_ventas cv
      JOIN empresas e ON e.id = cv.id_empresa
     CROSS JOIN p
     WHERE e.ruc = p.ruc AND cv.eliminado = false
       AND (cv.id::text = p.consignacion
            OR regexp_replace(COALESCE(cv.secuencial, ''), '^0+', '') = regexp_replace(p.consignacion, '^0+', ''))
),
lin AS (
    SELECT cons.id AS id_consignacion, cons.numero, cons.estado, cvd.id AS id_linea,
           cvd.id_producto, pr.nombre AS producto, cvd.lote, cvd.nup, cvd.cantidad
      FROM cons
      JOIN consignaciones_ventas_detalles cvd ON cvd.id_consignacion = cons.id AND cvd.eliminado = false
      JOIN productos pr ON pr.id = cvd.id_producto
     CROSS JOIN p
     WHERE pr.nombre ILIKE '%' || p.producto || '%'
)
SELECT lin.numero, lin.estado, lin.id_linea, lin.producto, lin.lote, lin.nup, lin.cantidad,
       COALESCE(re.c, 0)  AS retornado_enlazado,      -- lo que muestra el reporte
       COALESCE(rn.c, 0)  AS retornado_por_nup,       -- retornos Emitidos de esta consignación con el mismo NUP + lote
       COALESCE(fe.c, 0)  AS facturado_enlazado,
       COALESCE(fn.c, 0)  AS facturado_por_nup,
       COALESCE(ce.c, 0)  AS cambiado_enlazado,
       lin.cantidad - COALESCE(re.c, 0) - COALESCE(fe.c, 0) - COALESCE(ce.c, 0) AS saldo_en_reporte,
       lin.cantidad - COALESCE(rn.c, 0) - COALESCE(fn.c, 0) - COALESCE(ce.c, 0) AS saldo_si_fuera_por_nup,
       (UPPER(TRIM(COALESCE(lin.lote, ''))) = UPPER(TRIM(p.lote))) AS es_el_lote_consultado
  FROM lin
 CROSS JOIN p
  LEFT JOIN LATERAL (
        SELECT SUM(d.cantidad) c
          FROM retornos_cv_detalles d
          JOIN retornos_cv r ON r.id = d.id_retorno AND r.eliminado = false AND r.estado = 'Emitida'
         WHERE d.id_consignacion_detalle = lin.id_linea AND d.eliminado = false) re ON true
  LEFT JOIN LATERAL (
        SELECT SUM(d.cantidad) c
          FROM retornos_cv_detalles d
          JOIN retornos_cv r ON r.id = d.id_retorno AND r.eliminado = false AND r.estado = 'Emitida'
         WHERE d.id_consignacion = lin.id_consignacion AND d.id_producto = lin.id_producto
           AND d.eliminado = false
           AND UPPER(TRIM(COALESCE(d.nup, '')))  = UPPER(TRIM(COALESCE(lin.nup, '')))
           AND UPPER(TRIM(COALESCE(d.lote, ''))) = UPPER(TRIM(COALESCE(lin.lote, '')))) rn ON true
  LEFT JOIN LATERAL (
        SELECT SUM(d.cantidad) c
          FROM consignaciones_facturas_detalles d
          JOIN consignaciones_facturas cf ON cf.id = d.id_consignacion_factura AND cf.eliminado = false AND cf.estado = 'facturada'
         WHERE d.id_consignacion_detalle = lin.id_linea AND COALESCE(d.eliminado, false) = false) fe ON true
  LEFT JOIN LATERAL (
        SELECT SUM(d.cantidad) c
          FROM consignaciones_facturas_detalles d
          JOIN consignaciones_facturas cf ON cf.id = d.id_consignacion_factura AND cf.eliminado = false AND cf.estado = 'facturada'
          JOIN consignaciones_ventas_detalles x ON x.id = d.id_consignacion_detalle
         WHERE x.id_consignacion = lin.id_consignacion AND x.id_producto = lin.id_producto
           AND COALESCE(d.eliminado, false) = false
           AND UPPER(TRIM(COALESCE(d.nup, ''))) = UPPER(TRIM(COALESCE(lin.nup, '')))
           AND UPPER(TRIM(COALESCE(to_jsonb(d) ->> 'lote', lin.lote, ''))) = UPPER(TRIM(COALESCE(lin.lote, '')))) fn ON true
  LEFT JOIN LATERAL (
        SELECT SUM(cd.cantidad) c
          FROM cambios_producto_cv_detalles cd
          JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio AND cc.eliminado = false AND cc.estado = 'Emitida'
         WHERE cd.tipo_linea = 'entrega' AND cd.origen_tipo = 'CONSIGNACION'
           AND cd.id_origen_detalle = lin.id_linea AND cd.eliminado = false) ce ON true
 ORDER BY es_el_lote_consultado DESC, saldo_en_reporte, lin.nup, lin.id_linea;

-- ---------------------------------------------------------------------------
-- 2) Cada retorno / facturación / cambio enlazado a esas líneas, con el NUP y lote que
--    trae el documento frente a los de la línea a la que quedó enlazado.
--    nup_coincide = false  → quedó enlazado a una línea de OTRO NUP (el problema).
--    linea_correcta        → id de la línea de la misma consignación con su NUP + lote
--                            (NULL = no existe; "varias" = NUP repetido en la consignación).
-- ---------------------------------------------------------------------------
WITH p AS (SELECT '1792708389001'::text AS ruc, '41821'::text AS consignacion,
                  'Anillo para Fijador de Guia 4,00'::text AS producto),
cons AS (
    SELECT cv.id, cv.id_empresa
      FROM consignaciones_ventas cv
      JOIN empresas e ON e.id = cv.id_empresa
     CROSS JOIN p
     WHERE e.ruc = p.ruc AND cv.eliminado = false
       AND (cv.id::text = p.consignacion
            OR regexp_replace(COALESCE(cv.secuencial, ''), '^0+', '') = regexp_replace(p.consignacion, '^0+', ''))
),
lin AS (
    SELECT cvd.*
      FROM cons
      JOIN consignaciones_ventas_detalles cvd ON cvd.id_consignacion = cons.id AND cvd.eliminado = false
      JOIN productos pr ON pr.id = cvd.id_producto
     CROSS JOIN p
     WHERE pr.nombre ILIKE '%' || p.producto || '%'
),
mov AS (
    SELECT 'Retorno' AS tipo, r.serie || '-' || r.secuencial AS documento, r.fecha_retorno::date AS fecha,
           r.estado, EXISTS (SELECT 1 FROM migracion_mysql_map m
                              WHERE m.id_empresa = r.id_empresa AND m.entidad = 'consignaciones_ret'
                                AND m.id_destino = r.id) AS migrado,
           d.id AS id_linea_doc, d.id_consignacion_detalle AS id_linea_enlazada,
           d.nup AS nup_doc, d.lote AS lote_doc, d.cantidad
      FROM retornos_cv_detalles d
      JOIN retornos_cv r ON r.id = d.id_retorno AND r.eliminado = false
     WHERE d.eliminado = false
       AND (d.id_consignacion_detalle IN (SELECT id FROM lin)
            OR (d.id_consignacion IN (SELECT id FROM cons) AND d.id_producto IN (SELECT id_producto FROM lin)))
    UNION ALL
    SELECT 'Facturación CV', COALESCE(cf.numero_factura, cf.serie || '-' || cf.secuencial), cf.fecha_emision::date,
           cf.estado, EXISTS (SELECT 1 FROM migracion_mysql_map m
                               WHERE m.id_empresa = cf.id_empresa AND m.entidad = 'consignaciones_fact'
                                 AND m.id_destino = cf.id),
           d.id, d.id_consignacion_detalle, d.nup, to_jsonb(d) ->> 'lote', d.cantidad
      FROM consignaciones_facturas_detalles d
      JOIN consignaciones_facturas cf ON cf.id = d.id_consignacion_factura AND cf.eliminado = false
     WHERE COALESCE(d.eliminado, false) = false AND d.id_consignacion_detalle IN (SELECT id FROM lin)
    UNION ALL
    SELECT 'Cambio (entrega)', cc.serie || '-' || cc.secuencial, cc.fecha_cambio::date, cc.estado, NULL,
           cd.id, cd.id_origen_detalle, cd.nup, to_jsonb(cd) ->> 'lote', cd.cantidad
      FROM cambios_producto_cv_detalles cd
      JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio AND cc.eliminado = false
     WHERE cd.eliminado = false AND cd.tipo_linea = 'entrega' AND cd.origen_tipo = 'CONSIGNACION'
       AND cd.id_origen_detalle IN (SELECT id FROM lin)
)
SELECT mov.tipo, mov.documento, mov.fecha, mov.estado, mov.migrado, mov.cantidad,
       mov.nup_doc, mov.lote_doc,
       mov.id_linea_enlazada, le.nup AS nup_linea_enlazada, le.lote AS lote_linea_enlazada,
       (UPPER(TRIM(COALESCE(mov.nup_doc, ''))) = UPPER(TRIM(COALESCE(le.nup, '')))) AS nup_coincide,
       (UPPER(TRIM(COALESCE(mov.lote_doc, le.lote, ''))) = UPPER(TRIM(COALESCE(le.lote, '')))) AS lote_coincide,
       CASE WHEN COUNT(ok.id) = 0 THEN NULL
            WHEN COUNT(ok.id) = 1 THEN MIN(ok.id)::text
            ELSE 'varias: ' || string_agg(ok.id::text, ',') END AS linea_correcta
  FROM mov
  LEFT JOIN consignaciones_ventas_detalles le ON le.id = mov.id_linea_enlazada
  LEFT JOIN lin ok ON ok.id_producto = le.id_producto
                  AND UPPER(TRIM(COALESCE(ok.nup, '')))  = UPPER(TRIM(COALESCE(mov.nup_doc, '')))
                  AND UPPER(TRIM(COALESCE(ok.lote, ''))) = UPPER(TRIM(COALESCE(mov.lote_doc, le.lote, '')))
 GROUP BY mov.tipo, mov.documento, mov.fecha, mov.estado, mov.migrado, mov.cantidad, mov.nup_doc, mov.lote_doc,
          mov.id_linea_enlazada, le.nup, le.lote, mov.id_linea_doc
 ORDER BY nup_coincide, mov.id_linea_enlazada, mov.fecha, mov.id_linea_doc;

-- ---------------------------------------------------------------------------
-- 3) Resumen del lote consultado: cuántas unidades por NUP salieron, volvieron y a qué
--    línea quedaron pegadas. Una fila por NUP de la consignación en ese lote.
-- ---------------------------------------------------------------------------
WITH p AS (SELECT '1792708389001'::text AS ruc, '41821'::text AS consignacion,
                  'Anillo para Fijador de Guia 4,00'::text AS producto, 'X030484385'::text AS lote),
cons AS (
    SELECT cv.id
      FROM consignaciones_ventas cv
      JOIN empresas e ON e.id = cv.id_empresa
     CROSS JOIN p
     WHERE e.ruc = p.ruc AND cv.eliminado = false
       AND (cv.id::text = p.consignacion
            OR regexp_replace(COALESCE(cv.secuencial, ''), '^0+', '') = regexp_replace(p.consignacion, '^0+', ''))
),
lin AS (
    SELECT cvd.*
      FROM cons
      JOIN consignaciones_ventas_detalles cvd ON cvd.id_consignacion = cons.id AND cvd.eliminado = false
      JOIN productos pr ON pr.id = cvd.id_producto
     CROSS JOIN p
     WHERE pr.nombre ILIKE '%' || p.producto || '%'
       AND UPPER(TRIM(COALESCE(cvd.lote, ''))) = UPPER(TRIM(p.lote))
)
SELECT TRIM(d.nup) AS nup_retornado, SUM(d.cantidad) AS unidades_retornadas,
       string_agg(DISTINCT d.id_consignacion_detalle::text, ',') AS enlazadas_a_lineas,
       string_agg(DISTINCT COALESCE(TRIM(le.nup), '(sin NUP)'), ',') AS nup_de_esas_lineas,
       string_agg(DISTINCT r.serie || '-' || r.secuencial, ', ') AS retornos
  FROM retornos_cv_detalles d
  JOIN retornos_cv r ON r.id = d.id_retorno AND r.eliminado = false AND r.estado = 'Emitida'
  LEFT JOIN consignaciones_ventas_detalles le ON le.id = d.id_consignacion_detalle
 WHERE d.eliminado = false
   AND (d.id_consignacion_detalle IN (SELECT id FROM lin)
        OR (d.id_consignacion IN (SELECT id FROM cons) AND d.id_producto IN (SELECT id_producto FROM lin)
            AND UPPER(TRIM(COALESCE(d.lote, ''))) = UPPER(TRIM((SELECT lote FROM p)))))
 GROUP BY TRIM(d.nup)
 ORDER BY 1;
