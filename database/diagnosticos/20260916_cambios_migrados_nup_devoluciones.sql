-- =============================================================================
-- Cambios de productos MIGRADOS: ¿por qué lo devuelto no tiene NUP?  (SOLO LECTURA)
--
-- El sistema anterior no guardaba el NUP de lo que el cliente devolvía (ni en el cambio, ni en la
-- factura, ni en el inventario). La migración lo toma de la unidad vendida en la facturación de
-- consignación de esa factura, o de lo entregado en un cambio anterior, cuando hay UNA sola unidad
-- posible. Esta consulta cuenta las devoluciones migradas por caso.
--
-- No toca datos. Ajustar `v_emp` (0 = todas las empresas) y ejecutar.
-- =============================================================================
WITH p AS (
    SELECT 0::int AS v_emp                      -- <<< AJUSTAR: id de la empresa (0 = todas)
),
dev AS (
    SELECT c.id_empresa, d.id, d.id_producto, COALESCE(d.nup, '') AS nup,
           COALESCE(d.origen_tipo, '') AS origen_tipo, d.id_origen, d.id_origen_detalle
      FROM cambios_producto_cv c
      JOIN migracion_mysql_map m
        ON m.entidad = 'cambios_producto' AND m.id_destino = c.id
       AND m.id_empresa = c.id_empresa AND m.vinculado IS NOT TRUE
      JOIN cambios_producto_cv_detalles d
        ON d.id_cambio = c.id AND d.tipo_linea = 'devolucion' AND COALESCE(d.eliminado, false) = false
     CROSS JOIN p
     WHERE c.eliminado = false
       AND (p.v_emp = 0 OR c.id_empresa = p.v_emp)
),
venta AS (
    -- Factura de venta de cada devolución enlazada a una factura: a la unidad de su facturación de
    -- consignación (formato actual) o solo a la línea de la factura de venta (formato anterior).
    SELECT dev.id,
           COALESCE(cf.id_factura, vd.id_venta) AS id_venta,
           (cfd.id IS NOT NULL)                 AS a_unidad,
           cfd.cantidad                         AS cantidad_unidad
      FROM dev
      LEFT JOIN consignaciones_facturas_detalles cfd
        ON dev.origen_tipo = 'FACTURA' AND cfd.id = dev.id_origen_detalle
       AND cfd.id_consignacion_factura = dev.id_origen AND cfd.id_producto = dev.id_producto
      LEFT JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura
      LEFT JOIN ventas_detalle vd
        ON dev.origen_tipo = 'FACTURA' AND cfd.id IS NULL AND vd.id = dev.id_origen_detalle
       AND vd.id_venta = dev.id_origen AND vd.id_producto = dev.id_producto
)
SELECT dev.id_empresa,
       CASE
         WHEN dev.nup <> ''                        THEN '1. Con NUP'
         WHEN dev.origen_tipo = ''                 THEN '2. Sin NUP: el cambio no tiene factura'
         WHEN dev.origen_tipo = 'CAMBIO'           THEN '3. Sin NUP: lo entregado en el cambio anterior no tiene NUP'
         WHEN v.a_unidad AND v.cantidad_unidad > 1 THEN '4. Sin NUP: la línea de la facturación agrupa varias unidades'
         WHEN v.a_unidad                           THEN '5. Sin NUP: esa unidad se facturó sin NUP'
         WHEN u.unidades = 0                       THEN '6. Sin NUP: la facturación de esa factura no tiene ese producto'
         WHEN u.unidades = 1                       THEN '7. Sin NUP: una sola unidad posible; vuelva a ejecutar la migración'
         ELSE '8. Sin NUP: la factura vendió varias unidades de ese producto y no se sabe cuál volvió'
       END      AS caso,
       COUNT(*) AS devoluciones
  FROM dev
  LEFT JOIN venta v ON v.id = dev.id
  LEFT JOIN LATERAL (
        -- Unidades de ese producto vendidas en la facturación de consignación de la factura, sin las
        -- facturaciones que crean los cambios (las del sistema anterior y los registros de este).
        SELECT COALESCE(SUM(x.cantidad), 0) AS unidades
          FROM consignaciones_facturas_detalles x
          JOIN consignaciones_facturas xf ON xf.id = x.id_consignacion_factura
         WHERE xf.id_factura = v.id_venta
           AND xf.eliminado = false AND xf.estado = 'facturada'
           AND COALESCE(x.eliminado, false) = false
           AND x.id_producto = dev.id_producto
           AND COALESCE(xf.observaciones, '') NOT ILIKE 'Cambios de productos facturados con anterioridad%'
           AND COALESCE(xf.observaciones, '') NOT ILIKE 'Registro del cambio de productos%'
  ) u ON true
 GROUP BY 1, 2
 ORDER BY 1, 2;
