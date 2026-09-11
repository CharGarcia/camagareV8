-- ============================================================================
-- Compras y liquidaciones MIGRADAS que siguen "pendientes de pago" en Cuentas
-- por Pagar aunque tienen su pago: POR QUÉ no cruza cada una.
--
-- Cuentas por Pagar resta de cada documento: los pagos (egresos_detalle con el
-- id del documento, egreso no anulado), las retenciones vigentes (por id o, en
-- compras, por número de sustento) y las NC/ND (por documento_modificado). Todo
-- lo que la migración dejó sin ese enlace no resta, y el documento sigue
-- "pendiente". Este script clasifica cada documento pendiente por su causa.
--
-- SOLO LECTURA: no modifica ni crea nada.
-- USO: por defecto revisa TODAS las empresas (id_empresa = 0). Para una sola,
--      cambie el 0 de `parametros`. Son 8 consultas: resalte UNA y presione F5
--      (si ejecuta todo el archivo, pgAdmin muestra solo la última).
--        1) DOCUMENTOS pendientes: resumen por empresa / tipo / motivo  ← compartir
--        2) DOCUMENTOS pendientes: detalle uno por uno
--        3) LÍNEAS DE PAGO huérfanas (sin documento o con referencia rota): resumen
--        4) LÍNEAS DE PAGO huérfanas: detalle
--        5) Líneas "Otros conceptos" que traen un número de compra (solo revisión)
--        6) COBERTURA por empresa: pagos migrados, fechas, líneas          ← compartir
--        7) COBERTURA por empresa y año: compras sin pago vs egresos        ← compartir
--        8) LIQUIDACIONES sin pago: compra gemela / registro como compra   ← compartir
--
-- MOTIVOS (consultas 1 y 2) y cómo se corrige cada uno:
--   PAGO SIN ENLAZAR            líneas de pago de egresos migrados calzan con el documento
--                               por número pero no tienen su id (o apuntan a un documento
--                               que ya no existe: borrado y vuelto a migrar).
--                               → database/20260910_reenlazar_pagos_migrados.sql
--   PAGO SIN ENLAZAR (PARCIAL)  igual, pero esas líneas no cubren todo el saldo.
--   PAGO REGISTRADO COMO COMPRA / COMO LIQUIDACIÓN
--                               el pago quedó con el tipo equivocado. El primero lo corrige
--                               database/20260910_enlazar_pagos_liquidaciones_migradas.sql;
--                               si sigue apareciendo, el caso es ambiguo: consulta 4.
--   RETENCIÓN EN BORRADOR       la retención de compra que resta está en borrador/pendiente
--                               (migradas antes del arreglo de estado): CxP no la cuenta.
--                               → volver a migrar Retenciones en compra (corrige el estado).
--   RETENCIÓN SIN ENLAZAR       retención vigente que calza por número con la LIQUIDACIÓN
--                               pero sin id_liquidacion. → volver a migrar Liquidaciones de
--                               compra o Retenciones en compra (hace el cruce).
--   PAGO Y RETENCIÓN SIN ENLAZAR  combinación de los anteriores: juntos cubren el saldo.
--   NC RESTADA EN EL PAGO       el egreso migrado descontó la nota de crédito como línea
--                               negativa sobre la factura, y la NC no existe como documento
--                               propio: el saldo que queda es el valor de la NC. → volver a
--                               migrar Compras (inserta la NC) y luego Pagos (egresos).
--   PAGO EN EGRESO ANULADO      el pago apunta al documento pero su egreso está anulado o
--                               eliminado aquí (si en el sistema anterior estaba vigente,
--                               revisar ese egreso).
--   CENTAVOS                    saldo <= 0.05: redondeo distinto entre sistemas.
--   PAGO PARCIAL                tiene pagos enlazados que no cubren el total y no hay rastro
--                               de más pagos en este sistema.
--   SIN PAGO EN EL SISTEMA      ningún pago ni retención apunta al documento: o de verdad
--                               está pendiente, o el egreso nunca se migró (revisar
--                               omitidos/errores al migrar Pagos).
--
-- ESTADOS de las líneas huérfanas (consultas 3 y 4):
--   SE ENLAZA        calza con UN documento del mismo tipo, del proveedor del egreso (o con su
--                    nombre en el texto). Lo corrige 20260910_reenlazar_pagos_migrados.sql
--   ES UNA LIQUIDACIÓN / ES UNA COMPRA   el número calza con un documento del OTRO tipo.
--   AMBIGUA          más de un documento igual de plausible (p. ej. duplicado): revisar.
--   OTRO PROVEEDOR   calza por número pero con otro proveedor y sin su nombre en el texto.
--   SIN DOCUMENTO    ningún documento vivo de la empresa tiene ese número.
--   SIN NÚMERO       no se pudo leer un número en la línea.
--   La columna `enlace` dice si la línea no tiene id (SIN ENLACE) o apunta a un documento
--   borrado/inexistente (REFERENCIA ROTA: pasa al borrar migrados y volver a migrar).
-- ============================================================================


