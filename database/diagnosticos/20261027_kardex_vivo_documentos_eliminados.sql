-- =====================================================================================
-- Diagnóstico (SOLO LECTURA): movimientos de kardex que siguen vivos de documentos
-- eliminados, anulados o que ya no existen.
--
-- Origen: consignación id 138 (empresa 0993408339001) se eliminó cuando
-- ConsignacionVentaService::eliminar() leía las líneas DESPUÉS de marcarlas eliminadas;
-- no revirtió nada y el stock quedó descontado dos veces (se rehízo como id 139).
-- Corregida el 2026-10-27.
--
-- Criterio: por documento se suma la cantidad de TODOS sus movimientos vivos
-- (eliminado = false), incluidas las entradas de reverso (ELIMINACION_*, CAMBIO_ESTADO_*,
-- EDICION_*). Si el documento está eliminado / anulado / no existe, ese neto debe ser 0.
-- Neto <> 0 = stock que salió (negativo) o entró (positivo) y nunca se devolvió.
--
-- Columnas que no existen en todas las tablas (eliminado, estado) se leen con to_jsonb
-- para que la consulta no falle por esquema; solo se aplica a las tablas pequeñas.
-- =====================================================================================

WITH k AS (
    SELECT k.id_empresa, k.referencia_id, k.cantidad, k.id_producto, k.id_bodega,
           CASE
             WHEN k.referencia_tipo = 'factura_venta'                     THEN 'factura_venta'
             WHEN k.referencia_tipo = 'recibo_venta'                      THEN 'recibo_venta'
             WHEN k.referencia_tipo = 'nota_credito'                      THEN 'nota_credito'
             WHEN k.referencia_tipo IN ('compra', 'nota_credito_compra')  THEN 'compra'
             WHEN k.referencia_tipo ILIKE '%CONSIGNACION_VENTA'           THEN 'consignacion_venta'
             WHEN k.referencia_tipo ILIKE '%FACTURACION_CV'               THEN 'facturacion_cv'
             WHEN k.referencia_tipo ILIKE '%RETORNO_CV'                   THEN 'retorno_cv'
             WHEN k.referencia_tipo ILIKE '%CAMBIO_PRODUCTO_CV%'          THEN 'cambio_producto_cv'
             WHEN k.referencia_tipo = 'carga_inventario'                  THEN 'carga_inventario'
             WHEN k.referencia_tipo = 'transferencia_inventario'          THEN 'transferencia_inventario'
             WHEN k.referencia_tipo = 'importacion'                       THEN 'importacion'
             WHEN k.referencia_tipo = 'taller_orden'                      THEN 'taller_orden'
             WHEN k.referencia_tipo = 'carwash_orden'                     THEN 'carwash_orden'
             WHEN k.referencia_tipo = 'servicioexterno_orden'             THEN 'servicioexterno_orden'
           END AS grupo
    FROM inventario_kardex k
    WHERE k.eliminado = false
      AND k.referencia_id IS NOT NULL
),
docs AS (
    -- Documentos eliminados o anulados (los vigentes no interesan aquí).
    SELECT 'factura_venta' AS grupo, v.id_empresa, v.id, v.eliminado, v.estado::text AS estado,
           v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial AS numero
      FROM ventas_cabecera v
     WHERE v.eliminado = true OR UPPER(v.estado::text) LIKE 'ANULAD%'
    UNION ALL
    SELECT 'recibo_venta', r.id_empresa, r.id, r.eliminado, r.estado::text,
           r.establecimiento || '-' || r.punto_emision || '-' || r.secuencial
      FROM recibos_venta_cabecera r
     WHERE r.eliminado = true OR UPPER(r.estado::text) LIKE 'ANULAD%'
    UNION ALL
    SELECT 'nota_credito', n.id_empresa, n.id, n.eliminado, n.estado::text,
           n.establecimiento || '-' || n.punto_emision || '-' || n.secuencial
      FROM notas_credito_cabecera n
     WHERE n.eliminado = true OR UPPER(n.estado::text) LIKE 'ANULAD%'
    UNION ALL
    SELECT 'consignacion_venta', cv.id_empresa, cv.id, cv.eliminado, cv.estado::text,
           cv.serie || '-' || cv.secuencial
      FROM consignaciones_ventas cv
     WHERE cv.eliminado = true OR UPPER(cv.estado::text) LIKE 'ANULAD%'
    UNION ALL
    SELECT 'facturacion_cv', f.id_empresa, f.id, f.eliminado, f.estado::text,
           COALESCE(f.numero_factura, f.establecimiento || '-' || f.punto_emision || '-' || f.secuencial)
      FROM consignaciones_facturas f
     WHERE f.eliminado = true OR UPPER(f.estado::text) LIKE 'ANULAD%'
    UNION ALL
    SELECT 'retorno_cv', r.id_empresa, r.id, r.eliminado, r.estado::text,
           r.establecimiento || '-' || r.punto_emision || '-' || r.secuencial
      FROM retornos_cv r
     WHERE r.eliminado = true OR UPPER(r.estado::text) LIKE 'ANULAD%'
    UNION ALL
    SELECT 'cambio_producto_cv', cp.id_empresa, cp.id, cp.eliminado, cp.estado::text,
           cp.establecimiento || '-' || cp.punto_emision || '-' || cp.secuencial
      FROM cambios_producto_cv cp
     WHERE cp.eliminado = true OR UPPER(cp.estado::text) LIKE 'ANULAD%'
    UNION ALL
    SELECT 'carga_inventario', c.id_empresa, c.id, c.eliminado, c.estado::text, c.numero::text
      FROM inventario_cargas c
     WHERE c.eliminado = true OR UPPER(c.estado::text) LIKE 'ANULAD%' OR UPPER(c.estado::text) LIKE 'RECHAZAD%'
    UNION ALL
    SELECT 'transferencia_inventario', t.id_empresa, t.id, t.eliminado, to_jsonb(t)->>'estado', COALESCE(to_jsonb(t)->>'numero', t.id::text)
      FROM transferencias_inventario_cabecera t
     WHERE t.eliminado = true OR UPPER(COALESCE(to_jsonb(t)->>'estado', '')) LIKE 'ANULAD%'
    UNION ALL
    SELECT 'taller_orden', o.id_empresa, o.id, o.eliminado, to_jsonb(o)->>'estado', COALESCE(to_jsonb(o)->>'numero', o.id::text)
      FROM taller_ordenes o
     WHERE o.eliminado = true OR UPPER(COALESCE(to_jsonb(o)->>'estado', '')) LIKE 'ANULAD%'
    UNION ALL
    SELECT 'carwash_orden', o.id_empresa, o.id, o.eliminado, to_jsonb(o)->>'estado', COALESCE(to_jsonb(o)->>'numero', o.id::text)
      FROM carwash_ordenes o
     WHERE o.eliminado = true OR UPPER(COALESCE(to_jsonb(o)->>'estado', '')) LIKE 'ANULAD%'
    UNION ALL
    SELECT 'servicioexterno_orden', o.id_empresa, o.id, o.eliminado, to_jsonb(o)->>'estado', COALESCE(to_jsonb(o)->>'numero', o.id::text)
      FROM servicioexterno_ordenes o
     WHERE o.eliminado = true OR UPPER(COALESCE(to_jsonb(o)->>'estado', '')) LIKE 'ANULAD%'
),
-- Documentos con referencia_id = id de DETALLE (compras, importaciones): eliminado si la
-- cabecera o el detalle están eliminados, o si el detalle ya no existe.
detalle AS (
    SELECT k.grupo, k.id_empresa, k.referencia_id AS id,
           true AS eliminado,
           CASE WHEN d.id IS NULL THEN 'DETALLE NO EXISTE' ELSE 'ELIMINADO' END AS estado,
           COALESCE(cc.establecimiento_prov || '-' || cc.punto_emision_prov || '-' || cc.secuencial_prov,
                    'detalle ' || k.referencia_id) AS numero
      FROM (SELECT DISTINCT grupo, id_empresa, referencia_id FROM k WHERE grupo = 'compra') k
      LEFT JOIN compras_detalle d   ON d.id = k.referencia_id
      LEFT JOIN compras_cabecera cc ON cc.id = d.id_compra
     WHERE d.id IS NULL OR cc.eliminado = true OR COALESCE(to_jsonb(d)->>'eliminado', 'false') = 'true'
    UNION ALL
    SELECT k.grupo, k.id_empresa, k.referencia_id,
           true,
           CASE WHEN d.id IS NULL THEN 'DETALLE NO EXISTE' ELSE 'ELIMINADO' END,
           'importación ' || COALESCE(ic.id::text, '?') || ' / detalle ' || k.referencia_id
      FROM (SELECT DISTINCT grupo, id_empresa, referencia_id FROM k WHERE grupo = 'importacion') k
      LEFT JOIN importaciones_detalle d   ON d.id = k.referencia_id
      LEFT JOIN importaciones_cabecera ic ON ic.id = (to_jsonb(d)->>'id_importacion')::int
     WHERE d.id IS NULL OR ic.eliminado = true OR COALESCE(to_jsonb(d)->>'eliminado', 'false') = 'true'
),
todos AS (
    SELECT grupo, id_empresa, id, eliminado, estado, numero FROM docs
    UNION ALL
    SELECT grupo, id_empresa, id, eliminado, estado, numero FROM detalle
)
SELECT e.ruc, e.nombre AS empresa, d.grupo, d.id AS id_documento, d.numero, d.estado, d.eliminado,
       COUNT(*)                          AS movimientos_vivos,
       COUNT(DISTINCT k.id_producto)     AS productos,
       SUM(k.cantidad)                   AS neto_kardex_vivo   -- debería ser 0
