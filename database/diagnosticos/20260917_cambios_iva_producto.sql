-- =====================================================================================
-- Diagnóstico SOLO LECTURA: IVA de los cambios de productos grabados ANTES del arreglo del 17-09-2026.
-- No modifica nada. Ejecutar cada PASO por separado en pgAdmin.
--
-- Hasta ese arreglo:
--   * Lo que SALE (entregas desde consignación, existencias o catálogo) se guardaba con el IVA que
--     mandaba el formulario: el de la línea de consignación (una consignación no graba IVA: 0) o 0 fijo
--     desde bodega, y sin tarifa (id_impuesto). Facturación de consignaciones factura con la tarifa
--     ACTUAL del producto.
--   * El registro que el cambio crea en Facturación de consignaciones copiaba esas líneas: IVA 0.
--   * Lo que ENTRA copiaba el IVA de su línea de origen (el que tuvo al facturarse), pero sin restar el
--     descuento de esa línea, y con 0 si el origen no tenía tarifa guardada (facturación migrada o
--     entrega de un cambio anterior).
-- Desde el arreglo, al guardar, lo que sale toma la tarifa vigente del producto y lo que entra resta el
-- descuento y, si su origen no tiene tarifa, usa la del producto. Lo ya grabado no cambia solo.
--
-- iva_faltante = subtotal de la línea × tarifa actual del producto − IVA guardado.
-- Los cambios MIGRADOS no cuentan: no traen precios ni IVA del sistema anterior.
-- =====================================================================================


-- PASO 0. ¿Está aplicada la migración del registro en Facturación de consignaciones?
--         (20260916_facturacion_cv_registro_cambio.sql). Si sale false, no ejecute el PASO 3.
SELECT COUNT(*) = 2 AS registro_facturacion_disponible
FROM information_schema.columns
WHERE (table_name = 'consignaciones_facturas' AND column_name = 'id_cambio_producto')
   OR (table_name = 'consignaciones_facturas_detalles' AND column_name = 'id_cambio_detalle');


-- PASO 1. Lo que SALE en cambios nativos, por empresa, origen y estado.
SELECT c.id_empresa,
       CASE WHEN d.origen_tipo = 'CONSIGNACION' THEN 'Sale desde consignación'
            ELSE 'Sale desde existencias o catálogo' END                                   AS origen,
       c.estado,
       COUNT(*)                                                                             AS lineas,
       COUNT(*) FILTER (WHERE COALESCE(ti.porcentaje_iva, 0) > 0)                           AS producto_gravado,
       COUNT(*) FILTER (WHERE COALESCE(ti.porcentaje_iva, 0) > 0
                          AND COALESCE(d.porcentaje_impuesto, 0) = 0)                       AS gravado_guardado_con_iva_0,
       COUNT(*) FILTER (WHERE ROUND(COALESCE(d.porcentaje_impuesto, 0), 2)
                           <> ROUND(COALESCE(ti.porcentaje_iva, 0), 2))                     AS iva_distinto_al_producto,
       ROUND(SUM(ROUND(COALESCE(d.subtotal, 0) * COALESCE(ti.porcentaje_iva, 0) / 100, 2)
                 - COALESCE(d.valor_impuesto, 0)), 2)                                       AS iva_faltante
FROM cambios_producto_cv_detalles d
JOIN cambios_producto_cv c ON c.id = d.id_cambio
JOIN productos p           ON p.id = d.id_producto
LEFT JOIN tarifa_iva ti    ON ti.id = p.tarifa_iva
WHERE d.eliminado = false AND c.eliminado = false
  AND d.tipo_linea = 'entrega'
  AND NOT EXISTS (SELECT 1 FROM migracion_mysql_map m
                  WHERE m.entidad = 'cambios_producto' AND m.id_destino = c.id
                    AND m.id_empresa = c.id_empresa AND m.vinculado IS NOT TRUE)
