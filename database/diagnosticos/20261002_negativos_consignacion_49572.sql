-- ============================================================================
-- DIAGNÓSTICO (SOLO LECTURA) — Negativos de inventario, empresa 1792708389001,
-- consignación 49572
-- ============================================================================
-- Qué responde, en orden:
--   Q1  Configuración que decide si la consignación valida stock
--       (facturacion_inventario TAL CUAL está guardado: ConsignacionVentaService
--        solo valida si el valor es exactamente 'true').
--   Q2  Cuál es la consignación 49572 (se busca por id Y por secuencial).
--   Q3  Sus líneas vigentes vs. sus salidas vigentes en el kardex, por
--       producto/bodega/lote: ¿descontó de más (doble salida)?
--   Q4  Todos sus movimientos de kardex, incluidos los anulados (ediciones).
--   Q5  Documentos que cuelgan de ella (retornos, facturaciones, cambios).
--   Q6  Saldo actual de cada producto/lote de la consignación: kardex por
--       ambiente + caché productos_bodegas. Ver cuál está en negativo.
--   Q7  Saldo corrido del lote: el PRIMER movimiento que lo dejó en negativo
--       (quién, cuándo, qué documento).
--   Q8  Panorama de toda la empresa: cuántos producto/bodega/lote negativos y
--       con qué documento entraron en negativo (¿es solo esta consignación?).
--
-- TOCA DATOS  No. Solo SELECT.
-- USO         pgAdmin → Query Tool. Ejecutar CADA consulta por separado
--             (seleccionarla y F5) y pasar el resultado.
-- ============================================================================


-- Q1 ─ Empresa y configuración de los establecimientos ------------------------
SELECT e.id AS id_empresa, e.ruc, e.tipo_ambiente,
       es.id AS id_establecimiento, es.codigo,
       es.facturacion_inventario::text      AS facturacion_inventario_crudo,
       es.factura_solo_stock_positivo::text AS solo_stock_positivo_crudo,
       es.obligatorio_lotes::text           AS obligatorio_lotes_crudo
  FROM empresas e
  LEFT JOIN empresa_establecimiento es ON es.id_empresa = e.id AND es.eliminado = false
 WHERE e.ruc = '1792708389001'
 ORDER BY es.id;


-- Q2 ─ Cabecera de la consignación 49572 (por id o por secuencial) -----------
SELECT cv.id, cv.serie, cv.secuencial, cv.estado, cv.fecha_emision,
       cv.eliminado, cv.created_at, cv.updated_at,
       CASE WHEN cv.id = 49572 THEN 'coincide por ID' ELSE 'coincide por SECUENCIAL' END AS como
  FROM consignaciones_ventas cv
  JOIN empresas e ON e.id = cv.id_empresa AND e.ruc = '1792708389001'
 WHERE cv.id = 49572
    OR NULLIF(LTRIM(cv.secuencial::text, '0'), '') = '49572';
-- Si salen dos filas (una por id, otra por secuencial), indicar cuál es y
-- cambiar el filtro de las consultas siguientes (CTE "cons").


