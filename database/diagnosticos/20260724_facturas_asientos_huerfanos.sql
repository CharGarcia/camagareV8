-- Escaneo COMPLETO (todas las empresas) de asientos contables de Facturas de Venta que
-- quedaron activos ("contabilizado", no anulados) apuntando a una factura que ya no debería
-- tener asiento: porque la factura fue ELIMINADA (borrador descartado), o porque sigue en
-- BORRADOR (nunca se autorizó) y por el bug de crear() nunca debió contabilizarse.
--
-- Causa raíz (ya corregida en código): FacturaVentaService::crear() generaba el asiento sin
-- condicionarlo a estado='autorizado' (a diferencia de actualizar(), que sí lo hacía bien), y
-- eliminar() no anulaba el asiento existente al borrar un borrador.

-- 1) Facturas ELIMINADAS (eliminado=true) que siguen con un asiento ACTIVO -- caso más grave,
--    el dinero de estas facturas está inflando cuentas reales aunque el documento ya no existe.
SELECT
    v.id_empresa,
    v.id                    AS id_venta,
    v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial AS numero_factura,
    v.estado                AS estado_factura,
    v.eliminado             AS factura_eliminada,
    v.deleted_at,
    v.importe_total,
    ac.id                   AS id_asiento,
    ac.numero_comprobante,
    ac.estado               AS estado_asiento,
    ac.total_debe,
    ac.created_at           AS asiento_creado
FROM ventas_cabecera v
JOIN asientos_contables_cabecera ac
     ON ac.id_empresa = v.id_empresa
    AND ac.modulo_origen = 'factura_venta'
    AND ac.id_referencia_origen = v.id
WHERE v.eliminado = true
  AND ac.eliminado = false
  AND ac.estado <> 'anulado'
ORDER BY v.id_empresa, v.id;

-- 2) Facturas TODAVÍA en borrador (no eliminadas, podrían autorizarse después) que ya tienen
--    un asiento activo -- no son urgentes de corregir (si se autorizan, el flujo normal
--    actualiza ese mismo asiento en sitio), pero conviene saber cuántas hay.
SELECT
    v.id_empresa,
    v.id                    AS id_venta,
    v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial AS numero_factura,
    v.estado                AS estado_factura,
    v.importe_total,
    ac.id                   AS id_asiento,
    ac.numero_comprobante,
    ac.created_at           AS asiento_creado
FROM ventas_cabecera v
JOIN asientos_contables_cabecera ac
     ON ac.id_empresa = v.id_empresa
    AND ac.modulo_origen = 'factura_venta'
    AND ac.id_referencia_origen = v.id
WHERE v.eliminado = false
  AND v.estado = 'borrador'
  AND ac.eliminado = false
  AND ac.estado <> 'anulado'
ORDER BY v.id_empresa, v.id;

-- 3) Resumen: cuánto dinero (total_debe) está de más en las cuentas por el caso 1, por empresa
SELECT
    v.id_empresa,
    COUNT(*)            AS facturas_afectadas,
    SUM(ac.total_debe)  AS monto_total_a_revertir
FROM ventas_cabecera v
JOIN asientos_contables_cabecera ac
     ON ac.id_empresa = v.id_empresa
    AND ac.modulo_origen = 'factura_venta'
    AND ac.id_referencia_origen = v.id
WHERE v.eliminado = true
  AND ac.eliminado = false
  AND ac.estado <> 'anulado'
GROUP BY v.id_empresa
ORDER BY v.id_empresa;
