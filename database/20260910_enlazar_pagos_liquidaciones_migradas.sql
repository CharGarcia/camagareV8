-- ============================================================================
-- Enlaza a su LIQUIDACIÓN DE COMPRA los pagos (egresos) migrados que quedaron
-- como "compra sin documento".
-- ----------------------------------------------------------------------------
-- Contexto:
--   En el sistema anterior el egreso pagaba la liquidación a través de su fila
--   en encabezado_compra (id_comprobante = 3). La migración de Compras excluye
--   esas filas (las liquidaciones se migran aparte, a liquidaciones_cabecera),
--   y la migración de Pagos (egresos) guardaba la línea como
--   tipo_documento = 'COMPRA' con id_referencia_documento NULL. Resultado: la
--   liquidación seguía "pendiente de pago" en Cuentas por Pagar, en Egresos
--   ("Liquidaciones de Compra - Pendientes de Pago") y en su pestaña Pagos.
--   El código de la migración ya está corregido (MigracionMysqlService::
--   migrarEgresos); este script repara lo YA migrado sin volver a migrar.
--
-- Qué hace (solo sobre egresos que vinieron de la migración — migracion_mysql_map):
--   1. Toma las líneas tipo 'COMPRA' sin documento cuyo número (el de la línea,
--      o el que aparece en su texto) calza con UNA liquidación de la misma
--      empresa, del mismo proveedor del egreso o con "liquidación" en el texto,
--      y que NO calzan también con una factura de compra de ese proveedor emitida
--      hasta la fecha del pago (una factura posterior no puede ser lo que se pagó).
--      Es exactamente el estado "SE ENLAZA" del diagnóstico
--      database/diagnosticos/20260910_liquidaciones_migradas_pago_sin_enlace.sql
--   2. Esas líneas pasan a tipo_documento = 'LIQUIDACION', con el id y el número
--      de la liquidación. El monto NO cambia.
--   3. El egreso pasa a tipo_egreso = 'LIQUIDACION' si ya no le queda ninguna
--      línea de compra (igual que un egreso nativo que solo paga liquidaciones).
--   4. Deja en log_sistema (accion 'ENLAZAR_PAGO_LIQ') una fila por egreso con
--      el antes y el después de sus líneas.
--   No toca montos, formas de pago, cheques ni asientos (los documentos
--   migrados no generan asiento propio).
--
-- Idempotente: al re-ejecutarlo solo encuentra lo que falte (0 la 2.ª vez).
-- Parámetros (dentro del DO): v_emp (0 = todas las empresas; producción: 8) y
-- v_user (usuario que ejecuta, para updated_by y log_sistema; producción: 2).
--
-- Antes de ejecutar: correr la consulta 1 del diagnóstico y revisar la fila
-- "SE ENLAZA" (cuántas líneas / liquidaciones / monto).
--
-- Reversible con log_sistema (ejecutar aparte, solo si hiciera falta):
--   UPDATE egresos_detalle d
--      SET tipo_documento          = x.tipo_documento,
--          id_referencia_documento = x.id_referencia_documento,
--          numero_documento        = x.numero_documento
--     FROM log_sistema ls
--     CROSS JOIN LATERAL jsonb_to_recordset(ls.datos_anteriores -> 'lineas')
--          AS x(id_detalle int, tipo_documento text, id_referencia_documento int, numero_documento text)
--    WHERE ls.accion = 'ENLAZAR_PAGO_LIQ' AND d.id = x.id_detalle;
--   UPDATE egresos_cabecera e
--      SET tipo_egreso = ls.datos_anteriores ->> 'tipo_egreso', updated_at = now()
--     FROM log_sistema ls
--    WHERE ls.accion = 'ENLAZAR_PAGO_LIQ' AND ls.tabla_afectada = 'egresos_cabecera'
--      AND e.id = ls.id_registro;
-- ============================================================================

