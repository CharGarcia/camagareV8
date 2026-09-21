-- =====================================================================================
-- Diagnóstico SOLO LECTURA: ¿la fecha de vencimiento (fecha_caducidad) vuelve con lo que se
-- DEVUELVE en el módulo Cambios de productos? No modifica nada.
-- Ejecutar cada PASO por separado en pgAdmin.
--
-- Cómo debería funcionar (código, 21-09-2026):
--   Devolución (ENTRA)  -> CambioProductoCvRepository::getDatosLineaOrigen() copia
--                         fecha_caducidad de la línea de origen:
--                            origen_tipo 'FACTURA' -> consignaciones_facturas_detalles
--                            origen_tipo 'CAMBIO'  -> cambios_producto_cv_detalles (entrega previa)
--                         -> se graba en cambios_producto_cv_detalles.fecha_caducidad (insertDetalle)
--                         -> y viaja al kardex como ENTRADA (moverInventarioLinea).
--   Entrega (SALE)      -> desde consignación: fecha_caducidad de consignaciones_ventas_detalles
--                         (no mueve stock; queda en el registro de Facturación de consignaciones).
--
-- Por tanto, una devolución sale SIN vencimiento solo si su ORIGEN no lo tenía. Estos PASOS
-- separan los dos casos: "el origen no tenía" (dato de origen) vs. "el origen sí tenía y no se
-- copió" (bug -- debería dar 0 filas).
-- Los cambios MIGRADOS desde MySQL no traen vencimiento: el sistema viejo no lo guardaba.
-- =====================================================================================


-- PASO 0. Panorama: líneas con lote / NUP / vencimiento, por empresa, lado y origen.
--         Si con_vencimiento es muy bajo pero con_lote es alto, el vencimiento se está
--         perdiendo o nunca existió en el origen: lo dicen los PASOS 1 y 2.
SELECT d.id_empresa,
       d.tipo_linea,
       COALESCE(d.origen_tipo, '(sin origen)') AS origen_tipo,
       COUNT(*)                                AS lineas,
       COUNT(NULLIF(d.lote, ''))               AS con_lote,
       COUNT(NULLIF(d.nup, ''))                AS con_nup,
       COUNT(d.fecha_caducidad)                AS con_vencimiento,
       COUNT(*) - COUNT(d.fecha_caducidad)     AS sin_vencimiento
FROM cambios_producto_cv_detalles d
INNER JOIN cambios_producto_cv c ON c.id = d.id_cambio
WHERE d.eliminado = false AND c.eliminado = false
GROUP BY 1, 2, 3
ORDER BY 1, 2, 3;


-- PASO 1. Devoluciones con origen 'FACTURA' (factura de consignación): comparación línea a línea
--         contra su origen. DEBE salir 0 en "copiado_mal".
SELECT d.id_empresa,
       COUNT(*)                                                            AS devoluciones,
       COUNT(o.fecha_caducidad)                                            AS origen_con_vencimiento,
       COUNT(d.fecha_caducidad)                                            AS devolucion_con_vencimiento,
       COUNT(*) FILTER (WHERE o.fecha_caducidad IS NULL)                   AS origen_sin_vencimiento,
       COUNT(*) FILTER (WHERE o.fecha_caducidad IS NOT NULL
                          AND (d.fecha_caducidad IS NULL
                               OR d.fecha_caducidad <> o.fecha_caducidad)) AS copiado_mal
FROM cambios_producto_cv_detalles d
INNER JOIN cambios_producto_cv c ON c.id = d.id_cambio
INNER JOIN consignaciones_facturas_detalles o ON o.id = d.id_origen_detalle
INNER JOIN consignaciones_facturas ocf ON ocf.id = o.id_consignacion_factura
                                      AND ocf.id_empresa = d.id_empresa
WHERE d.tipo_linea = 'devolucion' AND d.origen_tipo = 'FACTURA'
  AND d.eliminado = false AND c.eliminado = false
GROUP BY 1
ORDER BY 1;


-- PASO 1b. Detalle de las líneas del PASO 1 en las que el origen SÍ tenía vencimiento y la
--          devolución no lo copió (o lo copió distinto). Lo esperado: ninguna fila.
SELECT c.id_empresa,
       c.id                           AS id_cambio,
       c.serie || '-' || c.secuencial AS numero,
       c.fecha_cambio,
       c.estado,
       d.id                           AS id_linea,
       p.codigo                       AS producto,
       d.lote, d.nup,
       o.fecha_caducidad              AS vencimiento_origen,
       d.fecha_caducidad              AS vencimiento_devolucion
