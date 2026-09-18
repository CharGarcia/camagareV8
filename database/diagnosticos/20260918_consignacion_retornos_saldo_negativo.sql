-- =============================================================================
-- Consignación con saldo negativo en el PDF / Excel / reporte: ¿de dónde sale lo "retornado"?
-- SOLO LECTURA. Caso: consignación 51712, empresa 1792708389001, NUP 50272 (18-09-2026).
--
-- Dos causas posibles de una línea con más retornado que su cantidad:
--   a) Retornos / facturaciones MIGRADOS enlazados a la línea por producto y no por NUP (fue la
--      causa en 51712; se corrige con database/20260918_reenlazar_retornos_facturaciones_migradas.sql).
--   b) El PDF, el Excel y el detalle de la consignación en Reporte de inventarios cuentan como
--      retornado CUALQUIER retorno no eliminado (también Borrador o Anulado), mientras que el saldo
--      real (pestaña Resumen, validaciones de retornos, facturación y cambios) solo cuenta los
--      retornos Emitidos.
-- Las consultas 1-3 muestran cuál es el caso de cada línea de UNA consignación; la 4 lista las
-- líneas negativas de toda la empresa.
--
-- Uso en pgAdmin: ajustar los parámetros de la primera línea de CADA consulta (p) y correr cada
-- consulta por separado (seleccionarla y F5). "consignacion" acepta el id o el número (secuencial).
-- En la 4: correr desde DROP TABLE hasta el resumen (pgAdmin muestra el último SELECT) y después
-- el detalle solo.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- 1) Líneas de la consignación: retornado según el reporte vs según el saldo real
-- ---------------------------------------------------------------------------
WITH p AS (SELECT '1792708389001'::text AS ruc, '51712'::text AS consignacion),
cons AS (
    SELECT cv.id, cv.id_empresa, cv.serie || '-' || cv.secuencial AS numero, cv.estado, cl.nombre AS cliente
      FROM consignaciones_ventas cv
      JOIN empresas e ON e.id = cv.id_empresa
      LEFT JOIN clientes cl ON cl.id = cv.id_cliente
     CROSS JOIN p
     WHERE e.ruc = p.ruc AND cv.eliminado = false
       AND (cv.id::text = p.consignacion
            OR regexp_replace(COALESCE(cv.secuencial, ''), '^0+', '') = regexp_replace(p.consignacion, '^0+', ''))
)
SELECT cons.id AS id_consignacion, cons.numero, cons.estado, cons.cliente,
       cvd.id AS id_linea, pr.codigo, pr.nombre AS producto, cvd.lote, cvd.nup, cvd.cantidad,
       COALESCE(rr.cant, 0) AS retornado_en_reporte,   -- lo que muestra el PDF/Excel/reporte (cualquier estado)
       COALESCE(re.cant, 0) AS retornado_emitido,      -- lo que cuenta el saldo real (solo Emitidos)
       COALESCE(f.cant, 0)  AS facturado,
       COALESCE(c.cant, 0)  AS entregado_en_cambios,
       cvd.cantidad - COALESCE(rr.cant, 0) - COALESCE(f.cant, 0) - COALESCE(c.cant, 0) AS saldo_en_reporte,
       cvd.cantidad - COALESCE(re.cant, 0) - COALESCE(f.cant, 0) - COALESCE(c.cant, 0) AS saldo_real
  FROM cons
  JOIN consignaciones_ventas_detalles cvd ON cvd.id_consignacion = cons.id AND cvd.eliminado = false
  JOIN productos pr ON pr.id = cvd.id_producto
  LEFT JOIN LATERAL (
        SELECT SUM(rcd.cantidad) AS cant
          FROM retornos_cv_detalles rcd
          JOIN retornos_cv r ON r.id = rcd.id_retorno AND r.eliminado = false
         WHERE rcd.id_consignacion = cons.id AND rcd.id_consignacion_detalle = cvd.id
           AND (rcd.eliminado = false OR rcd.eliminado IS NULL)) rr ON true
  LEFT JOIN LATERAL (
        SELECT SUM(rcd.cantidad) AS cant
          FROM retornos_cv_detalles rcd
          JOIN retornos_cv r ON r.id = rcd.id_retorno AND r.eliminado = false AND r.estado = 'Emitida'
         WHERE rcd.id_consignacion_detalle = cvd.id AND rcd.eliminado = false) re ON true
  LEFT JOIN LATERAL (
        SELECT SUM(cfd.cantidad) AS cant
          FROM consignaciones_facturas_detalles cfd
          JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura AND cf.eliminado = false AND cf.estado = 'facturada'
         WHERE cfd.id_consignacion_detalle = cvd.id AND (cfd.eliminado = false OR cfd.eliminado IS NULL)) f ON true
  LEFT JOIN LATERAL (
        -- Entregado a cambio (Cambios de productos Emitida) que no quedó registrado en Facturación
        -- de consignaciones (esos ya cuentan en "facturado"). to_jsonb evita error si la columna
        -- id_cambio_detalle aún no existe en esta base.
        SELECT SUM(cd.cantidad) AS cant
          FROM cambios_producto_cv_detalles cd
          JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio AND cc.eliminado = false AND cc.estado = 'Emitida'
         WHERE cd.tipo_linea = 'entrega' AND cd.origen_tipo = 'CONSIGNACION'
           AND cd.id_origen_detalle = cvd.id AND cd.eliminado = false
           AND NOT EXISTS (SELECT 1
                             FROM consignaciones_facturas_detalles rfd
                             JOIN consignaciones_facturas rf ON rf.id = rfd.id_consignacion_factura
                            WHERE rfd.id_consignacion_detalle = cd.id_origen_detalle
                              AND (to_jsonb(rfd) ->> 'id_cambio_detalle') = cd.id::text
                              AND rfd.eliminado = false AND rf.eliminado = false AND rf.estado = 'facturada')) c ON true
 ORDER BY (cvd.cantidad - COALESCE(rr.cant, 0) - COALESCE(f.cant, 0) - COALESCE(c.cant, 0)) < 0 DESC, cvd.id;