DO $$
DECLARE
    v_emp     int := 0;   -- << 0 = todas las empresas; o el id de la empresa (producción: 8)
    v_user    int := 2;   -- << usuario que ejecuta (producción: 2)
    v_lineas  int;
    v_egresos int;
    v_tipo    int;
BEGIN
    DROP TABLE IF EXISTS pg_temp.tmp_enlace_liq;
    CREATE TEMP TABLE tmp_enlace_liq (
        id_detalle      int PRIMARY KEY,
        id_egreso       int NOT NULL,
        id_empresa      int NOT NULL,
        numero_ant      varchar(50),
        tipo_egreso_ant varchar(30),
        id_liq          int NOT NULL,
        numero_liq      varchar(50) NOT NULL
    );

    -- 1) Líneas a enlazar (misma lógica que el estado "SE ENLAZA" del diagnóstico)
    INSERT INTO tmp_enlace_liq (id_detalle, id_egreso, id_empresa, numero_ant, tipo_egreso_ant, id_liq, numero_liq)
    WITH lineas AS (
        SELECT d.id AS id_detalle, d.id_egreso, e.id_empresa, e.tipo_egreso, e.fecha_emision AS fecha_egreso,
               e.id_proveedor AS prov_egreso, e.tipo_ambiente AS amb_egreso,
               d.descripcion, d.numero_documento,
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
        WHERE d.tipo_documento = 'COMPRA'
          AND d.id_referencia_documento IS NULL
          AND d.eliminado = false
          AND e.eliminado = false
          AND (v_emp = 0 OR e.id_empresa = v_emp)
    ),
    liq AS (
        SELECT li.id_detalle, l.id AS id_liq, l.id_proveedor AS prov_liq, l.tipo_ambiente AS amb_liq
        FROM lineas li
        JOIN liquidaciones_cabecera l
          ON l.id_empresa = li.id_empresa
         AND l.eliminado = false
         AND COALESCE(l.establecimiento, '') || COALESCE(l.punto_emision, '') || COALESCE(l.secuencial, '') = li.num15
        WHERE length(li.num15) = 15
    ),
    eleccion AS (
        SELECT li.id_detalle,
               CASE
                 WHEN COUNT(q.id_liq) = 1 THEN MIN(q.id_liq)
                 WHEN COUNT(q.id_liq) FILTER (WHERE q.amb_liq = li.amb_egreso) = 1
                      THEN MIN(q.id_liq) FILTER (WHERE q.amb_liq = li.amb_egreso)
                 WHEN COUNT(q.id_liq) FILTER (WHERE q.amb_liq = li.amb_egreso AND q.prov_liq = li.prov_egreso) = 1
                      THEN MIN(q.id_liq) FILTER (WHERE q.amb_liq = li.amb_egreso AND q.prov_liq = li.prov_egreso)
               END AS id_liq
        FROM lineas li
        JOIN liq q ON q.id_detalle = li.id_detalle
        GROUP BY li.id_detalle, li.amb_egreso, li.prov_egreso
    ),
    compra AS (
        SELECT DISTINCT li.id_detalle
        FROM lineas li
        JOIN compras_cabecera c
          ON c.id_empresa = li.id_empresa
         AND c.eliminado = false
         AND c.id_proveedor = li.prov_egreso
         AND COALESCE(c.tipo_comprobante, '01') NOT IN ('04', '05')
         AND c.fecha_emision <= li.fecha_egreso        -- una factura posterior al pago no puede ser lo que se pagó
         AND COALESCE(c.establecimiento_prov, '') || COALESCE(c.punto_emision_prov, '') || COALESCE(c.secuencial_prov, '') = li.num15
        WHERE length(li.num15) = 15
    )
    SELECT li.id_detalle, li.id_egreso, li.id_empresa, li.numero_documento, li.tipo_egreso,
           l.id, l.establecimiento || '-' || l.punto_emision || '-' || l.secuencial
    FROM lineas li
    JOIN eleccion el              ON el.id_detalle = li.id_detalle AND el.id_liq IS NOT NULL
    JOIN liquidaciones_cabecera l ON l.id = el.id_liq
    WHERE (l.id_proveedor = li.prov_egreso OR li.descripcion ILIKE '%liquidaci%')
      AND NOT EXISTS (SELECT 1 FROM compra co WHERE co.id_detalle = li.id_detalle);

    -- 2) Enlazar las líneas a la liquidación (el monto no cambia)
    UPDATE egresos_detalle d
       SET tipo_documento          = 'LIQUIDACION',
           id_referencia_documento = t.id_liq,
           numero_documento        = t.numero_liq
      FROM tmp_enlace_liq t
     WHERE d.id = t.id_detalle
       AND d.tipo_documento = 'COMPRA'
       AND d.id_referencia_documento IS NULL;
    GET DIAGNOSTICS v_lineas = ROW_COUNT;

    -- 3) Tipo del egreso: LIQUIDACION si ya no paga ninguna compra
    UPDATE egresos_cabecera e
       SET tipo_egreso = 'LIQUIDACION',
           updated_at  = now(),
           updated_by  = v_user
     WHERE e.id IN (SELECT DISTINCT id_egreso FROM tmp_enlace_liq)
       AND e.tipo_egreso = 'COMPRA'
       AND NOT EXISTS (SELECT 1 FROM egresos_detalle d
                        WHERE d.id_egreso = e.id AND d.eliminado = false AND d.tipo_documento = 'COMPRA');
    GET DIAGNOSTICS v_tipo = ROW_COUNT;

    UPDATE egresos_cabecera e
       SET updated_at = now(), updated_by = v_user
     WHERE e.id IN (SELECT DISTINCT id_egreso FROM tmp_enlace_liq)
       AND e.tipo_egreso <> 'LIQUIDACION';

    -- 4) Auditoría: una fila por egreso con el antes y el después de sus líneas
    INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                             datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
    SELECT v_user, t.id_empresa, 'ENLAZAR_PAGO_LIQ', 'egresos_cabecera', t.id_egreso,
           jsonb_build_object(
               'tipo_egreso', MIN(t.tipo_egreso_ant),
               'lineas', jsonb_agg(jsonb_build_object(
                   'id_detalle', t.id_detalle, 'tipo_documento', 'COMPRA',
                   'id_referencia_documento', NULL, 'numero_documento', t.numero_ant) ORDER BY t.id_detalle)),
           jsonb_build_object(
               'tipo_egreso', MIN(e.tipo_egreso),
               'lineas', jsonb_agg(jsonb_build_object(
                   'id_detalle', t.id_detalle, 'tipo_documento', 'LIQUIDACION',
                   'id_referencia_documento', t.id_liq, 'numero_documento', t.numero_liq) ORDER BY t.id_detalle)),
           NULL, 'SQL 20260910_enlazar_pagos_liquidaciones_migradas', now()
      FROM tmp_enlace_liq t
      JOIN egresos_cabecera e ON e.id = t.id_egreso
     GROUP BY t.id_egreso, t.id_empresa;
    GET DIAGNOSTICS v_egresos = ROW_COUNT;

    RAISE NOTICE 'Líneas enlazadas a su liquidación: %  |  Egresos corregidos: %  |  Egresos que pasaron a tipo LIQUIDACION: %',
                 v_lineas, v_egresos, v_tipo;

    DROP TABLE IF EXISTS pg_temp.tmp_enlace_liq;
END $$;

-- Comprobación (es la última sentencia: pgAdmin muestra este resultado).
-- Una fila por empresa con lo corregido por este script (acumulado si se corrió más de una vez).
SELECT ls.id_empresa,
       COUNT(*)                                          AS egresos_corregidos,
       SUM(jsonb_array_length(ls.datos_nuevos -> 'lineas')) AS lineas_enlazadas,
       MAX(ls.created_at)                                AS ultima_ejecucion
FROM log_sistema ls
WHERE ls.accion = 'ENLAZAR_PAGO_LIQ'
GROUP BY ls.id_empresa
ORDER BY ls.id_empresa;
