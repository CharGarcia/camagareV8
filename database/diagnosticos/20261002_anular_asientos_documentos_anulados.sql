-- =====================================================================================
-- Anular 30 asientos VIVOS de documentos anulados / eliminados (diagnóstico del 02-10-2026)
-- =====================================================================================
-- Qué hace:   pone estado = 'anulado' en los 30 asientos identificados con
--             20261002_asientos_vivos_documentos_anulados.sql y
--             20261002_migrados_anulados_asiento_vivo.sql, y suelta el enlace del documento
--             (ingresos / egresos / roles), igual que el botón Anular de Asientos Contables.
--             Deja cada anulación en log_sistema ('Anular Asiento') a nombre del usuario indicado.
-- NO incluye: la factura VE-000015 de KMCONNECT (asiento 238319), pendiente de revisar.
--             NO toca NO-000006 (KMCONNECT) ni NO-000010 (BOMAN): son de los roles vigentes.
-- Seguridad:  se identifica cada asiento por su ID y se comprueba que el número y la empresa
--             coincidan. Si alguno no coincide, o cae en un PERÍODO CONTABLE CERRADO, no se
--             anula NADA (abra el período, corra de nuevo y vuelva a cerrarlo).
--             Los que ya estén anulados se saltan (se puede re-ejecutar sin daño).
-- Reversible: sí. Cada asiento queda con su enlace anterior en log_sistema.datos_anteriores;
--             ver «REVERTIR» al final.
--
-- Uso en pgAdmin: 1) ejecute el PASO 1 solo (seleccione y F5) y revise;
--                 2) ejecute el PASO 2 (DO … END $$;) y lea la pestaña Messages;
--                 3) ejecute la COMPROBACIÓN.
-- =====================================================================================


-- -------------------------------------------------------------------------------------
-- PASO 1 — VISTA PREVIA (solo lectura). Deben salir 30 filas, todas con numero_ok = true,
--          periodo_cerrado = false y estado_actual = 'contabilizado'.
-- -------------------------------------------------------------------------------------
WITH lista(id_asiento, numero, ruc, motivo) AS (VALUES
    -- KMCONNECT S.A.S.
    (238707, 'NO-000003', '1793212004001', 'rol eliminado'),
    (238824, 'NO-000004', '1793212004001', 'rol eliminado'),
    (302767, 'NO-000005', '1793212004001', 'rol eliminado'),
    (238498, 'EG-000208', '1793212004001', 'egreso anulado'),
    (238551, 'EG-000261', '1793212004001', 'egreso anulado'),
    (238563, 'EG-000273', '1793212004001', 'egreso anulado'),
    (238436, 'IN-000026', '1793212004001', 'ingreso anulado'),
    -- BOMAN ELECTRIC DEL ECUADOR SAS
    (302814, 'NO-000008', '1793231468001', 'rol eliminado'),
    (303520, 'NO-000009', '1793231468001', 'rol eliminado'),
    -- BIOZENTRIX ECUADOR S.A.S.
    (17308,  'IN-000027', '0993408339001', 'ingreso anulado'),
    (14949,  'EGR182102', '0993408339001', 'migrado anulado en el sistema'),
    (14948,  'EGR182101', '0993408339001', 'migrado anulado en el sistema'),
    (15104,  'ING178684', '0993408339001', 'migrado anulado en el sistema'),
    (15105,  'ING178685', '0993408339001', 'migrado anulado en el sistema'),
    (14897,  'ING179812', '0993408339001', 'migrado anulado en el sistema'),
    (14898,  'ING179813', '0993408339001', 'migrado anulado en el sistema'),
    (14954,  'ING182107', '0993408339001', 'migrado anulado en el sistema'),
    (14941,  'ING182067', '0993408339001', 'migrado anulado en el sistema'),
    (14946,  'ING182099', '0993408339001', 'migrado anulado en el sistema'),
    (14933,  'ING182015', '0993408339001', 'migrado anulado en el sistema'),
    (153251, 'ING185355', '0993408339001', 'migrado anulado en el sistema'),
    (153252, 'ING187148', '0993408339001', 'migrado anulado en el sistema'),
    -- COMERCIALIZADORA ASAMED IMPLANT
    (140585, 'ING189552', '1792708389001', 'migrado anulado en el sistema'),
    (141060, 'ING191401', '1792708389001', 'migrado anulado en el sistema'),
    (140614, 'ING189594', '1792708389001', 'anulado en el sistema anterior'),
    (140850, 'ING190464', '1792708389001', 'anulado en el sistema anterior'),
    (140998, 'ING191229', '1792708389001', 'anulado en el sistema anterior'),
    (140999, 'ING191230', '1792708389001', 'anulado en el sistema anterior'),
    (141099, 'ING191459', '1792708389001', 'anulado en el sistema anterior'),
    -- BEDOYA LEITON BLANCA ISABEL
    (237148, 'ING192900', '1712504032001', 'anulado en el sistema anterior')
)
SELECT l.id_asiento, l.numero, e.nombre AS empresa, l.motivo,
       a.numero_comprobante, a.fecha_asiento, a.total_debe AS monto,
       a.estado AS estado_actual,
       (a.numero_comprobante = l.numero AND e.ruc = l.ruc) AS numero_ok,
       EXISTS (SELECT 1 FROM periodos_contables p
                WHERE p.id_empresa = a.id_empresa AND p.eliminado = false AND p.status = 0
                  AND a.fecha_asiento BETWEEN p.fecha_inicial AND p.fecha_final) AS periodo_cerrado
  FROM lista l
  LEFT JOIN asientos_contables_cabecera a ON a.id = l.id_asiento AND a.eliminado = false
  LEFT JOIN empresas e ON e.id = a.id_empresa
 ORDER BY e.nombre, l.numero;