-- ── 1) DOCUMENTOS pendientes: resumen por empresa / tipo / motivo ────────────
WITH parametros AS (
    SELECT 0::int AS id_empresa          -- << 0 = todas; o el id de la empresa (p. ej. 23)
),
docs AS (
    SELECT 'COMPRA'::text AS tipo, c.id, c.id_empresa, c.id_proveedor, c.fecha_emision, c.estado,
           CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) AS numero,
           c.importe_total + COALESCE(c.total_terceros, 0) AS total,
           (SELECT BOOL_OR(COALESCE(m.vinculado, false)) FROM migracion_mysql_map m
             WHERE m.id_empresa = c.id_empresa AND m.entidad = 'compras' AND m.id_destino = c.id) AS vinculado
    FROM compras_cabecera c
    JOIN empresas emp ON emp.id = c.id_empresa
    CROSS JOIN parametros p
    WHERE c.eliminado = false
      AND (p.id_empresa = 0 OR c.id_empresa = p.id_empresa)
      AND EXISTS (SELECT 1 FROM migracion_mysql_map m WHERE m.id_empresa = c.id_empresa AND m.entidad = 'compras' AND m.id_destino = c.id)
      AND COALESCE(NULLIF(TRIM(c.tipo_comprobante), ''), '01') NOT IN ('04','23','47','51','05','06','07')
      AND UPPER(TRIM(COALESCE(c.estado, ''))) NOT IN ('ANULADO','ANULADA','RECHAZADA','RECHAZADO')
      AND c.tipo_ambiente = CAST(emp.tipo_ambiente AS VARCHAR(1))
    UNION ALL
    SELECT 'LIQUIDACION', l.id, l.id_empresa, l.id_proveedor, l.fecha_emision, l.estado,
           CONCAT(l.establecimiento, '-', l.punto_emision, '-', l.secuencial),
           l.importe_total,
           (SELECT BOOL_OR(COALESCE(m.vinculado, false)) FROM migracion_mysql_map m
             WHERE m.id_empresa = l.id_empresa AND m.entidad = 'liquidaciones' AND m.id_destino = l.id)
    FROM liquidaciones_cabecera l
    JOIN empresas emp ON emp.id = l.id_empresa
    CROSS JOIN parametros p
    WHERE l.eliminado = false
      AND (p.id_empresa = 0 OR l.id_empresa = p.id_empresa)
      AND EXISTS (SELECT 1 FROM migracion_mysql_map m WHERE m.id_empresa = l.id_empresa AND m.entidad = 'liquidaciones' AND m.id_destino = l.id)
      AND UPPER(TRIM(COALESCE(l.estado, ''))) IN ('AUTORIZADO','AUTORIZADA','APROBADO','APROBADA','CONTABILIZADO','CONTABILIZADA')
      AND (l.tipo_ambiente IS NULL OR l.tipo_ambiente = CAST(emp.tipo_ambiente AS VARCHAR(1)))
),
pagado AS (      -- lo que CxP resta como pagado
    SELECT d.tipo_documento AS tipo, d.id_referencia_documento AS id_doc,
           SUM(d.monto_pagado) AS pagado,
           COALESCE(SUM(d.monto_pagado) FILTER (WHERE d.monto_pagado < 0), 0) AS pago_negativo
    FROM egresos_detalle d
    JOIN egresos_cabecera e ON e.id = d.id_egreso
    WHERE d.tipo_documento IN ('COMPRA','LIQUIDACION') AND d.id_referencia_documento IS NOT NULL
      AND e.estado <> 'anulado' AND e.eliminado = false AND d.eliminado = false
    GROUP BY 1, 2
),
pago_anulado AS (   -- apunta al documento, pero el egreso está anulado/eliminado (CxP no lo cuenta)
    SELECT d.tipo_documento AS tipo, d.id_referencia_documento AS id_doc, SUM(d.monto_pagado) AS monto
    FROM egresos_detalle d
    JOIN egresos_cabecera e ON e.id = d.id_egreso
    WHERE d.tipo_documento IN ('COMPRA','LIQUIDACION') AND d.id_referencia_documento IS NOT NULL
      AND (e.estado = 'anulado' OR e.eliminado = true OR d.eliminado = true)
    GROUP BY 1, 2
),
nc_nd AS (
    SELECT nc.id_empresa, nc.id_proveedor, nc.documento_modificado,
           SUM(CASE WHEN nc.tipo_comprobante = '04' THEN nc.importe_total ELSE 0 END) AS total_nc,
           SUM(CASE WHEN nc.tipo_comprobante = '05' THEN nc.importe_total ELSE 0 END) AS total_nd
    FROM compras_cabecera nc
    WHERE nc.tipo_comprobante IN ('04','05') AND nc.eliminado = false
    GROUP BY 1, 2, 3
),
ret AS (         -- retenciones que CxP resta: por id, o (solo compras) por número de sustento
    SELECT tipo, id_doc, SUM(monto) AS retenido
    FROM (
        SELECT CASE WHEN r.id_compra IS NOT NULL THEN 'COMPRA' ELSE 'LIQUIDACION' END AS tipo,
               COALESCE(r.id_compra, r.id_liquidacion) AS id_doc, r.total_retenido AS monto, r.id AS id_ret
        FROM retencion_compra_cabecera r
        WHERE r.eliminado = false
          AND UPPER(r.estado) NOT IN ('ANULADO','ANULADA','BORRADOR','PENDIENTE')
          AND (r.id_compra IS NOT NULL OR r.id_liquidacion IS NOT NULL)
        UNION
        SELECT 'COMPRA', c2.id, r.total_retenido, r.id
        FROM retencion_compra_cabecera r
        JOIN compras_cabecera c2
          ON regexp_replace(r.num_doc_sustento, '[^0-9]', '', 'g')
             = regexp_replace(CONCAT(c2.establecimiento_prov, '-', c2.punto_emision_prov, '-', c2.secuencial_prov), '[^0-9]', '', 'g')
         AND c2.id_empresa = r.id_empresa AND c2.eliminado = false
        WHERE r.eliminado = false
          AND UPPER(r.estado) NOT IN ('ANULADO','ANULADA','BORRADOR','PENDIENTE')
          AND r.id_compra IS NULL AND r.id_liquidacion IS NULL
          AND r.num_doc_sustento IS NOT NULL AND r.num_doc_sustento <> ''
    ) t
    GROUP BY 1, 2
),
ret_borrador AS (   -- retenciones en borrador/pendiente que apuntan al documento (CxP NO las resta)
    SELECT tipo, id_doc, SUM(monto) AS monto
    FROM (
        SELECT CASE WHEN r.id_compra IS NOT NULL THEN 'COMPRA' ELSE 'LIQUIDACION' END AS tipo,
               COALESCE(r.id_compra, r.id_liquidacion) AS id_doc, r.total_retenido AS monto, r.id AS id_ret
        FROM retencion_compra_cabecera r
        WHERE r.eliminado = false AND UPPER(COALESCE(r.estado, '')) IN ('BORRADOR','PENDIENTE')
          AND (r.id_compra IS NOT NULL OR r.id_liquidacion IS NOT NULL)
        UNION
        SELECT 'COMPRA', c2.id, r.total_retenido, r.id
        FROM retencion_compra_cabecera r
        JOIN compras_cabecera c2
          ON regexp_replace(r.num_doc_sustento, '[^0-9]', '', 'g')
             = regexp_replace(CONCAT(c2.establecimiento_prov, '-', c2.punto_emision_prov, '-', c2.secuencial_prov), '[^0-9]', '', 'g')
         AND c2.id_empresa = r.id_empresa AND c2.eliminado = false
        WHERE r.eliminado = false AND UPPER(COALESCE(r.estado, '')) IN ('BORRADOR','PENDIENTE')
          AND r.id_compra IS NULL AND r.id_liquidacion IS NULL
          AND r.num_doc_sustento IS NOT NULL AND r.num_doc_sustento <> ''
        UNION
        SELECT 'LIQUIDACION', l2.id, r.total_retenido, r.id
        FROM retencion_compra_cabecera r
        JOIN liquidaciones_cabecera l2
          ON regexp_replace(r.num_doc_sustento, '[^0-9]', '', 'g')
             = COALESCE(l2.establecimiento, '') || COALESCE(l2.punto_emision, '') || COALESCE(l2.secuencial, '')
         AND l2.id_empresa = r.id_empresa AND l2.eliminado = false
        WHERE r.eliminado = false AND UPPER(COALESCE(r.estado, '')) IN ('BORRADOR','PENDIENTE')
          AND r.id_compra IS NULL AND r.id_liquidacion IS NULL
          AND r.num_doc_sustento IS NOT NULL AND r.num_doc_sustento <> ''
    ) t
    GROUP BY 1, 2
),
ret_sin_enlace AS ( -- retenciones vigentes que calzan por número con una LIQUIDACIÓN pero sin id (CxP no las resta)
    SELECT 'LIQUIDACION'::text AS tipo, l2.id AS id_doc, SUM(r.total_retenido) AS monto
    FROM retencion_compra_cabecera r
    JOIN liquidaciones_cabecera l2
      ON regexp_replace(r.num_doc_sustento, '[^0-9]', '', 'g')
         = COALESCE(l2.establecimiento, '') || COALESCE(l2.punto_emision, '') || COALESCE(l2.secuencial, '')
     AND l2.id_empresa = r.id_empresa AND l2.eliminado = false
    WHERE r.eliminado = false
      AND UPPER(r.estado) NOT IN ('ANULADO','ANULADA','BORRADOR','PENDIENTE')
      AND r.id_compra IS NULL AND r.id_liquidacion IS NULL
      AND r.num_doc_sustento IS NOT NULL AND r.num_doc_sustento <> ''
    GROUP BY 1, 2
),
lineas AS (      -- líneas de pago (COMPRA/LIQUIDACION) de egresos migrados
    SELECT d.id AS id_detalle, d.id_egreso, e.id_empresa, e.numero_egreso,
           e.fecha_emision AS fecha_egreso, e.estado AS estado_egreso,
           e.id_proveedor AS prov_egreso, e.tipo_ambiente AS amb_egreso,
           d.tipo_documento, d.id_referencia_documento AS id_ref,
           d.descripcion, d.numero_documento, d.monto_pagado,
           CASE
             WHEN d.id_referencia_documento IS NULL THEN 'SIN ENLACE'
             WHEN d.tipo_documento = 'COMPRA'
                  AND NOT EXISTS (SELECT 1 FROM compras_cabecera c WHERE c.id = d.id_referencia_documento AND c.eliminado = false)
                  THEN 'REFERENCIA ROTA'
             WHEN d.tipo_documento = 'LIQUIDACION'
                  AND NOT EXISTS (SELECT 1 FROM liquidaciones_cabecera l WHERE l.id = d.id_referencia_documento AND l.eliminado = false)
                  THEN 'REFERENCIA ROTA'
             ELSE 'OK'
           END AS enlace,
           COALESCE(
               CASE WHEN d.numero_documento ~ '^[0-9]{1,3}-[0-9]{1,3}-[0-9]{1,9}$'
                    THEN lpad(split_part(d.numero_documento, '-', 1), 3, '0')
                      || lpad(split_part(d.numero_documento, '-', 2), 3, '0')
                      || lpad(split_part(d.numero_documento, '-', 3), 9, '0')
               END,
               regexp_replace(substring(d.descripcion FROM '[0-9]{3}-?[0-9]{3}-?[0-9]{9}'), '[^0-9]', '', 'g')
           ) AS num15
    FROM egresos_detalle d
    JOIN egresos_cabecera e    ON e.id = d.id_egreso
    JOIN migracion_mysql_map m ON m.id_empresa = e.id_empresa AND m.entidad = 'egresos' AND m.id_destino = e.id
    CROSS JOIN parametros p
    WHERE d.tipo_documento IN ('COMPRA','LIQUIDACION')
      AND d.eliminado = false AND e.eliminado = false
      AND (p.id_empresa = 0 OR e.id_empresa = p.id_empresa)
),
huerfanas AS (SELECT * FROM lineas WHERE enlace <> 'OK'),
cand AS (        -- documentos vivos de la empresa con ese número (de ambos tipos)
    SELECT h.id_detalle, 'COMPRA'::text AS tipo_doc, c.id AS id_doc, c.fecha_emision,
           (h.tipo_documento = 'COMPRA') AS mismo_tipo,
           COALESCE(c.id_proveedor = h.prov_egreso
                    OR (length(p.razon_social) >= 4 AND h.descripcion ILIKE ('%' || p.razon_social || '%')), false) AS prov_ok,
           COALESCE(h.descripcion NOT ILIKE '%liquidaci%', true) AS texto_ok,
           (UPPER(TRIM(COALESCE(c.estado, ''))) NOT IN ('ANULADO','ANULADA','RECHAZADA','RECHAZADO')) AS vigente_ok,
           COALESCE(c.tipo_ambiente = h.amb_egreso, false) AS amb_ok,
           (CASE WHEN h.monto_pagado < 0
                 THEN COALESCE(NULLIF(TRIM(c.tipo_comprobante), ''), '01') IN ('04','23','47','51')
                 ELSE COALESCE(NULLIF(TRIM(c.tipo_comprobante), ''), '01') NOT IN ('04','23','47','51','05','06','07') END) AS signo_ok,
           COALESCE(c.fecha_emision <= h.fecha_egreso, false) AS antes_ok
    FROM huerfanas h
    JOIN compras_cabecera c
      ON c.id_empresa = h.id_empresa AND c.eliminado = false
     AND COALESCE(c.establecimiento_prov, '') || COALESCE(c.punto_emision_prov, '') || COALESCE(c.secuencial_prov, '') = h.num15
    JOIN proveedores p ON p.id = c.id_proveedor
    WHERE length(h.num15) = 15
    UNION ALL
    SELECT h.id_detalle, 'LIQUIDACION', l.id, l.fecha_emision,
           (h.tipo_documento = 'LIQUIDACION'),
           COALESCE(l.id_proveedor = h.prov_egreso
                    OR (length(p.razon_social) >= 4 AND h.descripcion ILIKE ('%' || p.razon_social || '%'))
                    OR h.descripcion ILIKE '%liquidaci%', false),
           COALESCE(h.descripcion ILIKE '%liquidaci%', false),
           (UPPER(TRIM(COALESCE(l.estado, ''))) IN ('AUTORIZADO','AUTORIZADA','APROBADO','APROBADA','CONTABILIZADO','CONTABILIZADA')),
           COALESCE(l.tipo_ambiente = h.amb_egreso, false),
           true,
           COALESCE(l.fecha_emision <= h.fecha_egreso, false)
    FROM huerfanas h
    JOIN liquidaciones_cabecera l
      ON l.id_empresa = h.id_empresa AND l.eliminado = false
     AND COALESCE(l.establecimiento, '') || COALESCE(l.punto_emision, '') || COALESCE(l.secuencial, '') = h.num15
    JOIN proveedores p ON p.id = l.id_proveedor
    WHERE length(h.num15) = 15
),
ranked AS (
    SELECT *, DENSE_RANK() OVER (PARTITION BY id_detalle
                 ORDER BY prov_ok DESC, texto_ok DESC, mismo_tipo DESC, vigente_ok DESC, amb_ok DESC,
                          signo_ok DESC, antes_ok DESC, fecha_emision DESC) AS rk
    FROM cand
),
eleccion AS (
    SELECT id_detalle,
           COUNT(*)                               AS candidatas,
           COUNT(*) FILTER (WHERE rk = 1)         AS empatadas,
           MIN(id_doc)   FILTER (WHERE rk = 1)    AS id_doc,
           MIN(tipo_doc) FILTER (WHERE rk = 1)    AS tipo_doc,
           BOOL_OR(prov_ok) FILTER (WHERE rk = 1) AS prov_ok
    FROM ranked
    GROUP BY id_detalle
),
clasif AS (
    SELECT h.*, COALESCE(el.candidatas, 0) AS candidatas, el.id_doc, el.tipo_doc,
           CASE
             WHEN h.num15 IS NULL OR length(h.num15) <> 15                       THEN 'SIN NÚMERO'
             WHEN el.id_doc IS NULL                                              THEN 'SIN DOCUMENTO'
             WHEN el.empatadas > 1                                               THEN 'AMBIGUA'
             WHEN NOT el.prov_ok                                                 THEN 'OTRO PROVEEDOR'
             WHEN el.tipo_doc <> h.tipo_documento AND el.tipo_doc = 'LIQUIDACION' THEN 'ES UNA LIQUIDACIÓN'
             WHEN el.tipo_doc <> h.tipo_documento                                THEN 'ES UNA COMPRA'
             ELSE 'SE ENLAZA'
           END AS estado
    FROM huerfanas h
    LEFT JOIN eleccion el ON el.id_detalle = h.id_detalle
),
huerfano_pago AS (
    SELECT tipo_doc AS tipo, id_doc,
           COALESCE(SUM(monto_pagado) FILTER (WHERE estado = 'SE ENLAZA'), 0)                              AS mismo_tipo,
           COALESCE(SUM(monto_pagado) FILTER (WHERE estado IN ('ES UNA LIQUIDACIÓN','ES UNA COMPRA')), 0) AS otro_tipo
    FROM clasif
    WHERE estado IN ('SE ENLAZA','ES UNA LIQUIDACIÓN','ES UNA COMPRA') AND estado_egreso <> 'anulado'
    GROUP BY 1, 2
),
docs_saldo AS (
    SELECT d.*,
           COALESCE(pg.pagado, 0)        AS pagado,
           COALESCE(pg.pago_negativo, 0) AS pago_negativo,
           COALESCE(nn.total_nc, 0)      AS nc,
           COALESCE(nn.total_nd, 0)      AS nd,
           COALESCE(r.retenido, 0)       AS retenido,
           d.total - COALESCE(pg.pagado, 0) - COALESCE(r.retenido, 0) - COALESCE(nn.total_nc, 0) + COALESCE(nn.total_nd, 0) AS saldo,
           COALESCE(hp.mismo_tipo, 0)    AS pago_sin_enlazar,
           COALESCE(hp.otro_tipo, 0)     AS pago_otro_tipo,
           COALESCE(pa.monto, 0)         AS pago_anulado,
           COALESCE(rb.monto, 0)         AS ret_borrador,
           COALESCE(rs.monto, 0)         AS ret_sin_enlazar
    FROM docs d
    LEFT JOIN pagado pg         ON pg.tipo = d.tipo AND pg.id_doc = d.id
    LEFT JOIN nc_nd nn          ON d.tipo = 'COMPRA' AND nn.id_empresa = d.id_empresa AND nn.id_proveedor = d.id_proveedor AND nn.documento_modificado = d.numero
    LEFT JOIN ret r             ON r.tipo = d.tipo AND r.id_doc = d.id
    LEFT JOIN huerfano_pago hp  ON hp.tipo = d.tipo AND hp.id_doc = d.id
    LEFT JOIN pago_anulado pa   ON pa.tipo = d.tipo AND pa.id_doc = d.id
    LEFT JOIN ret_borrador rb   ON rb.tipo = d.tipo AND rb.id_doc = d.id
    LEFT JOIN ret_sin_enlace rs ON rs.tipo = d.tipo AND rs.id_doc = d.id
),
pendientes AS (
    SELECT *,
           CASE
             WHEN saldo <= 0.05                                                              THEN 'CENTAVOS'
             WHEN pago_sin_enlazar >= saldo - 0.05                                           THEN 'PAGO SIN ENLAZAR'
             WHEN pago_otro_tipo >= saldo - 0.05 AND tipo = 'LIQUIDACION'                    THEN 'PAGO REGISTRADO COMO COMPRA'
             WHEN pago_otro_tipo >= saldo - 0.05                                             THEN 'PAGO REGISTRADO COMO LIQUIDACIÓN'
             WHEN pago_sin_enlazar + pago_otro_tipo > 0 AND ret_borrador + ret_sin_enlazar > 0
                  AND pago_sin_enlazar + pago_otro_tipo + ret_borrador + ret_sin_enlazar >= saldo - 0.05
                                                                                             THEN 'PAGO Y RETENCIÓN SIN ENLAZAR'
             WHEN pago_sin_enlazar + pago_otro_tipo > 0                                      THEN 'PAGO SIN ENLAZAR (PARCIAL)'
             WHEN ret_borrador >= saldo - 0.05                                               THEN 'RETENCIÓN EN BORRADOR'
             WHEN ret_sin_enlazar >= saldo - 0.05                                            THEN 'RETENCIÓN SIN ENLAZAR'
             WHEN pago_anulado >= saldo - 0.05                                               THEN 'PAGO EN EGRESO ANULADO'
             WHEN pago_negativo < 0 AND nc = 0 AND abs(saldo + pago_negativo) <= 0.05        THEN 'NC RESTADA EN EL PAGO'
             WHEN ret_borrador + ret_sin_enlazar > 0                                         THEN 'RETENCIÓN SIN RESTAR (PARCIAL)'
             WHEN pago_anulado > 0                                                           THEN 'PAGO EN EGRESO ANULADO (PARCIAL)'
             WHEN pagado > 0                                                                 THEN 'PAGO PARCIAL'
             ELSE 'SIN PAGO EN EL SISTEMA'
           END AS motivo
    FROM docs_saldo
    WHERE saldo > 0                     -- mismo criterio que la pestaña "Pendientes" de CxP
)
SELECT id_empresa, tipo, motivo,
       COUNT(*)                                   AS documentos,
       ROUND(SUM(saldo), 2)                       AS saldo_pendiente,
       ROUND(SUM(pago_sin_enlazar + pago_otro_tipo), 2) AS pago_sin_enlazar,
       ROUND(SUM(ret_borrador + ret_sin_enlazar), 2)    AS retencion_sin_restar,
       ROUND(SUM(pago_anulado), 2)                AS pago_en_anulados
