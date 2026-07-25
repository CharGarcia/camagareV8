-- Concilia el saldo del Libro Mayor de la cuenta de control (Cuentas por Pagar / Cuentas
-- por Cobrar) contra el saldo pendiente que muestran los reportes operativos, a una misma
-- fecha de corte. Reemplaza :id_empresa y :fecha_corte antes de correr.
--
-- IMPORTANTE antes de esperar que cuadre exacto:
--  1. El saldo del Mayor NO arrastra saldo inicial automático (decisión ya tomada en este
--     sistema) -- si hay saldos cargados en saldos_iniciales_cxp/cxc, deben tener también
--     su asiento de apertura manual en el Mayor por el mismo monto, o el desfase es esperado
--     (ver consulta 5 y 6 para detectarlo).
--  2. La cuenta de CxP/CxC NO está fija en el código -- se configura por empresa. La consulta
--     1 la resuelve dinámicamente vía asientos_programados/asientos_tipo (PORPAGARFACTURACOMPRA
--     / PORCOBRARFACTURAVENTA); no asumas que siempre es 2.1.1.01.001 / 1.1.2.01.001.
--  3. Recibos de Venta e Importaciones usan sus PROPIAS cuentas (PORCOBRARRECIBOVENTA,
--     PORPAGARPROVEEDOREXTERIOR) -- Recibos NO está incluido en el reporte de Cuentas por
--     Cobrar; Importaciones SÍ está incluido en Cuentas por Pagar (consulta 3).

-- 1) Resolver qué cuenta contable está configurada HOY para CxP y CxC de facturas
SELECT at.codigo AS concepto, ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
FROM asientos_programados ap
JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
WHERE ap.id_empresa = :id_empresa
  AND ap.eliminado = false
  AND at.codigo IN ('PORPAGARFACTURACOMPRA', 'PORCOBRARFACTURAVENTA');

-- 2) Saldo del Mayor de esas cuentas a la fecha de corte (histórico completo, sin arrastre
--    manual -- por eso fecha_inicio va bien atrás, no solo el año en curso).
--    Reemplaza :id_cuenta_cxp / :id_cuenta_cxc con los id_cuenta que salieron en la consulta 1.
SELECT
    pc.codigo, pc.nombre,
    SUM(CASE WHEN LEFT(pc.codigo,1) IN ('1','5','6') THEN ad.debe - ad.haber
             ELSE ad.haber - ad.debe END) AS saldo_mayor
FROM asientos_contables_detalle ad
JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
JOIN plan_cuentas pc ON ad.id_cuenta_contable = pc.id
WHERE ac.id_empresa = :id_empresa
  AND ac.estado = 'contabilizado'
  AND ac.eliminado = false
  AND ad.eliminado = false
  AND ac.fecha_asiento >= '1900-01-01'
  AND ac.fecha_asiento <= :fecha_corte
  AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
  AND pc.id IN (:id_cuenta_cxp, :id_cuenta_cxc)
GROUP BY pc.codigo, pc.nombre;

