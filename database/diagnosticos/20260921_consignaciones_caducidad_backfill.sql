-- =====================================================================================
-- Facturación y Retornos de consignaciones: rellenar la fecha de vencimiento que la
-- migración no trajo.
--
-- QUÉ PASÓ: MigracionMysqlService::migrarConsignacionesDerivado() insertaba sin
-- fecha_caducidad en las dos tablas de detalle que crea — consignaciones_facturas_detalles
-- (modo FACTURA) y retornos_cv_detalles (modo DEVOLUCIÓN). La línea de ENTRADA
-- (consignaciones_ventas_detalles) sí la tiene al 100%, porque migrarConsignaciones la trae
-- del `vencimiento` viejo y le aplica caducidadODef. Medido el 21-09-2026:
--     empresa 23 -> consignaciones_facturas_detalles: 137.649 líneas, 1.011 con fecha (0,7%)
--     empresa 33 -> consignaciones_facturas_detalles:     449 líneas,   141 con fecha (31,4%)
--     (retornos_cv_detalles: correr el PASO 0 para ver su magnitud)
--
-- POR QUÉ IMPORTA: la devolución del módulo Cambios de productos copia el vencimiento de
-- consignaciones_facturas_detalles, así que la unidad entraba al documento y al kardex sin
-- fecha aunque la consignación sí la tuviera. En Retornos el dato se copia igual desde la
-- línea de consignación y alimenta la entrada de inventario del retorno.
--
-- QUÉ HACE ESTE SQL: copia fecha_caducidad desde la línea de consignación a la que ya apunta
-- cada línea (id_consignacion_detalle) — la misma unidad física, la misma fecha. Es
-- exactamente lo que el migrador ya hacía con id_bodega.
-- Solo toca filas con fecha_caducidad NULL: nunca pisa un dato existente.
--
-- El código del migrador quedó corregido el 21-09-2026 (INSERT + reconcile, en las dos
-- ramas), así que re-migrar ya no vuelve a dejar el hueco. Este SQL es para lo ya migrado.
--
-- NO cubre las devoluciones ya grabadas en cambios_producto_cv_detalles: esas guardan su
-- propia copia del dato y no se corrigen hacia atrás con esto.
--
-- CÓMO USARLO: ejecutar PASO por PASO en pgAdmin, en orden. Los PASOS 0 y 1 no modifican nada.
-- =====================================================================================


-- ─────────────────────────────────────────────────────────────────────────────────────
-- PASO 0 (SOLO LECTURA). Cuántas filas se van a tocar, por tabla y empresa.
--   a_rellenar              = las que quedarán con vencimiento
--   sin_linea_consignacion  = no tienen a qué apuntar (quedan igual)
--   consignacion_sin_fecha  = la entrada tampoco la tiene (quedan igual)
-- ─────────────────────────────────────────────────────────────────────────────────────
SELECT 'consignaciones_facturas_detalles' AS tabla,
       d.id_empresa,
       COUNT(*)                                                  AS sin_vencimiento_hoy,
       COUNT(*) FILTER (WHERE e.fecha_caducidad IS NOT NULL)     AS a_rellenar,
       COUNT(*) FILTER (WHERE d.id_consignacion_detalle IS NULL) AS sin_linea_consignacion,
       COUNT(*) FILTER (WHERE e.id IS NOT NULL
                          AND e.fecha_caducidad IS NULL)         AS consignacion_sin_fecha
FROM consignaciones_facturas_detalles d
INNER JOIN consignaciones_facturas cf ON cf.id = d.id_consignacion_factura
LEFT JOIN consignaciones_ventas_detalles e ON e.id = d.id_consignacion_detalle
                                          AND e.id_empresa = d.id_empresa
WHERE COALESCE(d.eliminado, false) = false
  AND cf.eliminado = false
  AND d.fecha_caducidad IS NULL
GROUP BY 1, 2

UNION ALL

SELECT 'retornos_cv_detalles',
       d.id_empresa,
       COUNT(*),
       COUNT(*) FILTER (WHERE e.fecha_caducidad IS NOT NULL),
       COUNT(*) FILTER (WHERE d.id_consignacion_detalle IS NULL),
       COUNT(*) FILTER (WHERE e.id IS NOT NULL AND e.fecha_caducidad IS NULL)
