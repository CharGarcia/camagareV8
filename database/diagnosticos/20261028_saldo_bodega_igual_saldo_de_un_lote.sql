-- =====================================================================================
-- Diagnóstico (SOLO LECTURA): ¿el saldo de bodega quedó con el saldo de UN LOTE?
--
-- Hipótesis: con "lotes obligatorios" (empresa_establecimiento.obligatorio_lotes), cada venta
-- validaba el stock del lote y después escribía ESE saldo como saldo de la bodega
-- (InventarioService::registrarSalidaIndividual, corregido el 2026-10-28). Si es así, el saldo
-- de bodega de cada producto desfasado coincide con el saldo del lote de su último movimiento
-- (columna coincide = true).
--
-- Cambiar el RUC en la primera línea del WITH.
-- =====================================================================================

WITH emp AS (SELECT id, CAST(tipo_ambiente AS VARCHAR(1)) AS amb FROM empresas WHERE ruc = '1792708389001'),
desfasados AS (
    SELECT pb.id_producto, pb.id_bodega, pb.stock_actual,
           (SELECT ROUND(COALESCE(SUM(k.cantidad), 0), 2) FROM inventario_kardex k
             WHERE k.id_empresa = pb.id_empresa AND k.id_producto = pb.id_producto AND k.id_bodega = pb.id_bodega
               AND k.eliminado = false AND k.tipo_ambiente = (SELECT amb FROM emp)) AS kardex_total
    FROM productos_bodegas pb
    WHERE pb.id_empresa = (SELECT id FROM emp) AND pb.eliminado = false
),
ultimo AS (
    SELECT d.*, u.id AS id_ultimo, u.referencia_tipo AS ultimo_tipo, u.numero_lote AS ultimo_lote,
           to_char(u.fecha_movimiento, 'DD-MM-YYYY HH24:MI') AS ultimo_fecha
    FROM desfasados d
    LEFT JOIN LATERAL (
        SELECT k.id, k.referencia_tipo, k.numero_lote, k.fecha_movimiento
        FROM inventario_kardex k
        WHERE k.id_empresa = (SELECT id FROM emp) AND k.id_producto = d.id_producto AND k.id_bodega = d.id_bodega
          AND k.eliminado = false AND k.tipo_ambiente = (SELECT amb FROM emp)
        ORDER BY k.id DESC LIMIT 1
    ) u ON true
    WHERE ABS(d.stock_actual - d.kardex_total) > 0.01
)
SELECT p.codigo, b.nombre AS bodega, u.stock_actual AS saldo_bodega, u.kardex_total,
       u.ultimo_tipo, u.ultimo_lote, u.ultimo_fecha,
       (SELECT ROUND(COALESCE(SUM(k.cantidad), 0), 2) FROM inventario_kardex k
         WHERE k.id_empresa = (SELECT id FROM emp) AND k.id_producto = u.id_producto AND k.id_bodega = u.id_bodega
           AND k.eliminado = false AND k.tipo_ambiente = (SELECT amb FROM emp)
           AND COALESCE(NULLIF(k.numero_lote, 'sin_lote'), '') = COALESCE(NULLIF(u.ultimo_lote, 'sin_lote'), '')
           AND k.id <= u.id_ultimo) AS saldo_lote_tras_ultimo,
       ABS(u.stock_actual - (SELECT ROUND(COALESCE(SUM(k.cantidad), 0), 2) FROM inventario_kardex k
         WHERE k.id_empresa = (SELECT id FROM emp) AND k.id_producto = u.id_producto AND k.id_bodega = u.id_bodega
           AND k.eliminado = false AND k.tipo_ambiente = (SELECT amb FROM emp)
           AND COALESCE(NULLIF(k.numero_lote, 'sin_lote'), '') = COALESCE(NULLIF(u.ultimo_lote, 'sin_lote'), '')
           AND k.id <= u.id_ultimo)) < 0.01 AS coincide,
       (SELECT string_agg(DISTINCT CASE WHEN ee.obligatorio_lotes::text IN ('true', 't', '1') THEN 'SI' ELSE 'NO' END, ',')
          FROM empresa_establecimiento ee WHERE ee.id_empresa = (SELECT id FROM emp)) AS lotes_obligatorios
FROM ultimo u
JOIN productos p ON p.id = u.id_producto
JOIN bodegas b   ON b.id = u.id_bodega
ORDER BY coincide, ABS(u.stock_actual - u.kardex_total) DESC;
