-- Diagnóstico (solo lectura): compras migradas desde MySQL con el descuento restado DOS veces.
--
-- El sistema viejo guarda cuerpo_compra.subtotal ya NETO (cantidad × precio − descuento) y la
-- migración anterior al 17-08-2026 volvía a restar el descuento. Resultado: en esas líneas
--   precio_total_sin_impuesto = round(cantidad × precio, 2) − 2 × descuento
-- en vez de                   = round(cantidad × precio, 2) − descuento.
-- La base del IVA de la línea, total_sin_impuestos y el detalle_xml reconstruido heredan el error.
-- Caso detectado: compra 23536 (empresa 34, factura 002-001-000004959 de Ecuatek, 21-10-2025).
--
-- Corrección: volver a correr "Migrar desde base anterior → Compras" en cada empresa afectada
-- (el migrador actual reconcilia detalle, impuestos, totales y XML). No requiere SQL.

-- 1) Resumen por empresa
SELECT c.id_empresa,
       e.razon_social,
       COUNT(DISTINCT c.id) AS compras,
       COUNT(*)             AS lineas
FROM compras_detalle d
JOIN compras_cabecera c ON c.id = d.id_compra
JOIN empresas e         ON e.id = c.id_empresa
WHERE d.descuento > 0
  AND c.eliminado = false
  AND abs(d.precio_total_sin_impuesto - (round(d.cantidad * d.precio_unitario, 2) - 2 * d.descuento)) <= 0.02
  AND abs(d.precio_total_sin_impuesto - (round(d.cantidad * d.precio_unitario, 2) -     d.descuento)) >  0.02
  AND EXISTS (SELECT 1 FROM migracion_mysql_map m WHERE m.entidad = 'compras' AND m.id_destino = c.id)
GROUP BY c.id_empresa, e.razon_social
ORDER BY compras DESC;

-- 2) Documentos afectados (cambiar el id_empresa o quitar el filtro para ver todas)
SELECT c.id,
       c.id_empresa,
       p.identificacion                                                         AS ruc_proveedor,
       p.razon_social                                                           AS proveedor,
       c.tipo_comprobante,
       c.establecimiento_prov || '-' || c.punto_emision_prov || '-' || c.secuencial_prov AS numero,
       c.fecha_emision,
       c.total_sin_impuestos                                                    AS subtotal_guardado,
       round(SUM(round(d.cantidad * d.precio_unitario, 2) - d.descuento), 2)     AS subtotal_correcto,
       c.total_descuento,
       c.importe_total,
       COUNT(*) FILTER (
         WHERE d.descuento > 0
           AND abs(d.precio_total_sin_impuesto - (round(d.cantidad * d.precio_unitario, 2) - 2 * d.descuento)) <= 0.02
       )                                                                        AS lineas_con_error
FROM compras_cabecera c
JOIN compras_detalle d ON d.id_compra = c.id
JOIN proveedores p     ON p.id = c.id_proveedor
WHERE c.eliminado = false
  -- AND c.id_empresa = 34
  AND EXISTS (SELECT 1 FROM migracion_mysql_map m WHERE m.entidad = 'compras' AND m.id_destino = c.id)
  AND EXISTS (
        SELECT 1 FROM compras_detalle x
        WHERE x.id_compra = c.id
          AND x.descuento > 0
          AND abs(x.precio_total_sin_impuesto - (round(x.cantidad * x.precio_unitario, 2) - 2 * x.descuento)) <= 0.02
          AND abs(x.precio_total_sin_impuesto - (round(x.cantidad * x.precio_unitario, 2) -     x.descuento)) >  0.02
  )
GROUP BY c.id, c.id_empresa, p.identificacion, p.razon_social, c.tipo_comprobante,
         c.establecimiento_prov, c.punto_emision_prov, c.secuencial_prov,
         c.fecha_emision, c.total_sin_impuestos, c.total_descuento, c.importe_total
ORDER BY c.id_empresa, c.fecha_emision DESC;

-- 3) Detalle línea por línea de una compra concreta (para comparar con el documento físico)
-- SELECT d.id, d.descripcion, d.cantidad, d.precio_unitario, d.descuento,
--        d.precio_total_sin_impuesto                                     AS neto_guardado,
--        round(d.cantidad * d.precio_unitario, 2) - d.descuento          AS neto_correcto,
--        i.tarifa, i.base_imponible, i.valor
-- FROM compras_detalle d
-- LEFT JOIN compras_detalle_impuestos i ON i.id_compra_detalle = d.id AND i.codigo_impuesto = '2'
-- WHERE d.id_compra = 23536
-- ORDER BY d.id;