FROM cambios_producto_cv_detalles d
INNER JOIN cambios_producto_cv c ON c.id = d.id_cambio
INNER JOIN consignaciones_facturas_detalles o ON o.id = d.id_origen_detalle
INNER JOIN consignaciones_facturas ocf ON ocf.id = o.id_consignacion_factura
                                      AND ocf.id_empresa = d.id_empresa
LEFT JOIN productos p ON p.id = d.id_producto
WHERE d.tipo_linea = 'devolucion' AND d.origen_tipo = 'FACTURA'
  AND d.eliminado = false AND c.eliminado = false
  AND o.fecha_caducidad IS NOT NULL
  AND (d.fecha_caducidad IS NULL OR d.fecha_caducidad <> o.fecha_caducidad)
ORDER BY c.id_empresa, c.fecha_cambio DESC, d.id
LIMIT 200;


-- PASO 2. Devoluciones con origen 'CAMBIO' (la unidad la entregó un cambio anterior): mismo
--         cruce contra la línea de ENTREGA de ese cambio. "copiado_mal" debe ser 0.
SELECT d.id_empresa,
       COUNT(*)                                                            AS devoluciones,
       COUNT(e.fecha_caducidad)                                            AS origen_con_vencimiento,
       COUNT(d.fecha_caducidad)                                            AS devolucion_con_vencimiento,
       COUNT(*) FILTER (WHERE e.fecha_caducidad IS NOT NULL
                          AND (d.fecha_caducidad IS NULL
                               OR d.fecha_caducidad <> e.fecha_caducidad)) AS copiado_mal
FROM cambios_producto_cv_detalles d
INNER JOIN cambios_producto_cv c ON c.id = d.id_cambio
INNER JOIN cambios_producto_cv_detalles e ON e.id = d.id_origen_detalle
                                         AND e.tipo_linea = 'entrega'
                                         AND e.id_empresa = d.id_empresa
WHERE d.tipo_linea = 'devolucion' AND d.origen_tipo = 'CAMBIO'
  AND d.eliminado = false AND c.eliminado = false
GROUP BY 1
ORDER BY 1;


-- PASO 3. Entregas desde consignación: ¿copiaron el vencimiento de la línea de consignación?
--         "copiado_mal" debe ser 0.
SELECT d.id_empresa,
       COUNT(*)                                                              AS entregas,
       COUNT(cvd.fecha_caducidad)                                            AS consignacion_con_vencimiento,
       COUNT(d.fecha_caducidad)                                              AS entrega_con_vencimiento,
       COUNT(*) FILTER (WHERE cvd.fecha_caducidad IS NOT NULL
                          AND (d.fecha_caducidad IS NULL
                               OR d.fecha_caducidad <> cvd.fecha_caducidad)) AS copiado_mal
FROM cambios_producto_cv_detalles d
INNER JOIN cambios_producto_cv c ON c.id = d.id_cambio
INNER JOIN consignaciones_ventas_detalles cvd ON cvd.id = d.id_origen_detalle
                                             AND cvd.id_empresa = d.id_empresa
WHERE d.tipo_linea = 'entrega' AND d.origen_tipo = 'CONSIGNACION'
  AND d.eliminado = false AND c.eliminado = false
GROUP BY 1
ORDER BY 1;


-- PASO 4. ¿Llegó el vencimiento al KARDEX? La entrada de inventario de cada devolución debe
--         llevar el mismo numero_lote / fecha_caducidad / nup que la línea del cambio.
SELECT k.id_empresa,
       COUNT(*)                            AS movimientos_entrada,
       COUNT(k.fecha_caducidad)            AS kardex_con_vencimiento,
       COUNT(*) - COUNT(k.fecha_caducidad) AS kardex_sin_vencimiento,
       COUNT(NULLIF(k.numero_lote, ''))    AS kardex_con_lote
FROM inventario_kardex k
WHERE k.referencia_tipo = 'CAMBIO_PRODUCTO_CV'
  AND k.tipo_movimiento = 'entrada'
  AND k.cantidad > 0
GROUP BY 1
ORDER BY 1;