GROUP BY 1, 2, 3
ORDER BY 1, 2, 3;


-- PASO 2. Lo que ENTRA en cambios nativos:
--   sin_tarifa_guardada / gravado_guardado_con_iva_0: copiaron un origen sin tarifa siendo el producto gravado.
--   descuento_sin_restar: vienen de una línea facturada con descuento y su subtotal no lo restó.
--   La línea facturada se cruza por el PAR cabecera + detalle + producto: en devoluciones anteriores al
--   16-09-2026 'FACTURA' apunta a ventas_detalle y los ids se pisan con los de la facturación.
SELECT c.id_empresa,
       COALESCE(NULLIF(d.origen_tipo, ''), 'sin origen')                                    AS origen,
       c.estado,
       COUNT(*)                                                                             AS lineas,
       COUNT(*) FILTER (WHERE d.id_impuesto IS NULL)                                        AS sin_tarifa_guardada,
       COUNT(*) FILTER (WHERE d.id_impuesto IS NULL AND COALESCE(ti.porcentaje_iva, 0) > 0
                          AND COALESCE(d.porcentaje_impuesto, 0) = 0)                       AS gravado_guardado_con_iva_0,
       COUNT(*) FILTER (WHERE COALESCE(cfd.descuento, 0) > 0
                          AND ROUND(d.subtotal, 2)
                            = ROUND(d.precio_unitario * d.cantidad, 2))                     AS descuento_sin_restar
FROM cambios_producto_cv_detalles d
JOIN cambios_producto_cv c ON c.id = d.id_cambio
JOIN productos p           ON p.id = d.id_producto
LEFT JOIN tarifa_iva ti    ON ti.id = p.tarifa_iva
LEFT JOIN consignaciones_facturas_detalles cfd
       ON d.origen_tipo = 'FACTURA'
      AND cfd.id = d.id_origen_detalle
      AND cfd.id_consignacion_factura = d.id_origen
      AND cfd.id_producto = d.id_producto
WHERE d.eliminado = false AND c.eliminado = false
  AND d.tipo_linea = 'devolucion'
  AND NOT EXISTS (SELECT 1 FROM migracion_mysql_map m
                  WHERE m.entidad = 'cambios_producto' AND m.id_destino = c.id
                    AND m.id_empresa = c.id_empresa AND m.vinculado IS NOT TRUE)
GROUP BY 1, 2, 3
ORDER BY 1, 2, 3;


-- PASO 3. Registros de cambios nativos en Facturación de consignaciones (requiere PASO 0 = true).
SELECT cf.id_empresa,
       cf.estado,
       COUNT(DISTINCT cf.id)                                                                AS registros,
       COUNT(*)                                                                             AS lineas,
       COUNT(*) FILTER (WHERE COALESCE(ti.porcentaje_iva, 0) > 0
                          AND COALESCE(cfd.porcentaje_impuesto, 0) = 0)                     AS gravado_con_iva_0,
       ROUND(SUM(ROUND(COALESCE(cfd.subtotal, 0) * COALESCE(ti.porcentaje_iva, 0) / 100, 2)
                 - COALESCE(cfd.valor_impuesto, 0)), 2)                                     AS iva_faltante
FROM consignaciones_facturas cf
JOIN consignaciones_facturas_detalles cfd ON cfd.id_consignacion_factura = cf.id AND cfd.eliminado = false
JOIN productos p                          ON p.id = cfd.id_producto
LEFT JOIN tarifa_iva ti                   ON ti.id = p.tarifa_iva
WHERE cf.eliminado = false
  AND cf.id_cambio_producto IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM migracion_mysql_map m
                  WHERE m.entidad = 'cambios_producto' AND m.id_destino = cf.id_cambio_producto
                    AND m.id_empresa = cf.id_empresa AND m.vinculado IS NOT TRUE)
GROUP BY 1, 2
ORDER BY 1, 2;
