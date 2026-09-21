-- ============================================================================
-- DIAGNÓSTICO (SOLO LECTURA): el lote no aparece en Transferencias de Inventario
-- ============================================================================
-- CASO        Empresa RUC 1792708389001
--             Producto "IMPLANTE CM 3,8X11,5MM", lote Y110585689
--             El Reporte de Inventarios muestra 4 unidades, pero el selector de
--             lotes del modal de Transferencias no ofrece ese lote (ofrece otro).
--
-- HIPÓTESIS   Las dos pantallas NO leen el kardex con el mismo filtro:
--               · Reporte  > Existencias : NO filtra inventario_kardex.tipo_ambiente
--               · Transferencias         : SÍ filtra tipo_ambiente = el de la empresa
--             Si las filas de ese lote quedaron con un tipo_ambiente distinto al de
--             la empresa (DEFAULT '1' en una empresa de producción '2'), el reporte
--             las suma y Transferencias no las ve.
--             Hipótesis alternativa: el lote está en OTRA bodega que la de origen.
--
-- OJO         empresas es única por (ruc, establecimiento): un mismo RUC puede
--             tener varias filas. Por eso todas las consultas devuelven id_empresa,
--             para poder quedarse con la empresa en la que se está trabajando.
--
-- TOCA DATOS  No. Son ocho SELECT. Reversible por definición (no cambia nada).
-- USO         Pegar todo en el Query Tool de pgAdmin y ejecutar (F5).
--             Devuelve 8 resultados; revisar la pestaña de cada uno.
-- ============================================================================

-- ── 0) Empresa: id y ambiente configurado ───────────────────────────────────
SELECT e.id            AS id_empresa,
       e.ruc,
       e.nombre,
       e.establecimiento,
       e.tipo_ambiente AS ambiente_empresa,   -- 1 = pruebas, 2 = produccion
       e.estado,
       e.eliminado
  FROM empresas e
 WHERE e.ruc = '1792708389001'
 ORDER BY e.id;

-- ── 1) Producto: id y código ────────────────────────────────────────────────
SELECT p.id AS id_producto, p.id_empresa, p.codigo, p.nombre,
       p.inventariable, p.eliminado
  FROM productos p
  JOIN empresas e ON e.id = p.id_empresa
 WHERE e.ruc = '1792708389001'
   AND p.nombre ILIKE '%IMPLANTE CM 3,8X11,5%'
 ORDER BY p.id_empresa, p.eliminado, p.nombre;

-- ── 2) CLAVE: kardex del producto, abierto por bodega + lote + tipo_ambiente ─
--    Esta es la consulta que responde la pregunta. Si la fila del lote Y110585689
--    sale con un "ambiente_kardex" distinto de "ambiente_empresa" (punto 0),
--    la causa es tipo_ambiente. Si sale con el ambiente correcto pero en una
--    bodega distinta a la de origen de la transferencia, la causa es la bodega.
SELECT e.id                                      AS id_empresa,
       b.id                                      AS id_bodega,
       b.nombre                                  AS bodega,
       COALESCE(k.numero_lote, '(NULL)')         AS numero_lote,
       '[' || COALESCE(k.numero_lote, '') || ']' AS lote_delimitado, -- delata espacios
       LENGTH(k.numero_lote)                     AS largo_lote,
       k.tipo_ambiente                           AS ambiente_kardex,
       CAST(e.tipo_ambiente AS VARCHAR(1))       AS ambiente_empresa,
       CASE WHEN k.tipo_ambiente IS NOT DISTINCT FROM CAST(e.tipo_ambiente AS VARCHAR(1))
            THEN 'SI lo ve Transferencias'
            ELSE 'NO lo ve Transferencias  <<<<'
       END                                       AS visible_en_transferencias,
       COUNT(*)                                  AS movimientos,
       ROUND(SUM(k.cantidad), 2)                 AS saldo
  FROM inventario_kardex k
  JOIN empresas e  ON e.id = k.id_empresa
  JOIN productos p ON p.id = k.id_producto AND p.id_empresa = k.id_empresa
  JOIN bodegas b   ON b.id = k.id_bodega
 WHERE e.ruc = '1792708389001'
   AND p.nombre ILIKE '%IMPLANTE CM 3,8X11,5%'
   AND k.eliminado = false
 GROUP BY e.id, b.id, b.nombre, k.numero_lote, k.tipo_ambiente, e.tipo_ambiente
 ORDER BY e.id, b.nombre, k.numero_lote;

