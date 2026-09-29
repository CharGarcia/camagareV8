-- =====================================================================================
-- Plan de cuentas: CORRECCIÓN de códigos y niveles fuera del formato de la casa
-- (resultado del diagnóstico 2026-09-29_plan_cuentas_diagnostico_codigos_niveles.sql en producción)
-- -------------------------------------------------------------------------------------
-- Formato: N1=1 · N2=1.1 · N3=1.1.1 · N4=1.1.1.01 · N5=1.1.1.01.001 ; nivel = nº de segmentos.
--
-- ¿Toca datos?  SÍ: UPDATE de codigo/nivel en plan_cuentas (por id), 1 INSERT (cuenta padre
--               2.1.7.01 de la empresa 34) y 1 eliminación LÓGICA (2.1.05 de la empresa 8).
--               Cada cambio queda en log_sistema (accion = 'CORREGIR CODIGO NIVEL').
-- Seguro:       los asientos y configuraciones apuntan a la cuenta por su ID, no por su código:
--               renombrar el código no rompe ninguna referencia. Cada fila solo se toca si su
--               código actual sigue siendo el esperado y si el código nuevo no lo usa otra
--               cuenta activa de la misma empresa (si no, se salta y se avisa en Mensajes).
-- Idempotente:  se puede ejecutar varias veces; la segunda no hace nada.
-- Atómico:      todo va en un solo bloque DO; si algo falla no se aplica nada.
-- Reversible:   con log_sistema (datos_anteriores tiene el código/nivel previos):
--   UPDATE plan_cuentas p SET codigo = l.datos_anteriores->>'codigo', nivel = l.datos_anteriores->>'nivel'
--     FROM log_sistema l WHERE l.accion = 'CORREGIR CODIGO NIVEL' AND l.id_registro = p.id
--      AND l.datos_anteriores ? 'codigo';
--
-- Pegar en pgAdmin y ejecutar (F5). Revisar la pestaña "Mensajes".
-- =====================================================================================
DO $$
DECLARE
    r       record;
    v_seq   int;
    v_id    int;
    v_n     int;
