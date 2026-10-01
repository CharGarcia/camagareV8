-- =============================================================================
-- Vista previa: facturas MIGRADAS con saldo pendiente (SOLO LECTURA)
-- -----------------------------------------------------------------------------
-- Insumo para el ingreso de cruce. Ejecutar cada consulta por separado (F5).
-- Cambiar RUC y fecha de corte en el CTE `p`.
-- Saldo = mismo calculo de Cuentas por Cobrar (cobros + retenciones + NC - ND).
-- =============================================================================


-- --- A. FACTURAS PENDIENTES (migradas = en migracion_mysql_map y no vinculadas) ---
WITH p AS (SELECT '1717136574001'::text AS ruc, DATE '2025-12-31' AS fecha_corte),
fac AS (
    SELECT v.id, v.id_empresa, v.id_cliente, v.fecha_emision, v.importe_total,
           v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial AS numero,
           (lpad(regexp_replace(v.establecimiento, '[^0-9]', '', 'g'), 3, '0')
         || lpad(regexp_replace(v.punto_emision,   '[^0-9]', '', 'g'), 3, '0')
         || lpad(regexp_replace(v.secuencial,      '[^0-9]', '', 'g'), 9, '0')) AS num_norm,
           EXISTS (SELECT 1 FROM migracion_mysql_map mm
                    WHERE mm.entidad = 'facturas' AND mm.id_destino = v.id
                      AND mm.vinculado IS NOT TRUE) AS es_migrada
    FROM ventas_cabecera v
    JOIN empresas e ON e.id = v.id_empresa
    WHERE e.ruc = (SELECT ruc FROM p)
      AND v.eliminado = false
      AND v.estado IN ('autorizado', 'autorizada')
      AND v.tipo_ambiente::text = e.tipo_ambiente::text
      AND v.fecha_emision <= (SELECT fecha_corte FROM p)
),
calc AS (
    SELECT f.*,
           COALESCE((SELECT SUM(d.monto_cobrado) FROM ingresos_detalle d
                       JOIN ingresos_cabecera ic ON ic.id = d.id_ingreso
                      WHERE d.tipo_documento = 'FACTURA' AND d.id_referencia_documento = f.id
                        AND ic.estado != 'anulado' AND ic.eliminado = false), 0) AS cobrado,
           COALESCE((SELECT SUM(x.m) FROM (
                SELECT rd.valor_retenido AS m
                  FROM retencion_venta_cabecera r
                  JOIN retencion_venta_detalle rd ON rd.id_retencion = r.id
                 WHERE r.eliminado = false AND r.id_empresa = f.id_empresa
                   AND COALESCE(rd.num_doc_sustento, '') <> ''
                   AND regexp_replace(rd.num_doc_sustento, '[^0-9]', '', 'g') = f.num_norm
                UNION ALL
                SELECT (r.total_renta + r.total_iva + r.total_isd)
                  FROM retencion_venta_cabecera r
                 WHERE r.eliminado = false AND r.id_venta = f.id
                   AND NOT EXISTS (SELECT 1 FROM retencion_venta_detalle rd
                                    WHERE rd.id_retencion = r.id AND COALESCE(rd.num_doc_sustento, '') <> '')
           ) x), 0) AS retenido,
           COALESCE((SELECT SUM(n.importe_total) FROM notas_credito_cabecera n
                      WHERE n.estado != 'anulado' AND n.eliminado = false AND n.id_empresa = f.id_empresa
                        AND regexp_replace(COALESCE(n.num_doc_modificado, ''), '[^0-9]', '', 'g') = f.num_norm), 0) AS nc,
           COALESCE((SELECT SUM(n.importe_total) FROM nota_debito_cabecera n
                      WHERE n.estado != 'anulado' AND n.eliminado = false AND n.id_empresa = f.id_empresa
                        AND regexp_replace(COALESCE(n.num_doc_modificado, ''), '[^0-9]', '', 'g') = f.num_norm), 0) AS nd
    FROM fac f
)
SELECT c.id_empresa, c.id AS id_venta, c.numero, to_char(c.fecha_emision, 'DD-MM-YYYY') AS emision,
       cl.nombre AS cliente, cl.identificacion, c.es_migrada,
       c.importe_total, c.cobrado, c.retenido, c.nc, c.nd,
       c.importe_total + c.nd - c.cobrado - c.retenido - c.nc AS saldo
FROM calc c
JOIN clientes cl ON cl.id = c.id_cliente
WHERE c.importe_total + c.nd - c.cobrado - c.retenido - c.nc > 0
ORDER BY c.es_migrada DESC, cl.nombre, c.fecha_emision;


-- --- B. FORMAS DE COBRO de la empresa (para elegir la del cruce) ---------------
WITH p AS (SELECT '1717136574001'::text AS ruc)
SELECT fp.id_empresa, fp.id AS id_forma_cobro, fp.nombre, fp.tipo, fp.aplica_en,
       fp.activo, fp.id_cuenta_contable
FROM empresa_formas_pago fp
JOIN empresas e ON e.id = fp.id_empresa
WHERE e.ruc = (SELECT ruc FROM p) AND fp.eliminado = false
ORDER BY fp.id_empresa, fp.activo DESC, fp.nombre;


-- --- C. SERIES y NUMERACION DE INGRESOS (el cruce necesita modo consecutivo) ---
WITH p AS (SELECT '1717136574001'::text AS ruc)
SELECT es.id_empresa, es.id_punto_emision, es.tipo_documento,
       COALESCE(es.modo_numeracion, 'consecutivo') AS modo, es.periodo_reinicio, es.secuencial_inicial,
       (SELECT max(ic.secuencial) FROM ingresos_cabecera ic
         WHERE ic.id_punto_emision = es.id_punto_emision AND ic.eliminado = false) AS ultimo_secuencial
FROM empresa_secuencial es
JOIN empresas e ON e.id = es.id_empresa
WHERE e.ruc = (SELECT ruc FROM p) AND es.eliminado = false AND es.tipo_documento = 'Ingresos';