FROM pendientes
GROUP BY id_empresa, tipo, motivo
ORDER BY id_empresa, tipo, motivo;


-- ── 2) DOCUMENTOS pendientes: detalle uno por uno ────────────────────────────
WITH parametros AS (
    SELECT 0::int AS id_empresa          -- << 0 = todas; o el id de la empresa (p. ej. 23)
),
docs AS (
    SELECT 'COMPRA'::text AS tipo, c.id, c.id_empresa, c.id_proveedor, c.fecha_emision, c.estado,
           CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) AS numero,
           c.importe_total + COALESCE(c.total_terceros, 0) AS total,
           (SELECT BOOL_OR(COALESCE(m.vinculado, false)) FROM migracion_mysql_map m
             WHERE m.id_empresa = c.id_empresa AND m.entidad = 'compras' AND m.id_destino = c.id) AS vinculado
    FROM compras_cabecera c
    JOIN empresas emp ON emp.id = c.id_empresa
    CROSS JOIN parametros p
    WHERE c.eliminado = false
      AND (p.id_empresa = 0 OR c.id_empresa = p.id_empresa)
      AND EXISTS (SELECT 1 FROM migracion_mysql_map m WHERE m.id_empresa = c.id_empresa AND m.entidad = 'compras' AND m.id_destino = c.id)
      AND COALESCE(NULLIF(TRIM(c.tipo_comprobante), ''), '01') NOT IN ('04','23','47','51','05','06','07')
      AND UPPER(TRIM(COALESCE(c.estado, ''))) NOT IN ('ANULADO','ANULADA','RECHAZADA','RECHAZADO')
      AND c.tipo_ambiente = CAST(emp.tipo_ambiente AS VARCHAR(1))
    UNION ALL
    SELECT 'LIQUIDACION', l.id, l.id_empresa, l.id_proveedor, l.fecha_emision, l.estado,
           CONCAT(l.establecimiento, '-', l.punto_emision, '-', l.secuencial),
           l.importe_total,
           (SELECT BOOL_OR(COALESCE(m.vinculado, false)) FROM migracion_mysql_map m
             WHERE m.id_empresa = l.id_empresa AND m.entidad = 'liquidaciones' AND m.id_destino = l.id)
    FROM liquidaciones_cabecera l
    JOIN empresas emp ON emp.id = l.id_empresa
    CROSS JOIN parametros p
    WHERE l.eliminado = false
      AND (p.id_empresa = 0 OR l.id_empresa = p.id_empresa)
      AND EXISTS (SELECT 1 FROM migracion_mysql_map m WHERE m.id_empresa = l.id_empresa AND m.entidad = 'liquidaciones' AND m.id_destino = l.id)
      AND UPPER(TRIM(COALESCE(l.estado, ''))) IN ('AUTORIZADO','AUTORIZADA','APROBADO','APROBADA','CONTABILIZADO','CONTABILIZADA')
      AND (l.tipo_ambiente IS NULL OR l.tipo_ambiente = CAST(emp.tipo_ambiente AS VARCHAR(1)))
),
pagado AS (
    SELECT d.tipo_documento AS tipo, d.id_referencia_documento AS id_doc,
           SUM(d.monto_pagado) AS pagado,
           COALESCE(SUM(d.monto_pagado) FILTER (WHERE d.monto_pagado < 0), 0) AS pago_negativo
    FROM egresos_detalle d
    JOIN egresos_cabecera e ON e.id = d.id_egreso
    WHERE d.tipo_documento IN ('COMPRA','LIQUIDACION') AND d.id_referencia_documento IS NOT NULL
      AND e.estado <> 'anulado' AND e.eliminado = false AND d.eliminado = false
    GROUP BY 1, 2
),
pago_anulado AS (
    SELECT d.tipo_documento AS tipo, d.id_referencia_documento AS id_doc, SUM(d.monto_pagado) AS monto
    FROM egresos_detalle d
    JOIN egresos_cabecera e ON e.id = d.id_egreso
    WHERE d.tipo_documento IN ('COMPRA','LIQUIDACION') AND d.id_referencia_documento IS NOT NULL
      AND (e.estado = 'anulado' OR e.eliminado = true OR d.eliminado = true)
    GROUP BY 1, 2
),
nc_nd AS (
    SELECT nc.id_empresa, nc.id_proveedor, nc.documento_modificado,
           SUM(CASE WHEN nc.tipo_comprobante = '04' THEN nc.importe_total ELSE 0 END) AS total_nc,
           SUM(CASE WHEN nc.tipo_comprobante = '05' THEN nc.importe_total ELSE 0 END) AS total_nd
    FROM compras_cabecera nc
    WHERE nc.tipo_comprobante IN ('04','05') AND nc.eliminado = false
    GROUP BY 1, 2, 3
),
ret AS (
    SELECT tipo, id_doc, SUM(monto) AS retenido
    FROM (
        SELECT CASE WHEN r.id_compra IS NOT NULL THEN 'COMPRA' ELSE 'LIQUIDACION' END AS tipo,
               COALESCE(r.id_compra, r.id_liquidacion) AS id_doc, r.total_retenido AS monto, r.id AS id_ret
        FROM retencion_compra_cabecera r
        WHERE r.eliminado = false
          AND UPPER(r.estado) NOT IN ('ANULADO','ANULADA','BORRADOR','PENDIENTE')
          AND (r.id_compra IS NOT NULL OR r.id_liquidacion IS NOT NULL)
        UNION
        SELECT 'COMPRA', c2.id, r.total_retenido, r.id
        FROM retencion_compra_cabecera r
        JOIN compras_cabecera c2
          ON regexp_replace(r.num_doc_sustento, '[^0-9]', '', 'g')
             = regexp_replace(CONCAT(c2.establecimiento_prov, '-', c2.punto_emision_prov, '-', c2.secuencial_prov), '[^0-9]', '', 'g')
         AND c2.id_empresa = r.id_empresa AND c2.eliminado = false
        WHERE r.eliminado = false
          AND UPPER(r.estado) NOT IN ('ANULADO','ANULADA','BORRADOR','PENDIENTE')
          AND r.id_compra IS NULL AND r.id_liquidacion IS NULL
          AND r.num_doc_sustento IS NOT NULL AND r.num_doc_sustento <> ''
    ) t
    GROUP BY 1, 2
),
ret_borrador AS (
    SELECT tipo, id_doc, SUM(monto) AS monto
    FROM (
        SELECT CASE WHEN r.id_compra IS NOT NULL THEN 'COMPRA' ELSE 'LIQUIDACION' END AS tipo,
               COALESCE(r.id_compra, r.id_liquidacion) AS id_doc, r.total_retenido AS monto, r.id AS id_ret
        FROM retencion_compra_cabecera r
        WHERE r.eliminado = false AND UPPER(COALESCE(r.estado, '')) IN ('BORRADOR','PENDIENTE')
          AND (r.id_compra IS NOT NULL OR r.id_liquidacion IS NOT NULL)
        UNION
        SELECT 'COMPRA', c2.id, r.total_retenido, r.id
        FROM retencion_compra_cabecera r
        JOIN compras_cabecera c2
          ON regexp_replace(r.num_doc_sustento, '[^0-9]', '', 'g')
             = regexp_replace(CONCAT(c2.establecimiento_prov, '-', c2.punto_emision_prov, '-', c2.secuencial_prov), '[^0-9]', '', 'g')
         AND c2.id_empresa = r.id_empresa AND c2.eliminado = false
        WHERE r.eliminado = false AND UPPER(COALESCE(r.estado, '')) IN ('BORRADOR','PENDIENTE')
          AND r.id_compra IS NULL AND r.id_liquidacion IS NULL
          AND r.num_doc_sustento IS NOT NULL AND r.num_doc_sustento <> ''
        UNION
        SELECT 'LIQUIDACION', l2.id, r.total_retenido, r.id
        FROM retencion_compra_cabecera r
        JOIN liquidaciones_cabecera l2
          ON regexp_replace(r.num_doc_sustento, '[^0-9]', '', 'g')
             = COALESCE(l2.establecimiento, '') || COALESCE(l2.punto_emision, '') || COALESCE(l2.secuencial, '')
         AND l2.id_empresa = r.id_empresa AND l2.eliminado = false
        WHERE r.eliminado = false AND UPPER(COALESCE(r.estado, '')) IN ('BORRADOR','PENDIENTE')
          AND r.id_compra IS NULL AND r.id_liquidacion IS NULL
          AND r.num_doc_sustento IS NOT NULL AND r.num_doc_sustento <> ''
    ) t
    GROUP BY 1, 2
),
ret_sin_enlace AS (
    SELECT 'LIQUIDACION'::text AS tipo, l2.id AS id_doc, SUM(r.total_retenido) AS monto
    FROM retencion_compra_cabecera r
    JOIN liquidaciones_cabecera l2
      ON regexp_replace(r.num_doc_sustento, '[^0-9]', '', 'g')
         = COALESCE(l2.establecimiento, '') || COALESCE(l2.punto_emision, '') || COALESCE(l2.secuencial, '')
     AND l2.id_empresa = r.id_empresa AND l2.eliminado = false
    WHERE r.eliminado = false
      AND UPPER(r.estado) NOT IN ('ANULADO','ANULADA','BORRADOR','PENDIENTE')
      AND r.id_compra IS NULL AND r.id_liquidacion IS NULL
      AND r.num_doc_sustento IS NOT NULL AND r.num_doc_sustento <> ''
    GROUP BY 1, 2
),
lineas AS (
    SELECT d.id AS id_detalle, d.id_egreso, e.id_empresa, e.numero_egreso,
           e.fecha_emision AS fecha_egreso, e.estado AS estado_egreso,
           e.id_proveedor AS prov_egreso, e.tipo_ambiente AS amb_egreso,
           d.tipo_documento, d.id_referencia_documento AS id_ref,
           d.descripcion, d.numero_documento, d.monto_pagado,
           CASE
             WHEN d.id_referencia_documento IS NULL THEN 'SIN ENLACE'
             WHEN d.tipo_documento = 'COMPRA'
                  AND NOT EXISTS (SELECT 1 FROM compras_cabecera c WHERE c.id = d.id_referencia_documento AND c.eliminado = false)
                  THEN 'REFERENCIA ROTA'
             WHEN d.tipo_documento = 'LIQUIDACION'
                  AND NOT EXISTS (SELECT 1 FROM liquidaciones_cabecera l WHERE l.id = d.id_referencia_documento AND l.eliminado = false)
                  THEN 'REFERENCIA ROTA'
             ELSE 'OK'
           END AS enlace,
           COALESCE(
               CASE WHEN d.numero_documento ~ '^[0-9]{1,3}-[0-9]{1,3}-[0-9]{1,9}$'
                    THEN lpad(split_part(d.numero_documento, '-', 1), 3, '0')
                      || lpad(split_part(d.numero_documento, '-', 2), 3, '0')
                      || lpad(split_part(d.numero_documento, '-', 3), 9, '0')
               END,
               regexp_replace(substring(d.descripcion FROM '[0-9]{3}-?[0-9]{3}-?[0-9]{9}'), '[^0-9]', '', 'g')
           ) AS num15
    FROM egresos_detalle d
    JOIN egresos_cabecera e    ON e.id = d.id_egreso
    JOIN migracion_mysql_map m ON m.id_empresa = e.id_empresa AND m.entidad = 'egresos' AND m.id_destino = e.id
    CROSS JOIN parametros p
    WHERE d.tipo_documento IN ('COMPRA','LIQUIDACION')
      AND d.eliminado = false AND e.eliminado = false
      AND (p.id_empresa = 0 OR e.id_empresa = p.id_empresa)
),
huerfanas AS (SELECT * FROM lineas WHERE enlace <> 'OK'),
cand AS (
    SELECT h.id_detalle, 'COMPRA'::text AS tipo_doc, c.id AS id_doc, c.fecha_emision,
           (h.tipo_documento = 'COMPRA') AS mismo_tipo,
           COALESCE(c.id_proveedor = h.prov_egreso
                    OR (length(p.razon_social) >= 4 AND h.descripcion ILIKE ('%' || p.razon_social || '%')), false) AS prov_ok,
           COALESCE(h.descripcion NOT ILIKE '%liquidaci%', true) AS texto_ok,
           (UPPER(TRIM(COALESCE(c.estado, ''))) NOT IN ('ANULADO','ANULADA','RECHAZADA','RECHAZADO')) AS vigente_ok,
           COALESCE(c.tipo_ambiente = h.amb_egreso, false) AS amb_ok,
           (CASE WHEN h.monto_pagado < 0
                 THEN COALESCE(NULLIF(TRIM(c.tipo_comprobante), ''), '01') IN ('04','23','47','51')
                 ELSE COALESCE(NULLIF(TRIM(c.tipo_comprobante), ''), '01') NOT IN ('04','23','47','51','05','06','07') END) AS signo_ok,
           COALESCE(c.fecha_emision <= h.fecha_egreso, false) AS antes_ok
    FROM huerfanas h
    JOIN compras_cabecera c
      ON c.id_empresa = h.id_empresa AND c.eliminado = false
     AND COALESCE(c.establecimiento_prov, '') || COALESCE(c.punto_emision_prov, '') || COALESCE(c.secuencial_prov, '') = h.num15
    JOIN proveedores p ON p.id = c.id_proveedor
    WHERE length(h.num15) = 15
    UNION ALL
    SELECT h.id_detalle, 'LIQUIDACION', l.id, l.fecha_emision,
           (h.tipo_documento = 'LIQUIDACION'),
           COALESCE(l.id_proveedor = h.prov_egreso
                    OR (length(p.razon_social) >= 4 AND h.descripcion ILIKE ('%' || p.razon_social || '%'))
                    OR h.descripcion ILIKE '%liquidaci%', false),
           COALESCE(h.descripcion ILIKE '%liquidaci%', false),
           (UPPER(TRIM(COALESCE(l.estado, ''))) IN ('AUTORIZADO','AUTORIZADA','APROBADO','APROBADA','CONTABILIZADO','CONTABILIZADA')),
           COALESCE(l.tipo_ambiente = h.amb_egreso, false),
           true,
           COALESCE(l.fecha_emision <= h.fecha_egreso, false)
    FROM huerfanas h
    JOIN liquidaciones_cabecera l
      ON l.id_empresa = h.id_empresa AND l.eliminado = false
     AND COALESCE(l.establecimiento, '') || COALESCE(l.punto_emision, '') || COALESCE(l.secuencial, '') = h.num15
    JOIN proveedores p ON p.id = l.id_proveedor
    WHERE length(h.num15) = 15
),
ranked AS (
    SELECT *, DENSE_RANK() OVER (PARTITION BY id_detalle
                 ORDER BY prov_ok DESC, texto_ok DESC, mismo_tipo DESC, vigente_ok DESC, amb_ok DESC,
                          signo_ok DESC, antes_ok DESC, fecha_emision DESC) AS rk
    FROM cand
),
eleccion AS (
    SELECT id_detalle,
           COUNT(*)                               AS candidatas,
           COUNT(*) FILTER (WHERE rk = 1)         AS empatadas,
           MIN(id_doc)   FILTER (WHERE rk = 1)    AS id_doc,
           MIN(tipo_doc) FILTER (WHERE rk = 1)    AS tipo_doc,
           BOOL_OR(prov_ok) FILTER (WHERE rk = 1) AS prov_ok
    FROM ranked
    GROUP BY id_detalle
),
clasif AS (
    SELECT h.*, COALESCE(el.candidatas, 0) AS candidatas, el.id_doc, el.tipo_doc,
           CASE
             WHEN h.num15 IS NULL OR length(h.num15) <> 15                       THEN 'SIN NÚMERO'
             WHEN el.id_doc IS NULL                                              THEN 'SIN DOCUMENTO'
             WHEN el.empatadas > 1                                               THEN 'AMBIGUA'
             WHEN NOT el.prov_ok                                                 THEN 'OTRO PROVEEDOR'
             WHEN el.tipo_doc <> h.tipo_documento AND el.tipo_doc = 'LIQUIDACION' THEN 'ES UNA LIQUIDACIÓN'
             WHEN el.tipo_doc <> h.tipo_documento                                THEN 'ES UNA COMPRA'
             ELSE 'SE ENLAZA'
           END AS estado
    FROM huerfanas h
    LEFT JOIN eleccion el ON el.id_detalle = h.id_detalle
),
huerfano_pago AS (
    SELECT tipo_doc AS tipo, id_doc,
           COALESCE(SUM(monto_pagado) FILTER (WHERE estado = 'SE ENLAZA'), 0)                              AS mismo_tipo,
           COALESCE(SUM(monto_pagado) FILTER (WHERE estado IN ('ES UNA LIQUIDACIÓN','ES UNA COMPRA')), 0) AS otro_tipo
    FROM clasif
    WHERE estado IN ('SE ENLAZA','ES UNA LIQUIDACIÓN','ES UNA COMPRA') AND estado_egreso <> 'anulado'
    GROUP BY 1, 2
),
docs_saldo AS (
    SELECT d.*,
           COALESCE(pg.pagado, 0)        AS pagado,
           COALESCE(pg.pago_negativo, 0) AS pago_negativo,
           COALESCE(nn.total_nc, 0)      AS nc,
           COALESCE(nn.total_nd, 0)      AS nd,
           COALESCE(r.retenido, 0)       AS retenido,
           d.total - COALESCE(pg.pagado, 0) - COALESCE(r.retenido, 0) - COALESCE(nn.total_nc, 0) + COALESCE(nn.total_nd, 0) AS saldo,
           COALESCE(hp.mismo_tipo, 0)    AS pago_sin_enlazar,
           COALESCE(hp.otro_tipo, 0)     AS pago_otro_tipo,
           COALESCE(pa.monto, 0)         AS pago_anulado,
           COALESCE(rb.monto, 0)         AS ret_borrador,
           COALESCE(rs.monto, 0)         AS ret_sin_enlazar
    FROM docs d
    LEFT JOIN pagado pg         ON pg.tipo = d.tipo AND pg.id_doc = d.id
    LEFT JOIN nc_nd nn          ON d.tipo = 'COMPRA' AND nn.id_empresa = d.id_empresa AND nn.id_proveedor = d.id_proveedor AND nn.documento_modificado = d.numero
    LEFT JOIN ret r             ON r.tipo = d.tipo AND r.id_doc = d.id
    LEFT JOIN huerfano_pago hp  ON hp.tipo = d.tipo AND hp.id_doc = d.id
    LEFT JOIN pago_anulado pa   ON pa.tipo = d.tipo AND pa.id_doc = d.id
    LEFT JOIN ret_borrador rb   ON rb.tipo = d.tipo AND rb.id_doc = d.id
    LEFT JOIN ret_sin_enlace rs ON rs.tipo = d.tipo AND rs.id_doc = d.id
),
pendientes AS (
    SELECT *,
           CASE
             WHEN saldo <= 0.05                                                              THEN 'CENTAVOS'
             WHEN pago_sin_enlazar >= saldo - 0.05                                           THEN 'PAGO SIN ENLAZAR'
             WHEN pago_otro_tipo >= saldo - 0.05 AND tipo = 'LIQUIDACION'                    THEN 'PAGO REGISTRADO COMO COMPRA'
             WHEN pago_otro_tipo >= saldo - 0.05                                             THEN 'PAGO REGISTRADO COMO LIQUIDACIÓN'
             WHEN pago_sin_enlazar + pago_otro_tipo > 0 AND ret_borrador + ret_sin_enlazar > 0
                  AND pago_sin_enlazar + pago_otro_tipo + ret_borrador + ret_sin_enlazar >= saldo - 0.05
                                                                                             THEN 'PAGO Y RETENCIÓN SIN ENLAZAR'
             WHEN pago_sin_enlazar + pago_otro_tipo > 0                                      THEN 'PAGO SIN ENLAZAR (PARCIAL)'
             WHEN ret_borrador >= saldo - 0.05                                               THEN 'RETENCIÓN EN BORRADOR'
             WHEN ret_sin_enlazar >= saldo - 0.05                                            THEN 'RETENCIÓN SIN ENLAZAR'
             WHEN pago_anulado >= saldo - 0.05                                               THEN 'PAGO EN EGRESO ANULADO'
             WHEN pago_negativo < 0 AND nc = 0 AND abs(saldo + pago_negativo) <= 0.05        THEN 'NC RESTADA EN EL PAGO'
             WHEN ret_borrador + ret_sin_enlazar > 0                                         THEN 'RETENCIÓN SIN RESTAR (PARCIAL)'
             WHEN pago_anulado > 0                                                           THEN 'PAGO EN EGRESO ANULADO (PARCIAL)'
             WHEN pagado > 0                                                                 THEN 'PAGO PARCIAL'
             ELSE 'SIN PAGO EN EL SISTEMA'
           END AS motivo
    FROM docs_saldo
    WHERE saldo > 0
)
SELECT pe.id_empresa, pe.tipo, pe.motivo, pe.numero,
       to_char(pe.fecha_emision, 'DD-MM-YYYY') AS fecha,
       pr.razon_social                         AS proveedor,
       pe.estado,
       pe.total, pe.pagado, pe.retenido, pe.nc, pe.nd,
       ROUND(pe.saldo, 2)                      AS saldo,
       pe.pago_sin_enlazar, pe.pago_otro_tipo, pe.ret_borrador, pe.ret_sin_enlazar, pe.pago_anulado, pe.pago_negativo,
       pe.vinculado                            AS documento_nativo,
       pe.id