-- -------------------------------------------------------------------------------------
-- PASO 2 — ANULAR (seleccione desde DO hasta END $$; y F5). Todo o nada.
--          Cambie v_mail si quiere que la auditoría quede a nombre de otro usuario.
-- -------------------------------------------------------------------------------------
DO $$
DECLARE
    v_mail    CONSTANT text := 'carlosgarciarevelo@gmail.com';
    v_usuario int;
    v_ids     int[] := ARRAY[238707, 238824, 302767, 238498, 238551, 238563, 238436,
                             302814, 303520,
                             17308, 14949, 14948, 15104, 15105, 14897, 14898, 14954, 14941, 14946, 14933, 153251, 153252,
                             140585, 141060, 140614, 140850, 140998, 140999, 141099,
                             237148];
    v_numeros text[] := ARRAY['NO-000003', 'NO-000004', 'NO-000005', 'EG-000208', 'EG-000261', 'EG-000273', 'IN-000026',
                              'NO-000008', 'NO-000009',
                              'IN-000027', 'EGR182102', 'EGR182101', 'ING178684', 'ING178685', 'ING179812', 'ING179813', 'ING182107', 'ING182067', 'ING182099', 'ING182015', 'ING185355', 'ING187148',
                              'ING189552', 'ING191401', 'ING189594', 'ING190464', 'ING191229', 'ING191230', 'ING191459',
                              'ING192900'];
    r         record;
    v_malos   text;
    v_n       int := 0;
    v_ing     int[];
    v_egr     int[];
    v_rol     int[];