-- Q3 ─ Líneas vigentes vs. salidas vigentes en kardex (detecta doble descuento)
WITH cons AS (
    SELECT cv.id, cv.id_empresa
      FROM consignaciones_ventas cv
      JOIN empresas e ON e.id = cv.id_empresa AND e.ruc = '1792708389001'
     WHERE cv.id = 49572 OR NULLIF(LTRIM(cv.secuencial::text, '0'), '') = '49572'
),
lineas AS (
    SELECT d.id_producto, d.id_bodega, COALESCE(NULLIF(TRIM(d.lote), ''), '(sin lote)') AS lote,
           SUM(d.cantidad) AS cant_lineas
      FROM consignaciones_ventas_detalles d
      JOIN cons c ON c.id = d.id_consignacion AND c.id_empresa = d.id_empresa
     WHERE d.eliminado = false
     GROUP BY 1, 2, 3
),
kdx AS (
    SELECT k.id_producto, k.id_bodega, COALESCE(NULLIF(TRIM(k.numero_lote), ''), '(sin lote)') AS lote,
           -SUM(k.cantidad) AS cant_kardex
      FROM inventario_kardex k
      JOIN cons c ON c.id = k.referencia_id AND c.id_empresa = k.id_empresa
     WHERE k.referencia_tipo IN ('CONSIGNACION_VENTA', 'EDICION_CONSIGNACION_VENTA')
       AND k.eliminado = false
     GROUP BY 1, 2, 3
)
SELECT p.codigo, p.nombre, b.nombre AS bodega, x.lote,
       COALESCE(l.cant_lineas, 0) AS en_lineas,
       COALESCE(k.cant_kardex, 0) AS salio_en_kardex,
       CASE WHEN COALESCE(l.cant_lineas, 0) = COALESCE(k.cant_kardex, 0) THEN 'ok'
            ELSE 'DESCUADRE  <<<<' END AS nota
  FROM (SELECT id_producto, id_bodega, lote FROM lineas
        UNION SELECT id_producto, id_bodega, lote FROM kdx) x
  LEFT JOIN lineas l USING (id_producto, id_bodega, lote)
  LEFT JOIN kdx    k USING (id_producto, id_bodega, lote)
  JOIN productos p ON p.id = x.id_producto
  LEFT JOIN bodegas b ON b.id = x.id_bodega
 ORDER BY nota DESC, p.nombre, x.lote;


-- Q4 ─ Todos los movimientos de kardex de la consignación (vigentes y anulados)
WITH cons AS (
    SELECT cv.id, cv.id_empresa
      FROM consignaciones_ventas cv
      JOIN empresas e ON e.id = cv.id_empresa AND e.ruc = '1792708389001'
     WHERE cv.id = 49572 OR NULLIF(LTRIM(cv.secuencial::text, '0'), '') = '49572'
)
SELECT k.id, k.fecha_movimiento, k.referencia_tipo, k.tipo_movimiento,
       p.codigo, p.nombre, b.nombre AS bodega, k.numero_lote, k.nup,
       k.cantidad, k.stock_anterior, k.stock_posterior, k.tipo_ambiente,
       k.eliminado, k.deleted_at, u.nombre AS creado_por, k.observaciones
  FROM inventario_kardex k
  JOIN cons c ON c.id = k.referencia_id AND c.id_empresa = k.id_empresa
  JOIN productos p ON p.id = k.id_producto
  LEFT JOIN bodegas  b ON b.id = k.id_bodega
  LEFT JOIN usuarios u ON u.id = k.created_by
 WHERE k.referencia_tipo IN ('CONSIGNACION_VENTA', 'EDICION_CONSIGNACION_VENTA')
 ORDER BY k.fecha_movimiento, k.id;


-- Q5 ─ Documentos que dependen de la consignación ----------------------------
WITH cons AS (
    SELECT cv.id, cv.id_empresa
      FROM consignaciones_ventas cv
      JOIN empresas e ON e.id = cv.id_empresa AND e.ruc = '1792708389001'
     WHERE cv.id = 49572 OR NULLIF(LTRIM(cv.secuencial::text, '0'), '') = '49572'
),
det AS (
    SELECT d.id FROM consignaciones_ventas_detalles d JOIN cons c ON c.id = d.id_consignacion
)
SELECT 'RETORNO' AS tipo, r.id, r.serie || '-' || r.secuencial AS numero, r.estado, r.eliminado,
       SUM(rd.cantidad) AS cantidad
  FROM retornos_cv_detalles rd
  JOIN retornos_cv r ON r.id = rd.id_retorno
 WHERE rd.id_consignacion_detalle IN (SELECT id FROM det)
 GROUP BY r.id, r.serie, r.secuencial, r.estado, r.eliminado
