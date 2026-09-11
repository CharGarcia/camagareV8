-- ============================================================================
-- Liquidaciones de compra MIGRADAS que aparecen "pendientes de pago" aunque en
-- el sistema anterior sí se pagaron.
--
-- Causa: en el sistema anterior el egreso pagaba la liquidación a través de su
-- fila en encabezado_compra (id_comprobante = 3). La migración de Compras
-- excluye esas filas (las liquidaciones se migran aparte, a
-- liquidaciones_cabecera), así que al migrar Pagos (egresos) la línea quedó
-- como tipo_documento = 'COMPRA' SIN documento (id_referencia_documento NULL).
-- Cuentas por Pagar, Egresos ("Liquidaciones de Compra - Pendientes de Pago")
-- y la pestaña Pagos de la liquidación solo cuentan líneas 'LIQUIDACION' con el
-- id de la liquidación → la liquidación sigue saliendo sin pagar.
--
-- SOLO LECTURA: no modifica ni crea nada.
-- USO: por defecto revisa TODAS las empresas (id_empresa = 0). Para una sola,
--      cambie el 0 de `parametros` (en producción la empresa migrada es la 8).
--      Hay 3 consultas: resalte UNA y presione F5 (si ejecuta todo el archivo,
--      pgAdmin muestra solo el resultado de la última).
--        1) Resumen por empresa y estado   ← la que conviene compartir
--        2) Detalle línea por línea
--        3) Por liquidación: saldo hoy y saldo después de enlazar
--
-- Estado de cada línea huérfana (tipo COMPRA sin documento, de egresos migrados):
--   SE ENLAZA       calza con UNA liquidación de la misma empresa por número, del
--                   mismo proveedor o con "liquidación" en el texto. Es lo que
--                   corrige database/20260910_enlazar_pagos_liquidaciones_migradas.sql
--   ES UNA COMPRA   el número calza con una factura de compra del mismo proveedor,
--                   emitida hasta la fecha del pago:
--                   no es liquidación; la compra se migró DESPUÉS que los egresos.
--                   Se arregla volviendo a migrar Pagos (egresos).
--   AMBIGUA         calza con más de un documento: revisar a mano.
--   OTRO PROVEEDOR  calza por número con una liquidación de otro proveedor y el
--                   texto no dice "liquidación": revisar a mano.
--   SIN DOCUMENTO   ningún documento del sistema nuevo tiene ese número
--                   (la liquidación o la compra no se migró).
--   SIN NÚMERO      no se pudo leer el número en la línea.
-- ============================================================================