BEGIN
    SELECT id INTO v_usuario FROM usuarios WHERE LOWER(mail) = LOWER(v_mail) AND eliminado = false LIMIT 1;
    IF v_usuario IS NULL THEN
        RAISE EXCEPTION 'No existe el usuario %. Corrija v_mail. No se anuló nada.', v_mail;
    END IF;

    -- 1. Cada id debe existir y tener el número esperado.
    SELECT STRING_AGG(x.id || ' (' || x.num || ')', ', ') INTO v_malos
      FROM UNNEST(v_ids, v_numeros) AS x(id, num)
      LEFT JOIN asientos_contables_cabecera a ON a.id = x.id AND a.eliminado = false
     WHERE a.id IS NULL OR a.numero_comprobante <> x.num;
    IF v_malos IS NOT NULL THEN
        RAISE EXCEPTION 'Estos asientos no existen o su número no coincide: %. No se anuló nada.', v_malos;
    END IF;

    -- 2. Ninguno puede caer en un período contable cerrado.
    SELECT STRING_AGG(a.numero_comprobante || ' (' || a.fecha_asiento || ')', ', ') INTO v_malos
      FROM asientos_contables_cabecera a
     WHERE a.id = ANY(v_ids) AND a.estado <> 'anulado'
       AND EXISTS (SELECT 1 FROM periodos_contables p
                    WHERE p.id_empresa = a.id_empresa AND p.eliminado = false AND p.status = 0
                      AND a.fecha_asiento BETWEEN p.fecha_inicial AND p.fecha_final);
    IF v_malos IS NOT NULL THEN
        RAISE EXCEPTION 'Período contable cerrado para: %. Ábralo, ejecute de nuevo y vuelva a cerrarlo. No se anuló nada.', v_malos;
    END IF;

    -- 3. Anular, soltar el enlace del documento y auditar (uno por uno).
    FOR r IN SELECT a.* FROM asientos_contables_cabecera a
              WHERE a.id = ANY(v_ids) AND a.estado <> 'anulado' AND a.eliminado = false
              ORDER BY a.id
    LOOP
        SELECT ARRAY_AGG(id) INTO v_ing FROM ingresos_cabecera WHERE id_asiento_contable = r.id;
        SELECT ARRAY_AGG(id) INTO v_egr FROM egresos_cabecera  WHERE id_asiento_contable = r.id;
        SELECT ARRAY_AGG(id) INTO v_rol FROM rol_cabecera      WHERE id_asiento = r.id;

        UPDATE asientos_contables_cabecera
           SET estado = 'anulado', updated_by = v_usuario, updated_at = now()
         WHERE id = r.id;
        UPDATE ingresos_cabecera SET id_asiento_contable = NULL WHERE id_asiento_contable = r.id;
        UPDATE egresos_cabecera  SET id_asiento_contable = NULL WHERE id_asiento_contable = r.id;
        UPDATE rol_cabecera      SET id_asiento = NULL          WHERE id_asiento = r.id;

        INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                                 datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
        VALUES (v_usuario, r.id_empresa, 'Anular Asiento', 'asientos_contables_cabecera', r.id,
                jsonb_strip_nulls(jsonb_build_object('estado', r.estado, 'numero_comprobante', r.numero_comprobante,
                                   'ingresos_enlazados', v_ing, 'egresos_enlazados', v_egr, 'roles_enlazados', v_rol)),
                jsonb_build_object('estado', 'anulado',
                                   'motivo', 'Asiento vivo de documento anulado/eliminado (script 20261002)'),
                'pgAdmin', 'script 20261002_anular_asientos_documentos_anulados.sql', now());
        v_n := v_n + 1;
    END LOOP;

    RAISE NOTICE 'Asientos anulados: % (de % en la lista; el resto ya estaba anulado).', v_n, array_length(v_ids, 1);
END $$;


-- -------------------------------------------------------------------------------------
-- COMPROBACIÓN — debe salir 30 en «anulados» y 0 en «vivos».
-- -------------------------------------------------------------------------------------
-- SELECT COUNT(*) FILTER (WHERE estado = 'anulado')  AS anulados,
--        COUNT(*) FILTER (WHERE estado <> 'anulado') AS vivos
--   FROM asientos_contables_cabecera
--  WHERE id IN (238707, 238824, 302767, 238498, 238551, 238563, 238436, 302814, 303520,
--               17308, 14949, 14948, 15104, 15105, 14897, 14898, 14954, 14941, 14946, 14933, 153251, 153252,
--               140585, 141060, 140614, 140850, 140998, 140999, 141099, 237148);
-- Luego vuelva a correr 20261002_asientos_vivos_documentos_anulados.sql: debe quedar solo VE-000015.


-- -------------------------------------------------------------------------------------
-- REVERTIR (solo si hiciera falta) — devuelve a 'contabilizado' lo anulado por este script y
-- restaura los enlaces guardados en log_sistema.
-- -------------------------------------------------------------------------------------
-- DO $$
-- DECLARE r record;
-- BEGIN
--   FOR r IN SELECT id_registro, datos_anteriores FROM log_sistema
--             WHERE user_agent = 'script 20261002_anular_asientos_documentos_anulados.sql'
--   LOOP
--     UPDATE asientos_contables_cabecera SET estado = 'contabilizado', updated_at = now() WHERE id = r.id_registro;
--     UPDATE ingresos_cabecera SET id_asiento_contable = r.id_registro
--      WHERE id IN (SELECT jsonb_array_elements_text(COALESCE(r.datos_anteriores->'ingresos_enlazados', '[]'))::int);
--     UPDATE egresos_cabecera  SET id_asiento_contable = r.id_registro
--      WHERE id IN (SELECT jsonb_array_elements_text(COALESCE(r.datos_anteriores->'egresos_enlazados', '[]'))::int);
--     UPDATE rol_cabecera      SET id_asiento = r.id_registro
--      WHERE id IN (SELECT jsonb_array_elements_text(COALESCE(r.datos_anteriores->'roles_enlazados', '[]'))::int);
--   END LOOP;
-- END $$;