FROM pendientes pe
LEFT JOIN proveedores pr ON pr.id = pe.id_proveedor
ORDER BY pe.id_empresa, pe.motivo, pe.tipo, pe.fecha_emision, pe.numero;


-- ── 3) LÍNEAS DE PAGO huérfanas: resumen por empresa / tipo / enlace / estado ─
WITH parametros AS (
    SELECT 0::int AS id_empresa          -- << 0 = todas; o el id de la empresa (p. ej. 23)
),
lineas AS (
    SELECT d.id AS id_detalle, d.id_egreso, e.id_empresa, e.numero_egreso,
           e.fecha_emision AS fecha_egreso, e.estado AS estado_egreso,
           e.id_proveedor AS prov_egreso, e.tipo_ambiente AS amb_egreso,
           d.tipo_documento, d.id_referencia_documento AS id_ref,
           d.descripcion, d.numero_documento, d.monto_pagado,
           CASE
             WHEN d.id_referencia_documento IS NULL THEN 'SIN ENLACE'
             WHEN d.tipo_documento = 'COMPRA'
                  AND NOT EXISTS (SELECT 1 FROM compras_cabecera c WHERE c.id = d.id_referencia_documento AND c.eliminado = false)
                  THEN 'REFERENCIA ROTA'
             WHEN d.tipo_documento = 'LIQUIDACION'
                  AND NOT EXISTS (SELECT 1 FROM liquidaciones_cabecera l WHERE l.id = d.id_referencia_documento AND l.eliminado = false)
                  THEN 'REFERENCIA ROTA'
             ELSE 'OK'
           END AS enlace,
           COALESCE(
               CASE WHEN d.numero_documento ~ '^[0-9]{1,3}-[0-9]{1,3}-[0-9]{1,9}$'
                    THEN lpad(split_part(d.numero_documento, '-', 1), 3, '0')
                      || lpad(split_part(d.numero_documento, '-', 2), 3, '0')
                      || lpad(split_part(d.numero_documento, '-', 3), 9, '0')
               END,
               regexp_replace(substring(d.descripcion FROM '[0-9]{3}-?[0-9]{3}-?[0-9]{9}'), '[^0-9]', '', 'g')
           ) AS num15
    FROM egresos_detalle d
    JOIN egresos_cabecera e    ON e.id = d.id_egreso
    JOIN migracion_mysql_map m ON m.id_empresa = e.id_empresa AND m.entidad = 'egresos' AND m.id_destino = e.id
    CROSS JOIN parametros p
    WHERE d.tipo_documento IN ('COMPRA','LIQUIDACION')
      AND d.eliminado = false AND e.eliminado = false
      AND (p.id_empresa = 0 OR e.id_empresa = p.id_empresa)
),
huerfanas AS (SELECT * FROM lineas WHERE enlace <> 'OK'),
cand AS (
    SELECT h.id_detalle, 'COMPRA'::text AS tipo_doc, c.id AS id_doc, c.fecha_emision,
           (h.tipo_documento = 'COMPRA') AS mismo_tipo,
           COALESCE(c.id_proveedor = h.prov_egreso
                    OR (length(p.razon_social) >= 4 AND h.descripcion ILIKE ('%' || p.razon_social || '%')), false) AS prov_ok,
           COALESCE(h.descripcion NOT ILIKE '%liquidaci%', true) AS texto_ok,
           (UPPER(TRIM(COALESCE(c.estado, ''))) NOT IN ('ANULADO','ANULADA','RECHAZADA','RECHAZADO')) AS vigente_ok,
           COALESCE(c.tipo_ambiente = h.amb_egreso, false) AS amb_ok,
           (CASE WHEN h.monto_pagado < 0
                 THEN COALESCE(NULLIF(TRIM(c.tipo_comprobante), ''), '01') IN ('04','23','47','51')
                 ELSE COALESCE(NULLIF(TRIM(c.tipo_comprobante), ''), '01') NOT IN ('04','23','47','51','05','06','07') END) AS signo_ok,
           COALESCE(c.fecha_emision <= h.fecha_egreso, false) AS antes_ok
    FROM huerfanas h
    JOIN compras_cabecera c
      ON c.id_empresa = h.id_empresa AND c.eliminado = false
     AND COALESCE(c.establecimiento_prov, '') || COALESCE(c.punto_emision_prov, '') || COALESCE(c.secuencial_prov, '') = h.num15
    JOIN proveedores p ON p.id = c.id_proveedor
    WHERE length(h.num15) = 15
    UNION ALL
    SELECT h.id_detalle, 'LIQUIDACION', l.id, l.fecha_emision,
           (h.tipo_documento = 'LIQUIDACION'),
           COALESCE(l.id_proveedor = h.prov_egreso
                    OR (length(p.razon_social) >= 4 AND h.descripcion ILIKE ('%' || p.razon_social || '%'))
                    OR h.descripcion ILIKE '%liquidaci%', false),
           COALESCE(h.descripcion ILIKE '%liquidaci%', false),
           (UPPER(TRIM(COALESCE(l.estado, ''))) IN ('AUTORIZADO','AUTORIZADA','APROBADO','APROBADA','CONTABILIZADO','CONTABILIZADA')),
           COALESCE(l.tipo_ambiente = h.amb_egreso, false),
           true,
           COALESCE(l.fecha_emision <= h.fecha_egreso, false)
    FROM huerfanas h
    JOIN liquidaciones_cabecera l
      ON l.id_empresa = h.id_empresa AND l.eliminado = false
     AND COALESCE(l.establecimiento, '') || COALESCE(l.punto_emision, '') || COALESCE(l.secuencial, '') = h.num15
    JOIN proveedores p ON p.id = l.id_proveedor
    WHERE length(h.num15) = 15
),
ranked AS (
    SELECT *, DENSE_RANK() OVER (PARTITION BY id_detalle
                 ORDER BY prov_ok DESC, texto_ok DESC, mismo_tipo DESC, vigente_ok DESC, amb_ok DESC,
                          signo_ok DESC, antes_ok DESC, fecha_emision DESC) AS rk
    FROM cand
),
eleccion AS (
    SELECT id_detalle,
           COUNT(*)                               AS candidatas,
           COUNT(*) FILTER (WHERE rk = 1)         AS empatadas,
           MIN(id_doc)   FILTER (WHERE rk = 1)    AS id_doc,
           MIN(tipo_doc) FILTER (WHERE rk = 1)    AS tipo_doc,
           BOOL_OR(prov_ok) FILTER (WHERE rk = 1) AS prov_ok
    FROM ranked
    GROUP BY id_detalle
),
clasif AS (
    SELECT h.*, COALESCE(el.candidatas, 0) AS candidatas, el.id_doc, el.tipo_doc,
           CASE
             WHEN h.num15 IS NULL OR length(h.num15) <> 15                       THEN 'SIN NÚMERO'
             WHEN el.id_doc IS NULL                                              THEN 'SIN DOCUMENTO'
             WHEN el.empatadas > 1                                               THEN 'AMBIGUA'
             WHEN NOT el.prov_ok                                                 THEN 'OTRO PROVEEDOR'
             WHEN el.tipo_doc <> h.tipo_documento AND el.tipo_doc = 'LIQUIDACION' THEN 'ES UNA LIQUIDACIÓN'
             WHEN el.tipo_doc <> h.tipo_documento                                THEN 'ES UNA COMPRA'
             ELSE 'SE ENLAZA'
           END AS estado
    FROM huerfanas h
    LEFT JOIN eleccion el ON el.id_detalle = h.id_detalle
)
SELECT id_empresa, tipo_documento, enlace, estado,
       COUNT(*)                                          AS lineas,
       COUNT(DISTINCT id_egreso)                         AS egresos,
       COUNT(DISTINCT id_doc)                            AS documentos,
       ROUND(SUM(monto_pagado), 2)                       AS monto,
       COUNT(*) FILTER (WHERE estado_egreso = 'anulado') AS de_egresos_anulados
