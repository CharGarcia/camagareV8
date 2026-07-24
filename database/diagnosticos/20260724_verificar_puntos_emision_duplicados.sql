-- Verifica si las 3 facturas duplicadas (001-102-000000255) usan el MISMO id_punto_emision/
-- id_establecimiento internos, o si en realidad hay puntos de emisión "gemelos" (mismo código
-- visible, distinto id) que dejarían pasar el choque sin violar el índice único.

-- 1) IDs internos de establecimiento/punto de emisión de las 3 facturas
SELECT id, id_cliente, importe_total, establecimiento, punto_emision, secuencial,
       id_establecimiento, id_punto_emision, created_at
FROM ventas_cabecera
WHERE id IN (1955, 1957, 1958);

-- 2) Si el resultado de arriba muestra id_punto_emision o id_establecimiento DISTINTOS entre
--    las 3 filas, confirma la hipótesis. Compáralos contra el catálogo real:
SELECT id, codigo_punto, id_establecimiento, id_empresa, eliminado
FROM empresa_punto_emision
WHERE id_empresa = 8
ORDER BY codigo_punto, id;

SELECT id, codigo, id_empresa, eliminado
FROM empresa_establecimiento
WHERE id_empresa = 8
ORDER BY codigo, id;

-- 3) Alcance real del problema: TODOS los números de factura duplicados en la empresa 8
--    (no solo el que ya encontraste), para saber cuántas facturas están afectadas en total.
SELECT
    establecimiento, punto_emision, secuencial,
    COUNT(*) AS ocurrencias,
    STRING_AGG(id::text, ', ' ORDER BY id) AS ids_venta,
    STRING_AGG(id_cliente::text, ', ' ORDER BY id) AS ids_cliente,
    STRING_AGG(importe_total::text, ', ' ORDER BY id) AS montos
FROM ventas_cabecera
WHERE id_empresa = 8
  AND eliminado = false
GROUP BY establecimiento, punto_emision, secuencial
HAVING COUNT(*) > 1
ORDER BY establecimiento, punto_emision, secuencial;
