-- =====================================================================================
-- Diagnóstico (SOLO LECTURA): de dónde sale la diferencia entre el saldo de bodega
-- (productos_bodegas.stock_actual) y el kardex, por tipo de documento.
--
-- Uso: después de la consulta 3 de 20261027_kardex_vivo_documentos_eliminados.sql. Cambiar
-- el RUC en la primera línea del WITH. Muestra, por producto/bodega desfasado:
--   - el kardex del ambiente actual (el que usa el sistema) y el del OTRO ambiente;
--   - cuánto aporta cada referencia_tipo (factura, compra, migracion, consignación, …).
-- Si saldo_bodega ≈ kardex_todos_ambientes, el saldo se armó sumando pruebas + producción.
-- Si la diferencia es exactamente el doble de un movimiento 'migracion', es el cambio de
-- signo de la re-migración, que no recalculaba el saldo.
-- =====================================================================================

WITH emp AS (SELECT id, CAST(tipo_ambiente AS VARCHAR(1)) AS amb FROM empresas WHERE ruc = '1792708389001'),
pb AS (
    SELECT pb.id_producto, pb.id_bodega, pb.stock_actual
    FROM productos_bodegas pb
    WHERE pb.id_empresa = (SELECT id FROM emp) AND pb.eliminado = false
),
k AS (
    SELECT k.id_producto, k.id_bodega,
           SUM(CASE WHEN k.tipo_ambiente = (SELECT amb FROM emp) THEN k.cantidad ELSE 0 END) AS kardex_actual,
           SUM(CASE WHEN k.tipo_ambiente <> (SELECT amb FROM emp) THEN k.cantidad ELSE 0 END) AS kardex_otro_ambiente,
           SUM(k.cantidad) AS kardex_todos_ambientes
    FROM inventario_kardex k
    WHERE k.id_empresa = (SELECT id FROM emp) AND k.eliminado = false
    GROUP BY k.id_producto, k.id_bodega
),
por_tipo AS (
    SELECT k.id_producto, k.id_bodega,
           string_agg(COALESCE(k.referencia_tipo, '(sin tipo)') || ': ' || ROUND(k.suma, 2)::text, ' | ' ORDER BY k.referencia_tipo) AS detalle
    FROM (
        SELECT id_producto, id_bodega, referencia_tipo, SUM(cantidad) AS suma
        FROM inventario_kardex
        WHERE id_empresa = (SELECT id FROM emp) AND eliminado = false
          AND tipo_ambiente = (SELECT amb FROM emp)
        GROUP BY id_producto, id_bodega, referencia_tipo
    ) k
    GROUP BY k.id_producto, k.id_bodega
)
SELECT p.codigo, p.nombre, b.nombre AS bodega,
       pb.stock_actual                         AS saldo_bodega,
       ROUND(COALESCE(k.kardex_actual, 0), 2)  AS kardex_actual,
       ROUND(COALESCE(k.kardex_otro_ambiente, 0), 2)   AS kardex_otro_ambiente,
       ROUND(COALESCE(k.kardex_todos_ambientes, 0), 2) AS kardex_todos_ambientes,
       ROUND(pb.stock_actual - COALESCE(k.kardex_actual, 0), 2) AS diferencia,
       t.detalle AS kardex_por_tipo
FROM pb
JOIN productos p ON p.id = pb.id_producto
JOIN bodegas b   ON b.id = pb.id_bodega
LEFT JOIN k        ON k.id_producto = pb.id_producto AND k.id_bodega = pb.id_bodega
LEFT JOIN por_tipo t ON t.id_producto = pb.id_producto AND t.id_bodega = pb.id_bodega
WHERE ABS(pb.stock_actual - ROUND(COALESCE(k.kardex_actual, 0), 2)) > 0.01
ORDER BY ABS(pb.stock_actual - COALESCE(k.kardex_actual, 0)) DESC
LIMIT 30;
