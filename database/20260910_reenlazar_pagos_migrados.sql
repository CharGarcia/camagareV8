-- ============================================================================
-- Re-enlaza a su COMPRA o LIQUIDACIÓN los pagos (egresos) migrados que quedaron
-- sin documento o apuntando a un documento que ya no existe.
-- ----------------------------------------------------------------------------
-- Contexto:
--   Al migrar Pagos (egresos), cada línea se enlaza al documento por el mapa de
--   la migración. La línea queda SIN id cuando la compra/liquidación aún no
--   estaba migrada (o cayó en omitidos/errores y se migró después), y queda con
--   un id ROTO cuando las compras se borraron con "Eliminar migrados" y se
--   volvieron a migrar (reciben ids nuevos; egresos_detalle no tiene FK y
--   conserva los viejos). En ambos casos Cuentas por Pagar no descuenta el pago
--   y el documento sigue "pendiente".
--
-- Qué hace (solo sobre egresos que vinieron de la migración — migracion_mysql_map):
--   1. Toma las líneas tipo COMPRA/LIQUIDACION sin id, o con id de un documento
--      inexistente/eliminado, cuyo número (el de la línea o el que aparece en su
--      texto) calza con UN documento vivo del MISMO tipo, en la misma empresa,
--      del proveedor del egreso (o con el nombre del proveedor en el texto).
--      Con varios candidatos elige por ambiente, signo (una línea negativa es
--      una NC) y fecha (el documento debe ser anterior al pago); si sigue
--      empatado, no toca la línea. Es el estado "SE ENLAZA" del diagnóstico
--      database/diagnosticos/20260910_cxp_migrados_pagos_no_cruzan.sql
--   2. Les pone el id y el número del documento. El tipo y el monto NO cambian.
--   3. Deja en log_sistema (accion 'REENLAZAR_PAGO_MIG') una fila por egreso
--      con el antes y el después de sus líneas.
--   No toca montos, formas de pago, cheques ni asientos (los documentos
--   migrados no generan asiento propio).
--
-- Idempotente: al re-ejecutarlo solo encuentra lo que falte (0 la 2.ª vez).
-- Parámetros (dentro del DO): v_emp (0 = todas las empresas) y v_user (usuario
-- que ejecuta, para updated_by y log_sistema; producción: 2).
--
-- Antes de ejecutar: correr la consulta 3 del diagnóstico y revisar la fila
-- "SE ENLAZA" (cuántas líneas / documentos / monto).
--
-- Reversible con log_sistema (ejecutar aparte, solo si hiciera falta):
--   UPDATE egresos_detalle d
--      SET id_referencia_documento = x.id_referencia_documento,
--          numero_documento        = x.numero_documento
--     FROM log_sistema ls
--     CROSS JOIN LATERAL jsonb_to_recordset(ls.datos_anteriores -> 'lineas')
--          AS x(id_detalle int, id_referencia_documento int, numero_documento text)
--    WHERE ls.accion = 'REENLAZAR_PAGO_MIG' AND d.id = x.id_detalle;
-- ============================================================================

DO $$
DECLARE
    v_emp     int := 0;   -- << 0 = todas las empresas; o el id de la empresa
    v_user    int := 2;   -- << usuario que ejecuta (producción: 2)
    v_lineas  int;
    v_egresos int;
