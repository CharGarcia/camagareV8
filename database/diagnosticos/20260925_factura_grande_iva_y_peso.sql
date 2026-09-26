-- Diagnóstico de una factura de venta grande: líneas, peso estimado del XML
-- (tope SRI 320 Kb) y diferencia de IVA "línea por línea" vs "al subtotal".
-- Solo lectura. Reemplazar el secuencial / id de la factura en el CTE "f".
-- Fórmula de peso = App\Helpers\SriFichaTecnica::pesoEstimadoXml()
-- (1.450 B base + 10 KB firma + 404 B por línea + 1 B por carácter + 150 B por impuesto extra).

WITH f AS (
    SELECT v.id, v.id_empresa, v.estado, v.establecimiento, v.punto_emision, v.secuencial,
           v.total_sin_impuestos, COALESCE(v.total_ice, 0) AS total_ice,
           COALESCE(v.propina, 0) AS propina, v.importe_total
    FROM ventas_cabecera v
    WHERE v.eliminado = false
      AND v.id = 0          -- <== id de la factura (o cambiar por: v.secuencial = '000001234' AND v.id_empresa = X)
),
lineas AS (
    SELECT d.id_venta,
           COUNT(*) AS n_lineas,
           SUM(404 + length(COALESCE(d.descripcion, '')) + length(COALESCE(d.codigo_principal, ''))
                   + length(COALESCE(d.codigo_auxiliar, ''))) AS bytes_lineas
    FROM ventas_detalle d JOIN f ON f.id = d.id_venta
    GROUP BY d.id_venta
),
imp_extra AS (
    SELECT d.id_venta, SUM(GREATEST(x.n - 1, 0)) * 150 AS bytes
    FROM ventas_detalle d JOIN f ON f.id = d.id_venta
    JOIN LATERAL (SELECT COUNT(*) AS n FROM ventas_detalle_impuestos i WHERE i.id_venta_detalle = d.id) x ON true
    GROUP BY d.id_venta
),
adic AS (
    SELECT a.id_venta, SUM(40 + length(COALESCE(a.nombre, '')) + length(COALESCE(a.valor, ''))) AS bytes
    FROM ventas_adicional a JOIN f ON f.id = a.id_venta
    GROUP BY a.id_venta
),
iva AS (
    SELECT d.id_venta, i.tarifa,
           SUM(i.base_imponible) AS base,
           SUM(i.valor)          AS iva_guardado_lineas,
           SUM(ROUND(i.base_imponible * i.tarifa / 100, 2)) AS iva_linea_a_linea
    FROM ventas_detalle d JOIN f ON f.id = d.id_venta
    JOIN ventas_detalle_impuestos i ON i.id_venta_detalle = d.id AND i.codigo_impuesto::text = '2'
    GROUP BY d.id_venta, i.tarifa
)
SELECT f.id, f.estado, f.establecimiento || '-' || f.punto_emision || '-' || f.secuencial AS numero,
       l.n_lineas,
       ROUND((1450 + 10240 + l.bytes_lineas + COALESCE(e.bytes, 0) + COALESCE(a.bytes, 0)) / 1024.0, 1) AS peso_estimado_kb,
       ROUND(100.0 * (1450 + 10240 + l.bytes_lineas + COALESCE(e.bytes, 0) + COALESCE(a.bytes, 0)) / (320 * 1024), 1) AS pct_limite_sri,
       f.importe_total,
       ROUND(f.importe_total - f.total_sin_impuestos - f.total_ice - f.propina, 2) AS iva_segun_total,
       (SELECT SUM(iva_guardado_lineas) FROM iva WHERE iva.id_venta = f.id) AS iva_suma_lineas,
       (SELECT SUM(ROUND(base * tarifa / 100, 2)) FROM iva WHERE iva.id_venta = f.id) AS iva_al_subtotal,
       (SELECT SUM(iva_linea_a_linea) FROM iva WHERE iva.id_venta = f.id) AS iva_linea_a_linea
FROM f
JOIN lineas l ON l.id_venta = f.id
LEFT JOIN imp_extra e ON e.id_venta = f.id
LEFT JOIN adic a ON a.id_venta = f.id;