FROM clasif
GROUP BY id_empresa, tipo_documento, enlace, estado
ORDER BY id_empresa, tipo_documento, enlace, estado;


-- ── 4) LÍNEAS DE PAGO huérfanas: detalle ─────────────────────────────────────
WITH parametros AS (
    SELECT 0::int AS id_empresa          -- << 0 = todas; o el id de la empresa (p. ej. 23)
),
lineas AS (
    SELECT d.id AS id_detalle, d.id_egreso, e.id_empresa, e.numero_egreso,
           e.fecha_emision AS fecha_egreso, e.estado AS estado_egreso,
           e.id_proveedor AS prov_egreso, e.tipo_ambiente AS amb_egreso,
           d.tipo_documento, d.id_referencia_documento AS id_ref,
           d.descripcion, d.numero_documento, d.monto_pagado,
           CASE
             WHEN d.id_referencia_documento IS NULL THEN 'SIN ENLACE'
             WHEN d.tipo_documento = 'COMPRA'
                  AND NOT EXISTS (SELECT 1 FROM compras_cabecera c WHERE c.id = d.id_referencia_documento AND c.eliminado = false)
                  THEN 'REFERENCIA ROTA'
             WHEN d.tipo_documento = 'LIQUIDACION'
                  AND NOT EXISTS (SELECT 1 FROM liquidaciones_cabecera l WHERE l.id = d.id_referencia_documento AND l.eliminado = false)
                  THEN 'REFERENCIA ROTA'
             ELSE 'OK'
           END AS enlace,
           COALESCE(
               CASE WHEN d.numero_documento ~ '^[0-9]{1,3}-[0-9]{1,3}-[0-9]{1,9}$'
                    THEN lpad(split_part(d.numero_documento, '-', 1), 3, '0')
                      || lpad(split_part(d.numero_documento, '-', 2), 3, '0')
                      || lpad(split_part(d.numero_documento, '-', 3), 9, '0')
               END,
               regexp_replace(substring(d.descripcion FROM '[0-9]{3}-?[0-9]{3}-?[0-9]{9}'), '[^0-9]', '', 'g')
           ) AS num15
    FROM egresos_detalle d
    JOIN egresos_cabecera e    ON e.id = d.id_egreso
    JOIN migracion_mysql_map m ON m.id_empresa = e.id_empresa AND m.entidad = 'egresos' AND m.id_destino = e.id
    CROSS JOIN parametros p
    WHERE d.tipo_documento IN ('COMPRA','LIQUIDACION')
      AND d.eliminado = false AND e.eliminado = false
      AND (p.id_empresa = 0 OR e.id_empresa = p.id_empresa)
),
huerfanas AS (SELECT * FROM lineas WHERE enlace <> 'OK'),
cand AS (
    SELECT h.id_detalle, 'COMPRA'::text AS tipo_doc, c.id AS id_doc, c.fecha_emision,
           (h.tipo_documento = 'COMPRA') AS mismo_tipo,
           COALESCE(c.id_proveedor = h.prov_egreso
                    OR (length(p.razon_social) >= 4 AND h.descripcion ILIKE ('%' || p.razon_social || '%')), false) AS prov_ok,
           COALESCE(h.descripcion NOT ILIKE '%liquidaci%', true) AS texto_ok,
           (UPPER(TRIM(COALESCE(c.estado, ''))) NOT IN ('ANULADO','ANULADA','RECHAZADA','RECHAZADO')) AS vigente_ok,
           COALESCE(c.tipo_ambiente = h.amb_egreso, false) AS amb_ok,
           (CASE WHEN h.monto_pagado < 0
                 THEN COALESCE(NULLIF(TRIM(c.tipo_comprobante), ''), '01') IN ('04','23','47','51')
                 ELSE COALESCE(NULLIF(TRIM(c.tipo_comprobante), ''), '01') NOT IN ('04','23','47','51','05','06','07') END) AS signo_ok,
           COALESCE(c.fecha_emision <= h.fecha_egreso, false) AS antes_ok
    FROM huerfanas h
    JOIN compras_cabecera c
      ON c.id_empresa = h.id_empresa AND c.eliminado = false
     AND COALESCE(c.establecimiento_prov, '') || COALESCE(c.punto_emision_prov, '') || COALESCE(c.secuencial_prov, '') = h.num15
    JOIN proveedores p ON p.id = c.id_proveedor
    WHERE length(h.num15) = 15
    UNION ALL
    SELECT h.id_detalle, 'LIQUIDACION', l.id, l.fecha_emision,
           (h.tipo_documento = 'LIQUIDACION'),
           COALESCE(l.id_proveedor = h.prov_egreso
                    OR (length(p.razon_social) >= 4 AND h.descripcion ILIKE ('%' || p.razon_social || '%'))
                    OR h.descripcion ILIKE '%liquidaci%', false),
           COALESCE(h.descripcion ILIKE '%liquidaci%', false),
           (UPPER(TRIM(COALESCE(l.estado, ''))) IN ('AUTORIZADO','AUTORIZADA','APROBADO','APROBADA','CONTABILIZADO','CONTABILIZADA')),
           COALESCE(l.tipo_ambiente = h.amb_egreso, false),
           true,
           COALESCE(l.fecha_emision <= h.fecha_egreso, false)
    FROM huerfanas h
    JOIN liquidaciones_cabecera l
      ON l.id_empresa = h.id_empresa AND l.eliminado = false
     AND COALESCE(l.establecimiento, '') || COALESCE(l.punto_emision, '') || COALESCE(l.secuencial, '') = h.num15
    JOIN proveedores p ON p.id = l.id_proveedor
    WHERE length(h.num15) = 15
),
ranked AS (
    SELECT *, DENSE_RANK() OVER (PARTITION BY id_detalle
                 ORDER BY prov_ok DESC, texto_ok DESC, mismo_tipo DESC, vigente_ok DESC, amb_ok DESC,
                          signo_ok DESC, antes_ok DESC, fecha_emision DESC) AS rk
    FROM cand
),
eleccion AS (
    SELECT id_detalle,
           COUNT(*)                               AS candidatas,
           COUNT(*) FILTER (WHERE rk = 1)         AS empatadas,
           MIN(id_doc)   FILTER (WHERE rk = 1)    AS id_doc,
           MIN(tipo_doc) FILTER (WHERE rk = 1)    AS tipo_doc,
           BOOL_OR(prov_ok) FILTER (WHERE rk = 1) AS prov_ok
    FROM ranked
    GROUP BY id_detalle
),
clasif AS (
    SELECT h.*, COALESCE(el.candidatas, 0) AS candidatas, el.id_doc, el.tipo_doc,
           CASE
             WHEN h.num15 IS NULL OR length(h.num15) <> 15                       THEN 'SIN NÚMERO'
             WHEN el.id_doc IS NULL                                              THEN 'SIN DOCUMENTO'
             WHEN el.empatadas > 1                                               THEN 'AMBIGUA'
             WHEN NOT el.prov_ok                                                 THEN 'OTRO PROVEEDOR'
             WHEN el.tipo_doc <> h.tipo_documento AND el.tipo_doc = 'LIQUIDACION' THEN 'ES UNA LIQUIDACIÓN'
             WHEN el.tipo_doc <> h.tipo_documento                                THEN 'ES UNA COMPRA'
             ELSE 'SE ENLAZA'
           END AS estado
    FROM huerfanas h
    LEFT JOIN eleccion el ON el.id_detalle = h.id_detalle
)
SELECT c.id_empresa, c.tipo_documento, c.enlace, c.estado, c.numero_egreso,
       to_char(c.fecha_egreso, 'DD-MM-YYYY')   AS fecha_egreso,
       c.estado_egreso,
       pe.razon_social                         AS proveedor_egreso,
       c.descripcion, c.monto_pagado,
       c.numero_documento                      AS numero_en_linea,
       c.num15                                 AS numero_leido,
       c.candidatas,
       c.tipo_doc                              AS documento_tipo,
       COALESCE(CONCAT(co.establecimiento_prov, '-', co.punto_emision_prov, '-', co.secuencial_prov),
                CONCAT(li.establecimiento, '-', li.punto_emision, '-', li.secuencial)) AS documento_numero,
       COALESCE(pc.razon_social, pl.razon_social) AS documento_proveedor,
       COALESCE(co.importe_total, li.importe_total) AS documento_total,
       c.id_egreso, c.id_detalle, c.id_ref AS referencia_actual, c.id_doc AS documento_id
