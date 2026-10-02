-- ============================================================================
-- Enlaza a su FACTURA DE VENTA los cobros (ingresos) que quedaron sin enlace,
-- SOLO para la empresa RUC 1717136574001 (todas sus filas en `empresas` con ese
-- RUC exacto: no toca ningún otro RUC ni establecimiento).
-- ----------------------------------------------------------------------------
-- Contexto:
--   Un cobro descuenta una factura solo si su línea en ingresos_detalle tiene
--   tipo_documento = 'FACTURA' e id_referencia_documento = ventas_cabecera.id.
--   La migración llena ese id con el mapa de facturas que existía AL MIGRAR los
--   cobros: si las facturas se migraron después (o se borraron y re-migraron con
--   ids nuevos), la línea queda sin id o con un id roto, y Cuentas por Cobrar /
--   Ingresos siguen mostrando la factura con todo su saldo.
--
-- Qué hace:
--   1. Toma las líneas tipo FACTURA de ingresos vigentes de esa empresa que no
--      tienen id, o cuyo id no es una factura viva de la misma empresa.
--   2. Saca el número de la línea (numero_documento o, si falta, el que aparece
--      en la descripción) y busca facturas vivas (no anuladas, no eliminadas)
--      de la MISMA empresa con ese número (normalizado a 3-3-9 dígitos).
--   3. Con varios candidatos prefiere: mismo cliente del ingreso, ambiente de
--      la empresa y factura emitida antes o el mismo día del cobro. Si aun así
--      queda empate, NO toca la línea (mejor sin enlace que mal enlazada).
--   4. Pone el id y el número de la factura. NO cambia montos, tipo, formas de
--      cobro ni asientos.
--   5. Deja en log_sistema (accion 'ENLAZAR_COBRO_MIG') una fila por ingreso con
--      el antes y el después de sus líneas, para poder revertir.
--   Al final muestra un aviso (pestaña "Messages" de pgAdmin) con cuántas líneas
--   enlazó, cuántas quedaron sin candidato o empatadas, y cuántas facturas
--   quedarían cobradas DE MÁS (cobrado > total + 0.01) tras enlazar.
--
-- Idempotente: al re-ejecutarlo solo encuentra lo que falte (0 la 2.ª vez).
-- Ejecutar completo en pgAdmin (F5). Todo va en una sola transacción: si algo
-- falla, no queda nada a medias.
--
-- Reversible con log_sistema (ejecutar aparte, solo si hiciera falta):
--   UPDATE ingresos_detalle d
--      SET id_referencia_documento = x.id_referencia_documento,
--          numero_documento        = x.numero_documento
--     FROM log_sistema ls
--     CROSS JOIN LATERAL jsonb_to_recordset(ls.datos_anteriores -> 'lineas')
--          AS x(id_detalle int, id_referencia_documento int, numero_documento text)
--    WHERE ls.accion = 'ENLAZAR_COBRO_MIG' AND d.id = x.id_detalle;
-- ============================================================================

DO $$
DECLARE
    v_ruc       text := '1717136574001';  -- << solo esta empresa
    v_user      int  := 2;                -- << usuario que ejecuta (producción: 2)
    v_lineas    int;
    v_ingresos  int;
    v_monto     numeric;
    v_sin_cand  int;
    v_empate    int;
    v_de_mas    int;
    v_otros     int;