-- PASO 4b. Entradas de kardex SIN vencimiento cuya línea del cambio SÍ lo tenía.
--          El kardex no guarda el id de la línea, así que el cruce es por
--          (cambio, producto, bodega, lote): con varias líneas iguales en un cambio la
--          comparación es de conjuntos. Lo esperado: ninguna fila.
SELECT k.id_empresa,
       k.referencia_id                AS id_cambio,
       c.serie || '-' || c.secuencial AS numero,
       k.id                           AS id_kardex,
       k.id_producto, k.id_bodega,
       k.numero_lote, k.nup,
       k.fecha_caducidad              AS vencimiento_kardex,
       MAX(d.fecha_caducidad)         AS vencimiento_linea
FROM inventario_kardex k
INNER JOIN cambios_producto_cv c ON c.id = k.referencia_id AND c.id_empresa = k.id_empresa
INNER JOIN cambios_producto_cv_detalles d ON d.id_cambio = c.id
                                         AND d.tipo_linea = 'devolucion'
                                         AND d.eliminado = false
                                         AND d.id_producto = k.id_producto
                                         AND d.id_bodega   = k.id_bodega
                                         AND COALESCE(d.lote, '') = COALESCE(k.numero_lote, '')
WHERE k.referencia_tipo = 'CAMBIO_PRODUCTO_CV'
  AND k.tipo_movimiento = 'entrada'
  AND k.cantidad > 0
  AND k.fecha_caducidad IS NULL
  AND c.eliminado = false
GROUP BY k.id_empresa, k.referencia_id, c.serie, c.secuencial, k.id, k.id_producto, k.id_bodega,
         k.numero_lote, k.nup, k.fecha_caducidad
HAVING MAX(d.fecha_caducidad) IS NOT NULL
ORDER BY 1, 2, 4
LIMIT 200;


-- PASO 5. Cuánto de lo que se devuelve no puede traer vencimiento porque el ORIGEN no lo tiene.
--         Separa los cambios migrados desde MySQL (nunca lo traen) de los nativos.
SELECT d.id_empresa,
       (m.id_destino IS NOT NULL)                                      AS migrado,
       COUNT(*)                                                        AS devoluciones,
       COUNT(d.fecha_caducidad)                                        AS con_vencimiento,
       COUNT(*) - COUNT(d.fecha_caducidad)                             AS sin_vencimiento,
       ROUND(100.0 * COUNT(d.fecha_caducidad) / NULLIF(COUNT(*), 0), 1) AS pct_con_vencimiento
FROM cambios_producto_cv_detalles d
INNER JOIN cambios_producto_cv c ON c.id = d.id_cambio
LEFT JOIN migracion_mysql_map m ON m.entidad = 'cambios_producto'
                               AND m.id_destino = c.id
                               AND m.id_empresa = c.id_empresa
                               AND m.vinculado IS NOT TRUE
WHERE d.tipo_linea = 'devolucion' AND d.eliminado = false AND c.eliminado = false
GROUP BY 1, 2
ORDER BY 1, 2;


-- PASO 6. Origen del problema, si el PASO 5 muestra muchos sin vencimiento en cambios NATIVOS:
--         ¿cuántas líneas de consignación y de facturación de consignaciones tienen vencimiento?
--         Si aquí ya es bajo, el vencimiento nunca se capturó al consignar: no se pierde al cambiar.
SELECT 'consignaciones_ventas_detalles' AS tabla, id_empresa,
       COUNT(*) AS lineas, COUNT(fecha_caducidad) AS con_vencimiento
FROM consignaciones_ventas_detalles
WHERE COALESCE(eliminado, false) = false
GROUP BY 1, 2
UNION ALL
SELECT 'consignaciones_facturas_detalles', d.id_empresa,
       COUNT(*), COUNT(d.fecha_caducidad)
FROM consignaciones_facturas_detalles d
WHERE COALESCE(d.eliminado, false) = false
GROUP BY 1, 2
ORDER BY 2, 1;


-- =====================================================================================
-- AMPLIACIÓN 21-09-2026, tras correr el PASO 6 en producción:
--   empresa 23 -> consignaciones_ventas_detalles 233.533 / 233.533 con vencimiento (100%)
--                 consignaciones_facturas_detalles 137.649 /   1.011 con vencimiento (0,7%)
--   empresa 33 -> 537 / 536 (99,8%)  vs  449 / 141 (31,4%)
--
-- La ENTRADA de consignación sí tiene el vencimiento; la FACTURACIÓN de consignaciones lo
-- pierde. Causa en el código: MigracionMysqlService::migrarConsignacionesDerivado() inserta
-- en consignaciones_facturas_detalles solo (… id_bodega, lote, nup) — sin fecha_caducidad —
-- y su precarga del detalle viejo no pide la columna `vencimiento` (el de la ENTRADA sí:
-- migrarConsignaciones lo trae y le aplica caducidadODef, de ahí el 100%).
-- Mismo caso en retornos_cv_detalles (la otra rama del mismo método).
--
-- Consecuencia en Cambios de productos: la devolución copia el vencimiento de
-- consignaciones_facturas_detalles, así que entra NULL aunque la consignación lo tenga.
-- =====================================================================================