BEGIN
    DROP TABLE IF EXISTS pg_temp.tmp_reenlace;
    CREATE TEMP TABLE tmp_reenlace (
        id_detalle   int PRIMARY KEY,
        id_egreso    int NOT NULL,
        id_empresa   int NOT NULL,
        tipo         varchar(50) NOT NULL,
        id_ref_ant   int,
        numero_ant   varchar(50),
        id_doc       int NOT NULL,
        numero_doc   varchar(50) NOT NULL
    );

    -- 1) Líneas a re-enlazar (misma lógica que el estado "SE ENLAZA" del diagnóstico)
    INSERT INTO tmp_reenlace (id_detalle, id_egreso, id_empresa, tipo, id_ref_ant, numero_ant, id_doc, numero_doc)
    WITH lineas AS (
        SELECT d.id AS id_detalle, d.id_egreso, e.id_empresa,
               e.fecha_emision AS fecha_egreso,
               e.id_proveedor AS prov_egreso, e.tipo_ambiente AS amb_egreso,
               d.tipo_documento, d.id_referencia_documento AS id_ref,
               d.descripcion, d.numero_documento, d.monto_pagado,
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
        WHERE d.tipo_documento IN ('COMPRA','LIQUIDACION')
          AND d.eliminado = false AND e.eliminado = false
          AND (v_emp = 0 OR e.id_empresa = v_emp)
          AND (d.id_referencia_documento IS NULL
               OR (d.tipo_documento = 'COMPRA'
                   AND NOT EXISTS (SELECT 1 FROM compras_cabecera c WHERE c.id = d.id_referencia_documento AND c.eliminado = false))
               OR (d.tipo_documento = 'LIQUIDACION'
                   AND NOT EXISTS (SELECT 1 FROM liquidaciones_cabecera l WHERE l.id = d.id_referencia_documento AND l.eliminado = false)))
    ),
    cand AS (
        SELECT h.id_detalle, 'COMPRA'::text AS tipo_doc, c.id AS id_doc, c.fecha_emision,
               CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) AS numero_doc,
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
        FROM lineas h
        JOIN compras_cabecera c
          ON c.id_empresa = h.id_empresa AND c.eliminado = false
         AND COALESCE(c.establecimiento_prov, '') || COALESCE(c.punto_emision_prov, '') || COALESCE(c.secuencial_prov, '') = h.num15
        JOIN proveedores p ON p.id = c.id_proveedor
        WHERE length(h.num15) = 15
        UNION ALL
        SELECT h.id_detalle, 'LIQUIDACION', l.id, l.fecha_emision,
               CONCAT(l.establecimiento, '-', l.punto_emision, '-', l.secuencial),
               (h.tipo_documento = 'LIQUIDACION'),
               COALESCE(l.id_proveedor = h.prov_egreso
                        OR (length(p.razon_social) >= 4 AND h.descripcion ILIKE ('%' || p.razon_social || '%'))
                        OR h.descripcion ILIKE '%liquidaci%', false),
               COALESCE(h.descripcion ILIKE '%liquidaci%', false),
               (UPPER(TRIM(COALESCE(l.estado, ''))) IN ('AUTORIZADO','AUTORIZADA','APROBADO','APROBADA','CONTABILIZADO','CONTABILIZADA')),
               COALESCE(l.tipo_ambiente = h.amb_egreso, false),
               true,
               COALESCE(l.fecha_emision <= h.fecha_egreso, false)
        FROM lineas h
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
               COUNT(*) FILTER (WHERE rk = 1)           AS empatadas,
               MIN(id_doc)     FILTER (WHERE rk = 1)    AS id_doc,
               MIN(tipo_doc)   FILTER (WHERE rk = 1)    AS tipo_doc,
               MIN(numero_doc) FILTER (WHERE rk = 1)    AS numero_doc,
               BOOL_OR(prov_ok) FILTER (WHERE rk = 1)   AS prov_ok
        FROM ranked
        GROUP BY id_detalle
    )
    SELECT h.id_detalle, h.id_egreso, h.id_empresa, h.tipo_documento, h.id_ref, h.numero_documento, el.id_doc, el.numero_doc
    FROM lineas h
    JOIN eleccion el ON el.id_detalle = h.id_detalle
    WHERE el.empatadas = 1
      AND el.prov_ok
      AND el.tipo_doc = h.tipo_documento;       -- solo el MISMO tipo ("SE ENLAZA")

    -- 2) Enlazar las líneas al documento (tipo y monto no cambian)
    UPDATE egresos_detalle d
       SET id_referencia_documento = t.id_doc,
           numero_documento        = t.numero_doc
      FROM tmp_reenlace t
     WHERE d.id = t.id_detalle;
    GET DIAGNOSTICS v_lineas = ROW_COUNT;

    UPDATE egresos_cabecera e
       SET updated_at = now(), updated_by = v_user
     WHERE e.id IN (SELECT DISTINCT id_egreso FROM tmp_reenlace);

    -- 3) Auditoría: una fila por egreso con el antes y el después de sus líneas
    INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                             datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
    SELECT v_user, t.id_empresa, 'REENLAZAR_PAGO_MIG', 'egresos_cabecera', t.id_egreso,
           jsonb_build_object('lineas', jsonb_agg(jsonb_build_object(
               'id_detalle', t.id_detalle, 'tipo_documento', t.tipo,
               'id_referencia_documento', t.id_ref_ant, 'numero_documento', t.numero_ant) ORDER BY t.id_detalle)),
           jsonb_build_object('lineas', jsonb_agg(jsonb_build_object(
               'id_detalle', t.id_detalle, 'tipo_documento', t.tipo,
               'id_referencia_documento', t.id_doc, 'numero_documento', t.numero_doc) ORDER BY t.id_detalle)),
           NULL, 'SQL 20260910_reenlazar_pagos_migrados', now()
      FROM tmp_reenlace t
     GROUP BY t.id_egreso, t.id_empresa;
    GET DIAGNOSTICS v_egresos = ROW_COUNT;

    RAISE NOTICE 'Líneas re-enlazadas: %  |  Egresos corregidos: %', v_lineas, v_egresos;

    DROP TABLE IF EXISTS pg_temp.tmp_reenlace;
END $$;

-- Comprobación (es la última sentencia: pgAdmin muestra este resultado).
-- Una fila por empresa con lo corregido por este script (acumulado si se corrió más de una vez).
SELECT ls.id_empresa,
       COUNT(*)                                             AS egresos_corregidos,
       SUM(jsonb_array_length(ls.datos_nuevos -> 'lineas')) AS lineas_reenlazadas,
       MAX(ls.created_at)                                   AS ultima_ejecucion
FROM log_sistema ls
WHERE ls.accion = 'REENLAZAR_PAGO_MIG'
GROUP BY ls.id_empresa
ORDER BY ls.id_empresa;
