-- =============================================================================
-- 20260918_kardex_salidas_costo_cero.sql — SOLO LECTURA
-- -----------------------------------------------------------------------------
-- Qué hace  : desglosa la causa «4. Salida con costo 0» de
--             20260918_costo_ventas_sin_asiento.sql: por qué la salida de
--             inventario de la factura se grabó con costo 0 (y por eso su asiento
--             no puede llevar Costo de Ventas).
--             El costo de la venta lo calcula InventarioService al guardar la
--             factura: promedio de las ENTRADAS (tipo 'entrada') de ESA bodega, o la
--             primera/última entrada si el establecimiento usa FIFO/LIFO. Aquí se
--             revisa qué había en el kardex de ese producto y bodega JUSTO ANTES de
--             la venta (id menor = ya existía cuando se calculó el costo).
-- Resultado : una fila por empresa, método de costeo y subcausa, con facturas,
--             salidas, productos, cuántas salidas tenían costo en la ficha del
--             producto (productos.costo_producto) y hasta 5 facturas de ejemplo.
-- Toca datos: NO. Solo SELECT.
-- Parámetros: en el CTE «par» (id de empresa o NULL para todas, y fecha desde).
-- Carga     : revisa el historial de cada producto vendido con costo 0. Segundos.
-- Cómo      : pgAdmin → Query Tool sobre producción → F5 → copiar el resultado.
-- =============================================================================
WITH par AS (
    SELECT NULL::int                   AS id_empresa,   -- ← id de la empresa, o NULL = todas
           (CURRENT_DATE - 60)::date   AS desde         -- ← fecha de emisión desde
),
sal AS (     -- salidas de facturas autorizadas grabadas con costo 0
    SELECT k.id, k.id_empresa, k.id_producto, k.id_bodega, k.stock_anterior,
           v.id AS id_venta, v.id_establecimiento,
           LPAD(v.establecimiento::text, 3, '0') || '-' || LPAD(v.punto_emision::text, 3, '0') || '-'
               || LPAD(v.secuencial::text, 9, '0') AS numero
    FROM inventario_kardex k
    JOIN ventas_cabecera v ON v.id = k.referencia_id AND v.id_empresa = k.id_empresa
    CROSS JOIN par
    WHERE k.referencia_tipo = 'factura_venta' AND k.tipo_movimiento = 'salida' AND k.eliminado = false
      AND COALESCE(k.costo_total, 0) = 0
      AND v.eliminado = false AND v.estado IN ('autorizado', 'contabilizado')
      AND v.fecha_emision >= par.desde
      AND (par.id_empresa IS NULL OR v.id_empresa = par.id_empresa)
),
antes AS (   -- lo que había en ESA bodega antes de la venta
    SELECT s.id,
           COUNT(m.id) FILTER (WHERE m.tipo_movimiento = 'entrada' AND m.costo_total > 0)                      AS entradas_con_costo,
           COUNT(m.id) FILTER (WHERE m.tipo_movimiento = 'entrada' AND COALESCE(m.costo_total, 0) = 0)         AS entradas_sin_costo,
           COUNT(m.id) FILTER (WHERE m.tipo_movimiento = 'transferencia' AND m.cantidad > 0 AND m.costo_total > 0) AS transferencias_con_costo,
           COUNT(m.id) FILTER (WHERE m.tipo_movimiento NOT IN ('entrada', 'salida', 'transferencia') AND m.cantidad > 0) AS otros_ingresos
    FROM sal s
    LEFT JOIN inventario_kardex m ON m.id_empresa = s.id_empresa AND m.id_producto = s.id_producto
                                 AND m.id_bodega = s.id_bodega AND m.eliminado = false AND m.id < s.id
    GROUP BY s.id
),
otra AS (    -- ¿tenía costo en OTRA bodega?
    SELECT s.id, COUNT(m.id) AS con_costo_otra_bodega
    FROM sal s
    LEFT JOIN inventario_kardex m ON m.id_empresa = s.id_empresa AND m.id_producto = s.id_producto
                                 AND m.id_bodega <> s.id_bodega AND m.eliminado = false AND m.id < s.id
                                 AND m.cantidad > 0 AND m.costo_total > 0
    GROUP BY s.id
),
despues AS ( -- ¿entró con costo DESPUÉS de la venta? (la compra se registró tarde)
    SELECT s.id, COUNT(m.id) AS con_costo_despues
    FROM sal s
    LEFT JOIN inventario_kardex m ON m.id_empresa = s.id_empresa AND m.id_producto = s.id_producto
                                 AND m.id_bodega = s.id_bodega AND m.eliminado = false AND m.id > s.id
                                 AND m.cantidad > 0 AND m.costo_total > 0
    GROUP BY s.id
),
det AS (
    SELECT s.id_empresa, s.id_venta, s.id_producto, s.numero,
           COALESCE(ee.metodo_costeo, 'promedio') AS metodo_costeo,
           COALESCE(p.costo_producto, 0) > 0     AS costo_en_ficha,
           CASE
             WHEN EXISTS (
                    SELECT 1 FROM migracion_mysql_map mm
                    WHERE mm.entidad = 'facturas' AND mm.id_destino = s.id_venta AND mm.vinculado IS NOT TRUE)
               THEN '4m. Factura migrada: su salida de kardex vino sin costo'
             WHEN a.entradas_con_costo > 0 AND COALESCE(ee.metodo_costeo, 'promedio') IN ('fifo', 'lifo')
               THEN '4f. FIFO/LIFO: toma la primera/última entrada aunque esa tenga costo 0'
             WHEN a.entradas_con_costo > 0
               THEN '4z. Tenía entradas con costo en esa bodega (revisar a mano)'
             WHEN a.transferencias_con_costo > 0
               THEN '4a. La bodega se abasteció por transferencia (el costo de la venta no cuenta transferencias)'
             WHEN a.entradas_sin_costo > 0 OR a.otros_ingresos > 0
               THEN '4b. Entró a la bodega sin costo (carga, ajuste o saldo inicial en 0)'
             WHEN o.con_costo_otra_bodega > 0
               THEN '4c. Tiene costo en otra bodega, no en la de la venta'
             WHEN d.con_costo_despues > 0
               THEN '4d. Se vendió antes de registrar la compra (la entrada con costo llegó después)'
             ELSE '4e. Nunca entró a inventario con costo (no se registran compras/cargas con costo)'
           END AS subcausa
    FROM sal s
    JOIN antes a   ON a.id = s.id
    JOIN otra o    ON o.id = s.id
    JOIN despues d ON d.id = s.id
    LEFT JOIN productos p ON p.id = s.id_producto
    LEFT JOIN empresa_establecimiento ee ON ee.id = s.id_establecimiento
)
SELECT det.id_empresa,
       e.nombre_comercial,
       det.metodo_costeo,
       det.subcausa,
       COUNT(DISTINCT det.id_venta)                       AS facturas,
       COUNT(*)                                           AS salidas,
       COUNT(DISTINCT det.id_producto)                    AS productos,
       COUNT(*) FILTER (WHERE det.costo_en_ficha)         AS salidas_con_costo_en_ficha,
       (array_agg(DISTINCT det.numero ORDER BY det.numero DESC))[1:5] AS ejemplos
FROM det
LEFT JOIN empresas e ON e.id = det.id_empresa
GROUP BY det.id_empresa, e.nombre_comercial, det.metodo_costeo, det.subcausa
ORDER BY det.id_empresa, det.subcausa;
