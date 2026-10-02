-- =====================================================================
-- Diagnóstico (SOLO LECTURA) — RUC 1717136574001: facturas que Cuentas por
-- Cobrar muestra pendientes y el buscador de Ingresos no (o con otro saldo).
-- Pegar completo en pgAdmin y ejecutar (F5). No modifica nada.
--
-- Calcula el saldo de cada factura con las dos reglas actuales:
--   saldo_cxc      = total + ND(ambiente actual) - cobros - retenciones(todas)
--                    - NC(ambiente actual o sin ambiente)
--   saldo_ingresos = total + ND(todas) - cobros - retenciones(ambiente actual)
--                    - NC(todas)
-- Las columnas nc_otro_ambiente / ret_otro_ambiente dicen qué parte explica la
-- diferencia. notas_credito lista cada NC con su ambiente (amb) y si vino de la
-- migración (mig).
-- Generado desde App\Helpers\AbonosVentaSql (mismas expresiones que el código).
-- =====================================================================
WITH emp AS (
    SELECT id, CAST(tipo_ambiente AS VARCHAR(1)) AS amb
    FROM empresas WHERE ruc = '1717136574001' AND eliminado = false
),
cobrado AS (
    SELECT d.id_referencia_documento AS id_venta, SUM(d.monto_cobrado) AS total
    FROM ingresos_detalle d
    JOIN ingresos_cabecera i ON i.id = d.id_ingreso
    WHERE d.tipo_documento = 'FACTURA' AND i.estado <> 'anulado' AND i.eliminado = false
      AND i.id_empresa = ANY(SELECT id FROM emp)
    GROUP BY 1
),
ret_todas AS (
            SELECT x.id_venta, SUM(x.monto) AS total_retenido
            FROM (
                -- (a) Líneas del detalle enlazadas a la factura por número de sustento
                --     (número normalizado): cada factura recibe lo retenido en SUS líneas.
                SELECT vc.id AS id_venta, SUM(rd.valor_retenido) AS monto
                FROM retencion_venta_cabecera r
                JOIN retencion_venta_detalle rd ON rd.id_retencion = r.id
                JOIN ventas_cabecera vc
                     ON vc.id_empresa = r.id_empresa
                    AND vc.eliminado  = false
                    AND (CASE WHEN COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), ''), '[^0-9]', '', 'g') END) = (CASE WHEN COALESCE(rd.num_doc_sustento, '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE(rd.num_doc_sustento, ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(rd.num_doc_sustento, ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(rd.num_doc_sustento, ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE(rd.num_doc_sustento, ''), '[^0-9]', '', 'g') END)
                WHERE r.eliminado  = false
                  AND r.id_empresa = ANY(SELECT id FROM emp)
                  AND COALESCE(rd.num_doc_sustento, '') <> ''
                  
                GROUP BY vc.id

                UNION ALL

                -- (b) Retenciones registradas desde la factura (id_venta) cuyo detalle
                --     no enlaza ninguna factura por número: total de la cabecera.
                SELECT r.id_venta, (r.total_renta + r.total_iva + r.total_isd) AS monto
                FROM retencion_venta_cabecera r
                WHERE r.eliminado  = false
                  AND r.id_empresa = ANY(SELECT id FROM emp)
                  AND r.id_venta IS NOT NULL
                  
                  AND NOT EXISTS (
                      SELECT 1
                      FROM retencion_venta_detalle rd
                      JOIN ventas_cabecera vc
                           ON vc.id_empresa = r.id_empresa
                          AND vc.eliminado  = false
                          AND (CASE WHEN COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), ''), '[^0-9]', '', 'g') END) = (CASE WHEN COALESCE(rd.num_doc_sustento, '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE(rd.num_doc_sustento, ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(rd.num_doc_sustento, ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(rd.num_doc_sustento, ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE(rd.num_doc_sustento, ''), '[^0-9]', '', 'g') END)
                      WHERE rd.id_retencion = r.id
                        AND COALESCE(rd.num_doc_sustento, '') <> ''
                  )
            ) x
            GROUP BY x.id_venta
        ),