FROM retornos_cv_detalles d
INNER JOIN retornos_cv r ON r.id = d.id_retorno
LEFT JOIN consignaciones_ventas_detalles e ON e.id = d.id_consignacion_detalle
                                          AND e.id_empresa = d.id_empresa
WHERE COALESCE(d.eliminado, false) = false
  AND r.eliminado = false
  AND d.fecha_caducidad IS NULL
GROUP BY 1, 2

ORDER BY 2, 1;


-- ─────────────────────────────────────────────────────────────────────────────────────
-- PASO 1 (SOLO LECTURA). Respaldo: deja en una tabla el estado previo de las filas que los
-- PASOS 2A y 2B van a tocar, con la columna `tabla` para distinguirlas. No modifica nada.
-- Si ya existe de una corrida anterior, bórrela antes:  DROP TABLE _cmg_cv_caducidad_bk;
-- ─────────────────────────────────────────────────────────────────────────────────────
CREATE TABLE _cmg_cv_caducidad_bk AS
SELECT 'consignaciones_facturas_detalles' AS tabla,
       d.id,
       d.id_empresa,
       d.id_consignacion_detalle,
       d.fecha_caducidad AS fecha_caducidad_antes,
       e.fecha_caducidad AS fecha_caducidad_nueva,
       now()             AS respaldado_en
FROM consignaciones_facturas_detalles d
INNER JOIN consignaciones_facturas cf ON cf.id = d.id_consignacion_factura
INNER JOIN consignaciones_ventas_detalles e ON e.id = d.id_consignacion_detalle
                                           AND e.id_empresa = d.id_empresa
WHERE COALESCE(d.eliminado, false) = false
  AND cf.eliminado = false
  AND d.fecha_caducidad IS NULL
  AND e.fecha_caducidad IS NOT NULL

UNION ALL

SELECT 'retornos_cv_detalles',
       d.id,
       d.id_empresa,
       d.id_consignacion_detalle,
       d.fecha_caducidad,
       e.fecha_caducidad,
       now()
FROM retornos_cv_detalles d
INNER JOIN retornos_cv r ON r.id = d.id_retorno
INNER JOIN consignaciones_ventas_detalles e ON e.id = d.id_consignacion_detalle
                                           AND e.id_empresa = d.id_empresa
WHERE COALESCE(d.eliminado, false) = false
  AND r.eliminado = false
  AND d.fecha_caducidad IS NULL
  AND e.fecha_caducidad IS NOT NULL;

-- Debe coincidir con "a_rellenar" del PASO 0.
SELECT tabla, id_empresa, COUNT(*) AS respaldadas
FROM _cmg_cv_caducidad_bk GROUP BY 1, 2 ORDER BY 2, 1;


-- ─────────────────────────────────────────────────────────────────────────────────────
-- PASO 2A (MODIFICA). Facturación de consignaciones.
-- Ejecutar solo después de revisar los PASOS 0 y 1.
-- Para hacerlo por empresa, descomente la línea del filtro.
-- ─────────────────────────────────────────────────────────────────────────────────────
UPDATE consignaciones_facturas_detalles AS d
   SET fecha_caducidad = e.fecha_caducidad
  FROM consignaciones_ventas_detalles AS e,
       consignaciones_facturas AS cf
 WHERE e.id = d.id_consignacion_detalle
   AND e.id_empresa = d.id_empresa
   AND cf.id = d.id_consignacion_factura
   AND cf.eliminado = false
   AND COALESCE(d.eliminado, false) = false
   AND d.fecha_caducidad IS NULL
   AND e.fecha_caducidad IS NOT NULL
   -- AND d.id_empresa = 23
;


-- ─────────────────────────────────────────────────────────────────────────────────────
-- PASO 2B (MODIFICA). Retornos de consignaciones. Mismo criterio que el 2A.
-- ─────────────────────────────────────────────────────────────────────────────────────
UPDATE retornos_cv_detalles AS d
   SET fecha_caducidad = e.fecha_caducidad
  FROM consignaciones_ventas_detalles AS e,
       retornos_cv AS r
 WHERE e.id = d.id_consignacion_detalle
   AND e.id_empresa = d.id_empresa
   AND r.id = d.id_retorno
   AND r.eliminado = false
   AND COALESCE(d.eliminado, false) = false
   AND d.fecha_caducidad IS NULL
   AND e.fecha_caducidad IS NOT NULL
   -- AND d.id_empresa = 23