-- ---------------------------------------------------------------------------
-- 2) Cada línea de retorno de la consignación (o del NUP), con su estado y si cuenta
-- ---------------------------------------------------------------------------
WITH p AS (SELECT '1792708389001'::text AS ruc, '51712'::text AS consignacion, '50272'::text AS nup),
cons AS (
    SELECT cv.id, cv.id_empresa
      FROM consignaciones_ventas cv
      JOIN empresas e ON e.id = cv.id_empresa
     CROSS JOIN p
     WHERE e.ruc = p.ruc AND cv.eliminado = false
       AND (cv.id::text = p.consignacion
            OR regexp_replace(COALESCE(cv.secuencial, ''), '^0+', '') = regexp_replace(p.consignacion, '^0+', ''))
)
SELECT r.id AS id_retorno, r.serie || '-' || r.secuencial AS retorno, r.fecha_retorno, r.estado,
       r.eliminado AS retorno_eliminado, r.created_at AS retorno_creado, u.nombre AS creado_por,
       EXISTS (SELECT 1 FROM migracion_mysql_map m
                WHERE m.id_empresa = r.id_empresa AND m.entidad = 'consignaciones_ret' AND m.id_destino = r.id) AS migrado,
       rcd.id AS id_linea_retorno, rcd.id_consignacion, rcd.id_consignacion_detalle,
       rcd.nup AS nup_retorno, cvd.nup AS nup_de_la_linea_enlazada, rcd.cantidad,
       rcd.eliminado AS linea_eliminada,
       (r.eliminado = false AND (rcd.eliminado = false OR rcd.eliminado IS NULL))                   AS cuenta_en_reporte,
       (r.eliminado = false AND rcd.eliminado = false AND r.estado = 'Emitida')                      AS cuenta_en_saldo_real
  FROM cons
 CROSS JOIN p
  JOIN retornos_cv_detalles rcd ON rcd.id_empresa = cons.id_empresa
                               AND (rcd.id_consignacion = cons.id OR TRIM(rcd.nup) = p.nup)
  JOIN retornos_cv r ON r.id = rcd.id_retorno
  LEFT JOIN consignaciones_ventas_detalles cvd ON cvd.id = rcd.id_consignacion_detalle
  LEFT JOIN usuarios u ON u.id = r.created_by
 WHERE TRIM(COALESCE(rcd.nup, '')) = p.nup OR TRIM(COALESCE(cvd.nup, '')) = p.nup
 ORDER BY r.fecha_retorno, r.id, rcd.id;

