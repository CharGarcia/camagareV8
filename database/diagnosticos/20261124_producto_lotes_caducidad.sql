-- Diagnóstico de UN producto: cómo se reparten sus lotes / NUP / caducidades en el kardex.
-- Solo lectura. Empresa 1792708389001, producto "Abutment Mini CM Unitite ...4.8x0.8".
-- (El nombre se busca con comodines porque lleva un símbolo de diámetro que puede no copiarse bien.)
--
-- Una fila por bodega + lote + NUP + caducidad, con:
--   entradas / salidas / saldo               lo que suma el kardex de ese grupo
--   cad_es_fecha_mov                         cuántos movimientos tienen como caducidad su PROPIA
--                                            fecha (caducidad inventada por la migración)
--   origenes                                 qué módulos movieron ese grupo
--   primer_mov / ultimo_mov                  rango de fechas
-- Ordenado por bodega y lote, para ver juntos los grupos de un mismo lote.

WITH prod AS (
    SELECT p.id, p.codigo, p.nombre, p.id_empresa
    FROM productos p
    JOIN empresas e ON e.id = p.id_empresa
    WHERE e.ruc = '1792708389001' AND e.eliminado = false
      AND p.eliminado = false
      AND p.nombre ILIKE 'Abutment Mini CM Unitite%4.8x0.8%'
)
SELECT pr.codigo,
       b.nombre                                        AS bodega,
       k.numero_lote                                   AS lote,
       k.nup,
       TO_CHAR(k.fecha_caducidad, 'DD-MM-YYYY')        AS caducidad,
       SUM(k.cantidad) FILTER (WHERE k.cantidad > 0)   AS entradas,
       -SUM(k.cantidad) FILTER (WHERE k.cantidad < 0)  AS salidas,
       SUM(k.cantidad)                                 AS saldo,
       COUNT(*)                                        AS movimientos,
       COUNT(*) FILTER (WHERE k.fecha_caducidad = k.fecha_movimiento::date) AS cad_es_fecha_mov,
       STRING_AGG(DISTINCT k.referencia_tipo, ', ')    AS origenes,
       TO_CHAR(MIN(k.fecha_movimiento), 'DD-MM-YYYY')  AS primer_mov,
       TO_CHAR(MAX(k.fecha_movimiento), 'DD-MM-YYYY')  AS ultimo_mov
FROM inventario_kardex k
JOIN prod pr   ON pr.id = k.id_producto AND pr.id_empresa = k.id_empresa
JOIN bodegas b ON b.id = k.id_bodega
WHERE k.eliminado = false
GROUP BY pr.codigo, b.nombre, k.numero_lote, k.nup, k.fecha_caducidad
ORDER BY b.nombre, k.numero_lote NULLS LAST, k.fecha_caducidad NULLS LAST, k.nup NULLS FIRST;