UNION ALL
SELECT 'FACTURACION_CV', cf.id, cf.serie || '-' || cf.secuencial, cf.estado, cf.eliminado,
       SUM(cfd.cantidad)
  FROM consignaciones_facturas_detalles cfd
  JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura
 WHERE cfd.id_consignacion_detalle IN (SELECT id FROM det)
 GROUP BY cf.id, cf.serie, cf.secuencial, cf.estado, cf.eliminado
UNION ALL
SELECT 'CAMBIO_PRODUCTO', cc.id, cc.serie || '-' || cc.secuencial, cc.estado, cc.eliminado,
       SUM(cd.cantidad)
  FROM cambios_producto_cv_detalles cd
  JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio
 WHERE cd.origen_tipo = 'CONSIGNACION'
   AND cd.id_origen_detalle IN (SELECT id FROM det)
 GROUP BY cc.id, cc.serie, cc.secuencial, cc.estado, cc.eliminado
 ORDER BY 1, 2;


-- Q6 ─ Saldo actual de cada producto/lote de la consignación -----------------
--      kardex por ambiente (el operativo solo ve el ambiente de la empresa) y
--      caché productos_bodegas (lo que muestran los listados rápidos).
WITH cons AS (
    SELECT cv.id, cv.id_empresa
      FROM consignaciones_ventas cv
      JOIN empresas e ON e.id = cv.id_empresa AND e.ruc = '1792708389001'
     WHERE cv.id = 49572 OR NULLIF(LTRIM(cv.secuencial::text, '0'), '') = '49572'
),
pb AS (
    SELECT DISTINCT d.id_empresa, d.id_producto, d.id_bodega
      FROM consignaciones_ventas_detalles d JOIN cons c ON c.id = d.id_consignacion
)
SELECT p.codigo, p.nombre, b.nombre AS bodega,
       COALESCE(NULLIF(TRIM(k.numero_lote), ''), '(sin lote)') AS lote,
       k.tipo_ambiente,
       CAST(e.tipo_ambiente AS VARCHAR(1)) AS ambiente_empresa,
       ROUND(SUM(k.cantidad), 2) AS saldo_kardex_lote,
       ROUND(SUM(SUM(k.cantidad)) OVER (PARTITION BY k.id_producto, k.id_bodega, k.tipo_ambiente), 2) AS saldo_kardex_producto,
       MAX(c.stock_actual) AS cache_productos_bodegas,
       CASE WHEN SUM(k.cantidad) < 0 THEN 'NEGATIVO  <<<<' ELSE '' END AS nota
  FROM pb
  JOIN inventario_kardex k ON k.id_empresa = pb.id_empresa AND k.id_producto = pb.id_producto
                          AND k.id_bodega = pb.id_bodega AND k.eliminado = false
  JOIN empresas e  ON e.id = pb.id_empresa
  JOIN productos p ON p.id = pb.id_producto
  LEFT JOIN bodegas b ON b.id = pb.id_bodega
  LEFT JOIN productos_bodegas c ON c.id_empresa = pb.id_empresa AND c.id_producto = pb.id_producto
                               AND c.id_bodega = pb.id_bodega AND c.eliminado = false
 GROUP BY p.codigo, p.nombre, b.nombre, k.id_producto, k.id_bodega,
          COALESCE(NULLIF(TRIM(k.numero_lote), ''), '(sin lote)'), k.tipo_ambiente, e.tipo_ambiente
 ORDER BY nota DESC, p.nombre, lote;


