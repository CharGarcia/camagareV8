-- =============================================================================
-- Diagnóstico Cuentas por Cobrar por RUC  (SOLO LECTURA: únicamente SELECT)
-- -----------------------------------------------------------------------------
-- Replica el cálculo de CuentasPorCobrarRepository (cobrado, retenido, NC, ND)
-- pero SIN los filtros del módulo, y explica en `motivo_fuera_cxc` por qué un
-- documento NO aparecería en el listado de pendientes.
--
-- El RUC se busca de dos formas:
--   * como EMPRESA emisora (empresas.ruc)  → su cartera completa
--   * como CLIENTE (clientes.identificacion, cédula o RUC) en cualquier empresa
-- Cambiar el RUC solo en el CTE `p` de cada consulta.
-- =============================================================================


-- ─── 1. FACTURAS DE VENTA ────────────────────────────────────────────────────
WITH p AS (SELECT '1717136574001'::text AS ruc),
fac AS (
    SELECT v.*,
           e.nombre                   AS empresa,
           e.ruc                      AS empresa_ruc,
           e.tipo_ambiente::text      AS ambiente_empresa,
           c.nombre                   AS cliente,
           c.identificacion           AS cliente_ident,
           CASE WHEN e.ruc = (SELECT ruc FROM p) THEN 'EMPRESA' ELSE 'CLIENTE' END AS ruc_como,
           (lpad(regexp_replace(v.establecimiento, '[^0-9]', '', 'g'), 3, '0')
         || lpad(regexp_replace(v.punto_emision,   '[^0-9]', '', 'g'), 3, '0')
         || lpad(regexp_replace(v.secuencial,      '[^0-9]', '', 'g'), 9, '0')) AS num_norm
    FROM ventas_cabecera v
    JOIN empresas e ON e.id = v.id_empresa
    JOIN clientes c ON c.id = v.id_cliente
    WHERE v.eliminado = false
      AND (   e.ruc = (SELECT ruc FROM p)
           OR c.identificacion LIKE left((SELECT ruc FROM p), 10) || '%')
),
cobrado AS (
    SELECT d.id_referencia_documento AS id_venta, SUM(d.monto_cobrado) AS total
    FROM ingresos_detalle d
    JOIN ingresos_cabecera ic ON ic.id = d.id_ingreso
    WHERE d.tipo_documento = 'FACTURA'
      AND ic.estado != 'anulado' AND ic.eliminado = false
      AND d.id_referencia_documento IN (SELECT id FROM fac)
    GROUP BY 1
),
retenido AS (
    SELECT x.id_venta, SUM(x.monto) AS total
    FROM (
        SELECT f.id AS id_venta, rd.valor_retenido AS monto
        FROM fac f
        JOIN retencion_venta_cabecera r ON r.id_empresa = f.id_empresa AND r.eliminado = false
        JOIN retencion_venta_detalle rd ON rd.id_retencion = r.id
        WHERE COALESCE(rd.num_doc_sustento, '') <> ''
          AND f.num_norm = CASE WHEN rd.num_doc_sustento LIKE '%-%-%'
                THEN lpad(regexp_replace(split_part(rd.num_doc_sustento, '-', 1), '[^0-9]', '', 'g'), 3, '0')
                  || lpad(regexp_replace(split_part(rd.num_doc_sustento, '-', 2), '[^0-9]', '', 'g'), 3, '0')
                  || lpad(regexp_replace(split_part(rd.num_doc_sustento, '-', 3), '[^0-9]', '', 'g'), 9, '0')
                ELSE regexp_replace(rd.num_doc_sustento, '[^0-9]', '', 'g') END
        UNION ALL
        SELECT r.id_venta, (r.total_renta + r.total_iva + r.total_isd)
        FROM retencion_venta_cabecera r
        WHERE r.eliminado = false
          AND r.id_venta IN (SELECT id FROM fac)
          AND NOT EXISTS (SELECT 1 FROM retencion_venta_detalle rd
                          WHERE rd.id_retencion = r.id AND COALESCE(rd.num_doc_sustento, '') <> '')
    ) x
    GROUP BY 1
),
notas AS (
    SELECT 'NC' AS tipo, n.id_empresa, n.num_doc_modificado AS ndm, n.importe_total
    FROM notas_credito_cabecera n
    WHERE n.estado != 'anulado' AND n.eliminado = false
      AND n.id_empresa IN (SELECT id_empresa FROM fac)
    UNION ALL
    SELECT 'ND', n.id_empresa, n.num_doc_modificado, n.importe_total
    FROM nota_debito_cabecera n
    WHERE n.estado != 'anulado' AND n.eliminado = false
      AND n.id_empresa IN (SELECT id_empresa FROM fac)
),
notas_norm AS (
    SELECT tipo, id_empresa, importe_total,
           CASE WHEN COALESCE(ndm, '') LIKE '%-%-%'
                THEN lpad(regexp_replace(split_part(ndm, '-', 1), '[^0-9]', '', 'g'), 3, '0')
                  || lpad(regexp_replace(split_part(ndm, '-', 2), '[^0-9]', '', 'g'), 3, '0')
                  || lpad(regexp_replace(split_part(ndm, '-', 3), '[^0-9]', '', 'g'), 9, '0')
                ELSE regexp_replace(COALESCE(ndm, ''), '[^0-9]', '', 'g') END AS num_norm
    FROM notas
),
calc AS (
    SELECT f.*,
           COALESCE(cb.total, 0) AS cobrado,
           COALESCE(rt.total, 0) AS retenido,
           COALESCE((SELECT SUM(importe_total) FROM notas_norm n
                     WHERE n.tipo = 'NC' AND n.id_empresa = f.id_empresa AND n.num_norm = f.num_norm), 0) AS nc,
           COALESCE((SELECT SUM(importe_total) FROM notas_norm n
                     WHERE n.tipo = 'ND' AND n.id_empresa = f.id_empresa AND n.num_norm = f.num_norm), 0) AS nd
    FROM fac f
    LEFT JOIN cobrado  cb ON cb.id_venta = f.id
    LEFT JOIN retenido rt ON rt.id_venta = f.id
)
SELECT ruc_como,
       id_empresa,
       empresa,
       id                                                  AS id_venta,
       establecimiento || '-' || punto_emision || '-' || secuencial AS numero,
       to_char(fecha_emision, 'DD-MM-YYYY')                AS emision,
       cliente,
       cliente_ident,
       estado,
       tipo_ambiente                                       AS ambiente_doc,
       ambiente_empresa,
       id_usuario,
       id_vendedor,
       dias_credito,
       importe_total,
       cobrado, retenido, nc, nd,
       importe_total + nd - cobrado - retenido - nc        AS saldo,
       CASE
           WHEN estado NOT IN ('autorizado', 'autorizada')
               THEN 'FUERA: estado ' || estado || ' (CxC solo toma autorizadas)'
           WHEN COALESCE(tipo_ambiente::text, '') <> COALESCE(ambiente_empresa, '')
               THEN 'FUERA: ambiente del documento (' || COALESCE(tipo_ambiente::text, 'NULL')
                 || ') distinto al de la empresa (' || COALESCE(ambiente_empresa, 'NULL') || ')'
           WHEN importe_total + nd - cobrado - retenido - nc <= 0
               THEN 'PAGADA (no sale en Pendientes)'
           ELSE 'PENDIENTE: debe salir en CxC'
       END                                                 AS motivo_fuera_cxc