FROM clasif c
LEFT JOIN proveedores pe             ON pe.id = c.prov_egreso
LEFT JOIN compras_cabecera co        ON c.tipo_doc = 'COMPRA'      AND co.id = c.id_doc
LEFT JOIN liquidaciones_cabecera li  ON c.tipo_doc = 'LIQUIDACION' AND li.id = c.id_doc
LEFT JOIN proveedores pc             ON pc.id = co.id_proveedor
LEFT JOIN proveedores pl             ON pl.id = li.id_proveedor
ORDER BY c.id_empresa, c.estado, c.tipo_documento, c.fecha_egreso, c.numero_egreso;


-- ── 5) Líneas "Otros conceptos" (MANUAL) de egresos migrados que traen un número
--       de compra o liquidación de la empresa: posibles pagos de documento que la
--       migración clasificó como concepto. Solo para revisión (no lo corrige nada).
WITH parametros AS (
    SELECT 0::int AS id_empresa          -- << 0 = todas; o el id de la empresa (p. ej. 23)
),
manuales AS (
    SELECT e.id_empresa, e.id AS id_egreso, e.numero_egreso, e.fecha_emision, d.descripcion, d.monto_pagado,
           regexp_replace(substring(d.descripcion FROM '[0-9]{3}-?[0-9]{3}-?[0-9]{9}'), '[^0-9]', '', 'g') AS num15
    FROM egresos_detalle d
    JOIN egresos_cabecera e    ON e.id = d.id_egreso
    JOIN migracion_mysql_map m ON m.id_empresa = e.id_empresa AND m.entidad = 'egresos' AND m.id_destino = e.id
    CROSS JOIN parametros p
    WHERE d.tipo_documento = 'MANUAL' AND d.eliminado = false AND e.eliminado = false AND e.estado <> 'anulado'
      AND (p.id_empresa = 0 OR e.id_empresa = p.id_empresa)
)
SELECT mn.id_empresa,
       COUNT(*)                          AS lineas,
       ROUND(SUM(mn.monto_pagado), 2)    AS monto,
       MIN(mn.numero_egreso)             AS ejemplo_egreso,
       MIN(LEFT(mn.descripcion, 70))     AS ejemplo_texto