-- Q7 ─ Saldo corrido por lote: primer movimiento que lo dejó en negativo -----
--      Solo los lotes de la consignación que hoy están en negativo.
WITH cons AS (
    SELECT cv.id, cv.id_empresa
      FROM consignaciones_ventas cv
      JOIN empresas e ON e.id = cv.id_empresa AND e.ruc = '1792708389001'
     WHERE cv.id = 49572 OR NULLIF(LTRIM(cv.secuencial::text, '0'), '') = '49572'
),
pbl AS (
    SELECT DISTINCT d.id_empresa, d.id_producto, d.id_bodega,
           COALESCE(NULLIF(TRIM(d.lote), ''), '(sin lote)') AS lote
      FROM consignaciones_ventas_detalles d JOIN cons c ON c.id = d.id_consignacion
),
mov AS (
    SELECT k.*, pbl.lote AS lote_n,
           SUM(k.cantidad) OVER (PARTITION BY k.id_producto, k.id_bodega, pbl.lote, k.tipo_ambiente
                                 ORDER BY k.fecha_movimiento, k.id) AS saldo_corrido,
           SUM(k.cantidad) OVER (PARTITION BY k.id_producto, k.id_bodega, pbl.lote, k.tipo_ambiente) AS saldo_final
      FROM pbl
      JOIN inventario_kardex k ON k.id_empresa = pbl.id_empresa AND k.id_producto = pbl.id_producto
                              AND k.id_bodega = pbl.id_bodega AND k.eliminado = false
                              AND COALESCE(NULLIF(TRIM(k.numero_lote), ''), '(sin lote)') = pbl.lote
)
SELECT DISTINCT ON (m.id_producto, m.id_bodega, m.lote_n, m.tipo_ambiente)
       p.codigo, p.nombre, b.nombre AS bodega, m.lote_n AS lote, m.tipo_ambiente,
       ROUND(m.saldo_final, 2) AS saldo_hoy,
       m.id AS id_kardex_que_lo_dejo_negativo, m.fecha_movimiento,
       m.referencia_tipo, m.referencia_id, m.cantidad,
       ROUND(m.saldo_corrido, 2) AS saldo_tras_ese_movimiento,
       u.nombre AS usuario, m.observaciones
  FROM mov m
  JOIN productos p ON p.id = m.id_producto
  LEFT JOIN bodegas  b ON b.id = m.id_bodega
  LEFT JOIN usuarios u ON u.id = m.created_by
 WHERE m.saldo_final < 0
   AND m.saldo_corrido < 0
 ORDER BY m.id_producto, m.id_bodega, m.lote_n, m.tipo_ambiente, m.fecha_movimiento, m.id;


-- Q8 ─ Panorama de la empresa: lotes en negativo y documento que los hundió --
--      (pocas filas: agrupado por tipo de documento)
WITH emp AS (
    SELECT id, CAST(tipo_ambiente AS VARCHAR(1)) AS amb FROM empresas WHERE ruc = '1792708389001'
),
mov AS (
    SELECT k.id, k.id_producto, k.id_bodega, k.referencia_tipo, k.fecha_movimiento,
           COALESCE(NULLIF(TRIM(k.numero_lote), ''), '(sin lote)') AS lote,
           SUM(k.cantidad) OVER w_corr AS saldo_corrido,
           SUM(k.cantidad) OVER w_tot  AS saldo_final
      FROM inventario_kardex k
      JOIN emp e ON e.id = k.id_empresa AND k.tipo_ambiente = e.amb
     WHERE k.eliminado = false
    WINDOW w_corr AS (PARTITION BY k.id_producto, k.id_bodega, COALESCE(NULLIF(TRIM(k.numero_lote), ''), '(sin lote)')
                      ORDER BY k.fecha_movimiento, k.id),
           w_tot  AS (PARTITION BY k.id_producto, k.id_bodega, COALESCE(NULLIF(TRIM(k.numero_lote), ''), '(sin lote)'))
),
primero AS (
    SELECT DISTINCT ON (id_producto, id_bodega, lote) *
      FROM mov
     WHERE saldo_final < -0.001 AND saldo_corrido < -0.001
     ORDER BY id_producto, id_bodega, lote, fecha_movimiento, id
)
SELECT referencia_tipo AS documento_que_lo_dejo_negativo,
       COUNT(*)                         AS lotes_negativos,
       ROUND(SUM(saldo_final), 2)       AS unidades_negativas,
       MIN(fecha_movimiento)::date      AS desde,
       MAX(fecha_movimiento)::date      AS hasta
  FROM primero
 GROUP BY referencia_tipo
 ORDER BY lotes_negativos DESC;
