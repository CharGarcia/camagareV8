-- ============================================================================
-- DIAGNÓSTICO (SOLO LECTURA) · Consignaciones de Venta: stock y costo afectados
-- ----------------------------------------------------------------------------
-- Qué mide (tres errores del módulo, verificados el 16-09-2026):
--
--   A) Consignaciones ELIMINADAS cuyo inventario nunca volvió a la bodega.
--      ConsignacionVentaService::eliminar() leía las líneas después de marcarlas
--      eliminadas, así que no registraba el reverso: sus salidas siguen vigentes.
--
--   B) Consignaciones EDITADAS con las salidas anteriores todavía vigentes.
--      Al editar se insertaba una entrada de reverso (EDICION_CONSIGNACION_VENTA)
--      sin anular las salidas viejas: el asiento de la consignación suma las dos
--      tandas (costo duplicado). La columna "cuadra" dice si las salidas viejas y
--      los reversos se cancelan exactamente por producto y bodega, que es la
--      condición para poder corregirlo automáticamente sin mover el stock.
--
--   C) Entradas a COSTO 0 que bajan el costo promedio de los productos
--      (reversos de edición/eliminación y entradas por retorno de consignación).
--
-- Toca datos: NO. Solo SELECT. Se puede ejecutar en cualquier momento.
--
-- Cómo usarlo en pgAdmin: Query Tool → pegar TODO → F5. Muestra el resultado de la
-- última consulta; para ver cada bloque, seleccionar esa consulta y F5.
-- Pasarle a soporte el resultado de las consultas 1, 2 y 3.
-- ============================================================================


-- ============================================================================
-- 1) RESUMEN POR EMPRESA
-- ============================================================================
WITH eliminadas AS (
    SELECT cv.id_empresa,
           COUNT(DISTINCT cv.id)  AS consignaciones,
           SUM(-k.cantidad)       AS unidades_sin_devolver,
           SUM(k.costo_total)     AS costo_sin_devolver
      FROM consignaciones_ventas cv
      JOIN inventario_kardex k
        ON k.id_empresa = cv.id_empresa AND k.referencia_tipo = 'CONSIGNACION_VENTA'
       AND k.referencia_id = cv.id AND k.tipo_movimiento = 'salida' AND k.eliminado = false
     WHERE cv.eliminado = true
       AND NOT EXISTS (SELECT 1 FROM inventario_kardex r
                        WHERE r.id_empresa = cv.id_empresa AND r.referencia_tipo = 'ELIMINACION_CONSIGNACION_VENTA'
                          AND r.referencia_id = cv.id AND r.eliminado = false)
     GROUP BY cv.id_empresa
),
ultimo_reverso AS (
    SELECT id_empresa, referencia_id AS id_consignacion, MAX(id) AS id_ultimo
      FROM inventario_kardex
     WHERE referencia_tipo = 'EDICION_CONSIGNACION_VENTA' AND eliminado = false
     GROUP BY id_empresa, referencia_id
),
editadas AS (
    SELECT u.id_empresa,
           COUNT(DISTINCT u.id_consignacion) AS consignaciones,
           SUM(k.costo_total)                AS costo_salidas_viejas_vigentes
      FROM ultimo_reverso u
      JOIN inventario_kardex k
        ON k.id_empresa = u.id_empresa AND k.referencia_tipo = 'CONSIGNACION_VENTA'
       AND k.referencia_id = u.id_consignacion AND k.tipo_movimiento = 'salida'
       AND k.eliminado = false AND k.id < u.id_ultimo
     GROUP BY u.id_empresa
),
costo_cero AS (
    SELECT id_empresa, COUNT(*) AS entradas_costo_cero, SUM(cantidad) AS unidades
      FROM inventario_kardex
     WHERE eliminado = false AND tipo_movimiento = 'entrada' AND cantidad > 0 AND COALESCE(costo_total, 0) = 0
       AND referencia_tipo IN ('EDICION_CONSIGNACION_VENTA', 'ELIMINACION_CONSIGNACION_VENTA', 'RETORNO_CV', 'CAMBIO_ESTADO_RETORNO_CV')
     GROUP BY id_empresa
)
SELECT e.id AS id_empresa, e.nombre, e.ruc,
       COALESCE(el.consignaciones, 0)            AS a_eliminadas_sin_devolver,
       COALESCE(el.unidades_sin_devolver, 0)     AS a_unidades,
       COALESCE(el.costo_sin_devolver, 0)        AS a_costo,
       COALESCE(ed.consignaciones, 0)            AS b_editadas_costo_duplicado,
       COALESCE(ed.costo_salidas_viejas_vigentes, 0) AS b_costo_de_mas,
       COALESCE(cc.entradas_costo_cero, 0)       AS c_entradas_costo_cero,
       COALESCE(cc.unidades, 0)                  AS c_unidades
  FROM empresas e
  LEFT JOIN eliminadas el ON el.id_empresa = e.id
  LEFT JOIN editadas   ed ON ed.id_empresa = e.id
  LEFT JOIN costo_cero cc ON cc.id_empresa = e.id
 WHERE el.id_empresa IS NOT NULL OR ed.id_empresa IS NOT NULL OR cc.id_empresa IS NOT NULL
 ORDER BY a_unidades DESC, b_costo_de_mas DESC;