-- ---------------------------------------------------------------------------
-- 3) Historia del NUP en la empresa: consignaciones, retornos, facturaciones y cambios
-- ---------------------------------------------------------------------------
WITH p AS (SELECT '1792708389001'::text AS ruc, '50272'::text AS nup),
emp AS (SELECT e.id FROM empresas e CROSS JOIN p WHERE e.ruc = p.ruc)
SELECT * FROM (
    SELECT 'Consignación' AS movimiento, cv.serie || '-' || cv.secuencial AS documento, cv.fecha_emision::date AS fecha,
           cv.estado, (cv.eliminado OR cvd.eliminado) AS eliminado, cvd.cantidad,
           cv.id AS id_consignacion, cvd.id AS id_linea_consignacion
      FROM consignaciones_ventas_detalles cvd
      JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
     CROSS JOIN p
     WHERE cv.id_empresa IN (SELECT id FROM emp) AND TRIM(cvd.nup) = p.nup
    UNION ALL
    SELECT 'Retorno', r.serie || '-' || r.secuencial, r.fecha_retorno::date,
           r.estado, (r.eliminado OR COALESCE(rcd.eliminado, false)), rcd.cantidad,
           rcd.id_consignacion, rcd.id_consignacion_detalle
      FROM retornos_cv_detalles rcd
      JOIN retornos_cv r ON r.id = rcd.id_retorno
     CROSS JOIN p
     WHERE r.id_empresa IN (SELECT id FROM emp) AND TRIM(rcd.nup) = p.nup
    UNION ALL
    SELECT 'Facturación CV', COALESCE(cf.numero_factura, cf.serie || '-' || cf.secuencial), cf.fecha_emision::date,
           cf.estado, (cf.eliminado OR COALESCE(cfd.eliminado, false)), cfd.cantidad,
           cfd.id_consignacion, cfd.id_consignacion_detalle
      FROM consignaciones_facturas_detalles cfd
      JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura
     CROSS JOIN p
     WHERE cf.id_empresa IN (SELECT id FROM emp) AND TRIM(cfd.nup) = p.nup
    UNION ALL
    SELECT 'Cambio (' || cd.tipo_linea || ')', cc.serie || '-' || cc.secuencial, cc.fecha_cambio::date,
           cc.estado, (cc.eliminado OR cd.eliminado), cd.cantidad,
           CASE WHEN cd.origen_tipo = 'CONSIGNACION' THEN cd.id_origen END, CASE WHEN cd.origen_tipo = 'CONSIGNACION' THEN cd.id_origen_detalle END
      FROM cambios_producto_cv_detalles cd
      JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio
     CROSS JOIN p
     WHERE cc.id_empresa IN (SELECT id FROM emp) AND TRIM(cd.nup) = p.nup
) h
ORDER BY h.fecha, h.movimiento;

