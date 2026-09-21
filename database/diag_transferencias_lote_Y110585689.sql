-- ============================================================================
-- DIAGNÓSTICO PASO 2 (SOLO LECTURA) — acotado al lote Y110585689
-- ============================================================================
-- Responde de una sola vez: esas 4 unidades, ¿son stock en bodega o están en
-- poder de un cliente (consignación)? Y si son stock, ¿las ve Transferencias?
--
-- Devuelve pocas filas, una por fuente:
--   KARDEX ...........  saldo real en bodega, abierto por tipo_ambiente.
--                       "INVISIBLE en Transferencias" = el reporte lo suma pero
--                       el selector de lotes no lo ve (bug de tipo_ambiente).
--   CONSIGNADO .......  saldo neto en poder del cliente (entregado - retornado
--                       - facturado - cambiado). NO es stock de bodega:
--                       Transferencias no puede moverlo, y es correcto que no
--                       lo ofrezca. El Reporte lo muestra en la columna
--                       "Consignación" y lo suma en "Stock Total".
--
-- NOTA   El CONSIGNADO no descuenta los cambios ya facturados (el reporte sí lo
--        hace, vía CambioProductoCvRepository::sqlSinRegistroFacturacion). Se
--        omite aquí para no depender de una columna que puede no existir en esta
--        base; puede dar 1-2 unidades de más, no cambia la conclusión.
--
-- TOCA DATOS  No. Un SELECT. USO: pegar en pgAdmin y ejecutar (F5).
-- ============================================================================

WITH emp AS (
    SELECT e.id, CAST(e.tipo_ambiente AS VARCHAR(1)) AS amb
      FROM empresas e
     WHERE e.ruc = '1792708389001'
),
prod AS (
    SELECT p.id, p.id_empresa
      FROM productos p
      JOIN emp e ON e.id = p.id_empresa
     WHERE p.nombre ILIKE '%IMPLANTE CM 3,8X11,5%'
       AND p.eliminado = false
)
-- A) Stock REAL en bodega, según el kardex, abierto por tipo_ambiente
SELECT 'KARDEX (stock en bodega)'::varchar   AS fuente,
       b.nombre                              AS bodega,
       k.tipo_ambiente                       AS ambiente_fila,
       e.amb                                 AS ambiente_empresa,
       CASE WHEN k.tipo_ambiente IS NOT DISTINCT FROM e.amb
            THEN 'lo ve Transferencias'
            ELSE 'INVISIBLE en Transferencias  <<<<'
       END::varchar                          AS nota,
       ROUND(SUM(k.cantidad), 2)             AS cantidad
  FROM inventario_kardex k
  JOIN emp     e ON e.id = k.id_empresa
  JOIN prod    p ON p.id = k.id_producto AND p.id_empresa = k.id_empresa
  JOIN bodegas b ON b.id = k.id_bodega
 WHERE k.eliminado = false
   AND k.numero_lote = 'Y110585689'
 GROUP BY b.nombre, k.tipo_ambiente, e.amb

UNION ALL

-- B) Saldo NETO en poder del cliente (consignación). No es movible.
SELECT 'CONSIGNADO (en poder del cliente)'::varchar,
       b.nombre,
       NULL::varchar,
       NULL::varchar,
       'no es stock de bodega: Transferencias no lo mueve'::varchar,
       ROUND(SUM(
           cvd.cantidad
           - COALESCE((SELECT SUM(rcd.cantidad)
                         FROM retornos_cv_detalles rcd
                         JOIN retornos_cv rc ON rc.id = rcd.id_retorno
                        WHERE rcd.id_consignacion_detalle = cvd.id
                          AND rcd.eliminado = false AND rc.eliminado = false
                          AND rc.estado = 'Emitida'), 0)
           - COALESCE((SELECT SUM(cfd.cantidad)
                         FROM consignaciones_facturas_detalles cfd
                         JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura
                        WHERE cfd.id_consignacion_detalle = cvd.id
                          AND cfd.eliminado = false AND cf.eliminado = false
                          AND cf.estado = 'facturada'), 0)
           - COALESCE((SELECT SUM(cd.cantidad)
                         FROM cambios_producto_cv_detalles cd
                         JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio
                        WHERE cd.tipo_linea = 'entrega'
                          AND cd.origen_tipo = 'CONSIGNACION'
                          AND cd.id_origen_detalle = cvd.id
                          AND cd.eliminado = false AND cc.eliminado = false
                          AND cc.estado = 'Emitida'), 0)
       ), 2)
  FROM consignaciones_ventas_detalles cvd
  JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
  JOIN emp     e ON e.id = cvd.id_empresa
  JOIN prod    p ON p.id = cvd.id_producto AND p.id_empresa = cvd.id_empresa
  JOIN bodegas b ON b.id = cvd.id_bodega
 WHERE cvd.eliminado = false AND cv.eliminado = false
   AND cvd.lote = 'Y110585689'
 GROUP BY b.nombre

 ORDER BY 1, 2;