-- ── 1) RESUMEN por empresa y estado ─────────────────────────────────────────
WITH parametros AS (
    SELECT 0::int AS id_empresa          -- << 0 = todas; o el id de la empresa (p. ej. 8)
),
lineas AS (
    SELECT d.id AS id_detalle, d.id_egreso, e.id_empresa, e.numero_egreso,
           e.fecha_emision AS fecha_egreso, e.estado AS estado_egreso,
           e.id_proveedor AS prov_egreso, e.tipo_ambiente AS amb_egreso,
           d.descripcion, d.numero_documento, d.monto_pagado,
           -- Número a 15 dígitos: el de la línea (relleno 3-3-9) o, si no se pudo
           -- leer al migrar, el primer número de 15 dígitos del texto de la línea.
           COALESCE(
               CASE WHEN d.numero_documento ~ '^[0-9]{1,3}-[0-9]{1,3}-[0-9]{1,9}$'
                    THEN lpad(split_part(d.numero_documento, '-', 1), 3, '0')
                      || lpad(split_part(d.numero_documento, '-', 2), 3, '0')
                      || lpad(split_part(d.numero_documento, '-', 3), 9, '0')
               END,
               regexp_replace(substring(d.descripcion FROM '[0-9]{3}-?[0-9]{3}-?[0-9]{9}'), '[^0-9]', '', 'g')
           ) AS num15
    FROM egresos_detalle d
    JOIN egresos_cabecera e      ON e.id = d.id_egreso
    JOIN migracion_mysql_map m   ON m.id_empresa = e.id_empresa AND m.entidad = 'egresos' AND m.id_destino = e.id
    CROSS JOIN parametros p
    WHERE d.tipo_documento = 'COMPRA'
      AND d.id_referencia_documento IS NULL
      AND d.eliminado = false
      AND e.eliminado = false
      AND (p.id_empresa = 0 OR e.id_empresa = p.id_empresa)
),
liq AS (
    SELECT li.id_detalle, l.id AS id_liq, l.id_proveedor AS prov_liq, l.tipo_ambiente AS amb_liq
    FROM lineas li
    JOIN liquidaciones_cabecera l
      ON l.id_empresa = li.id_empresa
     AND l.eliminado = false
     AND COALESCE(l.establecimiento, '') || COALESCE(l.punto_emision, '') || COALESCE(l.secuencial, '') = li.num15
    WHERE length(li.num15) = 15
),
eleccion AS (
    -- Una sola liquidación por línea; ante números repetidos se prefiere el ambiente
    -- del egreso y luego su proveedor (misma regla que la migración corregida).
    SELECT li.id_detalle,
           COUNT(q.id_liq) AS candidatas,
           CASE
             WHEN COUNT(q.id_liq) = 1 THEN MIN(q.id_liq)
             WHEN COUNT(q.id_liq) FILTER (WHERE q.amb_liq = li.amb_egreso) = 1
                  THEN MIN(q.id_liq) FILTER (WHERE q.amb_liq = li.amb_egreso)
             WHEN COUNT(q.id_liq) FILTER (WHERE q.amb_liq = li.amb_egreso AND q.prov_liq = li.prov_egreso) = 1
                  THEN MIN(q.id_liq) FILTER (WHERE q.amb_liq = li.amb_egreso AND q.prov_liq = li.prov_egreso)
           END AS id_liq
    FROM lineas li
    LEFT JOIN liq q ON q.id_detalle = li.id_detalle
    GROUP BY li.id_detalle, li.amb_egreso, li.prov_egreso
),
compra AS (
    SELECT li.id_detalle, COUNT(*) AS compras
    FROM lineas li
    JOIN compras_cabecera c
      ON c.id_empresa = li.id_empresa
     AND c.eliminado = false
     AND c.id_proveedor = li.prov_egreso
     AND COALESCE(c.tipo_comprobante, '01') NOT IN ('04', '05')
     AND c.fecha_emision <= li.fecha_egreso   -- una factura posterior al pago no es lo que se pagó
     AND COALESCE(c.establecimiento_prov, '') || COALESCE(c.punto_emision_prov, '') || COALESCE(c.secuencial_prov, '') = li.num15
    WHERE length(li.num15) = 15
    GROUP BY li.id_detalle
),
clasif AS (
    SELECT li.*, el.candidatas, el.id_liq, COALESCE(co.compras, 0) AS compras,
           CASE
             WHEN li.num15 IS NULL OR length(li.num15) <> 15                         THEN 'SIN NÚMERO'
             WHEN el.id_liq IS NOT NULL AND COALESCE(co.compras, 0) > 0               THEN 'AMBIGUA'
             WHEN el.id_liq IS NULL AND el.candidatas > 1                             THEN 'AMBIGUA'
             WHEN el.id_liq IS NOT NULL
                  AND (l.id_proveedor = li.prov_egreso OR li.descripcion ILIKE '%liquidaci%') THEN 'SE ENLAZA'
             WHEN el.id_liq IS NOT NULL                                               THEN 'OTRO PROVEEDOR'
             WHEN COALESCE(co.compras, 0) > 0                                         THEN 'ES UNA COMPRA'
             ELSE 'SIN DOCUMENTO'
           END AS estado
    FROM lineas li
    JOIN eleccion el              ON el.id_detalle = li.id_detalle
    LEFT JOIN compra co           ON co.id_detalle = li.id_detalle
    LEFT JOIN liquidaciones_cabecera l ON l.id = el.id_liq
)
SELECT id_empresa, estado,
       COUNT(*)                                                   AS lineas,
       COUNT(DISTINCT id_egreso)                                  AS egresos,
       COUNT(DISTINCT id_liq)                                     AS liquidaciones,
       ROUND(SUM(monto_pagado), 2)                                AS monto,
       COUNT(*) FILTER (WHERE estado_egreso = 'anulado')          AS de_egresos_anulados
FROM clasif
GROUP BY id_empresa, estado
ORDER BY id_empresa, estado;