-- ── 3) Lo que devuelve HOY el selector de Transferencias (getLotesDisponibles)
--    Mismo SQL del repositorio, abierto por bodega. Lo que salga aquí es
--    literalmente lo que ve el usuario en el desplegable "Seleccione el lote...".
SELECT e.id                                AS id_empresa,
       b.nombre                            AS bodega,
       COALESCE(k.numero_lote, 'sin_lote') AS numero_lote,
       MAX(k.fecha_caducidad)              AS fecha_caducidad,
       ROUND(SUM(k.cantidad), 2)           AS stock_lote
  FROM inventario_kardex k
  JOIN empresas e  ON e.id = k.id_empresa
  JOIN productos p ON p.id = k.id_producto AND p.id_empresa = k.id_empresa
  JOIN bodegas b   ON b.id = k.id_bodega
 WHERE e.ruc = '1792708389001'
   AND p.nombre ILIKE '%IMPLANTE CM 3,8X11,5%'
   AND k.eliminado = false
   AND k.tipo_ambiente = CAST(e.tipo_ambiente AS VARCHAR(1))
 GROUP BY e.id, b.nombre, COALESCE(k.numero_lote, 'sin_lote')
HAVING ROUND(SUM(k.cantidad), 2) > 0
 ORDER BY e.id, b.nombre, MAX(k.fecha_caducidad) ASC NULLS LAST, numero_lote ASC;

-- ── 4) Lo que devolvería SIN el filtro de ambiente (lo que muestra el Reporte)
--    La diferencia entre el punto 3 y el punto 4 es exactamente el stock que el
--    reporte enseña y que Transferencias no deja mover.
SELECT e.id                                AS id_empresa,
       b.nombre                            AS bodega,
       COALESCE(k.numero_lote, 'sin_lote') AS numero_lote,
       MAX(k.fecha_caducidad)              AS fecha_caducidad,
       ROUND(SUM(k.cantidad), 2)           AS stock_lote
  FROM inventario_kardex k
  JOIN empresas e  ON e.id = k.id_empresa
  JOIN productos p ON p.id = k.id_producto AND p.id_empresa = k.id_empresa
  JOIN bodegas b   ON b.id = k.id_bodega
 WHERE e.ruc = '1792708389001'
   AND p.nombre ILIKE '%IMPLANTE CM 3,8X11,5%'
   AND k.eliminado = false
 GROUP BY e.id, b.nombre, COALESCE(k.numero_lote, 'sin_lote')
HAVING ROUND(SUM(k.cantidad), 2) > 0
 ORDER BY e.id, b.nombre, MAX(k.fecha_caducidad) ASC NULLS LAST, numero_lote ASC;

-- ── 5) Movimientos crudos del lote Y110585689 (de dónde salieron esas 4) ─────
SELECT k.id, k.id_empresa, k.fecha_movimiento, b.nombre AS bodega,
       k.tipo_movimiento, k.referencia_tipo, k.referencia_id, k.cantidad,
       k.numero_lote, k.nup, k.fecha_caducidad,
       k.tipo_ambiente, k.created_at, k.created_by
  FROM inventario_kardex k
  JOIN empresas e  ON e.id = k.id_empresa
  JOIN productos p ON p.id = k.id_producto AND p.id_empresa = k.id_empresa
  JOIN bodegas b   ON b.id = k.id_bodega
 WHERE e.ruc = '1792708389001'
   AND p.nombre ILIKE '%IMPLANTE CM 3,8X11,5%'
   AND k.eliminado = false
   AND k.numero_lote ILIKE '%Y110585689%'
 ORDER BY k.id_empresa, k.fecha_movimiento, k.id;

