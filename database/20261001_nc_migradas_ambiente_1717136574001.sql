-- ============================================================================
-- Pone el ambiente ACTUAL de la empresa a sus notas de crédito MIGRADAS que
-- quedaron con otro ambiente. SOLO RUC 1717136574001.
-- ----------------------------------------------------------------------------
-- Contexto:
--   La empresa está en ambiente 2 (producción), pero 4 notas de crédito
--   migradas y autorizadas ($2.707,29) quedaron con tipo_ambiente = 1 (la
--   migración antigua copiaba el ambiente del sistema anterior). Cuentas por
--   Cobrar solo descuenta las NC del ambiente actual de la empresa, así que esas
--   facturas siguen con saldo allí; el buscador de Ingresos sí las descuenta y
--   no las muestra. Diagnóstico:
--   database/diagnosticos/20261001_cxc_vs_ingresos_nc_ambiente_1717136574001.sql
--
-- Qué hace:
--   1. Toma las NC de esa empresa que vinieron de la migración
--      (migracion_mysql_map, entidad 'notas_credito'), no eliminadas, cuyo
--      tipo_ambiente es distinto al de la empresa (o NULL).
--   2. Omite cualquiera cuyo número ya exista en el ambiente actual (evita
--      dejar la misma NC dos veces en producción).
--   3. Les pone el ambiente de la empresa. No cambia montos, estado, número,
--      clave de acceso, cliente ni asientos.
--   4. Deja en log_sistema (accion 'AMBIENTE_NC_MIG') una fila por NC con el
--      ambiente anterior y el nuevo.
--   NO toca las NC creadas a mano (no migradas), aunque estén en otro ambiente:
--   p. ej. la NC en borrador de ambiente 1 por $1.725,00 queda como está.
--
-- Ejecutar completo en pgAdmin (F5). Una sola transacción; idempotente (la
-- 2.ª vez no encuentra nada). El resultado sale en la pestaña "Messages".
--
-- Reversible con log_sistema (ejecutar aparte, solo si hiciera falta):
--   UPDATE notas_credito_cabecera n
--      SET tipo_ambiente = ls.datos_anteriores ->> 'tipo_ambiente'
--     FROM log_sistema ls
--    WHERE ls.accion = 'AMBIENTE_NC_MIG' AND ls.tabla_afectada = 'notas_credito_cabecera'
--      AND n.id = ls.id_registro;
-- ============================================================================

DO $$
DECLARE
    v_ruc      text := '1717136574001';  -- << solo esta empresa
    v_user     int  := 2;                -- << usuario que ejecuta (producción: 2)
    v_notas    int;
    v_total    numeric;
    v_omitidas int;
BEGIN
    IF NOT EXISTS (SELECT 1 FROM empresas WHERE ruc = v_ruc AND eliminado = false) THEN
        RAISE EXCEPTION 'No existe ninguna empresa activa con RUC %', v_ruc;
    END IF;

    DROP TABLE IF EXISTS pg_temp.tmp_nc_amb;
    CREATE TEMP TABLE tmp_nc_amb AS
    SELECT n.id, n.id_empresa, n.tipo_ambiente AS amb_ant,
           CAST(e.tipo_ambiente AS VARCHAR(1)) AS amb_nuevo,
           n.establecimiento || '-' || n.punto_emision || '-' || n.secuencial AS numero,
           n.importe_total, n.estado,
           EXISTS (SELECT 1 FROM notas_credito_cabecera o
                    WHERE o.id_empresa = n.id_empresa AND o.id <> n.id AND o.eliminado = false
                      AND o.tipo_ambiente = CAST(e.tipo_ambiente AS VARCHAR(1))
                      AND o.establecimiento = n.establecimiento
                      AND o.punto_emision   = n.punto_emision
                      AND o.secuencial      = n.secuencial) AS ya_existe
    FROM notas_credito_cabecera n
    JOIN empresas e ON e.id = n.id_empresa AND e.ruc = v_ruc AND e.eliminado = false
    WHERE n.eliminado = false
      AND (n.tipo_ambiente IS NULL OR n.tipo_ambiente <> CAST(e.tipo_ambiente AS VARCHAR(1)))
      AND EXISTS (SELECT 1 FROM migracion_mysql_map m
                   WHERE m.entidad = 'notas_credito' AND m.id_destino = n.id AND m.id_empresa = n.id_empresa);

    INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                             datos_anteriores, datos_nuevos, ip_usuario, user_agent)
    SELECT v_user, t.id_empresa, 'AMBIENTE_NC_MIG', 'notas_credito_cabecera', t.id,
           jsonb_build_object('tipo_ambiente', t.amb_ant,   'numero', t.numero, 'importe_total', t.importe_total, 'estado', t.estado),
           jsonb_build_object('tipo_ambiente', t.amb_nuevo, 'numero', t.numero, 'importe_total', t.importe_total, 'estado', t.estado),
           'pgAdmin', 'database/20261001_nc_migradas_ambiente_1717136574001.sql'
    FROM tmp_nc_amb t
    WHERE NOT t.ya_existe;

    UPDATE notas_credito_cabecera n
       SET tipo_ambiente = t.amb_nuevo,
           updated_at    = now(),
           updated_by    = v_user
      FROM tmp_nc_amb t
     WHERE n.id = t.id
       AND NOT t.ya_existe;
    GET DIAGNOSTICS v_notas = ROW_COUNT;

    SELECT COALESCE(SUM(importe_total) FILTER (WHERE NOT ya_existe), 0),
           COUNT(*) FILTER (WHERE ya_existe)
      INTO v_total, v_omitidas
      FROM tmp_nc_amb;

    RAISE NOTICE 'RUC %: % notas de crédito migradas pasaron al ambiente de la empresa (total %).', v_ruc, v_notas, v_total;
    RAISE NOTICE 'Omitidas porque ese número ya existe en el ambiente actual: % (revisar a mano).', v_omitidas;
END $$;

-- ----------------------------------------------------------------------------
-- Comprobación (ejecutar aparte): vuelva a correr el diagnóstico
--   database/diagnosticos/20261001_cxc_vs_ingresos_nc_ambiente_1717136574001.sql
-- En el resumen ya no debe salir la fila ambiente_nc = 1 / autorizado / 4 migradas;
-- solo queda la NC en borrador de ambiente 1 (no migrada, $1.725,00).
-- ============================================================================