-- ── 2) DETALLE línea por línea ──────────────────────────────────────────────
WITH parametros AS (
    SELECT 0::int AS id_empresa          -- << 0 = todas; o el id de la empresa (p. ej. 8)
),
lineas AS (
    SELECT d.id AS id_detalle, d.id_egreso, e.id_empresa, e.numero_egreso,
           e.fecha_emision AS fecha_egreso, e.estado AS estado_egreso,
           e.id_proveedor AS prov_egreso, e.tipo_ambiente AS amb_egreso,
           d.descripcion, d.numero_documento, d.monto_pagado,
           COALESCE(
               CASE WHEN d.numero_documento ~ '^[0-9]{1,3}-[0-9]{1,3}-[0-9]{1,9}$'
                    THEN lpad(split_part(d.numero_documento, '-', 1), 3, '0')
                      || lpad(split_part(d.numero_documento, '-', 2), 3, '0')
                      || lpad(split_part(d.numero_documento, '-', 3), 9, '0')
               END,
               regexp_replace(substring(d.descripcion FROM '[0-9]{3}-?[0-9]{3}-?[0-9]{9}'), '[^0-9]', '', 'g')
           ) AS num15
    FROM egresos_detalle d
    JOIN egresos_cabecera e      ON e.id = d.id_egreso
    JOIN migracion_mysql_map m   ON m.id_empresa = e.id_empresa AND m.entidad = 'egresos' AND m.id_destino = e.id
    CROSS JOIN parametros p
    WHERE d.tipo_documento = 'COMPRA'
      AND d.id_referencia_documento IS NULL
      AND d.eliminado = false
      AND e.eliminado = false
      AND (p.id_empresa = 0 OR e.id_empresa = p.id_empresa)
),
liq AS (
    SELECT li.id_detalle, l.id AS id_liq, l.id_proveedor AS prov_liq, l.tipo_ambiente AS amb_liq
    FROM lineas li
    JOIN liquidaciones_cabecera l
      ON l.id_empresa = li.id_empresa
     AND l.eliminado = false
     AND COALESCE(l.establecimiento, '') || COALESCE(l.punto_emision, '') || COALESCE(l.secuencial, '') = li.num15
    WHERE length(li.num15) = 15
),
eleccion AS (
    SELECT li.id_detalle,
           COUNT(q.id_liq) AS candidatas,
           CASE
             WHEN COUNT(q.id_liq) = 1 THEN MIN(q.id_liq)
             WHEN COUNT(q.id_liq) FILTER (WHERE q.amb_liq = li.amb_egreso) = 1
                  THEN MIN(q.id_liq) FILTER (WHERE q.amb_liq = li.amb_egreso)
             WHEN COUNT(q.id_liq) FILTER (WHERE q.amb_liq = li.amb_egreso AND q.prov_liq = li.prov_egreso) = 1
                  THEN MIN(q.id_liq) FILTER (WHERE q.amb_liq = li.amb_egreso AND q.prov_liq = li.prov_egreso)
           END AS id_liq
    FROM lineas li
    LEFT JOIN liq q ON q.id_detalle = li.id_detalle
    GROUP BY li.id_detalle, li.amb_egreso, li.prov_egreso
),
compra AS (
    SELECT li.id_detalle, COUNT(*) AS compras
    FROM lineas li
    JOIN compras_cabecera c
      ON c.id_empresa = li.id_empresa
     AND c.eliminado = false
     AND c.id_proveedor = li.prov_egreso
     AND COALESCE(c.tipo_comprobante, '01') NOT IN ('04', '05')
     AND c.fecha_emision <= li.fecha_egreso   -- una factura posterior al pago no es lo que se pagó
     AND COALESCE(c.establecimiento_prov, '') || COALESCE(c.punto_emision_prov, '') || COALESCE(c.secuencial_prov, '') = li.num15
    WHERE length(li.num15) = 15
    GROUP BY li.id_detalle
),
clasif AS (
    SELECT li.*, el.candidatas, el.id_liq, COALESCE(co.compras, 0) AS compras,
           CASE
             WHEN li.num15 IS NULL OR length(li.num15) <> 15                         THEN 'SIN NÚMERO'
             WHEN el.id_liq IS NOT NULL AND COALESCE(co.compras, 0) > 0               THEN 'AMBIGUA'
             WHEN el.id_liq IS NULL AND el.candidatas > 1                             THEN 'AMBIGUA'
             WHEN el.id_liq IS NOT NULL
                  AND (l.id_proveedor = li.prov_egreso OR li.descripcion ILIKE '%liquidaci%') THEN 'SE ENLAZA'
             WHEN el.id_liq IS NOT NULL                                               THEN 'OTRO PROVEEDOR'
             WHEN COALESCE(co.compras, 0) > 0                                         THEN 'ES UNA COMPRA'
             ELSE 'SIN DOCUMENTO'
           END AS estado
    FROM lineas li
    JOIN eleccion el              ON el.id_detalle = li.id_detalle
    LEFT JOIN compra co           ON co.id_detalle = li.id_detalle
    LEFT JOIN liquidaciones_cabecera l ON l.id = el.id_liq
)
SELECT c.id_empresa, c.estado, c.numero_egreso,
       to_char(c.fecha_egreso, 'DD-MM-YYYY')                        AS fecha_egreso,
       c.estado_egreso,
       pe.razon_social                                              AS proveedor_egreso,
       c.descripcion, c.monto_pagado,
       c.num15                                                      AS numero_leido,
       l.establecimiento || '-' || l.punto_emision || '-' || l.secuencial AS liquidacion,
       pl.razon_social                                              AS proveedor_liquidacion,
       l.importe_total                                              AS total_liquidacion,
       c.id_egreso, c.id_detalle, c.id_liq