BEGIN
    IF NOT EXISTS (SELECT 1 FROM empresas WHERE ruc = v_ruc) THEN
        RAISE EXCEPTION 'No existe ninguna empresa con RUC %', v_ruc;
    END IF;

    DROP TABLE IF EXISTS pg_temp.tmp_cobros_enlace;
    CREATE TEMP TABLE tmp_cobros_enlace (
        id_detalle  int PRIMARY KEY,
        id_ingreso  int NOT NULL,
        id_empresa  int NOT NULL,
        id_ref_ant  int,
        numero_ant  varchar(100),
        id_doc      int,          -- NULL = sin candidato o empatado
        numero_doc  varchar(50),
        n_cand      int NOT NULL,
        n_top       int NOT NULL
    );

    INSERT INTO tmp_cobros_enlace (id_detalle, id_ingreso, id_empresa, id_ref_ant, numero_ant, id_doc, numero_doc, n_cand, n_top)
    WITH emp AS (
        SELECT id, CAST(tipo_ambiente AS VARCHAR(1)) AS amb
        FROM empresas WHERE ruc = v_ruc
    ),
    lineas AS (
        SELECT d.id AS id_detalle, d.id_ingreso, i.id_empresa, emp.amb,
               i.id_cliente, i.fecha_emision AS fecha_cobro,
               d.id_referencia_documento AS id_ref, d.numero_documento,
               COALESCE(
                   CASE WHEN d.numero_documento ~ '^[0-9]{1,3}-[0-9]{1,3}-[0-9]{1,9}$'
                        THEN lpad(split_part(d.numero_documento, '-', 1), 3, '0')
                          || lpad(split_part(d.numero_documento, '-', 2), 3, '0')
                          || lpad(split_part(d.numero_documento, '-', 3), 9, '0')
                   END,
                   (SELECT lpad(split_part(x, '-', 1), 3, '0')
                        || lpad(split_part(x, '-', 2), 3, '0')
                        || lpad(split_part(x, '-', 3), 9, '0')
                      FROM substring(d.descripcion FROM '[0-9]{1,3}-[0-9]{1,3}-[0-9]{1,9}') AS x
                     WHERE x IS NOT NULL)
               ) AS num15
        FROM ingresos_detalle d
        JOIN ingresos_cabecera i ON i.id = d.id_ingreso
        JOIN emp ON emp.id = i.id_empresa
        WHERE d.tipo_documento = 'FACTURA'
          AND i.eliminado = false
          AND i.estado <> 'anulado'
          AND (d.id_referencia_documento IS NULL
               OR NOT EXISTS (SELECT 1 FROM ventas_cabecera v
                               WHERE v.id = d.id_referencia_documento
                                 AND v.id_empresa = i.id_empresa
                                 AND v.eliminado = false
                                 AND v.estado <> 'anulado'))
    ),
    cand AS (
        SELECT l.id_detalle, v.id AS id_doc,
               CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial) AS numero_doc,
               (COALESCE(v.id_cliente = l.id_cliente, false))::int  AS cli_ok,
               (COALESCE(v.tipo_ambiente = l.amb, false))::int      AS amb_ok,
               (COALESCE(v.fecha_emision <= l.fecha_cobro, false))::int AS antes_ok
        FROM lineas l
        JOIN ventas_cabecera v
          ON v.id_empresa = l.id_empresa
         AND v.eliminado = false
         AND v.estado <> 'anulado'
         AND lpad(v.establecimiento, 3, '0') || lpad(v.punto_emision, 3, '0') || lpad(v.secuencial, 9, '0') = l.num15
    ),
    rank AS (
        SELECT c.*,
               (cli_ok * 4 + amb_ok * 2 + antes_ok) AS puntaje,
               MAX(cli_ok * 4 + amb_ok * 2 + antes_ok) OVER (PARTITION BY id_detalle) AS mejor,
               COUNT(*) OVER (PARTITION BY id_detalle) AS n_cand
        FROM cand c
    ),
    top AS (
        SELECT id_detalle, MIN(id_doc) AS id_doc, MIN(numero_doc) AS numero_doc,
               MAX(n_cand) AS n_cand, COUNT(*) AS n_top
        FROM rank WHERE puntaje = mejor
        GROUP BY id_detalle
    )
    SELECT l.id_detalle, l.id_ingreso, l.id_empresa, l.id_ref, l.numero_documento,
           CASE WHEN t.n_top = 1 THEN t.id_doc END,
           CASE WHEN t.n_top = 1 THEN t.numero_doc END,
           COALESCE(t.n_cand, 0), COALESCE(t.n_top, 0)
    FROM lineas l
    LEFT JOIN top t ON t.id_detalle = l.id_detalle;

    -- Auditoría: una fila por ingreso con el antes y el después de las líneas que cambian
    INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                             datos_anteriores, datos_nuevos, ip_usuario, user_agent)
    SELECT v_user, t.id_empresa, 'ENLAZAR_COBRO_MIG', 'ingresos_detalle', t.id_ingreso,
           jsonb_build_object('lineas', jsonb_agg(jsonb_build_object(
               'id_detalle', t.id_detalle,
               'id_referencia_documento', t.id_ref_ant,
               'numero_documento', t.numero_ant) ORDER BY t.id_detalle)),
           jsonb_build_object('lineas', jsonb_agg(jsonb_build_object(
               'id_detalle', t.id_detalle,
               'id_referencia_documento', t.id_doc,
               'numero_documento', t.numero_doc) ORDER BY t.id_detalle)),
           'pgAdmin', 'database/20261001_enlazar_cobros_migrados_1717136574001.sql'
    FROM tmp_cobros_enlace t
    WHERE t.id_doc IS NOT NULL
    GROUP BY t.id_empresa, t.id_ingreso;

    UPDATE ingresos_detalle d
       SET id_referencia_documento = t.id_doc,
           numero_documento        = t.numero_doc
      FROM tmp_cobros_enlace t
     WHERE d.id = t.id_detalle
       AND t.id_doc IS NOT NULL;
    GET DIAGNOSTICS v_lineas = ROW_COUNT;

    SELECT COUNT(DISTINCT id_ingreso) FILTER (WHERE id_doc IS NOT NULL),
           COALESCE((SELECT SUM(d.monto_cobrado) FROM ingresos_detalle d
                      JOIN tmp_cobros_enlace t2 ON t2.id_detalle = d.id AND t2.id_doc IS NOT NULL), 0),
           COUNT(*) FILTER (WHERE id_doc IS NULL AND n_cand = 0),
           COUNT(*) FILTER (WHERE id_doc IS NULL AND n_cand > 0)
      INTO v_ingresos, v_monto, v_sin_cand, v_empate
      FROM tmp_cobros_enlace;

    -- Facturas que, ya enlazadas, quedan con más cobrado que su total (para revisar a mano)
    SELECT COUNT(*) INTO v_de_mas
    FROM (
        SELECT v.id
        FROM ventas_cabecera v
        JOIN ingresos_detalle d   ON d.tipo_documento = 'FACTURA' AND d.id_referencia_documento = v.id
        JOIN ingresos_cabecera i  ON i.id = d.id_ingreso AND i.eliminado = false AND i.estado <> 'anulado'
        WHERE v.id IN (SELECT id_doc FROM tmp_cobros_enlace WHERE id_doc IS NOT NULL)
        GROUP BY v.id, v.importe_total
        HAVING SUM(d.monto_cobrado) > v.importe_total + 0.01
    ) x;

    -- Solo informativo: líneas de "otros conceptos" (tipo OTRO) que mencionan un número de factura
    SELECT COUNT(*) INTO v_otros
    FROM ingresos_detalle d
    JOIN ingresos_cabecera i ON i.id = d.id_ingreso AND i.eliminado = false AND i.estado <> 'anulado'
    JOIN empresas e ON e.id = i.id_empresa AND e.ruc = v_ruc
    WHERE d.tipo_documento = 'OTRO'
      AND d.descripcion ~ '[0-9]{1,3}-[0-9]{1,3}-[0-9]{1,9}';

    RAISE NOTICE 'RUC %: % líneas enlazadas en % ingresos (monto %).', v_ruc, v_lineas, v_ingresos, v_monto;
    RAISE NOTICE 'Sin enlazar: % sin factura con ese número (no existe o no se migró), % con varias facturas empatadas.', v_sin_cand, v_empate;
    RAISE NOTICE 'Facturas que quedan cobradas DE MÁS tras enlazar: % (revisar con la consulta de comprobación).', v_de_mas;
    RAISE NOTICE 'Informativo: % líneas de "otros conceptos" mencionan un número de factura (no se tocan).', v_otros;
END $$;

-- ----------------------------------------------------------------------------
-- Comprobación (ejecutar aparte, seleccionando solo este bloque):
-- después de correr el script, esto debe devolver 0 filas, salvo las líneas que
-- el aviso reportó como "sin factura con ese número" o "empatadas".
--
-- SELECT i.id_empresa, i.numero_ingreso, d.id AS id_detalle, d.numero_documento,
--        d.descripcion, d.monto_cobrado
--   FROM ingresos_detalle d
--   JOIN ingresos_cabecera i ON i.id = d.id_ingreso AND i.eliminado = false AND i.estado <> 'anulado'
--   JOIN empresas e ON e.id = i.id_empresa AND e.ruc = '1717136574001'
--  WHERE d.tipo_documento = 'FACTURA'
--    AND NOT EXISTS (SELECT 1 FROM ventas_cabecera v
--                     WHERE v.id = d.id_referencia_documento AND v.id_empresa = i.id_empresa
--                       AND v.eliminado = false AND v.estado <> 'anulado')
--  ORDER BY i.fecha_emision;
--
-- Facturas cobradas de más tras enlazar (revisar a mano; deben salir 0 filas):
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