FROM todos d
JOIN k ON k.grupo = d.grupo AND k.referencia_id = d.id AND k.id_empresa = d.id_empresa
JOIN empresas e ON e.id = d.id_empresa
GROUP BY e.ruc, e.nombre, d.grupo, d.id, d.numero, d.estado, d.eliminado
HAVING ABS(SUM(k.cantidad)) > 0.000001
ORDER BY e.ruc, d.grupo, d.id;


-- =====================================================================================
-- 2) Doble descuento en documentos VIGENTES (solo lectura).
--
-- Origen: al convertir recibo → factura, factura → recibo, o generar la factura de una
-- orden de Taller / Servicio Externo, si la creación fallaba el rollBack ya restauraba
-- las salidas originales y el catch las volvía a registrar: quedan dos juegos idénticos.
--
-- Firma: mismo documento + producto + bodega + lote + cantidad, vivo más de una vez y
-- registrado en momentos distintos (las líneas de un mismo guardado comparten segundo;
-- la "restauración" llega después). Revisar a mano antes de corregir: dos guardados
-- legítimos con la misma línea darían el mismo patrón.
-- =====================================================================================

SELECT e.ruc, k.referencia_tipo, k.referencia_id, p.codigo, k.id_bodega,
       COALESCE(k.numero_lote, '') AS lote, k.cantidad,
       COUNT(*)                     AS veces,
       MIN(k.fecha_movimiento)      AS primera,
       MAX(k.fecha_movimiento)      AS ultima,
       string_agg(k.id::text, ', ' ORDER BY k.id) AS ids_kardex