FROM clasif c
LEFT JOIN proveedores pe             ON pe.id = c.prov_egreso
LEFT JOIN liquidaciones_cabecera l   ON l.id = c.id_liq
LEFT JOIN proveedores pl             ON pl.id = l.id_proveedor
ORDER BY c.id_empresa, c.estado, c.fecha_egreso, c.numero_egreso;


-- ── 3) POR LIQUIDACIÓN: saldo hoy y después de enlazar (solo "SE ENLAZA") ────
WITH parametros AS (
    SELECT 0::int AS id_empresa          -- << 0 = todas; o el id de la empresa (p. ej. 8)
),
lineas AS (
    SELECT d.id AS id_detalle, d.id_egreso, e.id_empresa, e.numero_egreso,
           e.fecha_emision AS fecha_egreso, e.estado AS estado_egreso,
           e.id_proveedor AS prov_egreso, e.tipo_ambiente AS amb_egreso,
           d.descripcion, d.numero_documento, d.monto_pagado,
           COALESCE(
               CASE WHEN d.numero_documento ~ '^[0-9]{1,3}-[0-9]{1,3}-[0-9]{1,9}$'
                    THEN lpad(split_part(d.numero_documento, '-', 1), 3, '0')
                      || lpad(split_part(d.numero_documento, '-', 2), 3, '0')
                      || lpad(split_part(d.numero_documento, '-', 3), 9, '0')
               END,
               regexp_replace(substring(d.descripcion FROM '[0-9]{3}-?[0-9]{3}-?[0-9]{9}'), '[^0-9]', '', 'g')
           ) AS num15
    FROM egresos_detalle d
    JOIN egresos_cabecera e      ON e.id = d.id_egreso
    JOIN migracion_mysql_map m   ON m.id_empresa = e.id_empresa AND m.entidad = 'egresos' AND m.id_destino = e.id
    CROSS JOIN parametros p
    WHERE d.tipo_documento = 'COMPRA'
      AND d.id_referencia_documento IS NULL
      AND d.eliminado = false
      AND e.eliminado = false
      AND (p.id_empresa = 0 OR e.id_empresa = p.id_empresa)
),
liq AS (
    SELECT li.id_detalle, l.id AS id_liq, l.id_proveedor AS prov_liq, l.tipo_ambiente AS amb_liq
    FROM lineas li
    JOIN liquidaciones_cabecera l
      ON l.id_empresa = li.id_empresa
     AND l.eliminado = false
     AND COALESCE(l.establecimiento, '') || COALESCE(l.punto_emision, '') || COALESCE(l.secuencial, '') = li.num15
    WHERE length(li.num15) = 15
),
eleccion AS (
    SELECT li.id_detalle,
           COUNT(q.id_liq) AS candidatas,
           CASE
             WHEN COUNT(q.id_liq) = 1 THEN MIN(q.id_liq)
             WHEN COUNT(q.id_liq) FILTER (WHERE q.amb_liq = li.amb_egreso) = 1
                  THEN MIN(q.id_liq) FILTER (WHERE q.amb_liq = li.amb_egreso)
             WHEN COUNT(q.id_liq) FILTER (WHERE q.amb_liq = li.amb_egreso AND q.prov_liq = li.prov_egreso) = 1
                  THEN MIN(q.id_liq) FILTER (WHERE q.amb_liq = li.amb_egreso AND q.prov_liq = li.prov_egreso)
           END AS id_liq
    FROM lineas li
    LEFT JOIN liq q ON q.id_detalle = li.id_detalle
    GROUP BY li.id_detalle, li.amb_egreso, li.prov_egreso
),
compra AS (
    SELECT li.id_detalle, COUNT(*) AS compras
    FROM lineas li
    JOIN compras_cabecera c
      ON c.id_empresa = li.id_empresa
     AND c.eliminado = false
     AND c.id_proveedor = li.prov_egreso
     AND COALESCE(c.tipo_comprobante, '01') NOT IN ('04', '05')
     AND c.fecha_emision <= li.fecha_egreso   -- una factura posterior al pago no es lo que se pagó
     AND COALESCE(c.establecimiento_prov, '') || COALESCE(c.punto_emision_prov, '') || COALESCE(c.secuencial_prov, '') = li.num15
    WHERE length(li.num15) = 15
    GROUP BY li.id_detalle
),
clasif AS (
    SELECT li.*, el.candidatas, el.id_liq, COALESCE(co.compras, 0) AS compras,
           CASE
             WHEN li.num15 IS NULL OR length(li.num15) <> 15                         THEN 'SIN NÚMERO'
             WHEN el.id_liq IS NOT NULL AND COALESCE(co.compras, 0) > 0               THEN 'AMBIGUA'
             WHEN el.id_liq IS NULL AND el.candidatas > 1                             THEN 'AMBIGUA'
             WHEN el.id_liq IS NOT NULL
                  AND (l.id_proveedor = li.prov_egreso OR li.descripcion ILIKE '%liquidaci%') THEN 'SE ENLAZA'
             WHEN el.id_liq IS NOT NULL                                               THEN 'OTRO PROVEEDOR'
             WHEN COALESCE(co.compras, 0) > 0                                         THEN 'ES UNA COMPRA'
             ELSE 'SIN DOCUMENTO'
           END AS estado
    FROM lineas li
    JOIN eleccion el              ON el.id_detalle = li.id_detalle
    LEFT JOIN compra co           ON co.id_detalle = li.id_detalle
    LEFT JOIN liquidaciones_cabecera l ON l.id = el.id_liq
),
pagado AS (    -- lo que HOY cuenta como pagado (líneas LIQUIDACION enlazadas)
    SELECT d.id_referencia_documento AS id_liq, SUM(d.monto_pagado) AS pagado
    FROM egresos_detalle d
    JOIN egresos_cabecera e ON e.id = d.id_egreso
    WHERE d.tipo_documento = 'LIQUIDACION' AND d.eliminado = false
      AND e.eliminado = false AND e.estado <> 'anulado'
    GROUP BY d.id_referencia_documento
),
retenido AS (  -- retenciones de compra enlazadas a la liquidación
    SELECT r.id_liquidacion AS id_liq, SUM(r.total_retenido) AS retenido
    FROM retencion_compra_cabecera r
    WHERE r.eliminado = false AND r.id_liquidacion IS NOT NULL
      AND UPPER(COALESCE(r.estado, '')) NOT IN ('ANULADO', 'ANULADA', 'BORRADOR', 'PENDIENTE')
    GROUP BY r.id_liquidacion
)
SELECT l.id_empresa,
       l.establecimiento || '-' || l.punto_emision || '-' || l.secuencial   AS liquidacion,
       to_char(l.fecha_emision, 'DD-MM-YYYY')                              AS fecha,
       p.razon_social                                                      AS proveedor,
       l.estado,
       l.importe_total                                                     AS total,
       COALESCE(pg.pagado, 0)                                              AS pagado_hoy,
       COALESCE(rt.retenido, 0)                                            AS retenido,
       ROUND(l.importe_total - COALESCE(pg.pagado, 0) - COALESCE(rt.retenido, 0), 2) AS saldo_hoy,
       SUM(c.monto_pagado)                                                 AS pago_a_enlazar,
       ROUND(l.importe_total - COALESCE(pg.pagado, 0) - COALESCE(rt.retenido, 0) - SUM(c.monto_pagado), 2) AS saldo_despues
FROM clasif c
JOIN liquidaciones_cabecera l ON l.id = c.id_liq
LEFT JOIN proveedores p       ON p.id = l.id_proveedor
LEFT JOIN pagado pg           ON pg.id_liq = l.id
LEFT JOIN retenido rt         ON rt.id_liq = l.id
WHERE c.estado = 'SE ENLAZA'
  AND c.estado_egreso <> 'anulado'     -- un egreso anulado no cuenta como pago
GROUP BY l.id, l.id_empresa, l.establecimiento, l.punto_emision, l.secuencial, l.fecha_emision,
         p.razon_social, l.estado, l.importe_total, pg.pagado, rt.retenido
ORDER BY l.id_empresa, l.fecha_emision, liquidacion;
