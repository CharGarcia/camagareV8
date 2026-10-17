-- ============================================================================
-- Diagnóstico: notas de crédito de compra (tipo 04) con ENTRADAS de inventario
-- ----------------------------------------------------------------------------
-- Hasta el 16-10-2026, "Procesar Entradas" en la pestaña Inventario de Compras
-- registraba siempre una ENTRADA (referencia_tipo = 'compra'), también cuando el
-- documento era una nota de crédito. Lo correcto es una SALIDA (devolución al
-- proveedor), que ahora se registra con referencia_tipo = 'nota_credito_compra'.
--
-- Solo lectura. Cada fila es un movimiento a revisar: el stock quedó SUMADO
-- (cantidad) cuando debía restarse. Para corregirlo, desde el modal de la NC
-- (pestaña Inventario): eliminar el movimiento con la papelera y volver a
-- procesar, que ahora registra la salida.
-- ============================================================================
SELECT e.ruc,
       c.id                                   AS id_compra,
       CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) AS numero_nc,
       c.fecha_emision,
       p.razon_social                         AS proveedor,
       k.id                                   AS id_kardex,
       pr.codigo                              AS producto_codigo,
       pr.nombre                              AS producto,
       b.nombre                               AS bodega,
       k.cantidad,
       k.costo_total,
       k.fecha_movimiento
  FROM compras_cabecera c
  JOIN empresas e          ON e.id = c.id_empresa
  JOIN proveedores p       ON p.id = c.id_proveedor
  JOIN compras_detalle d   ON d.id_compra = c.id
  JOIN inventario_kardex k ON k.referencia_tipo = 'compra'
                          AND k.referencia_id = d.id
                          AND k.id_empresa = c.id_empresa
                          AND k.eliminado = false
  LEFT JOIN productos pr   ON pr.id = k.id_producto
  LEFT JOIN bodegas b      ON b.id = k.id_bodega
 WHERE c.tipo_comprobante = '04'
   AND c.eliminado = false
 ORDER BY e.ruc, c.fecha_emision, c.id, k.id;
