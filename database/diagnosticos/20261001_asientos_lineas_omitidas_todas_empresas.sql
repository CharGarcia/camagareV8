-- =============================================================================
-- Diagnóstico (SOLO LECTURA) — TODAS LAS EMPRESAS: asientos de Facturas de Venta
-- y Recibos de Venta que quedaron INCOMPLETOS porque una línea del documento no
-- encontró cuenta en ninguna regla y se omitió en silencio (caso GOLIFE, factura
-- 001-001-000000104 / asiento VE-000212). Desde el 01-10-2026 eso ya se bloquea,
-- pero los asientos generados antes siguen así.
--
-- Señal del error: la CARTERA del asiento (líneas en cuentas configuradas como
-- Cuenta por Cobrar, lado Debe) es MENOR que el total del documento. Como el
-- producto faltaba a la vez en CxC, Subtotal e IVA, el asiento cuadraba igual.
--
-- Son 2 consultas: seleccione una (desde "-- N." hasta su punto y coma) y F5.
--   1. Resumen por empresa.
--   2. Detalle de cada documento.
--
-- Columnas clave de la consulta 2:
--   diferencia          total del documento - cartera del asiento
--   productos_sin_regla productos del documento que HOY no tienen cuenta por cobrar
--                       en ninguna regla (producto, categoría, marca, tipo de
--                       producción, cliente ni General) — normalmente sin categoría.
--   situacion           · LÍNEA OMITIDA → es este error. Asigne la categoría (o la
--                         regla) a esos productos y regenere el asiento desde
--                         Auditoría Contable.
--                       · las demás → otra causa (cuenta de cartera cambiada,
--                         asiento viejo, editado a mano); revisar aparte.
-- =============================================================================


-- 1. Resumen por empresa ------------------------------------------------------
WITH docs AS (
    SELECT 'factura_venta'::text AS modulo, v.id, v.id_empresa, v.id_cliente,
           v.importe_total AS total, 'PORCOBRARFACTURAVENTA'::text AS slot
      FROM ventas_cabecera v
     WHERE v.eliminado = false AND v.importe_total > 0
    UNION ALL
    SELECT 'recibo_venta', r.id, r.id_empresa, r.id_cliente, r.importe_total, 'PORCOBRARRECIBOVENTA'
      FROM recibos_venta_cabecera r
     WHERE r.eliminado = false AND r.importe_total > 0
),
cartera AS (
    SELECT DISTINCT ap.id_empresa, at.codigo AS slot, ap.id_cuenta
      FROM asientos_programados ap
      JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
     WHERE ap.eliminado = false AND ap.id_cuenta IS NOT NULL
       AND at.codigo IN ('PORCOBRARFACTURAVENTA', 'PORCOBRARRECIBOVENTA')
),
asiento AS (
    SELECT d.modulo, d.id AS id_doc, a.editado_manual,
           ROUND(SUM(ad.debe) FILTER (WHERE EXISTS (
                 SELECT 1 FROM cartera k WHERE k.id_empresa = d.id_empresa AND k.slot = d.slot
                                           AND k.id_cuenta = ad.id_cuenta_contable)), 2) AS cartera_asiento
      FROM docs d
      JOIN asientos_contables_cabecera a
        ON a.modulo_origen = d.modulo AND a.id_referencia_origen = d.id AND a.id_empresa = d.id_empresa
       AND a.eliminado = false AND a.estado <> 'anulado'
      JOIN asientos_contables_detalle ad ON ad.id_asiento = a.id AND ad.eliminado = false
     GROUP BY d.modulo, d.id, a.editado_manual
),
lineas_sin_regla AS (
    -- Líneas cuyo producto HOY no resuelve la Cuenta por Cobrar en ningún nivel de la cascada.
    SELECT x.modulo, x.id_doc, string_agg(DISTINCT x.producto, ', ') AS productos, ROUND(SUM(x.monto), 2) AS subtotal
      FROM (
        SELECT d.modulo, d.id AS id_doc, d.id_empresa, d.id_cliente, d.slot, p.id AS id_producto, p.nombre AS producto,
               p.id_categoria, p.id_marca, p.tipo_produccion, vd.precio_total_sin_impuesto AS monto
          FROM docs d
          JOIN ventas_detalle vd ON d.modulo = 'factura_venta' AND vd.id_venta = d.id
          JOIN productos p ON p.id = vd.id_producto
        UNION ALL
        SELECT d.modulo, d.id, d.id_empresa, d.id_cliente, d.slot, p.id, p.nombre,
               p.id_categoria, p.id_marca, p.tipo_produccion, rd.precio_total_sin_impuesto
          FROM docs d
          JOIN recibos_venta_detalle rd ON d.modulo = 'recibo_venta' AND rd.id_recibo = d.id
          JOIN productos p ON p.id = rd.id_producto
      ) x
     WHERE NOT EXISTS (
            SELECT 1 FROM asientos_programados ap
              JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo AND at.codigo = x.slot
             WHERE ap.id_empresa = x.id_empresa AND ap.eliminado = false AND ap.id_cuenta IS NOT NULL
               AND (   (ap.tipo_referencia = 'producto'  AND ap.id_referencia = x.id_producto)
                    OR (ap.tipo_referencia = 'categoria' AND ap.id_referencia = x.id_categoria)
                    OR (ap.tipo_referencia = 'marca'     AND ap.id_referencia = x.id_marca)
                    OR (ap.tipo_referencia = 'tipo_produccion'
                        AND ap.id_referencia = (CASE x.tipo_produccion WHEN '02' THEN 2 WHEN '01' THEN 1 END))
                    OR (ap.tipo_referencia = 'cliente'   AND ap.id_referencia = x.id_cliente)
                    OR  COALESCE(ap.tipo_referencia, '') NOT IN ('producto', 'categoria', 'marca', 'tipo_produccion',
                                                                 'cliente', 'proveedor', 'item_compra')))
     GROUP BY x.modulo, x.id_doc
)
SELECT e.ruc, e.nombre_comercial AS empresa,
       COUNT(*) FILTER (WHERE l.id_doc IS NOT NULL AND NOT s.editado_manual)       AS lineas_omitidas,
       ROUND(SUM(d.total - COALESCE(s.cartera_asiento, 0))
             FILTER (WHERE l.id_doc IS NOT NULL AND NOT s.editado_manual), 2)     AS monto_faltante,
       COUNT(*) FILTER (WHERE l.id_doc IS NULL OR s.editado_manual)               AS otras_causas
  FROM docs d
  JOIN asiento s ON s.modulo = d.modulo AND s.id_doc = d.id
  LEFT JOIN lineas_sin_regla l ON l.modulo = d.modulo AND l.id_doc = d.id
  JOIN empresas e ON e.id = d.id_empresa
 WHERE d.total - COALESCE(s.cartera_asiento, 0) > 0.03
 GROUP BY e.ruc, e.nombre_comercial
 ORDER BY lineas_omitidas DESC, otras_causas DESC;


