-- Diagnóstico (solo lectura): asientos de compra que no cuadran
--   001-108-000033897 ECUASANITAS  → aut. 0109202601179036333300120011080000338970000000715
--   001-007-009641864 BCO GUAYAQUIL → aut. 3108202601099004945900120010070096418647374947413
-- El asiento (AsientoBuilderService::armarDistribucionCompras) arma:
--   DEBE  = total_sin_impuestos (cabecera) + Σ IVA detalle (codigo_impuesto='2') + ICE + propina
--   HABER = importe_total (cabecera)
-- Si "diferencia" ≠ 0 (y > 0.05) es exactamente lo que ve el sincronizador.

-- Consulta 1: totales de cabecera vs. lo que suma el detalle
SELECT c.id, c.id_empresa, c.estado, c.tipo_comprobante,
       c.establecimiento_prov || '-' || c.punto_emision_prov || '-' || c.secuencial_prov AS numero,
       c.fecha_emision,
       c.total_sin_impuestos, c.total_descuento, c.total_ice, c.propina, c.importe_total,
       d.suma_lineas_sin_imp,
       d.num_lineas,
       i.iva_detalle,
       i.ice_detalle,
       i.otros_impuestos_detalle,
       i.codigos_impuesto,
       ROUND(c.importe_total
             - (c.total_sin_impuestos + COALESCE(i.iva_detalle,0)
                + CASE WHEN COALESCE(i.ice_detalle,0) > 0 THEN i.ice_detalle ELSE COALESCE(c.total_ice,0) END
                + COALESCE(c.propina,0)), 2) AS diferencia
FROM compras_cabecera c
LEFT JOIN LATERAL (
    SELECT ROUND(SUM(cd.precio_total_sin_impuesto)::numeric, 2) AS suma_lineas_sin_imp,
           COUNT(*) AS num_lineas
    FROM compras_detalle cd WHERE cd.id_compra = c.id
) d ON true
LEFT JOIN LATERAL (
    SELECT ROUND(SUM(ci.valor) FILTER (WHERE ci.codigo_impuesto = '2')::numeric, 2) AS iva_detalle,
           ROUND(SUM(ci.valor) FILTER (WHERE ci.codigo_impuesto = '3')::numeric, 2) AS ice_detalle,
           ROUND(SUM(ci.valor) FILTER (WHERE ci.codigo_impuesto NOT IN ('2','3'))::numeric, 2) AS otros_impuestos_detalle,
           STRING_AGG(DISTINCT ci.codigo_impuesto || '/' || ci.codigo_porcentaje, ', ') AS codigos_impuesto
    FROM compras_detalle_impuestos ci
    JOIN compras_detalle cd ON cd.id = ci.id_compra_detalle
    WHERE cd.id_compra = c.id
) i ON true
WHERE c.numero_autorizacion IN (
    '0109202601179036333300120011080000338970000000715',
    '3108202601099004945900120010070096418647374947413'
)
ORDER BY c.id;
