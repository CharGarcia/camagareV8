-- =============================================================================
-- Diagnóstico (SOLO LECTURA) del asiento de UNA factura de venta.
-- Cambie el RUC y el número de factura en las marcas "<== cambiar" de cada
-- consulta. Son 4 consultas: seleccione una (desde "-- N." hasta el punto y coma)
-- y ejecútela con F5.
-- =============================================================================


-- 1. CABECERA de la factura: totales que el asiento debe reflejar -------------
SELECT v.id, concat_ws('-', v.establecimiento, v.punto_emision, v.secuencial) AS factura,
       v.fecha_emision, v.estado, v.tipo_ambiente,
       v.total_sin_impuestos,
       v.total_descuento, v.total_ice, v.propina, v.importe_total,
       (v.importe_total - v.total_sin_impuestos - COALESCE(v.total_ice,0) - COALESCE(v.propina,0)) AS iva_aprox,
       cl.nombre AS cliente, v.id_asiento_contable, v.created_at, v.updated_at
  FROM ventas_cabecera v
  JOIN empresas emp ON emp.id = v.id_empresa
  LEFT JOIN clientes cl ON cl.id = v.id_cliente
 WHERE emp.ruc = '1791400135001'                                   -- <== cambiar
   AND concat_ws('-', v.establecimiento, v.punto_emision, v.secuencial) = '001-001-000000001'  -- <== cambiar
   AND v.eliminado = false;


-- 2. DETALLE de la factura: cada línea con su producto, categoría y costo ------
SELECT d.id, d.descripcion, d.cantidad, d.precio_unitario, d.descuento,
       d.precio_total_sin_impuesto, p.codigo, p.nombre AS producto,
       p.inventariable, p.tipo_produccion, cat.nombre AS categoria, mar.nombre AS marca
  FROM ventas_cabecera v
  JOIN empresas emp ON emp.id = v.id_empresa
  JOIN ventas_detalle d ON d.id_venta = v.id
  LEFT JOIN productos p ON p.id = d.id_producto
  LEFT JOIN categorias cat ON cat.id = p.id_categoria
  LEFT JOIN marcas mar ON mar.id = p.id_marca
 WHERE emp.ruc = '1791400135001'                                   -- <== cambiar
   AND concat_ws('-', v.establecimiento, v.punto_emision, v.secuencial) = '001-001-000000001'  -- <== cambiar
   AND v.eliminado = false
 ORDER BY d.id;


-- 3. ASIENTO(S) de la factura, línea por línea ---------------------------------
--    es_cartera = la cuenta está HOY configurada como Cuenta por Cobrar.
SELECT a.id AS id_asiento, a.numero_comprobante, a.estado, a.eliminado AS asiento_eliminado,
       a.editado_manual, a.created_at, a.updated_at,
       pc.codigo || ' - ' || pc.nombre AS cuenta,
       ad.debe, ad.haber, ad.referencia_detalle,
       EXISTS (SELECT 1 FROM asientos_programados ap
                 JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                WHERE ap.id_empresa = v.id_empresa AND ap.eliminado = false
                  AND at.codigo = 'PORCOBRARFACTURAVENTA'
                  AND ap.id_cuenta = ad.id_cuenta_contable) AS es_cartera
  FROM ventas_cabecera v
  JOIN empresas emp ON emp.id = v.id_empresa
  JOIN asientos_contables_cabecera a ON a.modulo_origen = 'factura_venta'
                                    AND a.id_referencia_origen = v.id
                                    AND a.id_empresa = v.id_empresa
  JOIN asientos_contables_detalle ad ON ad.id_asiento = a.id AND ad.eliminado = false
  LEFT JOIN plan_cuentas pc ON pc.id = ad.id_cuenta_contable
 WHERE emp.ruc = '1791400135001'                                   -- <== cambiar
   AND concat_ws('-', v.establecimiento, v.punto_emision, v.secuencial) = '001-001-000000001'  -- <== cambiar
   AND v.eliminado = false
 ORDER BY a.eliminado, a.id DESC, ad.debe DESC, ad.id;


-- 4. CUENTAS POR COBRAR configuradas para esa empresa (General, cliente, producto…)
SELECT ap.tipo_referencia, ap.id_referencia, pc.codigo || ' - ' || pc.nombre AS cuenta,
       ap.created_at, ap.updated_at
  FROM asientos_programados ap
  JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
  JOIN empresas emp ON emp.id = ap.id_empresa
  LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
 WHERE emp.ruc = '1791400135001'                                   -- <== cambiar
   AND at.codigo = 'PORCOBRARFACTURAVENTA'
   AND ap.eliminado = false
 ORDER BY ap.tipo_referencia, ap.id_referencia;