FROM manuales mn
WHERE length(mn.num15) = 15
  AND (EXISTS (SELECT 1 FROM compras_cabecera c WHERE c.id_empresa = mn.id_empresa AND c.eliminado = false
                  AND COALESCE(c.establecimiento_prov, '') || COALESCE(c.punto_emision_prov, '') || COALESCE(c.secuencial_prov, '') = mn.num15)
       OR EXISTS (SELECT 1 FROM liquidaciones_cabecera l WHERE l.id_empresa = mn.id_empresa AND l.eliminado = false
                  AND COALESCE(l.establecimiento, '') || COALESCE(l.punto_emision, '') || COALESCE(l.secuencial, '') = mn.num15))
GROUP BY mn.id_empresa
ORDER BY mn.id_empresa;


-- ── 6) COBERTURA por empresa: ¿se migraron los pagos, de qué fechas, y cómo
--       quedaron sus líneas? (una fila por empresa con compras migradas) ────────
--   egresos_migrados = 0 con muchas compras pendientes → los Pagos (egresos) no
--   se migraron para esa empresa. Si egresos_desde/hasta no cubre las fechas de
--   las compras → se migraron con un rango de fechas parcial (ver consulta 7).
--   lin_manual_de_compra → líneas de "Otros conceptos" cuyo texto trae el número
--   de una compra de la MISMA empresa (el pago quedó como concepto).
--   lin_sin_doc_en_otra_empresa → líneas de pago sin documento cuyo número SÍ
--   existe como compra en OTRA empresa del mismo RUC (se pagó desde otro
--   establecimiento: el egreso y la compra quedaron en empresas distintas).
--   docs_sobrepagados → documentos migrados cuyos pagos, solos, ya superan su
--   total: el pago de OTRO documento quedó enlazado a este (o se pagó dos veces).
--   Si hay muchos, parte de las compras "sin pago" tienen su pago en otra compra.
WITH parametros AS (
    SELECT 0::int AS id_empresa          -- << 0 = todas; o el id de la empresa (p. ej. 23)
),
mc AS (SELECT DISTINCT id_empresa, id_destino FROM migracion_mysql_map WHERE entidad = 'compras'),
ml AS (SELECT DISTINCT id_empresa, id_destino FROM migracion_mysql_map WHERE entidad = 'liquidaciones'),
me AS (SELECT DISTINCT id_empresa, id_destino FROM migracion_mysql_map WHERE entidad = 'egresos'),
mig_comp AS (
    SELECT c.id_empresa, COUNT(*) AS compras_migradas,
           MIN(c.fecha_emision) AS compras_desde, MAX(c.fecha_emision) AS compras_hasta
    FROM mc
    JOIN compras_cabecera c ON c.id = mc.id_destino AND c.id_empresa = mc.id_empresa
    CROSS JOIN parametros p
    WHERE c.eliminado = false AND (p.id_empresa = 0 OR c.id_empresa = p.id_empresa)
    GROUP BY c.id_empresa
),
mig_liq AS (
    SELECT l.id_empresa, COUNT(*) AS liq_migradas
    FROM ml
    JOIN liquidaciones_cabecera l ON l.id = ml.id_destino AND l.id_empresa = ml.id_empresa
    WHERE l.eliminado = false
    GROUP BY l.id_empresa
),
egr AS (
    SELECT e.id, e.id_empresa, e.fecha_emision, e.tipo_egreso, e.estado
    FROM me
    JOIN egresos_cabecera e ON e.id = me.id_destino AND e.id_empresa = me.id_empresa
    WHERE e.eliminado = false
),
mig_egr AS (
    SELECT id_empresa, COUNT(*) AS egresos_migrados,
           MIN(fecha_emision) AS egresos_desde, MAX(fecha_emision) AS egresos_hasta,
           COUNT(*) FILTER (WHERE tipo_egreso IN ('COMPRA','LIQUIDACION')) AS egr_documentos,
           COUNT(*) FILTER (WHERE tipo_egreso = 'GENERAL')                 AS egr_concepto,
           COUNT(*) FILTER (WHERE tipo_egreso = 'ROL')                     AS egr_nomina,
           COUNT(*) FILTER (WHERE estado = 'anulado')                      AS egr_anulados
    FROM egr
    GROUP BY id_empresa
),
lineas AS (
    SELECT d.id AS id_detalle, e.id_empresa, d.tipo_documento, d.id_referencia_documento,
           COALESCE(
               CASE WHEN d.numero_documento ~ '^[0-9]{1,3}-[0-9]{1,3}-[0-9]{1,9}$'
                    THEN lpad(split_part(d.numero_documento, '-', 1), 3, '0')
                      || lpad(split_part(d.numero_documento, '-', 2), 3, '0')
                      || lpad(split_part(d.numero_documento, '-', 3), 9, '0')
               END,
               regexp_replace(substring(d.descripcion FROM '[0-9]{3}-?[0-9]{3}-?[0-9]{9}'), '[^0-9]', '', 'g')
           ) AS num15
    FROM egresos_detalle d
    JOIN egr e ON e.id = d.id_egreso
    WHERE d.eliminado = false
),
lin AS (
    SELECT id_empresa,
           COUNT(*) FILTER (WHERE tipo_documento = 'COMPRA'      AND id_referencia_documento IS NOT NULL) AS lin_compra_ok,
           COUNT(*) FILTER (WHERE tipo_documento = 'COMPRA'      AND id_referencia_documento IS NULL)     AS lin_compra_sin_doc,
           COUNT(*) FILTER (WHERE tipo_documento = 'LIQUIDACION' AND id_referencia_documento IS NOT NULL) AS lin_liq_ok,
           COUNT(*) FILTER (WHERE tipo_documento = 'LIQUIDACION' AND id_referencia_documento IS NULL)     AS lin_liq_sin_doc,
           COUNT(*) FILTER (WHERE tipo_documento = 'MANUAL')                                              AS lin_manual,
           COUNT(*) FILTER (WHERE tipo_documento = 'MANUAL' AND length(num15) = 15)                       AS lin_manual_con_numero
    FROM lineas
    GROUP BY id_empresa
),
base AS (      -- RUC base (10 dígitos) de cada empresa
    SELECT id, LEFT(regexp_replace(COALESCE(ruc, ''), '[^0-9]', '', 'g'), 10) AS base10 FROM empresas
),
comp_num AS (  -- número de 15 dígitos de cada compra viva, con el RUC base de su empresa
    SELECT DISTINCT c.id_empresa, b.base10,
           COALESCE(c.establecimiento_prov, '') || COALESCE(c.punto_emision_prov, '') || COALESCE(c.secuencial_prov, '') AS num15
    FROM compras_cabecera c
    JOIN base b ON b.id = c.id_empresa
    WHERE c.eliminado = false
),
man AS (
    SELECT li.id_empresa, COUNT(DISTINCT li.id_detalle) AS lin_manual_de_compra
    FROM lineas li
    JOIN comp_num cn ON cn.id_empresa = li.id_empresa AND cn.num15 = li.num15
    WHERE li.tipo_documento = 'MANUAL' AND length(li.num15) = 15
    GROUP BY li.id_empresa
),
otra AS (
    SELECT li.id_empresa, COUNT(DISTINCT li.id_detalle) AS lin_sin_doc_en_otra_empresa
    FROM lineas li
    JOIN base b      ON b.id = li.id_empresa
    JOIN comp_num cn ON cn.base10 = b.base10 AND cn.num15 = li.num15 AND cn.id_empresa <> li.id_empresa
    WHERE li.tipo_documento IN ('COMPRA','LIQUIDACION') AND li.id_referencia_documento IS NULL
      AND length(li.num15) = 15
    GROUP BY li.id_empresa
),
pag AS (       -- lo pagado por documento (egresos vigentes)
    SELECT d.tipo_documento, d.id_referencia_documento AS id, SUM(d.monto_pagado) AS pagado
    FROM egresos_detalle d
    JOIN egresos_cabecera e ON e.id = d.id_egreso
    WHERE d.tipo_documento IN ('COMPRA','LIQUIDACION') AND d.id_referencia_documento IS NOT NULL
      AND d.eliminado = false AND e.eliminado = false AND e.estado <> 'anulado'
    GROUP BY 1, 2
),
sobre AS (     -- documentos migrados cuyos pagos, solos, superan su total
    SELECT t.id_empresa, COUNT(*) AS docs_sobrepagados, ROUND(SUM(t.exceso), 2) AS monto_sobrepagado
    FROM (
        SELECT c.id_empresa, p.pagado - (c.importe_total + COALESCE(c.total_terceros, 0)) AS exceso
        FROM mc
        JOIN compras_cabecera c ON c.id = mc.id_destino AND c.id_empresa = mc.id_empresa
        JOIN pag p ON p.tipo_documento = 'COMPRA' AND p.id = c.id
        WHERE c.eliminado = false
        UNION ALL
        SELECT l.id_empresa, p.pagado - l.importe_total
        FROM ml
        JOIN liquidaciones_cabecera l ON l.id = ml.id_destino AND l.id_empresa = ml.id_empresa
        JOIN pag p ON p.tipo_documento = 'LIQUIDACION' AND p.id = l.id
        WHERE l.eliminado = false
    ) t
    WHERE t.exceso > 0.05
    GROUP BY t.id_empresa
)
SELECT em.id AS id_empresa, em.ruc,
       x.compras_migradas, x.compras_desde, x.compras_hasta,
       COALESCE(ml2.liq_migradas, 0)             AS liq_migradas,
       COALESCE(me2.egresos_migrados, 0)         AS egresos_migrados,
       me2.egresos_desde, me2.egresos_hasta,
       COALESCE(me2.egr_documentos, 0)           AS egr_documentos,
       COALESCE(me2.egr_concepto, 0)             AS egr_concepto,
       COALESCE(me2.egr_nomina, 0)               AS egr_nomina,
       COALESCE(me2.egr_anulados, 0)             AS egr_anulados,
       COALESCE(li.lin_compra_ok, 0)             AS lin_compra_ok,
       COALESCE(li.lin_compra_sin_doc, 0)        AS lin_compra_sin_doc,
       COALESCE(li.lin_liq_ok, 0)                AS lin_liq_ok,
       COALESCE(li.lin_liq_sin_doc, 0)           AS lin_liq_sin_doc,
       COALESCE(li.lin_manual, 0)                AS lin_manual,
       COALESCE(li.lin_manual_con_numero, 0)     AS lin_manual_con_numero,
       COALESCE(mn.lin_manual_de_compra, 0)      AS lin_manual_de_compra,
       COALESCE(ot.lin_sin_doc_en_otra_empresa, 0) AS lin_sin_doc_en_otra_empresa,
       COALESCE(so.docs_sobrepagados, 0)         AS docs_sobrepagados,
       COALESCE(so.monto_sobrepagado, 0)         AS monto_sobrepagado