BEGIN
    DROP TABLE IF EXISTS _fix;
    CREATE TEMP TABLE _fix (
        id           int PRIMARY KEY,
        id_empresa   int  NOT NULL,
        codigo_ant   text NOT NULL,
        codigo_nuevo text NOT NULL,
        nivel_nuevo  text NOT NULL
    ) ON COMMIT DROP;

    -- ---------------------------------------------------------------------------------
    -- 1) Correcciones puntuales revisadas una por una
    -- ---------------------------------------------------------------------------------
    INSERT INTO _fix (id, id_empresa, codigo_ant, codigo_nuevo, nivel_nuevo) VALUES
        -- Empresa 8: la rama 2.1.05 pasa a colgar de 2.1.5 (id 3265, que ya existe).
        --            La 2.1.05 (id 3255) se elimina lógicamente más abajo.
        (3256,   8, '2.1.05.01',      '2.1.5.01',      '4'),
        (3257,   8, '2.1.05.01.001',  '2.1.5.01.001',  '5'),
        -- Empresa 23: solo el nivel
        (2985,  23, '1.2.2.01.009',   '1.2.2.01.009',  '5'),
        -- Empresa 33: 5.1.02 -> 5.1.2 (la 5.1.02.01.001 tiene 80 movimientos: conserva su id)
        (6057,  33, '5.1.02',         '5.1.2',         '3'),
        (6058,  33, '5.1.02.01',      '5.1.2.01',      '4'),
        (6059,  33, '5.1.02.01.001',  '5.1.2.01.001',  '5'),
        -- Empresa 34: 2.1.7 es el grupo (nivel 3). 2.1.7.01 era la cuenta de MOVIMIENTO
        --             (nivel 5): se conserva como movimiento pasando a 2.1.7.01.001, y más
        --             abajo se crea su padre 2.1.7.01 (nivel 4). Así cualquier configuración
        --             que apunte a ella (anticipos de clientes) sigue funcionando.
        (4395,  34, '2.1.7',          '2.1.7',         '3'),
        (4396,  34, '2.1.7.01',       '2.1.7.01.001',  '5'),
        (6063,  34, '5.3.01',         '5.3.1',         '3'),
        (6064,  34, '5.3.01.01',      '5.3.1.01',      '4'),
        (6065,  34, '5.3.01.01.001',  '5.3.1.01.001',  '5'),
        (7284,  34, '6.2.01',         '6.2.1',         '3'),
        (7285,  34, '6.2.01.01',      '6.2.1.01',      '4'),
        (7286,  34, '6.2.01.01.001',  '6.2.1.01.001',  '5'),
        -- Empresa 94: solo el nivel
        (6881,  94, '3.1.1.01.002',   '3.1.1.01.002',  '5'),
        -- Empresa 112: 2.1.2.02 es grupo (tiene hija) -> nivel 4; la hija a 3 dígitos
        (10362, 112, '2.1.2.02',      '2.1.2.02',      '4'),
        (10363, 112, '2.1.2.02.01',   '2.1.2.02.001',  '5'),
        -- Empresa 113: solo niveles (los códigos ya están bien)
        (10705, 113, '7.1',           '7.1',           '2'),
        (10706, 113, '7.1.1',         '7.1.1',         '3'),
        (10707, 113, '7.1.1.01',      '7.1.1.01',      '4'),
        (10708, 113, '7.1.1.01.001',  '7.1.1.01.001',  '5');

    -- ---------------------------------------------------------------------------------
    -- 2) Empresa 106: cuentas de nivel 6 (1.1.2.05.003.x / 1.1.2.05.004.x, sin movimientos).
    --    El plan admite 5 niveles: pasan a ser cuentas de nivel 5 hermanas bajo 1.1.2.05,
    --    con el siguiente número libre (1.1.2.05.NNN). Conservan id y nombre.
    -- ---------------------------------------------------------------------------------
    SELECT COALESCE(MAX(split_part(codigo, '.', 5)::int), 0)
      INTO v_seq
      FROM plan_cuentas
     WHERE id_empresa = 106
       AND codigo ~ '^1\.1\.2\.05\.[0-9]+$';          -- incluye eliminadas: no se reusa un código

    FOR r IN
        SELECT id, codigo
          FROM plan_cuentas
         WHERE id_empresa = 106 AND eliminado = false
           AND id IN (9198, 9199, 9201, 9202, 9203, 9204, 9205, 9206, 9207, 9208)
           AND codigo ~ '^1\.1\.2\.05\.[0-9]+\.[0-9]+$'   -- solo si sigue siendo de nivel 6
         ORDER BY string_to_array(codigo, '.')::int[]
    LOOP
        v_seq := v_seq + 1;
        INSERT INTO _fix VALUES (r.id, 106, r.codigo, '1.1.2.05.' || lpad(v_seq::text, 3, '0'), '5');
    END LOOP;

    -- ---------------------------------------------------------------------------------
    -- 3) Aplicar: solo filas cuyo código actual es el esperado, que aún tienen algo que
    --    cambiar y cuyo código nuevo no está ocupado por otra cuenta activa.
    -- ---------------------------------------------------------------------------------
    FOR r IN
        SELECT f.*
          FROM _fix f
          JOIN plan_cuentas p ON p.id = f.id AND p.id_empresa = f.id_empresa AND p.eliminado = false
         WHERE p.codigo = f.codigo_ant
           AND EXISTS (SELECT 1 FROM plan_cuentas o
                        WHERE o.id_empresa = f.id_empresa AND o.eliminado = false
                          AND o.id <> f.id AND o.codigo = f.codigo_nuevo)
    LOOP
        RAISE NOTICE 'SALTADA id % (empresa %): el código % ya lo usa otra cuenta activa', r.id, r.id_empresa, r.codigo_nuevo;
    END LOOP;

    WITH antes AS (
        SELECT p.id, p.id_empresa, p.codigo, p.nivel, f.codigo_nuevo, f.nivel_nuevo
          FROM _fix f
          JOIN plan_cuentas p ON p.id = f.id AND p.id_empresa = f.id_empresa AND p.eliminado = false
         WHERE p.codigo = f.codigo_ant
           AND (p.codigo <> f.codigo_nuevo OR p.nivel IS DISTINCT FROM f.nivel_nuevo)
           AND NOT EXISTS (SELECT 1 FROM plan_cuentas o
                            WHERE o.id_empresa = f.id_empresa AND o.eliminado = false
                              AND o.id <> f.id AND o.codigo = f.codigo_nuevo)
    ), upd AS (
        UPDATE plan_cuentas p
           SET codigo = a.codigo_nuevo, nivel = a.nivel_nuevo, updated_at = now()
          FROM antes a
         WHERE p.id = a.id
        RETURNING p.id, p.id_empresa, a.codigo AS codigo_ant, a.nivel AS nivel_ant, p.codigo, p.nivel
    )
    INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                             datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
    SELECT NULL, u.id_empresa, 'CORREGIR CODIGO NIVEL', 'plan_cuentas', u.id,
           jsonb_build_object('codigo', u.codigo_ant, 'nivel', u.nivel_ant),
           jsonb_build_object('codigo', u.codigo,     'nivel', u.nivel),
           'sql', 'Corrección de formato del plan de cuentas 2026-09-29', now()
      FROM upd u;
    GET DIAGNOSTICS v_n = ROW_COUNT;
    RAISE NOTICE 'Cuentas corregidas: %', v_n;

    -- ---------------------------------------------------------------------------------
    -- 4) Empresa 34: crear el padre 2.1.7.01 (nivel 4) de la nueva 2.1.7.01.001
    -- ---------------------------------------------------------------------------------
    IF EXISTS (SELECT 1 FROM plan_cuentas WHERE id_empresa = 34 AND eliminado = false AND codigo = '2.1.7.01.001')
       AND NOT EXISTS (SELECT 1 FROM plan_cuentas WHERE id_empresa = 34 AND eliminado = false AND codigo = '2.1.7.01') THEN
        INSERT INTO plan_cuentas (id_empresa, id_usuario, codigo, nivel, nombre, status, eliminado, created_by, created_at)
        SELECT 34, id_usuario, '2.1.7.01', '4', 'ANTICIPO CLIENTES', 1, false, created_by, now()
          FROM plan_cuentas WHERE id = 4396
        RETURNING id INTO v_id;
        INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                                 datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
        VALUES (NULL, 34, 'CORREGIR CODIGO NIVEL', 'plan_cuentas', v_id, NULL,
                jsonb_build_object('codigo', '2.1.7.01', 'nivel', '4', 'nombre', 'ANTICIPO CLIENTES', 'creada', true),
                'sql', 'Corrección de formato del plan de cuentas 2026-09-29', now());
        RAISE NOTICE 'Empresa 34: creada la cuenta padre 2.1.7.01 (id %)', v_id;
    END IF;

    -- ---------------------------------------------------------------------------------
    -- 5) Empresa 8: eliminar lógicamente 2.1.05 (id 3255), duplicado de 2.1.5 (id 3265),
    --    solo si ya no tiene hijas activas, ni movimientos, ni configuración contable.
    -- ---------------------------------------------------------------------------------
    IF EXISTS (SELECT 1 FROM plan_cuentas WHERE id = 3255 AND id_empresa = 8 AND eliminado = false AND codigo = '2.1.05') THEN
        IF EXISTS (SELECT 1 FROM plan_cuentas WHERE id_empresa = 8 AND eliminado = false AND codigo LIKE '2.1.05.%')
           OR EXISTS (SELECT 1 FROM asientos_contables_detalle WHERE id_cuenta_contable = 3255 AND eliminado = false)
           OR EXISTS (SELECT 1 FROM asientos_programados WHERE id_cuenta = 3255 AND eliminado = false) THEN
            RAISE NOTICE 'Empresa 8: 2.1.05 (id 3255) NO se eliminó: aún tiene hijas, movimientos o configuración';
        ELSE
            UPDATE plan_cuentas SET eliminado = true, deleted_at = now(), updated_at = now() WHERE id = 3255;
            INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                                     datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
            VALUES (NULL, 8, 'CORREGIR CODIGO NIVEL', 'plan_cuentas', 3255,
                    jsonb_build_object('codigo', '2.1.05', 'nivel', '3', 'eliminado', false),
                    jsonb_build_object('eliminado', true, 'motivo', 'duplicado de 2.1.5 (id 3265)'),
                    'sql', 'Corrección de formato del plan de cuentas 2026-09-29', now());
            RAISE NOTICE 'Empresa 8: eliminada lógicamente 2.1.05 (id 3255)';
        END IF;
    END IF;

    -- Aviso: 2.1.2.02 (empresa 112) pasó de nivel 5 a grupo (nivel 4). Si estaba configurada
    -- como cuenta de un asiento, esa configuración debería apuntar a su hija 2.1.2.02.001.
    IF EXISTS (SELECT 1 FROM asientos_programados WHERE id_cuenta = 10362 AND eliminado = false) THEN
        RAISE NOTICE 'Empresa 112: 2.1.2.02 (id 10362) está en Configuración Contable; reasígnela a 2.1.2.02.001';
    END IF;
END $$;

-- =====================================================================================
-- COMPROBACIÓN (ejecutar después):
--   a) Volver a correr 2026-09-29_plan_cuentas_diagnostico_codigos_niveles.sql -> 0 filas.
--   b) Cuentas activas cuyo padre inmediato no existe (deben salir 0 filas; si sale alguna,
--      pulsar "Reparar Jerarquía" en el plan de cuentas de esa empresa):
-- SELECT h.id_empresa, h.id, h.codigo, h.nombre
--   FROM plan_cuentas h
--  WHERE h.eliminado = false AND h.codigo LIKE '%.%'
--    AND NOT EXISTS (SELECT 1 FROM plan_cuentas p
--                     WHERE p.id_empresa = h.id_empresa AND p.eliminado = false
--                       AND p.codigo = regexp_replace(h.codigo, '\.[^.]*$', ''))
--    AND h.id_empresa IN (8, 23, 33, 34, 94, 106, 112, 113)
--  ORDER BY 1, 3;
--   c) Lo aplicado:
-- SELECT id_empresa, id_registro, datos_anteriores, datos_nuevos
--   FROM log_sistema WHERE accion = 'CORREGIR CODIGO NIVEL' ORDER BY id_empresa, id_registro;
-- =====================================================================================
