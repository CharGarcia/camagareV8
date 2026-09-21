-- =============================================================================
-- ¿Cuál debe ser la clave de unicidad del NUP en consignaciones de venta?
-- SOLO LECTURA. Mide en los datos reales de la empresa:
--   · si el mismo NUP se usa en productos distintos (clave NUP suelto = inviable),
--   · si el mismo producto repite NUP en lotes distintos (la clave necesita el lote),
--   · cuántos duplicados REALES hay (mismo producto + lote + NUP),
--   · si se puede exigir cantidad = 1 en las líneas con NUP,
--   · si el kardex tiene ENTRADAS con NUP (sin eso no se puede validar la serie contra el stock).
--
-- Uso en pgAdmin: ajustar el `ruc` de la primera línea de cada consulta y correr una por una.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- 1) Resumen: qué clave soportan los datos
-- ---------------------------------------------------------------------------
WITH p AS (SELECT '1792708389001'::text AS ruc),
emp AS (SELECT e.id FROM empresas e JOIN p ON e.ruc = p.ruc),
lin AS (
    SELECT cvd.id, cvd.id_consignacion, cvd.id_producto,
           COALESCE(NULLIF(TRIM(cvd.lote), ''), '(sin lote)') AS lote,
           UPPER(TRIM(cvd.nup)) AS nup, cvd.cantidad
      FROM consignaciones_ventas_detalles cvd
      JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion AND cv.eliminado = false
     WHERE cv.id_empresa IN (SELECT id FROM emp) AND COALESCE(cvd.eliminado, false) = false
       AND COALESCE(TRIM(cvd.nup), '') <> ''
)
SELECT (SELECT COUNT(*) FROM lin)                                                   AS lineas_con_nup,
       (SELECT COUNT(DISTINCT nup) FROM lin)                                        AS nups_distintos,
       -- El mismo NUP en 2+ productos: si sale > 0, la clave NO puede ser el NUP solo
       (SELECT COUNT(*) FROM (SELECT nup FROM lin GROUP BY nup
                               HAVING COUNT(DISTINCT id_producto) > 1) x)            AS nups_en_varios_productos,
       -- El mismo producto repite NUP en lotes distintos: si sale > 0, la clave necesita el lote
       (SELECT COUNT(*) FROM (SELECT id_producto, nup FROM lin GROUP BY 1, 2
                               HAVING COUNT(DISTINCT lote) > 1) x)                   AS nups_en_varios_lotes,
       -- Duplicados REALES: misma unidad física dos veces (producto + lote + NUP)
       (SELECT COUNT(*) FROM (SELECT id_producto, lote, nup FROM lin GROUP BY 1, 2, 3
                               HAVING COUNT(*) > 1) x)                               AS duplicados_reales,
       (SELECT COUNT(*) FROM (SELECT id_consignacion, id_producto, lote, nup FROM lin GROUP BY 1, 2, 3, 4
                               HAVING COUNT(*) > 1) x)                               AS duplicados_en_la_misma_consignacion,
       -- ¿Se puede exigir cantidad = 1 en la línea con NUP?
       (SELECT COUNT(*) FROM lin WHERE cantidad <> 1)                                AS lineas_con_nup_y_cantidad_distinta_de_1,
       (SELECT MAX(cantidad) FROM lin)                                               AS cantidad_maxima_en_linea_con_nup;

-- ---------------------------------------------------------------------------
-- 2) El caso de la pregunta: el mismo NUP en productos distintos
-- ---------------------------------------------------------------------------
WITH p AS (SELECT '1792708389001'::text AS ruc),
emp AS (SELECT e.id FROM empresas e JOIN p ON e.ruc = p.ruc),
lin AS (
    SELECT cv.id AS id_consignacion, cv.serie || '-' || cv.secuencial AS consignacion, cv.fecha_emision::date AS fecha,
           cvd.id_producto, pr.codigo AS producto_codigo, pr.nombre AS producto,
           COALESCE(NULLIF(TRIM(cvd.lote), ''), '(sin lote)') AS lote,
           UPPER(TRIM(cvd.nup)) AS nup, cvd.cantidad
      FROM consignaciones_ventas_detalles cvd
      JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion AND cv.eliminado = false
      LEFT JOIN productos pr ON pr.id = cvd.id_producto
     WHERE cv.id_empresa IN (SELECT id FROM emp) AND COALESCE(cvd.eliminado, false) = false
       AND COALESCE(TRIM(cvd.nup), '') <> ''
),
repetidos AS (SELECT nup FROM lin GROUP BY nup HAVING COUNT(DISTINCT id_producto) > 1)
SELECT l.nup, l.producto_codigo, l.producto, l.lote, l.cantidad, l.consignacion, l.fecha,
       COUNT(*) OVER (PARTITION BY l.nup) AS lineas_con_ese_nup
  FROM lin l JOIN repetidos r ON r.nup = l.nup
 ORDER BY l.nup, l.producto_codigo, l.lote, l.fecha;