-- 3) Saldo pendiente total del reporte Cuentas por Pagar a la fecha de corte
--    (facturas + liquidaciones + importaciones, netas de pagos/retenciones/NC/ND)
WITH pagado AS (
    SELECT ed.tipo_documento, ed.id_referencia_documento AS id_doc,
           SUM(ed.monto_pagado) AS total_pagado
    FROM egresos_detalle ed
    JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
    WHERE ed.tipo_documento IN ('COMPRA','LIQUIDACION','IMPORTACION')
      AND ec.estado != 'anulado' AND ec.eliminado = false AND ed.eliminado = false
      AND ec.fecha_emision <= :fecha_corte
    GROUP BY ed.tipo_documento, ed.id_referencia_documento
),
nc_nd AS (
    SELECT nc.id_empresa, nc.id_proveedor, nc.documento_modificado,
           SUM(CASE WHEN nc.tipo_comprobante='04' THEN nc.importe_total ELSE 0 END) AS total_nc,
           SUM(CASE WHEN nc.tipo_comprobante='05' THEN nc.importe_total ELSE 0 END) AS total_nd
    FROM compras_cabecera nc
    WHERE nc.tipo_comprobante IN ('04','05') AND nc.eliminado = false
      AND nc.fecha_emision <= :fecha_corte
    GROUP BY nc.id_empresa, nc.id_proveedor, nc.documento_modificado
),
ret AS (
    SELECT r.id_compra, r.id_liquidacion, SUM(r.total_retenido) AS total_retenido
    FROM retencion_compra_cabecera r
    WHERE r.eliminado = false
      AND UPPER(r.estado) NOT IN ('ANULADO','BORRADOR','PENDIENTE')
      AND r.fecha_emision <= :fecha_corte
    GROUP BY r.id_compra, r.id_liquidacion
),
docs AS (
    SELECT c.importe_total - COALESCE(pg.total_pagado,0) - COALESCE(ret.total_retenido,0)
           - COALESCE(nn.total_nc,0) + COALESCE(nn.total_nd,0) AS saldo
    FROM compras_cabecera c
    LEFT JOIN pagado pg ON pg.tipo_documento='COMPRA' AND pg.id_doc=c.id
    LEFT JOIN nc_nd nn ON nn.id_empresa=c.id_empresa AND nn.id_proveedor=c.id_proveedor
        AND nn.documento_modificado=CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov)
    LEFT JOIN ret ON ret.id_compra=c.id AND ret.id_liquidacion IS NULL
    WHERE c.id_empresa = :id_empresa AND c.eliminado=false AND c.tipo_comprobante='01'
      AND c.fecha_emision <= :fecha_corte
    UNION ALL
    SELECT l.importe_total - COALESCE(pg.total_pagado,0) - COALESCE(ret.total_retenido,0) AS saldo
    FROM liquidaciones_cabecera l
    LEFT JOIN pagado pg ON pg.tipo_documento='LIQUIDACION' AND pg.id_doc=l.id
    LEFT JOIN ret ON ret.id_liquidacion=l.id AND ret.id_compra IS NULL
    WHERE l.id_empresa = :id_empresa AND l.eliminado=false
      AND UPPER(l.estado) IN ('AUTORIZADO','APROBADO')
      AND l.fecha_emision <= :fecha_corte
    UNION ALL
    SELECT fe.monto_usd - COALESCE(pg.total_pagado,0) AS saldo
    FROM importaciones_factura_exterior fe
    JOIN importaciones_cabecera ic ON ic.id = fe.id_importacion
    LEFT JOIN pagado pg ON pg.tipo_documento='IMPORTACION' AND pg.id_doc=fe.id
    WHERE ic.id_empresa = :id_empresa AND fe.eliminado=false AND ic.eliminado=false
      AND COALESCE(fe.fecha_factura, ic.fecha_nacionalizacion, ic.created_at::date) <= :fecha_corte
)
SELECT SUM(saldo) AS saldo_pendiente_cxp_total_sin_filtrar_positivos,
       SUM(CASE WHEN saldo > 0 THEN saldo ELSE 0 END) AS saldo_pendiente_cxp_solo_positivos_igual_al_reporte
FROM docs;