-- ---------------------------------------------------------------------------
-- 4) Líneas con saldo negativo en TODA la empresa (p. ej. después del re-enlace por NUP de
--    database/20260918_reenlazar_retornos_facturaciones_migradas.sql). Deja _cmg_negativos.
--    saldo_real       = cantidad − retornos Emitidos − facturaciones facturadas − entregas en
--                       cambios Emitidos no registradas en Facturación (validaciones, Resumen).
--    saldo_en_reporte = igual, pero con los retornos de CUALQUIER estado (PDF / Excel / reporte).
--    *_de_otro_nup    = líneas de retorno / facturación enlazadas aquí con OTRO NUP: lo que el
--                       re-enlace no pudo mover (NUP inexistente o repetido en la consignación).
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS _cmg_negativos;
CREATE TEMP TABLE _cmg_negativos AS
WITH p AS (SELECT '1792708389001'::text AS ruc),
emp AS (SELECT e.id FROM empresas e JOIN p ON e.ruc = p.ruc),
ret AS (          -- saldo real: solo retornos Emitidos
    SELECT d.id_consignacion_detalle AS id, SUM(d.cantidad) AS c
      FROM retornos_cv_detalles d
      JOIN retornos_cv r ON r.id = d.id_retorno AND r.eliminado = false AND r.estado = 'Emitida'
     WHERE r.id_empresa IN (SELECT id FROM emp) AND d.eliminado = false
     GROUP BY 1
),
ret_rep AS (      -- PDF / Excel / reporte: retornos de cualquier estado (como getRetornadoPorConsignacion)
    SELECT d.id_consignacion, d.id_consignacion_detalle AS id, SUM(d.cantidad) AS c
      FROM retornos_cv_detalles d
      JOIN retornos_cv r ON r.id = d.id_retorno AND r.eliminado = false
     WHERE d.id_empresa IN (SELECT id FROM emp) AND COALESCE(d.eliminado, false) = false
     GROUP BY 1, 2
),
fac AS (
    SELECT d.id_consignacion_detalle AS id, SUM(d.cantidad) AS c
      FROM consignaciones_facturas_detalles d
      JOIN consignaciones_facturas cf ON cf.id = d.id_consignacion_factura AND cf.eliminado = false AND cf.estado = 'facturada'
     WHERE cf.id_empresa IN (SELECT id FROM emp) AND COALESCE(d.eliminado, false) = false
     GROUP BY 1
),
cam AS (
    SELECT cd.id_origen_detalle AS id, SUM(cd.cantidad) AS c
      FROM cambios_producto_cv_detalles cd
      JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio AND cc.eliminado = false AND cc.estado = 'Emitida'
     WHERE cc.id_empresa IN (SELECT id FROM emp)
       AND cd.tipo_linea = 'entrega' AND cd.origen_tipo = 'CONSIGNACION' AND cd.eliminado = false
       AND NOT EXISTS (SELECT 1
                         FROM consignaciones_facturas_detalles rfd
                         JOIN consignaciones_facturas rf ON rf.id = rfd.id_consignacion_factura
                        WHERE rfd.id_consignacion_detalle = cd.id_origen_detalle
                          AND (to_jsonb(rfd) ->> 'id_cambio_detalle') = cd.id::text
                          AND rfd.eliminado = false AND rf.eliminado = false AND rf.estado = 'facturada')
     GROUP BY 1
),
lineas AS (
    SELECT cvd.id AS id_linea, cv.id AS id_consignacion, cv.serie || '-' || cv.secuencial AS consignacion,
           cv.fecha_emision::date AS fecha, cv.estado, cl.nombre AS cliente, pr.codigo AS producto_codigo,
           cvd.nup, cvd.cantidad,
           COALESCE(ret.c, 0)     AS retornado_emitido,
           COALESCE(ret_rep.c, 0) AS retornado_en_reporte,
           COALESCE(fac.c, 0)     AS facturado,
           COALESCE(cam.c, 0)     AS entregado_en_cambios,
           cvd.cantidad - COALESCE(ret.c, 0)     - COALESCE(fac.c, 0) - COALESCE(cam.c, 0) AS saldo_real,
           cvd.cantidad - COALESCE(ret_rep.c, 0) - COALESCE(fac.c, 0) - COALESCE(cam.c, 0) AS saldo_en_reporte
      FROM consignaciones_ventas_detalles cvd
      JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion AND cv.eliminado = false
      LEFT JOIN clientes cl ON cl.id = cv.id_cliente
      LEFT JOIN productos pr ON pr.id = cvd.id_producto
      LEFT JOIN ret ON ret.id = cvd.id
      LEFT JOIN ret_rep ON ret_rep.id = cvd.id AND ret_rep.id_consignacion = cvd.id_consignacion
      LEFT JOIN fac ON fac.id = cvd.id
      LEFT JOIN cam ON cam.id = cvd.id
     WHERE cv.id_empresa IN (SELECT id FROM emp) AND COALESCE(cvd.eliminado, false) = false
)
SELECT l.*,
       (SELECT COUNT(*) FROM retornos_cv_detalles d
          JOIN retornos_cv r ON r.id = d.id_retorno AND r.eliminado = false
         WHERE d.id_consignacion_detalle = l.id_linea AND COALESCE(d.eliminado, false) = false
           AND COALESCE(TRIM(d.nup), '') <> COALESCE(TRIM(l.nup), ''))  AS retornos_de_otro_nup,
       (SELECT COUNT(*) FROM consignaciones_facturas_detalles d
          JOIN consignaciones_facturas cf ON cf.id = d.id_consignacion_factura AND cf.eliminado = false AND cf.estado = 'facturada'
         WHERE d.id_consignacion_detalle = l.id_linea AND COALESCE(d.eliminado, false) = false
           AND COALESCE(TRIM(d.nup), '') <> COALESCE(TRIM(l.nup), ''))  AS facturas_de_otro_nup
  FROM lineas l
 WHERE l.saldo_real < 0 OR l.saldo_en_reporte < 0;

-- Resumen:
SELECT COUNT(*)                                             AS lineas_negativas,
       COUNT(DISTINCT id_consignacion)                      AS consignaciones,
       COUNT(*) FILTER (WHERE saldo_real < 0)               AS negativas_en_saldo_real,
       COUNT(*) FILTER (WHERE saldo_real >= 0)              AS negativas_solo_en_reporte,
       COUNT(*) FILTER (WHERE retornos_de_otro_nup > 0
                           OR facturas_de_otro_nup > 0)     AS con_documentos_de_otro_nup
  FROM _cmg_negativos;

-- Detalle:
SELECT * FROM _cmg_negativos ORDER BY id_consignacion, id_linea;
