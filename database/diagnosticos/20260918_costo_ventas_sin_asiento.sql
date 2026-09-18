-- =============================================================================
-- 20260918_costo_ventas_sin_asiento.sql — SOLO LECTURA
-- -----------------------------------------------------------------------------
-- Qué hace  : clasifica las facturas de venta autorizadas según POR QUÉ su asiento
--             tiene (o no) las líneas de Costo de Ventas / Inventario, siguiendo el
--             mismo orden de decisión que AsientoBuilderService:
--               kardex → asiento existente → reglas del cliente → descuento →
--               cuentas configuradas.
-- Resultado : una fila por empresa y causa, con el número de facturas, el costo
--             del kardex involucrado y hasta 5 facturas de ejemplo.
-- Toca datos: NO. Solo SELECT.
-- Parámetros: en el CTE «par» (id de empresa o NULL para todas, y fecha desde).
-- Cómo      : pgAdmin → Query Tool sobre producción → F5 → copiar el resultado.
-- =============================================================================
WITH par AS (
    SELECT NULL::int                   AS id_empresa,   -- ← id de la empresa, o NULL = todas
           (CURRENT_DATE - 60)::date   AS desde         -- ← fecha de emisión desde
),
tipos AS (   -- conceptos de ventas_factura (Costo, Inventario, Descuento… se reconocen igual que el builder)
    SELECT id,
           (UPPER(COALESCE(codigo,'')) LIKE '%COSTO%'      OR LOWER(COALESCE(referencia,'')) LIKE '%costo%')      AS es_costo,
           (UPPER(COALESCE(codigo,'')) LIKE '%INVENTARIO%' OR LOWER(COALESCE(referencia,'')) LIKE '%inventario%') AS es_inventario,
           (UPPER(COALESCE(codigo,'')) LIKE '%DESC%'       OR LOWER(COALESCE(referencia,'')) LIKE '%descuento%')  AS es_descuento
    FROM asientos_tipo
    WHERE tipo_asiento = 'ventas_factura' AND eliminado = false
),
fac AS (
    SELECT v.id, v.id_empresa, v.id_cliente, v.id_asiento_contable,
           COALESCE(v.total_descuento, 0) AS descuento,
           LPAD(v.establecimiento::text, 3, '0') || '-' || LPAD(v.punto_emision::text, 3, '0') || '-'
               || LPAD(v.secuencial::text, 9, '0') AS numero
    FROM ventas_cabecera v
    CROSS JOIN par
    WHERE v.eliminado = false
      AND v.estado IN ('autorizado', 'contabilizado')
      AND v.fecha_emision >= par.desde
      AND (par.id_empresa IS NULL OR v.id_empresa = par.id_empresa)
),
lin AS (     -- líneas que deberían sacar inventario
    SELECT d.id_venta,
           COUNT(*) FILTER (WHERE p.inventariable AND COALESCE(p.tipo_produccion, '01') <> '02')                        AS inventariables,
           COUNT(*) FILTER (WHERE p.inventariable AND COALESCE(p.tipo_produccion, '01') <> '02' AND d.id_bodega IS NULL) AS sin_bodega
    FROM ventas_detalle d
    JOIN fac f ON f.id = d.id_venta
    LEFT JOIN productos p ON p.id = d.id_producto
    GROUP BY d.id_venta
),
kx AS (      -- salidas del kardex (de aquí sale el monto del costo)
    SELECT k.referencia_id AS id_venta, COUNT(*) AS movs, ROUND(SUM(k.costo_total)::numeric, 2) AS costo
    FROM inventario_kardex k
    JOIN fac f ON f.id = k.referencia_id
    WHERE k.referencia_tipo = 'factura_venta' AND k.tipo_movimiento = 'salida' AND k.eliminado = false
    GROUP BY k.referencia_id
),
asi AS (     -- asiento guardado (to_jsonb: no falla si la columna editado_manual no existe)
    SELECT c.id, c.modulo_origen,
           COALESCE((to_jsonb(c) ->> 'editado_manual')::boolean, false)       AS editado_manual,
           COALESCE(bool_or(d.referencia_detalle ILIKE '%costo%'), false)     AS tiene_costo
    FROM asientos_contables_cabecera c
    LEFT JOIN asientos_contables_detalle d ON d.id_asiento = c.id AND d.eliminado = false
    WHERE c.id IN (SELECT id_asiento_contable FROM fac WHERE id_asiento_contable IS NOT NULL)
    GROUP BY c.id
),
cli AS (     -- reglas del cliente: las de ventas_factura vs. las demás (IVA propio = id_asiento_tipo 0, otros documentos)
    SELECT f.id AS id_venta,
           COUNT(*) FILTER (WHERE t.id IS NOT NULL) AS reglas_venta,
           COUNT(*) FILTER (WHERE t.id IS NULL)     AS reglas_otras,
           COALESCE(bool_or(t.es_costo), false)      AS costo,
           COALESCE(bool_or(t.es_inventario), false) AS inventario
    FROM fac f
    JOIN asientos_programados ap ON ap.id_empresa = f.id_empresa AND ap.tipo_referencia = 'cliente'
                                AND ap.id_referencia = f.id_cliente AND ap.eliminado = false
    JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
    LEFT JOIN tipos t ON t.id = ap.id_asiento_tipo
    GROUP BY f.id
),
gen AS (     -- cuentas en la configuración General
    SELECT ap.id_empresa,
           bool_or(t.es_costo)     AS costo,
           bool_or(t.es_inventario) AS inventario,
           bool_or(t.es_descuento)  AS descuento
    FROM asientos_programados ap
    JOIN tipos t ON t.id = ap.id_asiento_tipo
    WHERE ap.eliminado = false AND ap.id_cuenta IS NOT NULL
      AND ap.id_referencia = ap.id_asiento_tipo
      AND ap.tipo_referencia IN ('asientos tipo', 'ventas_factura')
    GROUP BY ap.id_empresa
),
dim AS (     -- Costo / Inventario configurados por producto, categoría, marca o tipo de producción
    SELECT ap.id_empresa,
           bool_or(t.es_costo)      AS costo,
           bool_or(t.es_inventario) AS inventario
    FROM asientos_programados ap
    JOIN tipos t ON t.id = ap.id_asiento_tipo
    WHERE ap.eliminado = false AND ap.id_cuenta IS NOT NULL
      AND ap.tipo_referencia IN ('producto', 'categoria', 'marca', 'tipo_produccion')
    GROUP BY ap.id_empresa
),
det AS (
    SELECT f.id_empresa, f.numero, COALESCE(kx.costo, 0) AS costo_kardex,
           CASE
             WHEN asi.modulo_origen = 'migracion'
               THEN '0. Asiento migrado del sistema anterior (no se regenera)'
             WHEN kx.movs IS NULL AND COALESCE(lin.inventariables, 0) = 0
               THEN '1. Sin productos inventariables (servicios): no lleva costo'
             WHEN kx.movs IS NULL AND lin.sin_bodega > 0
               THEN '2. Líneas sin bodega: no se registró la salida de inventario'
             WHEN kx.movs IS NULL
               THEN '3. Sin salida en el kardex (¿«La facturación afecta al inventario» apagada?)'
             WHEN COALESCE(kx.costo, 0) = 0
               THEN '4. Salida con costo 0 (el producto no tenía costo al venderse)'
             WHEN f.id_asiento_contable IS NULL
               THEN '5. Aún sin asiento: la pestaña muestra una vista previa SIN costo'
             WHEN asi.tiene_costo
               THEN '9. OK: el asiento tiene Costo de Ventas'
             WHEN asi.editado_manual
               THEN '6. Asiento editado a mano: no se regenera (Restaurar asiento automático)'
             WHEN COALESCE(cli.reglas_venta, 0) = 0 AND COALESCE(cli.reglas_otras, 0) > 0
                  AND NOT (COALESCE(gen.costo, false) AND COALESCE(gen.inventario, false))
               THEN '7. BUG: el cliente solo tiene cuenta de IVA/otros documentos y eso apaga el reparto por producto/categoría'
             WHEN COALESCE(cli.reglas_venta, 0) > 0
                  AND NOT ((cli.costo OR COALESCE(gen.costo, false)) AND (cli.inventario OR COALESCE(gen.inventario, false)))
               THEN '8. El cliente tiene reglas propias (manda) y ni él ni la General tienen Costo/Inventario'
             WHEN f.descuento > 0 AND COALESCE(gen.descuento, false)
                  AND NOT (COALESCE(gen.costo, false) AND COALESCE(gen.inventario, false))
               THEN '7b. BUG: la cuenta de Descuento apaga el reparto por producto/categoría del costo'
             WHEN NOT (COALESCE(gen.costo, false) OR COALESCE(dim.costo, false))
                  OR NOT (COALESCE(gen.inventario, false) OR COALESCE(dim.inventario, false))
               THEN '8b. Falta configurar la cuenta de Costo de Ventas y/o Inventario'
             ELSE '8c. Revisar: costo configurado por producto/categoría que no cubre estos productos'
           END AS causa
    FROM fac f
    LEFT JOIN lin ON lin.id_venta = f.id
    LEFT JOIN kx  ON kx.id_venta  = f.id
    LEFT JOIN asi ON asi.id       = f.id_asiento_contable
    LEFT JOIN cli ON cli.id_venta = f.id
    LEFT JOIN gen ON gen.id_empresa = f.id_empresa
    LEFT JOIN dim ON dim.id_empresa = f.id_empresa
)
SELECT det.id_empresa,
       e.nombre_comercial,
       det.causa,
       COUNT(*)                         AS facturas,
       SUM(det.costo_kardex)            AS costo_kardex,
       (array_agg(det.numero ORDER BY det.numero DESC))[1:5] AS ejemplos
FROM det
LEFT JOIN empresas e ON e.id = det.id_empresa
GROUP BY det.id_empresa, e.nombre_comercial, det.causa
ORDER BY det.id_empresa, det.causa;
