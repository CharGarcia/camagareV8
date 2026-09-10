-- ============================================================================
-- Cuentas por Pagar · listado (estado = PENDIENTES) — MEDICIÓN
--
-- Es la consulta que ejecuta CuentasPorPagarRepository::getListado().
-- IMPORTANTE: reemplaza 999999 por el id de la empresa que va lenta (Buscar y
-- reemplazar en pgAdmin, Ctrl+H). Ese numero no aparece en ningun otro sitio.
--
-- Solo lee. Al final del resultado busca 'Execution Time'.
-- ============================================================================

EXPLAIN (ANALYZE, BUFFERS)

            WITH
            pagado AS (
            SELECT ed.tipo_documento,
                   ed.id_referencia_documento AS id_doc,
                   SUM(ed.monto_pagado)        AS total_pagado
            FROM egresos_detalle ed
            INNER JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
            WHERE ed.tipo_documento IN ('COMPRA','LIQUIDACION','IMPORTACION')
              AND ec.estado    != 'anulado'
              AND ec.eliminado  = false
              AND ed.eliminado  = false
              AND ec.id_empresa IN (999999)
              
            GROUP BY ed.tipo_documento, ed.id_referencia_documento
        ),
            nc_nd  AS (
            SELECT nc.id_empresa,
                   nc.id_proveedor,
                   nc.documento_modificado,
                   SUM(CASE WHEN nc.tipo_comprobante = '04' THEN nc.importe_total ELSE 0 END) AS total_nc,
                   SUM(CASE WHEN nc.tipo_comprobante = '05' THEN nc.importe_total ELSE 0 END) AS total_nd
            FROM compras_cabecera nc
            WHERE nc.tipo_comprobante IN ('04','05')
              AND nc.eliminado = false
              AND nc.id_empresa IN (999999)
              
            GROUP BY nc.id_empresa, nc.id_proveedor, nc.documento_modificado
        ),
            ret    AS (
            SELECT tmp.id_compra, tmp.id_liquidacion, SUM(tmp.monto) AS total_retenido
            FROM (
                SELECT r.id_compra, r.id_liquidacion, r.total_retenido AS monto, r.id AS id_ret
                FROM retencion_compra_cabecera r
                WHERE r.eliminado = false
                  AND UPPER(r.estado) NOT IN ('ANULADO','ANULADA','BORRADOR','PENDIENTE')
                  AND (r.id_compra IS NOT NULL OR r.id_liquidacion IS NOT NULL)
                  AND r.id_empresa IN (999999)
                  

                UNION

                SELECT c2.id AS id_compra, NULL::int AS id_liquidacion, r.total_retenido AS monto, r.id AS id_ret
                FROM retencion_compra_cabecera r
                JOIN compras_cabecera c2
                     ON regexp_replace(r.num_doc_sustento, '[^0-9]', '', 'g')
                        = regexp_replace(CONCAT(c2.establecimiento_prov,'-',c2.punto_emision_prov,'-',c2.secuencial_prov), '[^0-9]', '', 'g')
                    AND c2.id_empresa = r.id_empresa
                    AND c2.eliminado  = false
                WHERE r.eliminado = false
                  AND UPPER(r.estado) NOT IN ('ANULADO','ANULADA','BORRADOR','PENDIENTE')
                  AND r.id_compra IS NULL AND r.id_liquidacion IS NULL
                  AND r.num_doc_sustento IS NOT NULL AND r.num_doc_sustento <> ''
                  AND r.id_empresa IN (999999)
                  
            ) tmp
            GROUP BY tmp.id_compra, tmp.id_liquidacion
        ),
            docs   AS (
                -- ── FACTURAS DE COMPRA ────────────────────────────────────
                SELECT
                    c.id,
                    'COMPRA'                                                      AS tipo_fuente,
                    c.id_empresa AS id_empresa,
                COALESCE(emp.establecimiento, '') AS establecimiento,
                COALESCE(NULLIF(emp.nombre_comercial, ''), emp.nombre, '') AS empresa_nombre,
                    c.id_proveedor,
                    p.razon_social                                                AS proveedor_nombre,
                    p.identificacion                                              AS proveedor_ruc,
                    COALESCE(p.email,   '')                                       AS proveedor_email,
                    COALESCE(p.telefono,'')                                       AS proveedor_telefono,
                    CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov) AS numero_documento,
                    c.fecha_emision,
                    -- El total del documento incluye los valores de terceros (bomberos, tasa
                    -- de basura de las planillas de servicios básicos): es lo que se paga.
                    -- importe_total, sin ellos, sigue siendo el valor declarado al SRI.
                    c.importe_total + COALESCE(c.total_terceros, 0)                AS total,
                    COALESCE(pg.total_pagado, 0)                                  AS total_pagado,
                    COALESCE(nn.total_nc, 0)                                      AS total_nc,
                    COALESCE(nn.total_nd, 0)                                      AS total_nd,
                    COALESCE(ret.total_retenido, 0)                               AS total_retenido,
                    -- Valores recaudados por cuenta de terceros (planillas de luz/agua:
                    -- bomberos, tasa de basura). No son parte del importe declarado al SRI
                    -- pero sí se transfieren al proveedor, así que suman al saldo por pagar.
                    c.importe_total
                        + COALESCE(c.total_terceros,  0)
                        - COALESCE(pg.total_pagado,   0)
                        - COALESCE(ret.total_retenido,0)
                        - COALESCE(nn.total_nc,       0)
                        + COALESCE(nn.total_nd,       0)                         AS saldo,
                    (
            SELECT c.fecha_emision + INTERVAL '1 day' *
                COALESCE(
                    (SELECT CASE cp.unidad_tiempo
                                WHEN 'meses' THEN cp.plazo * 30
                                WHEN 'anos'  THEN cp.plazo * 365
                                ELSE cp.plazo
                            END
                     FROM compras_pagos cp
                     WHERE cp.id_compra = c.id
                     ORDER BY cp.plazo DESC LIMIT 1),
                    0
                )
        )                                                    AS fecha_vencimiento
                FROM compras_cabecera c
                LEFT JOIN empresas emp
                  ON emp.id = c.id_empresa
                JOIN proveedores p
                  ON p.id = c.id_proveedor
                LEFT JOIN pagado pg
                  ON pg.tipo_documento = 'COMPRA'
                 AND pg.id_doc = c.id
                LEFT JOIN nc_nd nn
                  ON nn.id_empresa         = c.id_empresa
                 AND nn.id_proveedor       = c.id_proveedor
                 AND nn.documento_modificado = CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov)
                LEFT JOIN ret
                  ON ret.id_compra = c.id
                 AND ret.id_liquidacion IS NULL
                WHERE c.id_empresa       IN (999999)
                  AND c.eliminado        = false
                  AND COALESCE(NULLIF(TRIM(c.tipo_comprobante), ''), '01') NOT IN ('04','23','47','51','05','06','07') AND UPPER(TRIM(COALESCE(c.estado, ''))) NOT IN ('ANULADO','ANULADA','RECHAZADA','RECHAZADO')
                  AND c.tipo_ambiente = (SELECT CAST(e.tipo_ambiente AS VARCHAR(1)) FROM empresas e WHERE e.id = c.id_empresa)

                UNION ALL

                -- ── LIQUIDACIONES DE COMPRA ───────────────────────────────
                SELECT
                    l.id,
                    'LIQUIDACION'                                                 AS tipo_fuente,
                    l.id_empresa AS id_empresa,
                COALESCE(emp.establecimiento, '') AS establecimiento,
                COALESCE(NULLIF(emp.nombre_comercial, ''), emp.nombre, '') AS empresa_nombre,
                    l.id_proveedor,
                    p.razon_social                                                AS proveedor_nombre,
                    p.identificacion                                              AS proveedor_ruc,
                    COALESCE(p.email,   '')                                       AS proveedor_email,
                    COALESCE(p.telefono,'')                                       AS proveedor_telefono,
                    CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial) AS numero_documento,
                    l.fecha_emision,
                    l.importe_total                                               AS total,
                    COALESCE(pg.total_pagado, 0)                                  AS total_pagado,
                    0::numeric                                                    AS total_nc,
                    0::numeric                                                    AS total_nd,
                    COALESCE(ret.total_retenido, 0)                               AS total_retenido,
                    l.importe_total
                        - COALESCE(pg.total_pagado,   0)
                        - COALESCE(ret.total_retenido,0)                         AS saldo,
                    (
            SELECT l.fecha_emision + INTERVAL '1 day' *
                COALESCE(
                    (SELECT CASE lp.unidad_tiempo
                                WHEN 'meses' THEN lp.plazo * 30
                                WHEN 'anos'  THEN lp.plazo * 365
                                ELSE lp.plazo
                            END
                     FROM liquidaciones_pagos lp
                     WHERE lp.id_cabecera = l.id
                     ORDER BY lp.plazo DESC LIMIT 1),
                    0
                )
        )                                                    AS fecha_vencimiento
                FROM liquidaciones_cabecera l
                LEFT JOIN empresas emp
                  ON emp.id = l.id_empresa
                JOIN proveedores p
                  ON p.id = l.id_proveedor
                LEFT JOIN pagado pg
                  ON pg.tipo_documento = 'LIQUIDACION'
                 AND pg.id_doc = l.id
                LEFT JOIN ret
                  ON ret.id_liquidacion = l.id
                 AND ret.id_compra IS NULL
                WHERE l.id_empresa    IN (999999)
                  AND l.eliminado     = false
                  AND UPPER(TRIM(COALESCE(l.estado, ''))) IN ('AUTORIZADO','AUTORIZADA','APROBADO','APROBADA','CONTABILIZADO','CONTABILIZADA')
                  AND (l.tipo_ambiente IS NULL OR l.tipo_ambiente = (SELECT CAST(e.tipo_ambiente AS VARCHAR(1)) FROM empresas e WHERE e.id = l.id_empresa))

                UNION ALL

                -- ── FACTURAS DEL PROVEEDOR DEL EXTERIOR (IMPORTACIONES) ───
                SELECT
                    fe.id,
                    'IMPORTACION'                                                  AS tipo_fuente,
                    ic.id_empresa AS id_empresa,
                COALESCE(emp.establecimiento, '') AS establecimiento,
                COALESCE(NULLIF(emp.nombre_comercial, ''), emp.nombre, '') AS empresa_nombre,
                    fe.id_proveedor,
                    p.razon_social                                                 AS proveedor_nombre,
                    p.identificacion                                               AS proveedor_ruc,
                    COALESCE(p.email,   '')                                        AS proveedor_email,
                    COALESCE(p.telefono,'')                                        AS proveedor_telefono,
                    COALESCE(fe.numero_factura, ic.numero_importacion)             AS numero_documento,
                    COALESCE(fe.fecha_factura, ic.fecha_nacionalizacion, ic.created_at::date) AS fecha_emision,
                    fe.monto_usd                                                   AS total,
                    COALESCE(pg.total_pagado, 0)                                   AS total_pagado,
                    0::numeric                                                     AS total_nc,
                    0::numeric                                                     AS total_nd,
                    0::numeric                                                     AS total_retenido,
                    fe.monto_usd - COALESCE(pg.total_pagado, 0)                    AS saldo,
                    (
            COALESCE(fe.fecha_factura, ic.fecha_nacionalizacion, ic.created_at::date)
            + INTERVAL '1 day' * COALESCE(fe.plazo_dias, 0)
        )                                                     AS fecha_vencimiento
                FROM importaciones_factura_exterior fe
                JOIN importaciones_cabecera ic
                  ON ic.id = fe.id_importacion
                LEFT JOIN empresas emp
                  ON emp.id = ic.id_empresa
                JOIN proveedores p
                  ON p.id = fe.id_proveedor
                LEFT JOIN pagado pg
                  ON pg.tipo_documento = 'IMPORTACION'
                 AND pg.id_doc = fe.id
                WHERE fe.eliminado    = false
                  AND ic.eliminado    = false
                  AND ic.id_empresa   IN (999999)
                  AND ic.tipo_ambiente = (SELECT CAST(e.tipo_ambiente AS VARCHAR(1)) FROM empresas e WHERE e.id = ic.id_empresa)
            )
            SELECT
                d.*,
                COALESCE((CURRENT_DATE - d.fecha_vencimiento::date), 0) AS dias_vencido
            FROM docs d
            WHERE 1=1
                   AND d.saldo > 0
            ORDER BY d.fecha_vencimiento ASC NULLS LAST, d.fecha_emision DESC
        ;