-- ── 6) Caché de stock (productos_bodegas) vs. kardex ────────────────────────
--    productos_bodegas NO tiene tipo_ambiente y es la fuente del stock TOTAL de
--    la pestaña Existencias. Si difiere del kardex, hay además desincronización
--    de caché (problema distinto al del ambiente).
SELECT e.id                                     AS id_empresa,
       b.nombre                                 AS bodega,
       pb.stock_actual                          AS stock_cache,
       ROUND(COALESCE(kk.saldo_ambiente, 0), 2) AS kardex_ambiente_empresa,
       ROUND(COALESCE(kk.saldo_todos, 0), 2)    AS kardex_todos_los_ambientes
  FROM productos_bodegas pb
  JOIN empresas e  ON e.id = pb.id_empresa
  JOIN productos p ON p.id = pb.id_producto AND p.id_empresa = pb.id_empresa
  JOIN bodegas b   ON b.id = pb.id_bodega
  LEFT JOIN LATERAL (
        SELECT SUM(k.cantidad) FILTER (
                   WHERE k.tipo_ambiente = CAST(e.tipo_ambiente AS VARCHAR(1))
               ) AS saldo_ambiente,
               SUM(k.cantidad) AS saldo_todos
          FROM inventario_kardex k
         WHERE k.id_empresa  = pb.id_empresa
           AND k.id_producto = pb.id_producto
           AND k.id_bodega   = pb.id_bodega
           AND k.eliminado   = false
  ) kk ON true
 WHERE e.ruc = '1792708389001'
   AND p.nombre ILIKE '%IMPLANTE CM 3,8X11,5%'
 ORDER BY e.id, b.nombre;

-- ── 7) Alcance del problema en TODA la empresa (cuántas filas desalineadas) ──
SELECT k.id_empresa,
       e.ruc,
       CAST(e.tipo_ambiente AS VARCHAR(1)) AS ambiente_empresa,
       k.tipo_ambiente                     AS ambiente_kardex,
       COUNT(*)                            AS filas,
       COUNT(DISTINCT k.id_producto)       AS productos_afectados,
       MIN(k.fecha_movimiento)             AS desde,
       MAX(k.fecha_movimiento)             AS hasta
  FROM inventario_kardex k
  JOIN empresas e ON e.id = k.id_empresa
 WHERE e.ruc = '1792708389001'
   AND k.eliminado = false
 GROUP BY k.id_empresa, e.ruc, e.tipo_ambiente, k.tipo_ambiente
 ORDER BY k.id_empresa, k.tipo_ambiente;

-- ── 8) ¿Esas 4 son CONSIGNACIÓN y no stock en bodega? ───────────────────────
--    El Reporte > Existencias tiene TRES columnas: "Consignación", "Stock" y
--    "Stock Total" (= Stock + Consignación). Lo consignado está en poder del
--    cliente, NO en la bodega, así que Transferencias no puede moverlo y no lo
--    ofrece en el selector de lotes. Si el 4 que se vio era la columna
--    "Consignación" o "Stock Total", el saldo real en bodega es otro y no hay
--    ningún bug: solo se leyó la columna equivocada.
SELECT e.id                              AS id_empresa,
       b.nombre                          AS bodega,
       COALESCE(cvd.lote, '(sin lote)')  AS lote,
       cvd.nup,
       ROUND(SUM(cvd.cantidad), 2)       AS entregado_en_consignacion
  FROM consignaciones_ventas_detalles cvd
  JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
  JOIN empresas e  ON e.id = cvd.id_empresa
  JOIN productos p ON p.id = cvd.id_producto AND p.id_empresa = cvd.id_empresa
  JOIN bodegas b   ON b.id = cvd.id_bodega
 WHERE e.ruc = '1792708389001'
   AND p.nombre ILIKE '%IMPLANTE CM 3,8X11,5%'
   AND cvd.eliminado = false
   AND cv.eliminado  = false
 GROUP BY e.id, b.nombre, cvd.lote, cvd.nup
 ORDER BY e.id, b.nombre, cvd.lote;
