-- =============================================================================
-- 20260916_verificar_impuestos_por_linea_lateral.sql — SOLO LECTURA
-- -----------------------------------------------------------------------------
-- Qué hace  : comprueba con los datos reales que la corrección de
--             AsientoBuilderService (impuestos por línea con LEFT JOIN LATERAL en
--             vez de una subconsulta agregada sobre toda la tabla) da EXACTAMENTE
--             el mismo valor para cada línea de cada documento. Compara las dos
--             formas línea por línea en las 6 variantes que usa el asiento:
--             facturas (IVA+ICE y solo ICE), recibos (IVA+ICE y solo ICE),
--             compras (IVA) y notas de crédito (IVA).
-- Resultado : 6 filas; "diferencias" debe ser 0 en todas.
-- Toca datos: NO. Solo SELECT.
-- Carga     : recorre las tablas de detalle completas una vez (segundos). De noche.
-- Cómo      : pgAdmin → Query Tool sobre producción → F5 → copiar el resultado.
-- =============================================================================
SELECT 'facturas: IVA + ICE' AS variante, count(*) AS lineas, count(a.v) AS con_impuesto,
       count(*) FILTER (WHERE a.v IS DISTINCT FROM b.v) AS diferencias
FROM ventas_detalle d
LEFT JOIN (SELECT id_venta_detalle, SUM(valor) v FROM ventas_detalle_impuestos
           WHERE codigo_impuesto IN ('2','3') GROUP BY id_venta_detalle) a ON a.id_venta_detalle = d.id
LEFT JOIN LATERAL (SELECT SUM(i.valor) v FROM ventas_detalle_impuestos i
                   WHERE i.id_venta_detalle = d.id AND i.codigo_impuesto IN ('2','3')) b ON true

UNION ALL
SELECT 'facturas: ICE', count(*), count(a.v), count(*) FILTER (WHERE a.v IS DISTINCT FROM b.v)
FROM ventas_detalle d
LEFT JOIN (SELECT id_venta_detalle, SUM(valor) v FROM ventas_detalle_impuestos
           WHERE codigo_impuesto = '3' GROUP BY id_venta_detalle) a ON a.id_venta_detalle = d.id
LEFT JOIN LATERAL (SELECT SUM(i.valor) v FROM ventas_detalle_impuestos i
                   WHERE i.id_venta_detalle = d.id AND i.codigo_impuesto = '3') b ON true

UNION ALL
SELECT 'recibos: IVA + ICE', count(*), count(a.v), count(*) FILTER (WHERE a.v IS DISTINCT FROM b.v)
FROM recibos_venta_detalle d
LEFT JOIN (SELECT id_recibo_detalle, SUM(valor) v FROM recibos_venta_detalle_impuestos
           WHERE codigo_impuesto IN ('2','3') GROUP BY id_recibo_detalle) a ON a.id_recibo_detalle = d.id
LEFT JOIN LATERAL (SELECT SUM(i.valor) v FROM recibos_venta_detalle_impuestos i
                   WHERE i.id_recibo_detalle = d.id AND i.codigo_impuesto IN ('2','3')) b ON true

UNION ALL
SELECT 'recibos: ICE', count(*), count(a.v), count(*) FILTER (WHERE a.v IS DISTINCT FROM b.v)
FROM recibos_venta_detalle d
LEFT JOIN (SELECT id_recibo_detalle, SUM(valor) v FROM recibos_venta_detalle_impuestos
           WHERE codigo_impuesto = '3' GROUP BY id_recibo_detalle) a ON a.id_recibo_detalle = d.id
LEFT JOIN LATERAL (SELECT SUM(i.valor) v FROM recibos_venta_detalle_impuestos i
                   WHERE i.id_recibo_detalle = d.id AND i.codigo_impuesto = '3') b ON true

UNION ALL
SELECT 'compras: IVA', count(*), count(a.v), count(*) FILTER (WHERE a.v IS DISTINCT FROM b.v)
FROM compras_detalle d
LEFT JOIN (SELECT id_compra_detalle, SUM(valor) v FROM compras_detalle_impuestos
           WHERE codigo_impuesto = '2' GROUP BY id_compra_detalle) a ON a.id_compra_detalle = d.id
LEFT JOIN LATERAL (SELECT SUM(i.valor) v FROM compras_detalle_impuestos i
                   WHERE i.id_compra_detalle = d.id AND i.codigo_impuesto = '2') b ON true

UNION ALL
SELECT 'notas de crédito: IVA', count(*), count(a.v), count(*) FILTER (WHERE a.v IS DISTINCT FROM b.v)
FROM notas_credito_detalle d
LEFT JOIN (SELECT id_nota_credito_detalle, SUM(valor) v FROM notas_credito_detalle_impuestos
           WHERE codigo_impuesto = '2' GROUP BY id_nota_credito_detalle) a ON a.id_nota_credito_detalle = d.id
LEFT JOIN LATERAL (SELECT SUM(i.valor) v FROM notas_credito_detalle_impuestos i
                   WHERE i.id_nota_credito_detalle = d.id AND i.codigo_impuesto = '2') b ON true;
