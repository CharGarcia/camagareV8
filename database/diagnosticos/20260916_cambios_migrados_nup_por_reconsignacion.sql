-- =============================================================================
-- Cambios de productos MIGRADOS sin NUP en lo devuelto porque la factura vendió VARIAS unidades
-- de ese producto: ¿cuántos se podrían precisar?  (SOLO LECTURA, estimación)
--
-- Idea: una unidad vendida solo vuelve a aparecer en otra consignación si regresó a la empresa.
-- Si, de las unidades posibles de un cambio, UNA SOLA volvió a consignarse por primera vez desde
-- la fecha del cambio en adelante, esa es la que el cliente devolvió.
--
-- No toca datos. Ajustar `v_emp` (una empresa) y ejecutar. Es una estimación: no considera que al
-- precisar un cambio se descarta esa unidad para los demás cambios de la misma factura, así que
-- en la práctica se precisarían algunos más.
-- =============================================================================
WITH p AS (
    SELECT 23::int AS v_emp                     -- <<< AJUSTAR: id de la empresa
),
dev AS (
    -- Devoluciones migradas sin NUP enlazadas solo a la línea de la factura de venta.
    SELECT c.id_empresa, c.fecha_cambio, d.id, d.id_producto,
           COALESCE(NULLIF(UPPER(TRIM(COALESCE(d.lote, ''))), 'SIN_LOTE'), '') AS lote,
           vc.id AS id_venta, vc.fecha_emision AS fecha_venta,
           vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial AS numero_venta
      FROM p
      JOIN migracion_mysql_map m
        ON m.entidad = 'cambios_producto' AND m.id_empresa = p.v_emp AND m.vinculado IS NOT TRUE
      JOIN cambios_producto_cv c
        ON c.id = m.id_destino AND c.id_empresa = m.id_empresa AND c.eliminado = false
      JOIN cambios_producto_cv_detalles d
        ON d.id_cambio = c.id AND d.tipo_linea = 'devolucion' AND d.origen_tipo = 'FACTURA'
       AND COALESCE(d.eliminado, false) = false AND COALESCE(TRIM(d.nup), '') = ''
      JOIN ventas_detalle vd
        ON vd.id = d.id_origen_detalle AND vd.id_venta = d.id_origen AND vd.id_producto = d.id_producto
      JOIN ventas_cabecera vc ON vc.id = vd.id_venta
     WHERE NOT EXISTS (SELECT 1 FROM consignaciones_facturas_detalles z
                        WHERE z.id = d.id_origen_detalle AND z.id_consignacion_factura = d.id_origen
                          AND z.id_producto = d.id_producto)
),
facturacion AS (
    -- Facturación de consignación de cada venta (por su id o, si quedó sin enlazar, por el número),
    -- sin las que crean los cambios.
    SELECT DISTINCT dev.id_venta, xf.id AS id_cf
      FROM dev
      JOIN consignaciones_facturas xf ON xf.id_factura = dev.id_venta
     WHERE xf.eliminado = false AND xf.estado = 'facturada'
       AND COALESCE(xf.observaciones, '') NOT ILIKE 'Cambios de productos facturados con anterioridad%'
       AND COALESCE(xf.observaciones, '') NOT ILIKE 'Registro del cambio de productos%'
    UNION
    SELECT DISTINCT dev.id_venta, xf.id
      FROM dev
      JOIN consignaciones_facturas xf
        ON xf.id_factura IS NULL AND xf.id_empresa = dev.id_empresa AND TRIM(xf.numero_factura) = dev.numero_venta
     WHERE xf.eliminado = false AND xf.estado = 'facturada'
       AND COALESCE(xf.observaciones, '') NOT ILIKE 'Cambios de productos facturados con anterioridad%'
       AND COALESCE(xf.observaciones, '') NOT ILIKE 'Registro del cambio de productos%'
),
cand AS (
    -- Unidades posibles de cada devolución (mismo producto; mismo lote o sin lote), con NUP.
    SELECT dev.id AS id_dev, dev.fecha_cambio, dev.fecha_venta, x.id_producto,
           UPPER(TRIM(x.nup)) AS nup, x.id_consignacion
      FROM dev
      JOIN facturacion f ON f.id_venta = dev.id_venta
      JOIN consignaciones_facturas_detalles x
        ON x.id_consignacion_factura = f.id_cf AND COALESCE(x.eliminado, false) = false
       AND x.id_producto = dev.id_producto AND COALESCE(TRIM(x.nup), '') <> ''
     WHERE dev.lote = ''
        OR COALESCE(NULLIF(UPPER(TRIM(COALESCE(x.lote, ''))), 'SIN_LOTE'), '') IN (dev.lote, '')
),
reconsig AS (
    -- Cada unidad (NUP) en las consignaciones de la empresa, con la fecha de esa consignación.
    SELECT UPPER(TRIM(r.nup)) AS nup, r.id_producto, r.id_consignacion, rv.fecha_emision
      FROM p
      JOIN consignaciones_ventas rv ON rv.id_empresa = p.v_emp AND rv.eliminado = false
      JOIN consignaciones_ventas_detalles r ON r.id_consignacion = rv.id AND r.eliminado = false
     WHERE COALESCE(TRIM(r.nup), '') <> ''
),
primera AS (
    -- Primera vez que cada unidad posible volvió a consignarse después de venderse.
    SELECT cand.id_dev, cand.nup, cand.fecha_cambio, MIN(rc.fecha_emision) AS volvio_el
      FROM cand
      LEFT JOIN reconsig rc
        ON rc.nup = cand.nup AND rc.id_producto = cand.id_producto
       AND rc.id_consignacion <> cand.id_consignacion AND rc.fecha_emision >= cand.fecha_venta
     GROUP BY cand.id_dev, cand.nup, cand.fecha_cambio
),
por_dev AS (
    SELECT id_dev,
           COUNT(*)                                          AS unidades,
           COUNT(*) FILTER (WHERE volvio_el >= fecha_cambio) AS volvieron_desde_el_cambio
      FROM primera
     GROUP BY id_dev
)
SELECT CASE
         WHEN volvieron_desde_el_cambio = 1 THEN 'a. Se podría precisar: una sola de las unidades volvió a consignarse desde el cambio'
         WHEN volvieron_desde_el_cambio = 0 THEN 'b. Ninguna de las unidades posibles volvió a consignarse'
         ELSE 'c. Varias de las unidades posibles volvieron a consignarse'
       END      AS caso,
       COUNT(*) AS devoluciones
  FROM por_dev
 GROUP BY 1
 ORDER BY 1;