FROM inventario_kardex k
JOIN empresas e  ON e.id = k.id_empresa
JOIN productos p ON p.id = k.id_producto
WHERE k.eliminado = false
  AND k.tipo_movimiento = 'salida'
  AND k.referencia_tipo IN ('recibo_venta', 'factura_venta', 'taller_orden', 'servicioexterno_orden')
  AND k.referencia_id IS NOT NULL
GROUP BY e.ruc, k.referencia_tipo, k.referencia_id, p.codigo, k.id_bodega, COALESCE(k.numero_lote, ''), k.cantidad
HAVING COUNT(*) > 1
   AND MAX(k.fecha_movimiento) - MIN(k.fecha_movimiento) > interval '2 seconds'
ORDER BY e.ruc, k.referencia_tipo, k.referencia_id;


-- =====================================================================================
-- 3) Saldo de bodega (productos_bodegas.stock_actual) distinto del kardex (solo lectura).
--
-- Origen: al revertir un movimiento en "modo tolerante" (anular/eliminar NC, factura o
-- recibo con el stock ya consumido) el saldo se forzaba a 0 aunque el kardex quedara en
-- negativo, y revertir un movimiento de otro ambiente movía el saldo del ambiente actual.
-- Compara contra el kardex del ambiente ACTUAL de cada empresa (el que usa el sistema).
-- Corregido en el código el 2026-10-27; esto encuentra lo que quedó desfasado antes.
-- =====================================================================================

SELECT e.ruc, pb.id_empresa, p.codigo, p.nombre, b.nombre AS bodega,
       pb.stock_actual                               AS saldo_bodega,
       COALESCE(k.suma, 0)                           AS saldo_kardex,
       ROUND(pb.stock_actual - COALESCE(k.suma, 0), 6) AS diferencia
FROM productos_bodegas pb
JOIN empresas e  ON e.id = pb.id_empresa
JOIN productos p ON p.id = pb.id_producto
JOIN bodegas b   ON b.id = pb.id_bodega
LEFT JOIN LATERAL (
    SELECT SUM(k.cantidad) AS suma
    FROM inventario_kardex k
    WHERE k.id_empresa = pb.id_empresa AND k.id_producto = pb.id_producto AND k.id_bodega = pb.id_bodega
      AND k.eliminado = false AND k.tipo_ambiente = CAST(e.tipo_ambiente AS VARCHAR(1))
) k ON true
WHERE pb.eliminado = false
  AND ABS(pb.stock_actual - COALESCE(k.suma, 0)) > 0.01
ORDER BY e.ruc, ABS(pb.stock_actual - COALESCE(k.suma, 0)) DESC;