FROM calc
ORDER BY ruc_como, id_empresa, fecha_emision, numero;


-- ─── 2. RECIBOS DE VENTA ─────────────────────────────────────────────────────
WITH p AS (SELECT '1717136574001'::text AS ruc)
SELECT CASE WHEN e.ruc = (SELECT ruc FROM p) THEN 'EMPRESA' ELSE 'CLIENTE' END AS ruc_como,
       v.id_empresa, e.nombre AS empresa, v.id AS id_recibo,
       to_char(v.fecha_emision, 'DD-MM-YYYY') AS emision,
       c.nombre AS cliente, c.identificacion AS cliente_ident,
       v.estado, v.tipo_ambiente AS ambiente_doc, e.tipo_ambiente AS ambiente_empresa,
       v.importe_total,
       COALESCE(cb.total, 0)                   AS cobrado,
       v.importe_total - COALESCE(cb.total, 0) AS saldo,
       CASE
           WHEN v.estado IN ('anulado', 'facturado') THEN 'FUERA: estado ' || v.estado
           WHEN COALESCE(v.tipo_ambiente::text, '') <> COALESCE(e.tipo_ambiente::text, '')
               THEN 'FUERA: ambiente distinto al de la empresa'
           WHEN v.importe_total - COALESCE(cb.total, 0) <= 0 THEN 'PAGADO'
           ELSE 'PENDIENTE: debe salir en CxC'
       END AS motivo_fuera_cxc
FROM recibos_venta_cabecera v
JOIN empresas e ON e.id = v.id_empresa
JOIN clientes c ON c.id = v.id_cliente
LEFT JOIN LATERAL (
    SELECT SUM(d.monto_cobrado) AS total
    FROM ingresos_detalle d
    JOIN ingresos_cabecera ic ON ic.id = d.id_ingreso
    WHERE d.tipo_documento = 'RECIBO' AND d.id_referencia_documento = v.id
      AND ic.estado != 'anulado' AND ic.eliminado = false
) cb ON true
WHERE v.eliminado = false
  AND (   e.ruc = (SELECT ruc FROM p)
       OR c.identificacion LIKE left((SELECT ruc FROM p), 10) || '%')
ORDER BY 1, v.id_empresa, v.fecha_emision;