ret_amb   AS (
            SELECT x.id_venta, SUM(x.monto) AS total_retenido
            FROM (
                -- (a) Líneas del detalle enlazadas a la factura por número de sustento
                --     (número normalizado): cada factura recibe lo retenido en SUS líneas.
                SELECT vc.id AS id_venta, SUM(rd.valor_retenido) AS monto
                FROM retencion_venta_cabecera r
                JOIN retencion_venta_detalle rd ON rd.id_retencion = r.id
                JOIN ventas_cabecera vc
                     ON vc.id_empresa = r.id_empresa
                    AND vc.eliminado  = false
                    AND (CASE WHEN COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), ''), '[^0-9]', '', 'g') END) = (CASE WHEN COALESCE(rd.num_doc_sustento, '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE(rd.num_doc_sustento, ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(rd.num_doc_sustento, ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(rd.num_doc_sustento, ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE(rd.num_doc_sustento, ''), '[^0-9]', '', 'g') END)
                WHERE r.eliminado  = false
                  AND r.id_empresa = ANY(SELECT id FROM emp)
                  AND COALESCE(rd.num_doc_sustento, '') <> ''
                  AND r.tipo_ambiente = (SELECT amb FROM emp WHERE emp.id = r.id_empresa)
                GROUP BY vc.id

                UNION ALL

                -- (b) Retenciones registradas desde la factura (id_venta) cuyo detalle
                --     no enlaza ninguna factura por número: total de la cabecera.
                SELECT r.id_venta, (r.total_renta + r.total_iva + r.total_isd) AS monto
                FROM retencion_venta_cabecera r
                WHERE r.eliminado  = false
                  AND r.id_empresa = ANY(SELECT id FROM emp)
                  AND r.id_venta IS NOT NULL
                  AND r.tipo_ambiente = (SELECT amb FROM emp WHERE emp.id = r.id_empresa)
                  AND NOT EXISTS (
                      SELECT 1
                      FROM retencion_venta_detalle rd
                      JOIN ventas_cabecera vc
                           ON vc.id_empresa = r.id_empresa
                          AND vc.eliminado  = false
                          AND (CASE WHEN COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE((vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial), ''), '[^0-9]', '', 'g') END) = (CASE WHEN COALESCE(rd.num_doc_sustento, '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE(rd.num_doc_sustento, ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(rd.num_doc_sustento, ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(rd.num_doc_sustento, ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE(rd.num_doc_sustento, ''), '[^0-9]', '', 'g') END)
                      WHERE rd.id_retencion = r.id
                        AND COALESCE(rd.num_doc_sustento, '') <> ''
                  )
            ) x
            GROUP BY x.id_venta
        ),
nc_todas  AS (
            SELECT n.id_empresa, (CASE WHEN COALESCE(n.num_doc_modificado, '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE(n.num_doc_modificado, ''), '[^0-9]', '', 'g') END) AS num_norm,
                   SUM(n.importe_total) AS total
            FROM notas_credito_cabecera n
            WHERE n.estado    != 'anulado'
              AND n.eliminado  = false
              AND n.id_empresa = ANY(SELECT id FROM emp)
              
            GROUP BY 1, 2
        ),
nc_amb    AS (
            SELECT n.id_empresa, (CASE WHEN COALESCE(n.num_doc_modificado, '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE(n.num_doc_modificado, ''), '[^0-9]', '', 'g') END) AS num_norm,
                   SUM(n.importe_total) AS total
            FROM notas_credito_cabecera n
            WHERE n.estado    != 'anulado'
              AND n.eliminado  = false
              AND n.id_empresa = ANY(SELECT id FROM emp)
              AND (n.tipo_ambiente IS NULL OR n.tipo_ambiente = (SELECT amb FROM emp WHERE emp.id = n.id_empresa))
            GROUP BY 1, 2
        ),
nd_todas  AS (
            SELECT n.id_empresa, (CASE WHEN COALESCE(n.num_doc_modificado, '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE(n.num_doc_modificado, ''), '[^0-9]', '', 'g') END) AS num_norm,
                   SUM(n.importe_total) AS total
            FROM nota_debito_cabecera n
            WHERE n.estado    != 'anulado'
              AND n.eliminado  = false
              AND n.id_empresa = ANY(SELECT id FROM emp)
              
            GROUP BY 1, 2
        ),
nd_amb    AS (
            SELECT n.id_empresa, (CASE WHEN COALESCE(n.num_doc_modificado, '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE(n.num_doc_modificado, ''), '[^0-9]', '', 'g') END) AS num_norm,
                   SUM(n.importe_total) AS total
            FROM nota_debito_cabecera n
            WHERE n.estado    != 'anulado'
              AND n.eliminado  = false
              AND n.id_empresa = ANY(SELECT id FROM emp)
              AND (n.tipo_ambiente IS NULL OR n.tipo_ambiente = (SELECT amb FROM emp WHERE emp.id = n.id_empresa))
            GROUP BY 1, 2
        ),
base AS (
    SELECT v.id, v.id_empresa,
           v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial AS factura,
           v.estado, v.fecha_emision, c.nombre AS cliente, v.importe_total,
           COALESCE(cb.total, 0)  AS cobrado,
           COALESCE(rt.total_retenido, 0) AS ret_todas,
           COALESCE(ra.total_retenido, 0) AS ret_amb,
           COALESCE(nt.total, 0)  AS nc_todas,
           COALESCE(na.total, 0)  AS nc_amb,
           COALESCE(dt.total, 0)  AS nd_todas,
           COALESCE(da.total, 0)  AS nd_amb,
           (CASE WHEN COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '[^0-9]', '', 'g') END) AS num_norm
    FROM ventas_cabecera v
    JOIN emp ON emp.id = v.id_empresa AND v.tipo_ambiente = emp.amb
    JOIN clientes c ON c.id = v.id_cliente
    LEFT JOIN cobrado   cb ON cb.id_venta = v.id
    LEFT JOIN ret_todas rt ON rt.id_venta = v.id
    LEFT JOIN ret_amb   ra ON ra.id_venta = v.id
    LEFT JOIN nc_todas  nt ON nt.id_empresa = v.id_empresa AND nt.num_norm = (CASE WHEN COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '[^0-9]', '', 'g') END)
    LEFT JOIN nc_amb    na ON na.id_empresa = v.id_empresa AND na.num_norm = (CASE WHEN COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '[^0-9]', '', 'g') END)
    LEFT JOIN nd_todas  dt ON dt.id_empresa = v.id_empresa AND dt.num_norm = (CASE WHEN COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '[^0-9]', '', 'g') END)
    LEFT JOIN nd_amb    da ON da.id_empresa = v.id_empresa AND da.num_norm = (CASE WHEN COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '[^0-9]', '', 'g') END)
    WHERE v.eliminado = false AND v.estado NOT IN ('anulado', 'anulada')
)
SELECT b.id_empresa, b.factura, b.estado, b.fecha_emision, b.cliente, b.importe_total, b.cobrado,
       ROUND(b.importe_total + b.nd_amb - b.cobrado - b.ret_todas - b.nc_amb, 2)   AS saldo_cxc,
       ROUND(b.importe_total + b.nd_todas - b.cobrado - b.ret_amb - b.nc_todas, 2) AS saldo_ingresos,
       b.nc_todas - b.nc_amb   AS nc_otro_ambiente,
       b.ret_todas - b.ret_amb AS ret_otro_ambiente,
       b.nd_todas - b.nd_amb   AS nd_otro_ambiente,
       (SELECT string_agg(n.establecimiento || '-' || n.punto_emision || '-' || n.secuencial
                          || ' $' || n.importe_total || ' amb=' || COALESCE(n.tipo_ambiente::text, 'NULL')
                          || ' ' || n.estado
                          || CASE WHEN EXISTS (SELECT 1 FROM migracion_mysql_map m
                                                WHERE m.entidad = 'notas_credito' AND m.id_destino = n.id
                                                  AND m.id_empresa = n.id_empresa) THEN ' mig' ELSE '' END, ' | ')
          FROM notas_credito_cabecera n
         WHERE n.id_empresa = b.id_empresa AND n.eliminado = false
           AND (CASE WHEN COALESCE(n.num_doc_modificado, '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE(n.num_doc_modificado, ''), '[^0-9]', '', 'g') END) = b.num_norm) AS notas_credito
FROM base b
WHERE ROUND(b.importe_total + b.nd_amb - b.cobrado - b.ret_todas - b.nc_amb, 2)
   <> ROUND(b.importe_total + b.nd_todas - b.cobrado - b.ret_amb - b.nc_todas, 2)
ORDER BY b.fecha_emision, b.factura;

-- Resumen por ambiente de las notas de crédito de la empresa (cuántas están en
-- un ambiente distinto al actual; esas NO descuentan en Cuentas por Cobrar):
WITH emp AS (
    SELECT id, CAST(tipo_ambiente AS VARCHAR(1)) AS amb
    FROM empresas WHERE ruc = '1717136574001' AND eliminado = false
)
SELECT n.id_empresa, emp.amb AS ambiente_empresa, n.tipo_ambiente AS ambiente_nc, n.estado,
       COUNT(*) AS notas, SUM(n.importe_total) AS total,
       COUNT(*) FILTER (WHERE EXISTS (SELECT 1 FROM migracion_mysql_map m
                                       WHERE m.entidad = 'notas_credito' AND m.id_destino = n.id
                                         AND m.id_empresa = n.id_empresa)) AS migradas
FROM notas_credito_cabecera n
JOIN emp ON emp.id = n.id_empresa
WHERE n.eliminado = false
GROUP BY 1, 2, 3, 4
ORDER BY 1, 3, 4;