-- ============================================================================
-- 2) DETALLE A · Consignaciones eliminadas sin devolver el inventario
--    tiene_documentos = hay retornos, facturaciones o cambios (no eliminados)
--      que la usan: esas NO se pueden corregir a ciegas.
--    ajustes_posteriores = ajustes manuales del mismo producto/bodega después
--      de eliminarla: alguien pudo haber corregido el stock a mano.
-- ============================================================================
SELECT cv.id_empresa, cv.id AS id_consignacion, cv.serie || '-' || cv.secuencial AS numero,
       cv.fecha_emision, cv.deleted_at, cv.estado,
       k.id_producto, p.codigo, p.nombre AS producto, k.id_bodega,
       -k.cantidad AS unidades_sin_devolver, k.costo_total,
       EXISTS (SELECT 1 FROM retornos_cv_detalles d JOIN retornos_cv r ON r.id = d.id_retorno
                WHERE d.id_consignacion = cv.id AND d.eliminado = false AND r.eliminado = false)
    OR EXISTS (SELECT 1 FROM consignaciones_facturas_detalles d JOIN consignaciones_facturas f ON f.id = d.id_consignacion_factura
                WHERE d.id_consignacion = cv.id AND d.eliminado = false AND f.eliminado = false AND f.estado <> 'anulada')
    OR EXISTS (SELECT 1 FROM cambios_producto_cv_detalles d JOIN cambios_producto_cv c ON c.id = d.id_cambio
                WHERE d.origen_tipo = 'CONSIGNACION' AND d.id_origen = cv.id AND d.eliminado = false AND c.eliminado = false)
       AS tiene_documentos,
       (SELECT COUNT(*) FROM inventario_kardex a
         WHERE a.id_empresa = k.id_empresa AND a.id_producto = k.id_producto AND a.id_bodega = k.id_bodega
           AND a.referencia_tipo = 'ajuste_manual' AND a.eliminado = false
           AND a.fecha_movimiento >= COALESCE(cv.deleted_at, cv.updated_at)) AS ajustes_posteriores
  FROM consignaciones_ventas cv
  JOIN inventario_kardex k
    ON k.id_empresa = cv.id_empresa AND k.referencia_tipo = 'CONSIGNACION_VENTA'
   AND k.referencia_id = cv.id AND k.tipo_movimiento = 'salida' AND k.eliminado = false
  LEFT JOIN productos p ON p.id = k.id_producto
 WHERE cv.eliminado = true
   AND NOT EXISTS (SELECT 1 FROM inventario_kardex r
                    WHERE r.id_empresa = cv.id_empresa AND r.referencia_tipo = 'ELIMINACION_CONSIGNACION_VENTA'
                      AND r.referencia_id = cv.id AND r.eliminado = false)
 ORDER BY cv.id_empresa, cv.deleted_at DESC, cv.id, k.id_producto;


-- ============================================================================
-- 3) DETALLE B · Consignaciones editadas con salidas viejas vigentes
--    cuadra = las salidas viejas y los reversos de edición se cancelan por
--      producto y bodega (se pueden anular juntos sin mover el stock).
--    tiene_asiento / fecha_asiento: el asiento a regenerar después.
-- ============================================================================
WITH ultimo_reverso AS (
    SELECT id_empresa, referencia_id AS id_consignacion, MAX(id) AS id_ultimo
      FROM inventario_kardex
     WHERE referencia_tipo = 'EDICION_CONSIGNACION_VENTA' AND eliminado = false
     GROUP BY id_empresa, referencia_id
),
movs AS (
    SELECT u.id_empresa, u.id_consignacion, k.id_producto, k.id_bodega,
           SUM(CASE WHEN k.referencia_tipo = 'CONSIGNACION_VENTA' THEN k.cantidad ELSE 0 END)   AS salidas_viejas,
           SUM(CASE WHEN k.referencia_tipo = 'EDICION_CONSIGNACION_VENTA' THEN k.cantidad ELSE 0 END) AS reversos,
           SUM(CASE WHEN k.referencia_tipo = 'CONSIGNACION_VENTA' THEN k.costo_total ELSE 0 END) AS costo_viejo
      FROM ultimo_reverso u
      JOIN inventario_kardex k
        ON k.id_empresa = u.id_empresa AND k.referencia_id = u.id_consignacion AND k.eliminado = false
       AND ((k.referencia_tipo = 'CONSIGNACION_VENTA' AND k.tipo_movimiento = 'salida' AND k.id < u.id_ultimo)
         OR  k.referencia_tipo = 'EDICION_CONSIGNACION_VENTA')
     GROUP BY u.id_empresa, u.id_consignacion, k.id_producto, k.id_bodega
)
SELECT m.id_empresa, m.id_consignacion, cv.serie || '-' || cv.secuencial AS numero, cv.fecha_emision, cv.estado,
       cv.eliminado AS consignacion_eliminada,
       SUM(m.costo_viejo)                              AS costo_de_mas_en_asiento,
       BOOL_AND(m.salidas_viejas + m.reversos = 0)     AS cuadra,
       cv.id_asiento_contable IS NOT NULL              AS tiene_asiento,
       a.fecha_asiento
  FROM movs m
  JOIN consignaciones_ventas cv ON cv.id = m.id_consignacion
  LEFT JOIN asientos_contables_cabecera a ON a.id = cv.id_asiento_contable
 GROUP BY m.id_empresa, m.id_consignacion, cv.serie, cv.secuencial, cv.fecha_emision, cv.estado, cv.eliminado, cv.id_asiento_contable, a.fecha_asiento
 ORDER BY m.id_empresa, cv.fecha_emision DESC;