-- ---------------------------------------------------------------------------
-- 3) Duplicados reales: la MISMA unidad (producto + lote + NUP) en más de una línea
-- ---------------------------------------------------------------------------
WITH p AS (SELECT '1792708389001'::text AS ruc),
emp AS (SELECT e.id FROM empresas e JOIN p ON e.ruc = p.ruc),
lin AS (
    SELECT cvd.id AS id_linea, cv.id AS id_consignacion, cv.serie || '-' || cv.secuencial AS consignacion,
           cv.fecha_emision::date AS fecha, cl.nombre AS cliente, cvd.id_producto,
           pr.codigo AS producto_codigo, COALESCE(NULLIF(TRIM(cvd.lote), ''), '(sin lote)') AS lote,
           UPPER(TRIM(cvd.nup)) AS nup, cvd.cantidad
      FROM consignaciones_ventas_detalles cvd
      JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion AND cv.eliminado = false
      LEFT JOIN clientes cl ON cl.id = cv.id_cliente
      LEFT JOIN productos pr ON pr.id = cvd.id_producto
     WHERE cv.id_empresa IN (SELECT id FROM emp) AND COALESCE(cvd.eliminado, false) = false
       AND COALESCE(TRIM(cvd.nup), '') <> ''
),
dup AS (SELECT id_producto, lote, nup FROM lin GROUP BY 1, 2, 3 HAVING COUNT(*) > 1)
SELECT l.*,
       (l.id_consignacion = MIN(l.id_consignacion) OVER (PARTITION BY l.id_producto, l.lote, l.nup)) AS es_la_primera
  FROM lin l JOIN dup d ON d.id_producto = l.id_producto AND d.lote = l.lote AND d.nup = l.nup
 ORDER BY l.producto_codigo, l.lote, l.nup, l.fecha, l.id_linea;

-- ---------------------------------------------------------------------------
-- 4) ¿El kardex tiene ENTRADAS con NUP? Sin eso no se puede validar la serie contra el stock
--    (el nivel "elegir el NUP de una lista" y "la serie debe tener saldo" dependen de esto).
-- ---------------------------------------------------------------------------
WITH p AS (SELECT '1792708389001'::text AS ruc),
emp AS (SELECT e.id FROM empresas e JOIN p ON e.ruc = p.ruc)
SELECT COUNT(*)                                                                  AS movimientos_con_nup,
       COUNT(*) FILTER (WHERE cantidad > 0)                                      AS entradas_con_nup,
       COUNT(*) FILTER (WHERE cantidad < 0)                                      AS salidas_con_nup,
       COUNT(DISTINCT UPPER(TRIM(nup))) FILTER (WHERE cantidad > 0)              AS series_que_entraron,
       COUNT(DISTINCT tipo_movimiento) FILTER (WHERE cantidad > 0)               AS tipos_de_entrada,
       MIN(fecha_movimiento::date) FILTER (WHERE cantidad > 0)                   AS primera_entrada,
       MAX(fecha_movimiento::date) FILTER (WHERE cantidad > 0)                   AS ultima_entrada
  FROM inventario_kardex
 WHERE id_empresa IN (SELECT id FROM emp) AND eliminado = false
   AND COALESCE(TRIM(nup), '') <> '';

