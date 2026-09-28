-- ============================================================================
--  Car-Wash: corregir el tipo_ambiente de las órdenes ya registradas.
--
--  Bug: al crear la orden, el ambiente salía de un dato que la pantalla nunca
--  enviaba, así que TODA orden quedó con tipo_ambiente = '1' (pruebas) aunque la
--  empresa estuviera en producción. Consecuencia: en producción no se podía
--  registrar ninguna orden ("El secuencial ya existe") y el listado mezclaba
--  órdenes de pruebas. El código ya está corregido; esto repara lo guardado.
--
--  Regla de corrección (la más fiel posible):
--   1. Orden con factura/recibo enlazado → el ambiente de ESE documento (que sí se
--      guardó bien).
--   2. Orden sin documento → el ambiente actual de la empresa.
--  Solo se actualiza si no choca con otra orden viva del mismo punto, secuencial y
--  ambiente destino (índice único uq_carwash_secuencial); los choques se listan
--  en el paso 3 para revisarlos a mano. Las órdenes migradas ya tienen el ambiente
--  correcto (la migración lo toma de la empresa) y no cambian.
--
--  Idempotente. Ejecutar primero el PASO 1 (solo lectura) para ver qué cambiará.
-- ============================================================================

-- PASO 1 (solo lectura): cuántas órdenes cambiarían, por empresa.
WITH destino AS (
    SELECT o.id, o.id_empresa, o.tipo_ambiente AS actual,
           COALESCE(
               CASE WHEN o.tipo_documento = 'FACTURA' THEN (SELECT vc.tipo_ambiente::varchar(1) FROM ventas_cabecera vc WHERE vc.id = o.id_documento)
                    WHEN o.tipo_documento = 'RECIBO'  THEN (SELECT rv.tipo_ambiente::varchar(1) FROM recibos_venta_cabecera rv WHERE rv.id = o.id_documento)
               END,
               (SELECT CASE WHEN CAST(e.tipo_ambiente AS VARCHAR(1)) = '2' THEN '2' ELSE '1' END FROM empresas e WHERE e.id = o.id_empresa),
               '1'
           ) AS nuevo
      FROM carwash_ordenes o
     WHERE o.eliminado = false
)
SELECT id_empresa, actual, nuevo, COUNT(*) AS ordenes
  FROM destino
 WHERE actual IS DISTINCT FROM nuevo
 GROUP BY 1, 2, 3
 ORDER BY 1;

-- PASO 2: corrección.
BEGIN;

WITH destino AS (
    SELECT o.id, o.id_empresa, o.id_punto_emision, o.secuencial,
           COALESCE(
               CASE WHEN o.tipo_documento = 'FACTURA' THEN (SELECT vc.tipo_ambiente::varchar(1) FROM ventas_cabecera vc WHERE vc.id = o.id_documento)
                    WHEN o.tipo_documento = 'RECIBO'  THEN (SELECT rv.tipo_ambiente::varchar(1) FROM recibos_venta_cabecera rv WHERE rv.id = o.id_documento)
               END,
               (SELECT CASE WHEN CAST(e.tipo_ambiente AS VARCHAR(1)) = '2' THEN '2' ELSE '1' END FROM empresas e WHERE e.id = o.id_empresa),
               '1'
           ) AS nuevo
      FROM carwash_ordenes o
     WHERE o.eliminado = false
)
UPDATE carwash_ordenes o
   SET tipo_ambiente = d.nuevo
  FROM destino d
 WHERE o.id = d.id
   AND o.tipo_ambiente IS DISTINCT FROM d.nuevo
   AND NOT EXISTS (SELECT 1 FROM carwash_ordenes x
                    WHERE x.id <> o.id AND x.eliminado = false
                      AND x.id_empresa = o.id_empresa AND x.id_punto_emision = o.id_punto_emision
                      AND x.secuencial = o.secuencial AND x.tipo_ambiente = d.nuevo);

COMMIT;

-- PASO 3 (solo lectura): órdenes que NO se pudieron mover por chocar con otra del
-- mismo número en el ambiente destino. Si aparece alguna, revisarla a mano.
SELECT o.id_empresa, o.id, o.numero_orden, o.tipo_ambiente AS actual,
       CASE WHEN CAST(e.tipo_ambiente AS VARCHAR(1)) = '2' THEN '2' ELSE '1' END AS ambiente_empresa
  FROM carwash_ordenes o
  JOIN empresas e ON e.id = o.id_empresa
 WHERE o.eliminado = false
   AND o.id_documento IS NULL
   AND o.tipo_ambiente <> CASE WHEN CAST(e.tipo_ambiente AS VARCHAR(1)) = '2' THEN '2' ELSE '1' END
 ORDER BY o.id_empresa, o.id;