FROM mig_comp x
JOIN empresas em      ON em.id = x.id_empresa
LEFT JOIN mig_liq ml2 ON ml2.id_empresa = x.id_empresa
LEFT JOIN mig_egr me2 ON me2.id_empresa = x.id_empresa
LEFT JOIN lin li      ON li.id_empresa = x.id_empresa
LEFT JOIN man mn      ON mn.id_empresa = x.id_empresa
LEFT JOIN otra ot     ON ot.id_empresa = x.id_empresa
LEFT JOIN sobre so    ON so.id_empresa = x.id_empresa
ORDER BY em.id;


-- ── 7) COBERTURA por empresa y AÑO: compras/liquidaciones migradas con y sin
--       pago vs. egresos migrados de ese año. Años con muchos documentos sin pago
--       y 0 egresos migrados = los pagos de ese período no se han migrado. ────
WITH parametros AS (
    SELECT 0::int AS id_empresa          -- << 0 = todas; o el id de la empresa (p. ej. 23)
),
mc AS (SELECT DISTINCT id_empresa, id_destino FROM migracion_mysql_map WHERE entidad = 'compras'),
ml AS (SELECT DISTINCT id_empresa, id_destino FROM migracion_mysql_map WHERE entidad = 'liquidaciones'),
me AS (SELECT DISTINCT id_empresa, id_destino FROM migracion_mysql_map WHERE entidad = 'egresos'),
pagados AS (   -- documentos con al menos una línea de pago de un egreso vigente
    SELECT DISTINCT d.tipo_documento, d.id_referencia_documento AS id
    FROM egresos_detalle d
    JOIN egresos_cabecera e ON e.id = d.id_egreso
    WHERE d.tipo_documento IN ('COMPRA','LIQUIDACION') AND d.id_referencia_documento IS NOT NULL
      AND d.eliminado = false AND e.eliminado = false AND e.estado <> 'anulado'
),
docs AS (
    SELECT 'COMPRA'::text AS tipo, c.id, c.id_empresa, c.fecha_emision, c.importe_total
    FROM mc
    JOIN compras_cabecera c ON c.id = mc.id_destino AND c.id_empresa = mc.id_empresa
    WHERE c.eliminado = false
      AND COALESCE(NULLIF(TRIM(c.tipo_comprobante), ''), '01') NOT IN ('04','23','47','51','05','06','07')
      AND UPPER(TRIM(COALESCE(c.estado, ''))) NOT IN ('ANULADO','ANULADA','RECHAZADA','RECHAZADO')
    UNION ALL
    SELECT 'LIQUIDACION', l.id, l.id_empresa, l.fecha_emision, l.importe_total
    FROM ml
    JOIN liquidaciones_cabecera l ON l.id = ml.id_destino AND l.id_empresa = ml.id_empresa
    WHERE l.eliminado = false
      AND UPPER(TRIM(COALESCE(l.estado, ''))) IN ('AUTORIZADO','AUTORIZADA','APROBADO','APROBADA','CONTABILIZADO','CONTABILIZADA')
),
doc_anio AS (
    SELECT d.id_empresa, EXTRACT(YEAR FROM d.fecha_emision)::int AS anio, d.tipo,
           COUNT(*)     AS migrados,
           COUNT(pg.id) AS con_algun_pago,
           ROUND(COALESCE(SUM(d.importe_total) FILTER (WHERE pg.id IS NULL), 0), 2) AS total_sin_pago
    FROM docs d
    LEFT JOIN pagados pg ON pg.tipo_documento = d.tipo AND pg.id = d.id
    CROSS JOIN parametros p
    WHERE p.id_empresa = 0 OR d.id_empresa = p.id_empresa
    GROUP BY 1, 2, 3
),
egr_anio AS (
    SELECT e.id_empresa, EXTRACT(YEAR FROM e.fecha_emision)::int AS anio,
           COUNT(*) AS egresos_migrados,
           COUNT(*) FILTER (WHERE e.tipo_egreso IN ('COMPRA','LIQUIDACION')) AS egresos_de_documentos
    FROM me
    JOIN egresos_cabecera e ON e.id = me.id_destino AND e.id_empresa = me.id_empresa
    WHERE e.eliminado = false
    GROUP BY 1, 2
)
SELECT da.id_empresa, da.anio, da.tipo,
       da.migrados,
       da.con_algun_pago,
       da.migrados - da.con_algun_pago       AS sin_pago,
       da.total_sin_pago,
       COALESCE(ea.egresos_migrados, 0)      AS egresos_migrados_en_el_anio,
       COALESCE(ea.egresos_de_documentos, 0) AS egresos_de_documentos_en_el_anio
FROM doc_anio da
LEFT JOIN egr_anio ea ON ea.id_empresa = da.id_empresa AND ea.anio = da.anio
ORDER BY da.id_empresa, da.anio, da.tipo;


-- ── 8) LIQUIDACIONES migradas sin pago: ¿dónde quedó su pago? ──────────────────
--   En el sistema anterior una liquidación solo se podía pagar con un egreso si
--   además estaba registrada como compra. Tres casos:
--   · con_compra_gemela: existe una COMPRA del mismo proveedor con el mismo número
--     (se registró como factura, no como liquidación): el pago está en esa compra
--     (gemela_pagada) y la liquidación queda duplicada como deuda.
--   · registrada_como_compra (tiene sustento tributario, que la migración solo
--     pone cuando halla su compra tipo 03) y sin gemela: allá figuraba en cuentas
--     por pagar; si no tiene pago, tampoco estaba pagada allá.
--   · sin_registro (sin sustento ni gemela): allá nunca figuró en cuentas por
--     pagar, así que no existe un pago que migrar.
--   Si en una empresa con_sustento_total = 0, el indicador de sustento no aplica
--   (liquidaciones migradas antes de ese cruce; re-migrarlas lo completa).
WITH parametros AS (
    SELECT 0::int AS id_empresa          -- << 0 = todas; o el id de la empresa (p. ej. 94)
),
liq AS (
    SELECT l.id, l.id_empresa, l.id_proveedor, l.importe_total, l.id_sustento_tributario,
           COALESCE(l.establecimiento, '') || COALESCE(l.punto_emision, '') || COALESCE(l.secuencial, '') AS num15
    FROM (SELECT DISTINCT id_empresa, id_destino FROM migracion_mysql_map WHERE entidad = 'liquidaciones') ml
    JOIN liquidaciones_cabecera l ON l.id = ml.id_destino AND l.id_empresa = ml.id_empresa
    CROSS JOIN parametros p
    WHERE l.eliminado = false AND (p.id_empresa = 0 OR l.id_empresa = p.id_empresa)
      AND UPPER(TRIM(COALESCE(l.estado, ''))) IN ('AUTORIZADO','AUTORIZADA','APROBADO','APROBADA','CONTABILIZADO','CONTABILIZADA')
),
pagados AS (
    SELECT DISTINCT d.tipo_documento, d.id_referencia_documento AS id
    FROM egresos_detalle d
    JOIN egresos_cabecera e ON e.id = d.id_egreso
    WHERE d.tipo_documento IN ('COMPRA','LIQUIDACION') AND d.id_referencia_documento IS NOT NULL
      AND d.eliminado = false AND e.eliminado = false AND e.estado <> 'anulado'
),
gemela AS (    -- compra del mismo proveedor y empresa con el número de la liquidación
    SELECT l.id AS id_liq, BOOL_OR(pc.id IS NOT NULL) AS gemela_pagada
    FROM liq l
    JOIN compras_cabecera c
      ON c.id_empresa = l.id_empresa AND c.id_proveedor = l.id_proveedor AND c.eliminado = false
     AND COALESCE(NULLIF(TRIM(c.tipo_comprobante), ''), '01') NOT IN ('04','23','47','51','05','06','07')
     AND COALESCE(c.establecimiento_prov, '') || COALESCE(c.punto_emision_prov, '') || COALESCE(c.secuencial_prov, '') = l.num15
    LEFT JOIN pagados pc ON pc.tipo_documento = 'COMPRA' AND pc.id = c.id
    GROUP BY l.id
)
SELECT l.id_empresa,
       COUNT(*)                                                                                     AS liq_migradas,
       COUNT(*) FILTER (WHERE l.id_sustento_tributario IS NOT NULL)                                 AS con_sustento_total,
       COUNT(*) FILTER (WHERE pg.id IS NULL)                                                        AS sin_pago,
       COUNT(*) FILTER (WHERE pg.id IS NULL AND g.id_liq IS NOT NULL)                               AS con_compra_gemela,
       COUNT(*) FILTER (WHERE pg.id IS NULL AND g.gemela_pagada)                                    AS gemela_pagada,
       COUNT(*) FILTER (WHERE pg.id IS NULL AND g.id_liq IS NULL AND l.id_sustento_tributario IS NOT NULL) AS registrada_como_compra,
       COUNT(*) FILTER (WHERE pg.id IS NULL AND g.id_liq IS NULL AND l.id_sustento_tributario IS NULL)     AS sin_registro,
       ROUND(COALESCE(SUM(l.importe_total) FILTER (WHERE pg.id IS NULL), 0), 2)                     AS total_sin_pago
FROM liq l
LEFT JOIN pagados pg ON pg.tipo_documento = 'LIQUIDACION' AND pg.id = l.id
LEFT JOIN gemela g   ON g.id_liq = l.id
GROUP BY l.id_empresa
HAVING COUNT(*) FILTER (WHERE pg.id IS NULL) > 0
ORDER BY l.id_empresa;
