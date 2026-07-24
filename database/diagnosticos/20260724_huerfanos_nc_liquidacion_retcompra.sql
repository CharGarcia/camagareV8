-- Mismo escaneo que se hizo para Facturas de Venta, ahora para Notas de Crédito,
-- Liquidaciones de Compra y Retenciones de Compra: documentos ELIMINADOS que siguen con un
-- asiento contable ACTIVO ("contabilizado"/no anulado). Sin filtro de empresa: cubre todas.
--
-- Causa raíz (ya corregida en código): NotaCreditoService/LiquidacionCompraService generaban
-- el asiento sin condicionarlo a estado='autorizado', y ninguno de los tres (incluida
-- RetencionCompraService) anulaba el asiento existente al eliminar el documento.

-- 1) Notas de Crédito eliminadas con asiento activo
SELECT
    nc.id_empresa, nc.id AS id_nc,
    nc.establecimiento || '-' || nc.punto_emision || '-' || nc.secuencial AS numero_nc,
    nc.estado AS estado_nc, nc.deleted_at, nc.importe_total,
    ac.id AS id_asiento, ac.numero_comprobante, ac.estado AS estado_asiento, ac.total_debe
FROM notas_credito_cabecera nc
JOIN asientos_contables_cabecera ac
     ON ac.id_empresa = nc.id_empresa
    AND ac.modulo_origen = 'nota_credito'
    AND ac.id_referencia_origen = nc.id
WHERE nc.eliminado = true
  AND ac.eliminado = false
  AND ac.estado <> 'anulado'
ORDER BY nc.id_empresa, nc.id;

-- 2) Liquidaciones de Compra eliminadas con asiento activo
SELECT
    lq.id_empresa, lq.id AS id_liquidacion,
    lq.establecimiento || '-' || lq.punto_emision || '-' || lq.secuencial AS numero_liquidacion,
    lq.estado AS estado_liquidacion, lq.deleted_at, lq.importe_total,
    ac.id AS id_asiento, ac.numero_comprobante, ac.estado AS estado_asiento, ac.total_debe
FROM liquidaciones_cabecera lq
JOIN asientos_contables_cabecera ac
     ON ac.id_empresa = lq.id_empresa
    AND ac.modulo_origen = 'liquidacion_compra'
    AND ac.id_referencia_origen = lq.id
WHERE lq.eliminado = true
  AND ac.eliminado = false
  AND ac.estado <> 'anulado'
ORDER BY lq.id_empresa, lq.id;

-- 3) Retenciones de Compra eliminadas con asiento activo
SELECT
    rc.id_empresa, rc.id AS id_retencion,
    rc.estado AS estado_retencion, rc.deleted_at,
    ac.id AS id_asiento, ac.numero_comprobante, ac.estado AS estado_asiento, ac.total_debe
FROM retencion_compra_cabecera rc
JOIN asientos_contables_cabecera ac
     ON ac.id_empresa = rc.id_empresa
    AND ac.modulo_origen = 'retencion_compra'
    AND ac.id_referencia_origen = rc.id
WHERE rc.eliminado = true
  AND ac.eliminado = false
  AND ac.estado <> 'anulado'
ORDER BY rc.id_empresa, rc.id;

-- 4) Resumen: cuánto dinero y cuántos documentos por empresa y por módulo
SELECT 'nota_credito' AS modulo, nc.id_empresa, COUNT(*) documentos_afectados, SUM(ac.total_debe) monto_total
FROM notas_credito_cabecera nc
JOIN asientos_contables_cabecera ac
     ON ac.id_empresa = nc.id_empresa AND ac.modulo_origen = 'nota_credito' AND ac.id_referencia_origen = nc.id
WHERE nc.eliminado = true AND ac.eliminado = false AND ac.estado <> 'anulado'
GROUP BY nc.id_empresa
UNION ALL
SELECT 'liquidacion_compra', lq.id_empresa, COUNT(*), SUM(ac.total_debe)
FROM liquidaciones_cabecera lq
JOIN asientos_contables_cabecera ac
     ON ac.id_empresa = lq.id_empresa AND ac.modulo_origen = 'liquidacion_compra' AND ac.id_referencia_origen = lq.id
WHERE lq.eliminado = true AND ac.eliminado = false AND ac.estado <> 'anulado'
GROUP BY lq.id_empresa
UNION ALL
SELECT 'retencion_compra', rc.id_empresa, COUNT(*), SUM(ac.total_debe)
FROM retencion_compra_cabecera rc
JOIN asientos_contables_cabecera ac
     ON ac.id_empresa = rc.id_empresa AND ac.modulo_origen = 'retencion_compra' AND ac.id_referencia_origen = rc.id
WHERE rc.eliminado = true AND ac.eliminado = false AND ac.estado <> 'anulado'
GROUP BY rc.id_empresa
ORDER BY modulo, id_empresa;