-- ─── 3. SALDOS INICIALES CxC ─────────────────────────────────────────────────
WITH p AS (SELECT '1717136574001'::text AS ruc)
SELECT CASE WHEN e.ruc = (SELECT ruc FROM p) THEN 'EMPRESA' ELSE 'CLIENTE' END AS ruc_como,
       s.id_empresa, e.nombre AS empresa, s.id AS id_saldo, s.nro_documento,
       to_char(s.fecha_emision, 'DD-MM-YYYY')     AS emision,
       to_char(s.fecha_vencimiento, 'DD-MM-YYYY') AS vencimiento,
       s.nombre_cliente, s.ruc_cliente, s.id_cliente, s.created_by,
       s.saldo_inicial, s.monto_cobrado,
       s.saldo_inicial - COALESCE(s.monto_cobrado, 0) AS saldo_sin_ret_ni_nc
FROM saldos_iniciales_cxc s
JOIN empresas e ON e.id = s.id_empresa
WHERE s.eliminado = false
  AND (   e.ruc = (SELECT ruc FROM p)
       OR s.ruc_cliente LIKE left((SELECT ruc FROM p), 10) || '%')
ORDER BY 1, s.id_empresa, s.fecha_emision;


-- --- 4. SALDOS INICIALES CON EL CALCULO EXACTO DEL MODULO (cobrado + retenido + NC) ---
WITH p AS (SELECT '1717136574001'::text AS ruc)
SELECT s.id_empresa, s.id AS id_saldo, s.nro_documento, s.nombre_cliente,
       s.id_cliente, cli.id_vendedor AS vendedor_del_cliente, s.created_by,
       s.saldo_inicial,
       s.monto_cobrado               AS cobrado,
       COALESCE(ret.retenido, 0)     AS retenido,
       COALESCE(ncsi.nc_total, 0)    AS nc,
       s.saldo_inicial - s.monto_cobrado - COALESCE(ret.retenido, 0) - COALESCE(ncsi.nc_total, 0) AS saldo_pendiente,
       (SELECT string_agg(vc.id || ' ' || vc.estado, ', ') FROM ventas_cabecera vc
         WHERE vc.id_empresa = s.id_empresa AND vc.eliminado = false
           AND regexp_replace(vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial, '[^0-9]', '', 'g')
             = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')) AS factura_mismo_numero
FROM saldos_iniciales_cxc s
JOIN empresas e ON e.id = s.id_empresa
LEFT JOIN clientes cli ON cli.id = s.id_cliente
LEFT JOIN LATERAL (
    SELECT SUM(rd.valor_retenido) AS retenido
    FROM retencion_venta_detalle rd
    JOIN retencion_venta_cabecera r ON r.id = rd.id_retencion
    WHERE r.eliminado = false AND r.id_empresa = s.id_empresa
      AND r.id_venta IS NULL AND r.id_cliente = s.id_cliente
      AND COALESCE(rd.num_doc_sustento, '') <> ''
      AND regexp_replace(rd.num_doc_sustento, '[^0-9]', '', 'g') = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
      AND NOT EXISTS (SELECT 1 FROM ventas_cabecera vc
                      WHERE vc.id_empresa = s.id_empresa AND vc.eliminado = false
                        AND regexp_replace(vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial, '[^0-9]', '', 'g')
                          = regexp_replace(s.nro_documento, '[^0-9]', '', 'g'))
) ret ON true
LEFT JOIN LATERAL (
    SELECT SUM(ncc.importe_total) AS nc_total
    FROM notas_credito_cabecera ncc
    WHERE ncc.eliminado = false AND ncc.estado != 'anulado' AND ncc.id_empresa = s.id_empresa
      AND regexp_replace(ncc.num_doc_modificado, '[^0-9]', '', 'g') = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
      AND NOT EXISTS (SELECT 1 FROM ventas_cabecera vc
                      WHERE vc.id_empresa = s.id_empresa AND vc.eliminado = false
                        AND regexp_replace(vc.establecimiento || '-' || vc.punto_emision || '-' || vc.secuencial, '[^0-9]', '', 'g')
                          = regexp_replace(s.nro_documento, '[^0-9]', '', 'g'))
) ncsi ON true
WHERE s.eliminado = false
  AND e.ruc = (SELECT ruc FROM p)
ORDER BY s.fecha_emision;


-- --- 5. USUARIOS CON ACCESO A CUENTAS POR COBRAR EN ESA EMPRESA (submodulo 36) ---
-- Nivel 1 sin 't' solo ve lo que registro (created_by) o lo de su vendedor.
WITH p AS (SELECT '1717136574001'::text AS ruc)
SELECT e.id AS id_empresa, u.id AS id_usuario, u.nombre, u.cedula, u.nivel,
       ma.r, ma.w, ma.u, ma.d, ma.t,
       CASE WHEN u.nivel >= 2 OR ma.t::text IN ('1', 'true', 't') THEN 'VE TODO'
            ELSE 'SOLO PROPIOS / SU VENDEDOR' END AS alcance
FROM empresas e
JOIN modulos_asignados ma ON ma.id_empresa = e.id AND ma.id_submodulo = 36
JOIN usuarios u ON u.id = ma.id_usuario AND u.eliminado = false
WHERE e.ruc = (SELECT ruc FROM p)
ORDER BY u.nivel DESC, u.nombre;
