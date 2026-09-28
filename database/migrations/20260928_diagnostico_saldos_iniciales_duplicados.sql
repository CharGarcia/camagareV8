-- =====================================================================================
-- Diagnóstico (SOLO LECTURA): saldos iniciales cargados dos veces.
--
-- Hasta el 28-09-2026 nada impedía crear o importar dos veces el mismo documento como saldo
-- inicial, ni cargar como saldo inicial una factura/compra que ya existe en el sistema. Cada
-- copia tiene su propio saldo, así que el mismo documento se podía cobrar/pagar dos veces.
-- Desde esa fecha el sistema lo rechaza al crear, editar e importar; esto muestra lo que
-- ya haya quedado cargado antes. No modifica nada.
--
-- Qué hacer con cada fila: revisar cuál copia es la buena y eliminar la otra desde
-- Saldos Iniciales (si ya tiene cobros/pagos, anular primero ese ingreso/egreso).
-- =====================================================================================

-- 1. Saldos por COBRAR repetidos (mismo cliente y número)
SELECT 'CXC repetido' AS caso, s.id_empresa, s.id_cliente, s.nombre_cliente,
       s.nro_documento, COUNT(*) AS copias,
       string_agg(s.id::text || ' ($' || s.saldo_inicial || ', cobrado $' || s.monto_cobrado || ')', ' | ' ORDER BY s.id) AS registros
  FROM saldos_iniciales_cxc s
 WHERE s.eliminado = false
 GROUP BY s.id_empresa, s.id_cliente, s.nombre_cliente, s.nro_documento
HAVING COUNT(*) > 1

UNION ALL

-- 2. Saldos por PAGAR repetidos (mismo proveedor, tipo y número)
SELECT 'CXP repetido', s.id_empresa, s.id_proveedor, s.nombre_proveedor,
       s.tipo_documento || ' ' || s.nro_documento, COUNT(*),
       string_agg(s.id::text || ' ($' || s.saldo_inicial || ', pagado $' || s.monto_pagado || ')', ' | ' ORDER BY s.id)
  FROM saldos_iniciales_cxp s
 WHERE s.eliminado = false
 GROUP BY s.id_empresa, s.id_proveedor, s.nombre_proveedor, s.tipo_documento, s.nro_documento
HAVING COUNT(*) > 1

UNION ALL

-- 3. Saldo por cobrar que además existe como factura de venta real del mismo cliente
SELECT 'CXC = factura real', s.id_empresa, s.id_cliente, s.nombre_cliente,
       s.nro_documento, 2,
       'saldo inicial ' || s.id || ' ($' || s.saldo_inicial || ') + factura ' || v.id || ' (' || v.estado || ')'
  FROM saldos_iniciales_cxc s
  JOIN ventas_cabecera v
    ON v.id_empresa = s.id_empresa AND v.id_cliente = s.id_cliente AND v.eliminado = false
   AND LOWER(COALESCE(v.estado, '')) NOT IN ('anulado', 'anulada')
   AND regexp_replace(CONCAT(v.establecimiento, v.punto_emision, v.secuencial), '[^0-9]', '', 'g')
       = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
 WHERE s.eliminado = false

UNION ALL

-- 4. Saldo por pagar que además existe como compra real del mismo proveedor
SELECT 'CXP = compra real', s.id_empresa, s.id_proveedor, s.nombre_proveedor,
       s.nro_documento, 2,
       'saldo inicial ' || s.id || ' ($' || s.saldo_inicial || ') + compra ' || c.id || ' (' || COALESCE(c.estado, '') || ')'
  FROM saldos_iniciales_cxp s
  JOIN compras_cabecera c
    ON c.id_empresa = s.id_empresa AND c.id_proveedor = s.id_proveedor AND c.eliminado = false
   AND LOWER(COALESCE(c.estado, '')) NOT IN ('anulado', 'anulada', 'rechazado', 'rechazada')
   AND COALESCE(c.tipo_comprobante, '01') NOT IN ('04', '05')
   AND regexp_replace(CONCAT(c.establecimiento_prov, c.punto_emision_prov, c.secuencial_prov), '[^0-9]', '', 'g')
       = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
 WHERE s.eliminado = false AND s.tipo_documento = 'FACTURA_COMPRA'

ORDER BY 1, 2, 5;
