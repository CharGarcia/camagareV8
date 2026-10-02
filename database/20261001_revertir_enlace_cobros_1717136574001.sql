-- ============================================================================
-- REVIERTE lo que hizo database/20261001_enlazar_cobros_migrados_1717136574001.sql
-- (RUC 1717136574001). Esa corrida enlazó 16 líneas de UN solo ingreso por
-- $2.121,03 y las 16 facturas quedaron cobradas DE MÁS: ya estaban cobradas por
-- otros ingresos, así que ese ingreso no las cobraba (o es un duplicado de la
-- migración). Se deja cada línea exactamente como estaba antes.
--
-- Qué hace:
--   1. Antes de revertir, muestra en "Messages" el ingreso afectado.
--   2. Devuelve id_referencia_documento y numero_documento a su valor anterior,
--      leyéndolos de log_sistema (accion 'ENLAZAR_COBRO_MIG' de esa empresa).
--      Solo toca líneas que siguen con el valor que puso el script (si alguien
--      las cambió después, no las pisa).
--   3. Deja constancia en log_sistema (accion 'REVERTIR_ENLAZAR_COBRO_MIG') y
--      marca las filas originales para que no se reviertan dos veces.
--
-- Ejecutar completo en pgAdmin (F5), SIN texto seleccionado. Una sola
-- transacción; idempotente (la 2.ª vez revierte 0 líneas).
-- ============================================================================

DO $$
DECLARE
    v_ruc       text := '1717136574001';
    v_user      int  := 2;
    v_lineas    int;
    r           record;
BEGIN
    DROP TABLE IF EXISTS pg_temp.tmp_revertir;
    CREATE TEMP TABLE tmp_revertir AS
    SELECT ls.id AS id_log, ls.id_empresa, ls.id_registro AS id_ingreso,
           a.id_detalle, a.id_referencia_documento AS ref_ant, a.numero_documento AS num_ant,
           n.id_referencia_documento AS ref_puesto, n.numero_documento AS num_puesto
    FROM log_sistema ls
    JOIN empresas e ON e.id = ls.id_empresa AND e.ruc = v_ruc
    CROSS JOIN LATERAL jsonb_to_recordset(ls.datos_anteriores -> 'lineas')
         AS a(id_detalle int, id_referencia_documento int, numero_documento text)
    JOIN LATERAL jsonb_to_recordset(ls.datos_nuevos -> 'lineas')
         AS n(id_detalle int, id_referencia_documento int, numero_documento text)
      ON n.id_detalle = a.id_detalle
    WHERE ls.accion = 'ENLAZAR_COBRO_MIG'
      AND ls.tabla_afectada = 'ingresos_detalle';

    FOR r IN
        SELECT i.numero_ingreso, i.fecha_emision, i.monto_total, i.estado,
               COALESCE(i.recibo_de, '') AS recibo_de, COUNT(*) AS lineas, SUM(d.monto_cobrado) AS monto
        FROM tmp_revertir t
        JOIN ingresos_detalle d  ON d.id = t.id_detalle
        JOIN ingresos_cabecera i ON i.id = d.id_ingreso
        GROUP BY 1, 2, 3, 4, 5
    LOOP
        RAISE NOTICE 'Ingreso % del % (% / total %, estado %): % líneas por %',
            r.numero_ingreso, r.fecha_emision, r.recibo_de, r.monto_total, r.estado, r.lineas, r.monto;
    END LOOP;

    UPDATE ingresos_detalle d
       SET id_referencia_documento = t.ref_ant,
           numero_documento        = t.num_ant
      FROM tmp_revertir t
     WHERE d.id = t.id_detalle
       AND d.id_referencia_documento IS NOT DISTINCT FROM t.ref_puesto;
    GET DIAGNOSTICS v_lineas = ROW_COUNT;

    INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                             datos_anteriores, datos_nuevos, ip_usuario, user_agent)
    SELECT v_user, ls.id_empresa, 'REVERTIR_ENLAZAR_COBRO_MIG', 'ingresos_detalle', ls.id_registro,
           ls.datos_nuevos, ls.datos_anteriores,
           'pgAdmin', 'database/20261001_revertir_enlace_cobros_1717136574001.sql'
    FROM log_sistema ls
    WHERE ls.id IN (SELECT DISTINCT id_log FROM tmp_revertir);

    -- Marca las filas originales como ya revertidas (no se borran: quedan como historial)
    UPDATE log_sistema
       SET accion = 'ENLAZAR_COBRO_MIG_REVERTIDO'
     WHERE id IN (SELECT DISTINCT id_log FROM tmp_revertir);

    RAISE NOTICE 'RUC %: % líneas devueltas a su estado anterior.', v_ruc, v_lineas;
END $$;

-- ----------------------------------------------------------------------------
-- Comprobación (ejecutar aparte): no debe quedar ninguna factura de la empresa
-- cobrada de más por este motivo (deben salir 0 filas, o las mismas que había
-- ANTES de correr el script de enlace).
--
-- SELECT v.id, CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS factura,
--        v.importe_total, SUM(d.monto_cobrado) AS cobrado,
--        string_agg(i.numero_ingreso, ' | ') AS ingresos
--   FROM ventas_cabecera v
--   JOIN empresas e ON e.id = v.id_empresa AND e.ruc = '1717136574001'
--   JOIN ingresos_detalle d  ON d.tipo_documento = 'FACTURA' AND d.id_referencia_documento = v.id
--   JOIN ingresos_cabecera i ON i.id = d.id_ingreso AND i.eliminado = false AND i.estado <> 'anulado'
--  WHERE v.eliminado = false
--  GROUP BY v.id, v.importe_total
-- HAVING SUM(d.monto_cobrado) > v.importe_total + 0.01;
-- ============================================================================