-- PASO 7. Confirmación: ¿son las líneas MIGRADAS las que no tienen vencimiento?
SELECT d.id_empresa,
       (m.id_destino IS NOT NULL)          AS migrado,
       COUNT(*)                            AS lineas,
       COUNT(d.fecha_caducidad)            AS con_vencimiento,
       COUNT(*) - COUNT(d.fecha_caducidad) AS sin_vencimiento
FROM consignaciones_facturas_detalles d
INNER JOIN consignaciones_facturas cf ON cf.id = d.id_consignacion_factura
LEFT JOIN migracion_mysql_map m ON m.entidad = 'consignaciones_fact'
                               AND m.id_destino = cf.id
                               AND m.id_empresa = cf.id_empresa
WHERE COALESCE(d.eliminado, false) = false AND cf.eliminado = false
GROUP BY 1, 2
ORDER BY 1, 2;


-- PASO 8. ¿Se puede recuperar? El vencimiento está en la línea de ENTRADA a la que apunta
--         cada línea de facturación (id_consignacion_detalle). Cuenta cuántas se rellenarían.
SELECT d.id_empresa,
       COUNT(*)                                       AS lineas_sin_vencimiento,
       COUNT(e.fecha_caducidad)                       AS recuperables_desde_consignacion,
       COUNT(*) FILTER (WHERE d.id_consignacion_detalle IS NULL) AS sin_linea_de_consignacion,
       COUNT(*) FILTER (WHERE e.id IS NOT NULL
                          AND e.fecha_caducidad IS NULL)         AS consignacion_tampoco_tiene
FROM consignaciones_facturas_detalles d
INNER JOIN consignaciones_facturas cf ON cf.id = d.id_consignacion_factura
LEFT JOIN consignaciones_ventas_detalles e ON e.id = d.id_consignacion_detalle
                                          AND e.id_empresa = d.id_empresa
WHERE COALESCE(d.eliminado, false) = false AND cf.eliminado = false
  AND d.fecha_caducidad IS NULL
GROUP BY 1
ORDER BY 1;


-- PASO 9. Lo mismo para Retornos de consignaciones (la otra rama del mismo método migrador).
SELECT d.id_empresa,
       COUNT(*)                                 AS lineas,
       COUNT(d.fecha_caducidad)                 AS con_vencimiento,
       COUNT(*) - COUNT(d.fecha_caducidad)      AS sin_vencimiento,
       COUNT(e.fecha_caducidad) FILTER (WHERE d.fecha_caducidad IS NULL) AS recuperables
FROM retornos_cv_detalles d
INNER JOIN retornos_cv r ON r.id = d.id_retorno
LEFT JOIN consignaciones_ventas_detalles e ON e.id = d.id_consignacion_detalle
                                          AND e.id_empresa = d.id_empresa
WHERE COALESCE(d.eliminado, false) = false AND r.eliminado = false
GROUP BY 1
ORDER BY 1;


-- PASO 10. Devoluciones ya grabadas en Cambios de productos que quedaron sin vencimiento y
--          que SÍ lo tendrían si su origen lo hubiera tenido (recuperable desde la ENTRADA
--          de consignación). Mide el arrastre del problema dentro de este módulo.
SELECT d.id_empresa,
       COUNT(*)                 AS devoluciones_sin_vencimiento,
       COUNT(e.fecha_caducidad) AS recuperables_desde_consignacion,
       COUNT(*) FILTER (WHERE c.estado = 'Emitida') AS de_cambios_emitidos
FROM cambios_producto_cv_detalles d
INNER JOIN cambios_producto_cv c ON c.id = d.id_cambio
LEFT JOIN consignaciones_facturas_detalles o ON o.id = d.id_origen_detalle
LEFT JOIN consignaciones_ventas_detalles e ON e.id = o.id_consignacion_detalle
                                          AND e.id_empresa = d.id_empresa
WHERE d.tipo_linea = 'devolucion' AND d.origen_tipo = 'FACTURA'
  AND d.fecha_caducidad IS NULL
  AND d.eliminado = false AND c.eliminado = false
GROUP BY 1
ORDER BY 1;
