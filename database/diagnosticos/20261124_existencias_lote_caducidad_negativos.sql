-- Diagnóstico: por qué "Lote + caducidad" (Reporte de Inventarios > Existencias) muestra stock negativo.
-- Solo lectura. Reemplazar __RUC__ por el RUC de la empresa y ejecutar en pgAdmin.
-- Clasifica cada fila negativa (kardex, sin consignado) en:
--   1. se arregla tratando vacío / guion / NULL como "sin dato" (problema de cómo se agrupa)
--   2. el lote completo cuadra, pero la salida quedó con otro NUP o caducidad que la entrada
--   3. el lote completo está negativo (salió más de lo que entró: lote sin entrada o mal escrito)

WITH k AS (
    SELECT k.id_producto, k.id_bodega,
           k.numero_lote, k.nup, k.fecha_caducidad, k.cantidad,
           NULLIF(NULLIF(TRIM(k.numero_lote), ''), '-') AS lote_n,
           NULLIF(NULLIF(TRIM(k.nup), ''), '-')         AS nup_n
    FROM inventario_kardex k
    JOIN empresas e ON e.id = k.id_empresa
    WHERE e.ruc = '__RUC__' AND e.eliminado = false AND k.eliminado = false
),
fila AS (      -- lo que muestra "Lote + caducidad" (solo kardex, sin consignado)
    SELECT id_producto, id_bodega, numero_lote, nup, fecha_caducidad,
           MAX(lote_n) AS lote_n, MAX(nup_n) AS nup_n, SUM(cantidad) AS stock
    FROM k GROUP BY 1, 2, 3, 4, 5
),
normalizado AS (   -- igual, pero tratando '' y '-' como "sin dato"
    SELECT id_producto, id_bodega, lote_n, nup_n, fecha_caducidad, SUM(cantidad) AS stock
    FROM k GROUP BY 1, 2, 3, 4, 5
),
por_lote AS (      -- "Por lotes": sin NUP ni caducidad
    SELECT id_producto, id_bodega, lote_n, SUM(cantidad) AS stock
    FROM k GROUP BY 1, 2, 3
)
SELECT CASE
         WHEN n.stock >= 0 THEN '1. Solo por vacio vs guion vs NULL en lote/NUP'
         WHEN l.stock >= 0 THEN '2. El lote cuadra: la salida se registro con otro NUP o caducidad'
         ELSE                   '3. El lote entero esta negativo (salio mas de lo que entro)'
       END AS causa,
       COUNT(*) AS filas_negativas,
       SUM(f.stock) AS unidades
FROM fila f
JOIN normalizado n ON n.id_producto = f.id_producto AND n.id_bodega = f.id_bodega
                  AND n.lote_n IS NOT DISTINCT FROM f.lote_n AND n.nup_n IS NOT DISTINCT FROM f.nup_n
                  AND n.fecha_caducidad IS NOT DISTINCT FROM f.fecha_caducidad
JOIN por_lote l    ON l.id_producto = f.id_producto AND l.id_bodega = f.id_bodega
                  AND l.lote_n IS NOT DISTINCT FROM f.lote_n
WHERE f.stock < 0
GROUP BY 1
ORDER BY 1;