-- 4) Saldo pendiente total del reporte Cuentas por Cobrar a la fecha de corte
--    (facturas, netas de cobros/retenciones/NC; Recibos de Venta quedan fuera del alcance)
WITH cobrado AS (
    SELECT idt.id_referencia_documento AS id_venta, SUM(idt.monto_cobrado) AS total_cobrado
    FROM ingresos_detalle idt
    JOIN ingresos_cabecera ic ON ic.id = idt.id_ingreso
    WHERE idt.tipo_documento='FACTURA' AND ic.estado != 'anulado' AND ic.eliminado=false
      AND ic.fecha_emision <= :fecha_corte
    GROUP BY idt.id_referencia_documento
),
retenido AS (
    SELECT tmp.id_venta, SUM(tmp.monto) AS total_retenido
    FROM (
        SELECT r.id_venta, (r.total_renta + r.total_iva + r.total_isd) AS monto
        FROM retencion_venta_cabecera r
        WHERE r.eliminado=false AND r.id_venta IS NOT NULL AND r.fecha_emision <= :fecha_corte
        UNION
        SELECT vc.id AS id_venta, (r.total_renta + r.total_iva + r.total_isd) AS monto
        FROM retencion_venta_cabecera r
        JOIN retencion_venta_detalle rd ON rd.id_retencion = r.id
        JOIN ventas_cabecera vc ON rd.num_doc_sustento = CONCAT(vc.establecimiento,'-',vc.punto_emision,'-',vc.secuencial)
            AND vc.id_empresa = r.id_empresa AND vc.eliminado = false
        WHERE r.eliminado=false AND r.fecha_emision <= :fecha_corte
    ) tmp GROUP BY tmp.id_venta
),
nc_aplic AS (
    SELECT nc.num_doc_modificado, SUM(nc.importe_total) AS total_nc
    FROM notas_credito_cabecera nc
    WHERE nc.estado != 'anulado' AND nc.eliminado=false AND nc.id_empresa = :id_empresa
      AND nc.fecha_emision <= :fecha_corte
    GROUP BY nc.num_doc_modificado
)
SELECT
    SUM(v.importe_total - COALESCE(cb.total_cobrado,0) - COALESCE(rt.total_retenido,0) - COALESCE(nc.total_nc,0)) AS saldo_pendiente_cxc_total,
    SUM(CASE WHEN (v.importe_total - COALESCE(cb.total_cobrado,0) - COALESCE(rt.total_retenido,0) - COALESCE(nc.total_nc,0)) > 0
             THEN (v.importe_total - COALESCE(cb.total_cobrado,0) - COALESCE(rt.total_retenido,0) - COALESCE(nc.total_nc,0)) ELSE 0 END) AS saldo_pendiente_cxc_solo_positivos_igual_al_reporte
FROM ventas_cabecera v
LEFT JOIN cobrado cb ON cb.id_venta = v.id
LEFT JOIN retenido rt ON rt.id_venta = v.id
LEFT JOIN nc_aplic nc ON nc.num_doc_modificado = CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial)
WHERE v.id_empresa = :id_empresa AND v.eliminado=false AND UPPER(v.estado) IN ('AUTORIZADO','AUTORIZADA')
  AND v.fecha_emision <= :fecha_corte;

-- 5) Saldos iniciales CXP/CXC cargados por el módulo de Saldos Iniciales -- si hay algo aquí,
--    súmalo aparte al resultado de 3/4 (así lo hace el reporte real), y confirma con la
--    consulta 6 si tienen su asiento de apertura contraparte en el Mayor.
SELECT 'cxp' AS tipo, SUM(saldo_pendiente) AS total_saldo_inicial_pendiente, COUNT(*) AS filas
FROM saldos_iniciales_cxp
WHERE id_empresa = :id_empresa AND eliminado = false
UNION ALL
SELECT 'cxc', SUM(saldo_pendiente), COUNT(*)
FROM saldos_iniciales_cxc
WHERE id_empresa = :id_empresa AND eliminado = false;

-- 6) ¿Existe algún asiento de tipo 'apertura' o manual que contabilice CxP/CxC? (para saber
--    si el saldo inicial del punto 5 tiene su contraparte en el Mayor, o si el desfase es
--    esperado por no tenerla)
SELECT ac.id, ac.numero_comprobante, ac.tipo_comprobante, ac.modulo_origen, ac.fecha_asiento,
       ad.debe, ad.haber, pc.codigo AS cuenta_codigo
FROM asientos_contables_cabecera ac
JOIN asientos_contables_detalle ad ON ad.id_asiento = ac.id
JOIN plan_cuentas pc ON pc.id = ad.id_cuenta_contable
WHERE ac.id_empresa = :id_empresa
  AND ac.eliminado = false AND ac.estado <> 'anulado'
  AND ac.tipo_comprobante IN ('apertura', 'diario')
  AND pc.id IN (:id_cuenta_cxp, :id_cuenta_cxc)
ORDER BY ac.fecha_asiento;