;


-- ─────────────────────────────────────────────────────────────────────────────────────
-- PASO 3 (SOLO LECTURA). Verificación: la cobertura debe subir a ~100% en ambas tablas.
-- Lo que quede sin vencimiento debe ser solo lo que el PASO 0 marcó como no recuperable.
-- ─────────────────────────────────────────────────────────────────────────────────────
SELECT 'consignaciones_facturas_detalles' AS tabla, d.id_empresa,
       COUNT(*)                            AS lineas,
       COUNT(d.fecha_caducidad)            AS con_vencimiento,
       COUNT(*) - COUNT(d.fecha_caducidad) AS sin_vencimiento,
       ROUND(100.0 * COUNT(d.fecha_caducidad) / NULLIF(COUNT(*), 0), 1) AS pct
FROM consignaciones_facturas_detalles d
INNER JOIN consignaciones_facturas cf ON cf.id = d.id_consignacion_factura
WHERE COALESCE(d.eliminado, false) = false AND cf.eliminado = false
GROUP BY 1, 2

UNION ALL

SELECT 'retornos_cv_detalles', d.id_empresa,
       COUNT(*), COUNT(d.fecha_caducidad), COUNT(*) - COUNT(d.fecha_caducidad),
       ROUND(100.0 * COUNT(d.fecha_caducidad) / NULLIF(COUNT(*), 0), 1)
FROM retornos_cv_detalles d
INNER JOIN retornos_cv r ON r.id = d.id_retorno
WHERE COALESCE(d.eliminado, false) = false AND r.eliminado = false
GROUP BY 1, 2

ORDER BY 2, 1;


-- Coherencia: ninguna línea debe discrepar de su línea de consignación. Esperado: 0 filas.
SELECT 'consignaciones_facturas_detalles' AS tabla, d.id_empresa, COUNT(*) AS discrepantes
FROM consignaciones_facturas_detalles d
INNER JOIN consignaciones_ventas_detalles e ON e.id = d.id_consignacion_detalle
                                           AND e.id_empresa = d.id_empresa
WHERE COALESCE(d.eliminado, false) = false
  AND d.fecha_caducidad IS NOT NULL
  AND e.fecha_caducidad IS NOT NULL
  AND d.fecha_caducidad <> e.fecha_caducidad
GROUP BY 1, 2

UNION ALL

SELECT 'retornos_cv_detalles', d.id_empresa, COUNT(*)
FROM retornos_cv_detalles d
INNER JOIN consignaciones_ventas_detalles e ON e.id = d.id_consignacion_detalle
                                           AND e.id_empresa = d.id_empresa
WHERE COALESCE(d.eliminado, false) = false
  AND d.fecha_caducidad IS NOT NULL
  AND e.fecha_caducidad IS NOT NULL
  AND d.fecha_caducidad <> e.fecha_caducidad
GROUP BY 1, 2

ORDER BY 2, 1;


-- ─────────────────────────────────────────────────────────────────────────────────────
-- REVERSA (solo si hace falta deshacer). Requiere el respaldo del PASO 1.
-- ─────────────────────────────────────────────────────────────────────────────────────
-- UPDATE consignaciones_facturas_detalles AS d
--    SET fecha_caducidad = b.fecha_caducidad_antes
--   FROM _cmg_cv_caducidad_bk AS b
--  WHERE b.id = d.id AND b.tabla = 'consignaciones_facturas_detalles';
--
-- UPDATE retornos_cv_detalles AS d
--    SET fecha_caducidad = b.fecha_caducidad_antes
--   FROM _cmg_cv_caducidad_bk AS b
--  WHERE b.id = d.id AND b.tabla = 'retornos_cv_detalles';

-- Al terminar y verificar, el respaldo se puede eliminar:
-- DROP TABLE _cmg_cv_caducidad_bk;