-- 2. Detalle por documento ----------------------------------------------------
--    Para una sola empresa, descomente el filtro "<== empresa" al final.
WITH docs AS (
    SELECT 'factura_venta'::text AS modulo, 'Factura de venta'::text AS tipo, v.id, v.id_empresa, v.id_cliente,
           concat_ws('-', v.establecimiento, v.punto_emision, v.secuencial) AS numero,
           v.fecha_emision, v.estado, v.importe_total AS total, 'PORCOBRARFACTURAVENTA'::text AS slot
      FROM ventas_cabecera v
     WHERE v.eliminado = false AND v.importe_total > 0
    UNION ALL
    SELECT 'recibo_venta', 'Recibo de venta', r.id, r.id_empresa, r.id_cliente,
           concat_ws('-', r.establecimiento, r.punto_emision, r.secuencial),
           r.fecha_emision, r.estado, r.importe_total, 'PORCOBRARRECIBOVENTA'
      FROM recibos_venta_cabecera r
     WHERE r.eliminado = false AND r.importe_total > 0
),
cartera AS (
    SELECT DISTINCT ap.id_empresa, at.codigo AS slot, ap.id_cuenta
      FROM asientos_programados ap
      JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
     WHERE ap.eliminado = false AND ap.id_cuenta IS NOT NULL
       AND at.codigo IN ('PORCOBRARFACTURAVENTA', 'PORCOBRARRECIBOVENTA')
),
asiento AS (
    SELECT d.modulo, d.id AS id_doc, a.id AS id_asiento, a.numero_comprobante, a.editado_manual,
           ROUND(SUM(ad.debe) FILTER (WHERE EXISTS (
                 SELECT 1 FROM cartera k WHERE k.id_empresa = d.id_empresa AND k.slot = d.slot
                                           AND k.id_cuenta = ad.id_cuenta_contable)), 2) AS cartera_asiento
      FROM docs d
      JOIN asientos_contables_cabecera a
        ON a.modulo_origen = d.modulo AND a.id_referencia_origen = d.id AND a.id_empresa = d.id_empresa
       AND a.eliminado = false AND a.estado <> 'anulado'
      JOIN asientos_contables_detalle ad ON ad.id_asiento = a.id AND ad.eliminado = false
     GROUP BY d.modulo, d.id, a.id, a.numero_comprobante, a.editado_manual
),
lineas_sin_regla AS (
    SELECT x.modulo, x.id_doc,
           string_agg(DISTINCT x.producto || CASE WHEN c.id IS NULL THEN ' (sin categoría)' ELSE ' (categoría ' || c.nombre || ')' END, ', ') AS productos,
           ROUND(SUM(x.monto), 2) AS subtotal
      FROM (
        SELECT d.modulo, d.id AS id_doc, d.id_empresa, d.id_cliente, d.slot, p.id AS id_producto, p.nombre AS producto,
               p.id_categoria, p.id_marca, p.tipo_produccion, vd.precio_total_sin_impuesto AS monto
          FROM docs d
          JOIN ventas_detalle vd ON d.modulo = 'factura_venta' AND vd.id_venta = d.id
          JOIN productos p ON p.id = vd.id_producto
        UNION ALL
        SELECT d.modulo, d.id, d.id_empresa, d.id_cliente, d.slot, p.id, p.nombre,
               p.id_categoria, p.id_marca, p.tipo_produccion, rd.precio_total_sin_impuesto
          FROM docs d
          JOIN recibos_venta_detalle rd ON d.modulo = 'recibo_venta' AND rd.id_recibo = d.id
          JOIN productos p ON p.id = rd.id_producto
      ) x
      LEFT JOIN categorias c ON c.id = x.id_categoria AND c.eliminado = false AND c.id_empresa = x.id_empresa
     WHERE NOT EXISTS (
            SELECT 1 FROM asientos_programados ap
              JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo AND at.codigo = x.slot
             WHERE ap.id_empresa = x.id_empresa AND ap.eliminado = false AND ap.id_cuenta IS NOT NULL
               AND (   (ap.tipo_referencia = 'producto'  AND ap.id_referencia = x.id_producto)
                    OR (ap.tipo_referencia = 'categoria' AND ap.id_referencia = x.id_categoria)
                    OR (ap.tipo_referencia = 'marca'     AND ap.id_referencia = x.id_marca)
                    OR (ap.tipo_referencia = 'tipo_produccion'
                        AND ap.id_referencia = (CASE x.tipo_produccion WHEN '02' THEN 2 WHEN '01' THEN 1 END))
                    OR (ap.tipo_referencia = 'cliente'   AND ap.id_referencia = x.id_cliente)
                    OR  COALESCE(ap.tipo_referencia, '') NOT IN ('producto', 'categoria', 'marca', 'tipo_produccion',
                                                                 'cliente', 'proveedor', 'item_compra')))
     GROUP BY x.modulo, x.id_doc
)
SELECT e.ruc,
       e.nombre_comercial                                  AS empresa,
       d.tipo,
       d.numero,
       d.fecha_emision,
       d.estado,
       s.numero_comprobante                                AS asiento,
       ROUND(d.total, 2)                                   AS total_documento,
       COALESCE(s.cartera_asiento, 0)                      AS cartera_asiento,
       ROUND(d.total - COALESCE(s.cartera_asiento, 0), 2)  AS diferencia,
       l.productos                                         AS productos_sin_regla,
       l.subtotal                                          AS subtotal_sin_regla,
       CASE
         WHEN s.editado_manual             THEN 'Asiento editado a mano: revisar a mano'
         WHEN l.id_doc IS NOT NULL         THEN 'LÍNEA OMITIDA: asignar categoría/regla al producto y regenerar desde Auditoría'
         ELSE 'Otra causa (cuenta de cartera cambiada o asiento viejo): revisar con 20261001_cartera_vs_documento.sql'
       END                                                 AS situacion,
       s.id_asiento
  FROM docs d
  JOIN asiento s ON s.modulo = d.modulo AND s.id_doc = d.id
  LEFT JOIN lineas_sin_regla l ON l.modulo = d.modulo AND l.id_doc = d.id
  JOIN empresas e ON e.id = d.id_empresa
 WHERE d.total - COALESCE(s.cartera_asiento, 0) > 0.03
   -- AND e.ruc = '0993384802001'                      -- <== empresa
 ORDER BY (l.id_doc IS NULL), e.ruc, d.fecha_emision DESC;
