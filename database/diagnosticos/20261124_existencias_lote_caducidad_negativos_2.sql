-- Diagnóstico 2 (sigue a 20261124_existencias_lote_caducidad_negativos.sql).
-- Para las filas negativas de "Lote + caducidad" cuyo lote completo SÍ cuadra (causa 2):
-- qué dato difiere entre la salida y la entrada del mismo lote (NUP, caducidad o ambos),
-- si cada lado lo tiene lleno o vacío, y qué módulos registraron esas salidas.
-- Solo lectura. Reemplazar __RUC__ por el RUC de la empresa y ejecutar en pgAdmin.

WITH k AS (
    SELECT k.id_producto, k.id_bodega, k.fecha_caducidad, k.cantidad, k.referencia_tipo,
           NULLIF(NULLIF(TRIM(k.numero_lote), ''), '-') AS lote_n,
           NULLIF(NULLIF(TRIM(k.nup), ''), '-')         AS nup_n
    FROM inventario_kardex k
    JOIN empresas e ON e.id = k.id_empresa
    WHERE e.ruc = '__RUC__' AND e.eliminado = false AND k.eliminado = false
),
fila AS (
    SELECT id_producto, id_bodega, lote_n, nup_n, fecha_caducidad, SUM(cantidad) AS stock,
           STRING_AGG(DISTINCT referencia_tipo, ', ' ORDER BY referencia_tipo)
               FILTER (WHERE cantidad < 0) AS origenes_salida
    FROM k GROUP BY 1, 2, 3, 4, 5
),
por_lote AS (
    SELECT id_producto, id_bodega, lote_n, SUM(cantidad) AS stock
    FROM k GROUP BY 1, 2, 3
),
negativas AS (
    SELECT f.*
    FROM fila f
    JOIN por_lote l ON l.id_producto = f.id_producto AND l.id_bodega = f.id_bodega
                   AND l.lote_n IS NOT DISTINCT FROM f.lote_n
    WHERE f.stock < 0 AND l.stock >= 0
)
SELECT CASE
         WHEN p.id_producto IS NULL THEN 'sin fila positiva en el lote'
         WHEN n.nup_n IS NOT DISTINCT FROM p.nup_n THEN 'caducidad distinta'
         WHEN n.fecha_caducidad IS NOT DISTINCT FROM p.fecha_caducidad THEN 'NUP distinto'
         ELSE 'NUP y caducidad distintos'
       END AS que_difiere,
       CASE WHEN n.nup_n IS NULL THEN 'sin NUP' ELSE 'con NUP' END            AS salida_nup,
       CASE WHEN p.nup_n IS NULL THEN 'sin NUP' ELSE 'con NUP' END            AS entrada_nup,
       CASE WHEN n.fecha_caducidad IS NULL THEN 'sin cad.' ELSE 'con cad.' END AS salida_cad,
       CASE WHEN p.fecha_caducidad IS NULL THEN 'sin cad.' ELSE 'con cad.' END AS entrada_cad,
       n.origenes_salida,
       COUNT(*)     AS filas_negativas,
       SUM(n.stock) AS unidades
FROM negativas n
LEFT JOIN LATERAL (            -- la fila positiva más grande del mismo lote
    SELECT f2.*
    FROM fila f2
    WHERE f2.id_producto = n.id_producto AND f2.id_bodega = n.id_bodega
      AND f2.lote_n IS NOT DISTINCT FROM n.lote_n AND f2.stock > 0
    ORDER BY f2.stock DESC
    LIMIT 1
) p ON true
GROUP BY 1, 2, 3, 4, 5, 6
ORDER BY filas_negativas DESC
LIMIT 40;
