-- =============================================================================
-- Diagnóstico (SOLO LECTURA): facturas / recibos de venta SIN asiento propio que
-- cobran los ingresos afectados por el 2.º error (saldo de cartera sumado a una
-- cuenta manual). Empresa 23 — ASAMED IMPLANT CIA LTDA (RUC 1792708389001).
--
-- Antes de volver a guardar esos ingresos conviene generar el asiento de estas
-- facturas/recibos (si corresponde): así el ingreso cancela la Cuenta por Cobrar
-- exacta que usó cada documento. Si no lo tienen a propósito (migrados, etc.),
-- su saldo irá a la cuenta del concepto del ingreso (Cuentas por Cobrar).
--
-- Para otra empresa, cambie el RUC en la marca "<== cambiar".
-- =============================================================================

WITH ingresos_afectados AS (
    -- Ingresos con el 2.º error (misma lógica que 20261001_cuenta_por_linea_todas_las_empresas.sql)
    SELECT DISTINCT i.id
      FROM (SELECT id_ingreso, id_cuenta_contable, SUM(monto_cobrado) AS monto_lineas
              FROM ingresos_detalle
             WHERE tipo_documento = 'OTRO' AND id_cuenta_contable IS NOT NULL AND monto_cobrado > 0
             GROUP BY id_ingreso, id_cuenta_contable) m
      JOIN ingresos_cabecera i ON i.id = m.id_ingreso AND i.eliminado = false
      JOIN empresas emp ON emp.id = i.id_empresa
      JOIN (SELECT c.id_referencia_origen AS id_ingreso, ad.id_cuenta_contable, SUM(ad.haber) AS en_asiento
              FROM asientos_contables_cabecera c
              JOIN asientos_contables_detalle ad ON ad.id_asiento = c.id AND ad.eliminado = false AND ad.haber > 0
             WHERE c.modulo_origen = 'ingreso' AND c.eliminado = false AND c.estado <> 'anulado'
             GROUP BY c.id_referencia_origen, ad.id_cuenta_contable) a
        ON a.id_ingreso = m.id_ingreso AND a.id_cuenta_contable = m.id_cuenta_contable
     WHERE emp.ruc = '1792708389001'          -- <== cambiar
       AND a.en_asiento > m.monto_lineas + 0.05
)
SELECT COALESCE(i.numero_ingreso,
                i.establecimiento || '-' || i.punto_emision || '-' || i.secuencial) AS ingreso,
       i.fecha_emision                       AS fecha_ingreso,
       d.tipo_documento,
       d.numero_documento,
       d.monto_cobrado,
       CASE d.tipo_documento WHEN 'FACTURA' THEN v.fecha_emision ELSE r.fecha_emision END AS fecha_documento,
       CASE d.tipo_documento WHEN 'FACTURA' THEN v.eliminado     ELSE r.eliminado     END AS documento_eliminado
  FROM ingresos_afectados x
  JOIN ingresos_cabecera i ON i.id = x.id
  JOIN ingresos_detalle d ON d.id_ingreso = i.id
                         AND d.tipo_documento IN ('FACTURA', 'RECIBO')
                         AND d.monto_cobrado > 0
  LEFT JOIN ventas_cabecera v        ON d.tipo_documento = 'FACTURA' AND v.id = d.id_referencia_documento
  LEFT JOIN recibos_venta_cabecera r ON d.tipo_documento = 'RECIBO'  AND r.id = d.id_referencia_documento
 WHERE NOT EXISTS (
         SELECT 1
           FROM asientos_contables_cabecera ac
          WHERE ac.modulo_origen = CASE d.tipo_documento WHEN 'FACTURA' THEN 'factura_venta' ELSE 'recibo_venta' END
            AND ac.id_referencia_origen = d.id_referencia_documento
            AND ac.id_empresa = i.id_empresa
            AND ac.eliminado = false
            AND ac.estado <> 'anulado'
       )
 ORDER BY i.fecha_emision, ingreso, d.numero_documento;