-- ---------------------------------------------------------------------------
-- 5) Los repetidos ENTRE documentos: ¿mercadería retornada y vuelta a consignar (normal) o la
--    misma serie entregada dos veces a la vez (problema)? Deja _cmg_nup_repetidos.
--    Una línea está ABIERTA si todavía tiene saldo: cantidad − retornos Emitidos − facturaciones
--    facturadas − entregas en cambios Emitidos no registradas en Facturación.
--    Correr desde DROP TABLE hasta el resumen; después el detalle solo.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS _cmg_nup_repetidos;
CREATE TEMP TABLE _cmg_nup_repetidos AS
WITH p AS (SELECT '1792708389001'::text AS ruc),
emp AS (SELECT e.id FROM empresas e JOIN p ON e.ruc = p.ruc),
lin AS (
    SELECT cvd.id AS id_linea, cv.id AS id_consignacion, cv.serie || '-' || cv.secuencial AS consignacion,
           cv.fecha_emision::date AS fecha, cv.estado, cl.nombre AS cliente,
           cvd.id_producto, pr.codigo AS producto_codigo,
           COALESCE(NULLIF(TRIM(cvd.lote), ''), '(sin lote)') AS lote,
           UPPER(TRIM(cvd.nup)) AS nup, cvd.cantidad
      FROM consignaciones_ventas_detalles cvd
      JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion AND cv.eliminado = false
      LEFT JOIN clientes cl ON cl.id = cv.id_cliente
      LEFT JOIN productos pr ON pr.id = cvd.id_producto
     WHERE cv.id_empresa IN (SELECT id FROM emp) AND COALESCE(cvd.eliminado, false) = false
       AND COALESCE(TRIM(cvd.nup), '') <> ''
),
ret AS (
    SELECT d.id_consignacion_detalle AS id, SUM(d.cantidad) AS c
      FROM retornos_cv_detalles d
      JOIN retornos_cv r ON r.id = d.id_retorno AND r.eliminado = false AND r.estado = 'Emitida'
     WHERE r.id_empresa IN (SELECT id FROM emp) AND d.eliminado = false
     GROUP BY 1
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
saldos AS (
    SELECT l.*, l.cantidad - COALESCE(ret.c, 0) - COALESCE(fac.c, 0) - COALESCE(cam.c, 0) AS saldo
      FROM lin l
      LEFT JOIN ret ON ret.id = l.id_linea
      LEFT JOIN fac ON fac.id = l.id_linea
      LEFT JOIN cam ON cam.id = l.id_linea
),
grupos AS (
    SELECT id_producto, lote, nup,
           COUNT(*)                                                      AS lineas,
           COUNT(DISTINCT id_consignacion)                               AS consignaciones,
           COUNT(*) FILTER (WHERE saldo > 0)                             AS lineas_abiertas,
           COUNT(DISTINCT id_consignacion) FILTER (WHERE saldo > 0)      AS consignaciones_abiertas
      FROM saldos
     GROUP BY 1, 2, 3
    HAVING COUNT(*) > 1
)
SELECT s.id_linea, s.id_consignacion, s.consignacion, s.fecha, s.estado, s.cliente,
       s.producto_codigo, s.lote, s.nup, s.cantidad, s.saldo,
       g.lineas, g.consignaciones, g.lineas_abiertas, g.consignaciones_abiertas
  FROM saldos s
  JOIN grupos g ON g.id_producto = s.id_producto AND g.lote = s.lote AND g.nup = s.nup;

-- Resumen:
SELECT COUNT(*)                                                       AS grupos_repetidos,
       COUNT(*) FILTER (WHERE lineas_abiertas >= 2)                   AS grupos_con_2_o_mas_abiertas,
       COUNT(*) FILTER (WHERE consignaciones_abiertas >= 2)           AS abiertas_en_consignaciones_distintas,
       COUNT(*) FILTER (WHERE lineas_abiertas <= 1)                   AS historicos_sin_conflicto
  FROM (SELECT DISTINCT producto_codigo, lote, nup, lineas, lineas_abiertas, consignaciones_abiertas
          FROM _cmg_nup_repetidos) g;

-- Detalle de los conflictos (la misma serie abierta en dos sitios a la vez):
SELECT * FROM _cmg_nup_repetidos
 WHERE lineas_abiertas >= 2
 ORDER BY producto_codigo, lote, nup, fecha, id_linea;

-- ---------------------------------------------------------------------------
-- 6) Los que la regla nueva bloquearía al volver a guardar: el mismo producto + lote + NUP
--    repetido DENTRO de una misma consignación (lo único que se considera error).
-- ---------------------------------------------------------------------------
WITH p AS (SELECT '1792708389001'::text AS ruc),
emp AS (SELECT e.id FROM empresas e JOIN p ON e.ruc = p.ruc),
lin AS (
    SELECT cvd.id AS id_linea, cv.id AS id_consignacion, cv.serie || '-' || cv.secuencial AS consignacion,
           cv.fecha_emision::date AS fecha, cv.estado, cl.nombre AS cliente, cvd.id_producto,
           pr.codigo AS producto_codigo, pr.nombre AS producto,
           COALESCE(NULLIF(TRIM(cvd.lote), ''), '(sin lote)') AS lote,
           UPPER(TRIM(cvd.nup)) AS nup, cvd.nup AS nup_como_esta, cvd.cantidad
      FROM consignaciones_ventas_detalles cvd
      JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion AND cv.eliminado = false
      LEFT JOIN clientes cl ON cl.id = cv.id_cliente
      LEFT JOIN productos pr ON pr.id = cvd.id_producto
     WHERE cv.id_empresa IN (SELECT id FROM emp) AND COALESCE(cvd.eliminado, false) = false
       AND COALESCE(TRIM(cvd.nup), '') <> ''
),
dup AS (SELECT id_consignacion, id_producto, lote, nup FROM lin GROUP BY 1, 2, 3, 4 HAVING COUNT(*) > 1)
SELECT l.*
  FROM lin l
  JOIN dup d ON d.id_consignacion = l.id_consignacion AND d.id_producto = l.id_producto
            AND d.lote = l.lote AND d.nup = l.nup
 ORDER BY l.id_consignacion, l.producto_codigo, l.lote, l.nup, l.id_linea;
