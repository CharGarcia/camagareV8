-- =============================================================================
-- Diagnóstico (SOLO LECTURA) — GOLIFE (RUC 0993384802001): ¿por qué SERVICIO GO
-- WOMAN no encuentra cuenta si las dos categorías (Productos y Servicios) están
-- configuradas? Son 4 consultas: seleccione una (desde "-- N." hasta el punto y
-- coma) y ejecútela con F5.
-- =============================================================================


-- 1. El producto: ¿qué categoría tiene asignada? --------------------------------
--    categoria_asignada vacía = el producto NO tiene categoría (o apunta a una
--    categoría que ya no existe / está eliminada / es de otra empresa).
SELECT p.id, p.codigo, p.nombre, p.tipo_produccion, p.inventariable,
       p.id_categoria,
       c.nombre       AS categoria_asignada,
       c.eliminado    AS categoria_eliminada,
       c.id_empresa = p.id_empresa AS categoria_de_esta_empresa
  FROM productos p
  JOIN empresas emp ON emp.id = p.id_empresa
  LEFT JOIN categorias c ON c.id = p.id_categoria
 WHERE emp.ruc = '0993384802001'
   AND p.eliminado = false
   AND (p.codigo = 'SER005' OR p.nombre ILIKE '%GO WOMAN%');


-- 2. Las categorías de la empresa y QUÉ conceptos de Ventas con Factura tiene cada una
--    (Cuenta por Cobrar, Subtotal, etc.) + sus tarifas de IVA.
SELECT c.id AS id_categoria, c.nombre AS categoria, c.eliminado,
       COALESCE(at.referencia, 'IVA tarifa ' || ap.codigo_tarifa_iva) AS concepto,
       pc.codigo || ' - ' || pc.nombre AS cuenta
  FROM categorias c
  JOIN empresas emp ON emp.id = c.id_empresa
  LEFT JOIN asientos_programados ap ON ap.id_referencia = c.id
                                   AND ap.tipo_referencia = 'categoria'
                                   AND ap.id_empresa = c.id_empresa
                                   AND ap.eliminado = false
  LEFT JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
  LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
 WHERE emp.ruc = '0993384802001'
   AND (at.tipo_asiento = 'ventas_factura' OR ap.direccion_iva = 'venta' OR ap.id IS NULL)
 ORDER BY c.nombre, concepto;


-- 3. TODOS los productos vendidos en facturas que NO tienen categoría válida
--    (los que se quedarían sin cuenta igual que SERVICIO GO WOMAN).
SELECT p.id, p.codigo, p.nombre, p.tipo_produccion,
       COUNT(DISTINCT v.id) AS facturas,
       MIN(v.fecha_emision) AS desde, MAX(v.fecha_emision) AS hasta
  FROM productos p
  JOIN empresas emp ON emp.id = p.id_empresa
  JOIN ventas_detalle d ON d.id_producto = p.id
  JOIN ventas_cabecera v ON v.id = d.id_venta AND v.eliminado = false
  LEFT JOIN categorias c ON c.id = p.id_categoria AND c.eliminado = false AND c.id_empresa = p.id_empresa
 WHERE emp.ruc = '0993384802001'
   AND c.id IS NULL
 GROUP BY p.id, p.codigo, p.nombre, p.tipo_produccion
 ORDER BY facturas DESC, p.nombre;


-- 4. Facturas de venta SIN asiento vigente (las que quedaron pendientes, como la 104).
SELECT concat_ws('-', v.establecimiento, v.punto_emision, v.secuencial) AS factura,
       v.fecha_emision, v.importe_total, v.estado
  FROM ventas_cabecera v
  JOIN empresas emp ON emp.id = v.id_empresa
 WHERE emp.ruc = '0993384802001'
   AND v.eliminado = false
   AND v.estado IN ('autorizado', 'contabilizado')
   AND NOT EXISTS (SELECT 1 FROM asientos_contables_cabecera a
                    WHERE a.modulo_origen = 'factura_venta' AND a.id_referencia_origen = v.id
                      AND a.id_empresa = v.id_empresa AND a.eliminado = false AND a.estado <> 'anulado')
 ORDER BY v.fecha_emision DESC;
