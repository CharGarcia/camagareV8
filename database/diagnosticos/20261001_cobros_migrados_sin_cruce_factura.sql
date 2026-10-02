-- =====================================================================
-- Diagnóstico (SOLO LECTURA): cobros MIGRADOS que no se cruzan con su
-- factura de venta.  Pegar completo en pgAdmin; no modifica nada.
--
-- Cómo funciona el cruce: un cobro descuenta una factura solo si su línea
-- en ingresos_detalle tiene tipo_documento = 'FACTURA' e
-- id_referencia_documento = ventas_cabecera.id. La migración llena ese id
-- con el mapa de facturas que existía AL MOMENTO de migrar los cobros: si
-- los cobros se migraron antes que las facturas (o en otro rango de
-- fechas), la línea queda sin enlace y la factura sigue con saldo completo.
-- =====================================================================

-- 1) Resumen por empresa
SELECT i.id_empresa,
       e.nombre                                                         AS empresa,
       COUNT(*)                                                         AS lineas_cobro_factura,
       COUNT(*) FILTER (WHERE d.id_referencia_documento IS NULL)        AS sin_enlace,
       COUNT(*) FILTER (WHERE d.id_referencia_documento IS NULL
                          AND d.numero_documento IS NOT NULL)           AS sin_enlace_con_numero,
       COUNT(*) FILTER (WHERE d.id_referencia_documento IS NOT NULL
                          AND v.id IS NULL)                             AS enlace_a_factura_inexistente,
       SUM(d.monto_cobrado) FILTER (WHERE d.id_referencia_documento IS NULL
                                       OR v.id IS NULL)                 AS monto_sin_cruzar
FROM ingresos_detalle d
JOIN ingresos_cabecera i ON i.id = d.id_ingreso AND i.eliminado = false AND i.estado <> 'anulado'
JOIN empresas e          ON e.id = i.id_empresa
LEFT JOIN ventas_cabecera v ON v.id = d.id_referencia_documento
                           AND v.id_empresa = i.id_empresa AND v.eliminado = false
WHERE d.tipo_documento = 'FACTURA'
  AND EXISTS (SELECT 1 FROM migracion_mysql_map m
               WHERE m.entidad = 'ingresos' AND m.id_destino = i.id AND m.id_empresa = i.id_empresa)
GROUP BY i.id_empresa, e.nombre
HAVING COUNT(*) FILTER (WHERE d.id_referencia_documento IS NULL OR v.id IS NULL) > 0
ORDER BY monto_sin_cruzar DESC;

-- 2) Detalle: cada línea sin cruzar y la factura que le CORRESPONDERÍA por
--    número (normalizado a 15 dígitos: 3-3-9). factura_candidata NULL = esa
--    factura no existe en el sistema nuevo (no se migró).
SELECT i.id_empresa,
       i.numero_ingreso,
       i.fecha_emision          AS fecha_cobro,
       d.id                     AS id_detalle,
       d.numero_documento,
       d.descripcion,
       d.monto_cobrado,
       d.id_referencia_documento AS enlace_actual,
       (SELECT string_agg(v2.id::text || ' (' || v2.estado || ')', ', ')
          FROM ventas_cabecera v2
         WHERE v2.id_empresa = i.id_empresa AND v2.eliminado = false
           AND lpad(v2.establecimiento, 3, '0') || lpad(v2.punto_emision, 3, '0') || lpad(v2.secuencial, 9, '0')
             = lpad(split_part(d.numero_documento, '-', 1), 3, '0')
            || lpad(split_part(d.numero_documento, '-', 2), 3, '0')
            || lpad(split_part(d.numero_documento, '-', 3), 9, '0')
       )                        AS factura_candidata
FROM ingresos_detalle d
JOIN ingresos_cabecera i ON i.id = d.id_ingreso AND i.eliminado = false AND i.estado <> 'anulado'
LEFT JOIN ventas_cabecera v ON v.id = d.id_referencia_documento
                           AND v.id_empresa = i.id_empresa AND v.eliminado = false
WHERE d.tipo_documento = 'FACTURA'
  AND (d.id_referencia_documento IS NULL OR v.id IS NULL)
  AND EXISTS (SELECT 1 FROM migracion_mysql_map m
               WHERE m.entidad = 'ingresos' AND m.id_destino = i.id AND m.id_empresa = i.id_empresa)
ORDER BY i.id_empresa, i.fecha_emision, i.numero_ingreso
LIMIT 500;

-- 3) Empresa puntual por RUC (caso reportado: 1717136574001). Una fila por
--    factura no anulada del ambiente actual de la empresa: lo cobrado que SÍ
--    cruza, y las líneas de cobro que NO cruzan pero llevan su número
--    (cobro_sin_cruzar > 0 = esa es la falla).
WITH emp AS (
    SELECT id, CAST(tipo_ambiente AS VARCHAR(1)) AS amb
    FROM empresas WHERE ruc = '1717136574001' AND eliminado = false
)
SELECT v.id_empresa,
       v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial AS factura,
       v.estado,
       v.fecha_emision,
       v.importe_total,
       COALESCE((SELECT SUM(d.monto_cobrado)
                   FROM ingresos_detalle d
                   JOIN ingresos_cabecera i ON i.id = d.id_ingreso AND i.eliminado = false AND i.estado <> 'anulado'
                  WHERE d.tipo_documento = 'FACTURA' AND d.id_referencia_documento = v.id), 0) AS cobro_cruzado,
       COALESCE((SELECT SUM(d.monto_cobrado)
                   FROM ingresos_detalle d
                   JOIN ingresos_cabecera i ON i.id = d.id_ingreso AND i.eliminado = false AND i.estado <> 'anulado'
                  WHERE i.id_empresa = v.id_empresa
                    AND (d.id_referencia_documento IS NULL OR d.id_referencia_documento <> v.id
                         OR d.tipo_documento <> 'FACTURA')
                    AND d.numero_documento IS NOT NULL
                    AND lpad(split_part(d.numero_documento, '-', 1), 3, '0')
                     || lpad(split_part(d.numero_documento, '-', 2), 3, '0')
                     || lpad(split_part(d.numero_documento, '-', 3), 9, '0')
                      = lpad(v.establecimiento, 3, '0') || lpad(v.punto_emision, 3, '0') || lpad(v.secuencial, 9, '0')), 0) AS cobro_sin_cruzar,
       (SELECT string_agg(i.numero_ingreso || ' [' || d.tipo_documento || ' ref=' || COALESCE(d.id_referencia_documento::text, 'NULL') || '] $' || d.monto_cobrado, ' | ')
          FROM ingresos_detalle d
          JOIN ingresos_cabecera i ON i.id = d.id_ingreso AND i.eliminado = false AND i.estado <> 'anulado'
         WHERE i.id_empresa = v.id_empresa
           AND d.numero_documento IS NOT NULL
           AND lpad(split_part(d.numero_documento, '-', 1), 3, '0')
            || lpad(split_part(d.numero_documento, '-', 2), 3, '0')
            || lpad(split_part(d.numero_documento, '-', 3), 9, '0')
             = lpad(v.establecimiento, 3, '0') || lpad(v.punto_emision, 3, '0') || lpad(v.secuencial, 9, '0')) AS lineas_de_cobro
FROM ventas_cabecera v
JOIN emp ON emp.id = v.id_empresa AND v.tipo_ambiente = emp.amb
WHERE v.eliminado = false AND v.estado <> 'anulado'
ORDER BY cobro_sin_cruzar DESC, v.fecha_emision, v.secuencial;
