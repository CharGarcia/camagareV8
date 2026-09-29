-- =====================================================================================
-- Plan de cuentas: CORRECCIÓN GENERAL DE NIVELES (todas las empresas)
-- -------------------------------------------------------------------------------------
-- Regla: nivel = número de segmentos del código (1 · 1.1 · 1.1.1 · 1.1.1.01 · 1.1.1.01.001).
--
-- Qué corrige, por cada cuenta activa cuyo nivel no coincide con su código:
--   A) GRUPO o código correcto  -> se ajusta solo el NIVEL al número de segmentos.
--   B) CUENTA DE MOVIMIENTO con código corto: tiene nivel guardado MAYOR que sus segmentos,
--      NO tiene cuentas hijas y su código tiene 4 segmentos (ej. 2.1.7.01 guardada como nivel 5).
--      -> la cuenta pasa a  código + '.001'  con nivel 5 (conserva su id: asientos y
--         configuración siguen apuntando a ella) y se CREA su padre de nivel 4 con el
--         código anterior y el mismo nombre en MAYÚSCULAS.
--   Solo se AVISAN (pestaña Mensajes), sin tocar, los casos que requieren decisión:
--      - cuentas con más de 5 niveles;
--      - cuentas de movimiento con código de 1 a 3 segmentos (faltan dos o más niveles).
--
-- NO corrige los dígitos del código (1.1.01 -> 1.1.1): eso lo hace
-- 2026-09-29_plan_cuentas_correccion_codigos_niveles.sql.
--
-- ¿Toca datos?  SÍ: UPDATE de nivel (y de código en el caso B) en plan_cuentas, e INSERT de
--               los padres del caso B. Todo queda en log_sistema (accion = 'CORREGIR NIVEL').
-- Idempotente:  sí; la segunda ejecución no hace nada.
-- Atómico:      un solo bloque DO; si algo falla no se aplica nada.
-- Reversible:   con log_sistema (datos_anteriores guarda código y nivel previos; las cuentas
--               creadas llevan 'creada': true en datos_nuevos y se eliminan lógicamente).
--
-- PASO 1 (opcional): ejecutar solo la VISTA PREVIA de abajo para ver qué va a cambiar.
-- PASO 2: ejecutar el bloque DO. Revisar la pestaña "Mensajes".
-- =====================================================================================

-- ---------------------------------------------------------------- VISTA PREVIA (solo lectura)
-- SELECT c.id_empresa, c.id, c.codigo, c.nivel AS nivel_actual, c.nombre,
--        array_length(string_to_array(c.codigo, '.'), 1) AS segmentos,
--        CASE
--          WHEN array_length(string_to_array(c.codigo, '.'), 1) > 5 THEN 'AVISO: más de 5 niveles'
--          WHEN c.nivel ~ '^[0-9]+$' AND c.nivel::int > array_length(string_to_array(c.codigo, '.'), 1)
--           AND NOT EXISTS (SELECT 1 FROM plan_cuentas h WHERE h.id_empresa = c.id_empresa
--                             AND h.eliminado = false AND h.codigo LIKE c.codigo || '.%')
--          THEN CASE WHEN array_length(string_to_array(c.codigo, '.'), 1) = 4
--                    THEN 'B: pasa a ' || c.codigo || '.001 nivel 5 y se crea el padre ' || c.codigo
--                    ELSE 'AVISO: cuenta de movimiento con código corto (revisar a mano)' END
--          ELSE 'A: nivel ' || array_length(string_to_array(c.codigo, '.'), 1)
--        END AS accion
--   FROM plan_cuentas c
--  WHERE c.eliminado = false
--    AND c.nivel IS DISTINCT FROM array_length(string_to_array(c.codigo, '.'), 1)::text
--  ORDER BY c.id_empresa, c.codigo;

-- ---------------------------------------------------------------- CORRECCIÓN
DO $$
DECLARE
    r        record;
    v_segs   int;
    v_nivel  int;
    v_id     int;
    v_a      int := 0;
    v_b      int := 0;
    v_aviso  int := 0;
BEGIN
    FOR r IN
        SELECT c.id, c.id_empresa, c.id_usuario, c.created_by, c.codigo, c.nivel, c.nombre
          FROM plan_cuentas c
         WHERE c.eliminado = false
           AND c.nivel IS DISTINCT FROM array_length(string_to_array(c.codigo, '.'), 1)::text
         ORDER BY c.id_empresa, c.codigo
    LOOP
        v_segs  := array_length(string_to_array(r.codigo, '.'), 1);
        v_nivel := CASE WHEN r.nivel ~ '^[0-9]+$' THEN r.nivel::int END;

        -- Más de 5 niveles: requiere decisión (renumerar / reubicar)
        IF v_segs > 5 THEN
            RAISE NOTICE 'AVISO empresa % id % (%): más de 5 niveles, no se tocó', r.id_empresa, r.id, r.codigo;
            v_aviso := v_aviso + 1;
            CONTINUE;
        END IF;

        -- ¿Cuenta de movimiento con código corto? (nivel guardado mayor y sin hijas)
        IF v_nivel IS NOT NULL AND v_nivel > v_segs
           AND NOT EXISTS (SELECT 1 FROM plan_cuentas h
                            WHERE h.id_empresa = r.id_empresa AND h.eliminado = false
                              AND h.codigo LIKE r.codigo || '.%') THEN

            IF v_segs <> 4 THEN
                RAISE NOTICE 'AVISO empresa % id % (%, nivel %): cuenta de movimiento con código corto, revisar a mano',
                             r.id_empresa, r.id, r.codigo, r.nivel;
                v_aviso := v_aviso + 1;
                CONTINUE;
            END IF;

            IF EXISTS (SELECT 1 FROM plan_cuentas o
                        WHERE o.id_empresa = r.id_empresa AND o.codigo = r.codigo || '.001') THEN
                RAISE NOTICE 'AVISO empresa % id % (%): el código %.001 ya existe (quizá eliminado), revisar a mano',
                             r.id_empresa, r.id, r.codigo, r.codigo;
                v_aviso := v_aviso + 1;
                CONTINUE;
            END IF;

            -- B.1) La cuenta pasa a nivel 5 conservando su id
            UPDATE plan_cuentas
               SET codigo = r.codigo || '.001', nivel = '5', updated_at = now()
             WHERE id = r.id;
            INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                                     datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
            VALUES (NULL, r.id_empresa, 'CORREGIR NIVEL', 'plan_cuentas', r.id,
                    jsonb_build_object('codigo', r.codigo, 'nivel', r.nivel),
                    jsonb_build_object('codigo', r.codigo || '.001', 'nivel', '5'),
                    'sql', 'Corrección general de niveles 2026-09-29', now());

            -- B.2) Se crea su padre de nivel 4 con el código anterior
            INSERT INTO plan_cuentas (id_empresa, id_usuario, codigo, nivel, nombre, status, eliminado, created_by, created_at)
            VALUES (r.id_empresa, r.id_usuario, r.codigo, '4', upper(r.nombre), 1, false, r.created_by, now())
            RETURNING id INTO v_id;
            INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                                     datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
            VALUES (NULL, r.id_empresa, 'CORREGIR NIVEL', 'plan_cuentas', v_id, NULL,
                    jsonb_build_object('codigo', r.codigo, 'nivel', '4', 'nombre', upper(r.nombre), 'creada', true),
                    'sql', 'Corrección general de niveles 2026-09-29', now());

            RAISE NOTICE 'B empresa % id %: % -> %.001 (nivel 5); creado padre % (nivel 4, id %)',
                         r.id_empresa, r.id, r.codigo, r.codigo, r.codigo, v_id;
            v_b := v_b + 1;
            CONTINUE;
        END IF;

        -- A) Grupo o código correcto: solo el nivel
        UPDATE plan_cuentas SET nivel = v_segs::text, updated_at = now() WHERE id = r.id;
        INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                                 datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
        VALUES (NULL, r.id_empresa, 'CORREGIR NIVEL', 'plan_cuentas', r.id,
                jsonb_build_object('codigo', r.codigo, 'nivel', r.nivel),
                jsonb_build_object('codigo', r.codigo, 'nivel', v_segs::text),
                'sql', 'Corrección general de niveles 2026-09-29', now());
        v_a := v_a + 1;
    END LOOP;

    RAISE NOTICE 'Resumen: % niveles ajustados (A), % cuentas de movimiento reubicadas con padre creado (B), % avisos sin tocar',
                 v_a, v_b, v_aviso;
END $$;

-- =====================================================================================
-- COMPROBACIÓN (debe devolver solo las cuentas que salieron como AVISO):
-- SELECT id_empresa, id, codigo, nivel, nombre
--   FROM plan_cuentas
--  WHERE eliminado = false
--    AND nivel IS DISTINCT FROM array_length(string_to_array(codigo, '.'), 1)::text
--  ORDER BY id_empresa, codigo;
--
-- Lo aplicado:
-- SELECT id_empresa, id_registro, datos_anteriores, datos_nuevos, created_at
--   FROM log_sistema WHERE accion = 'CORREGIR NIVEL' ORDER BY id_empresa, id_registro;
--
-- REVERTIR (si hiciera falta), en este orden:
-- 1) eliminar lógicamente los padres creados
-- UPDATE plan_cuentas p SET eliminado = true, deleted_at = now()
--   FROM log_sistema l
--  WHERE l.accion = 'CORREGIR NIVEL' AND l.id_registro = p.id AND (l.datos_nuevos->>'creada')::boolean;
-- 2) devolver código y nivel anteriores
-- UPDATE plan_cuentas p SET codigo = l.datos_anteriores->>'codigo', nivel = l.datos_anteriores->>'nivel', updated_at = now()
--   FROM log_sistema l
--  WHERE l.accion = 'CORREGIR NIVEL' AND l.id_registro = p.id AND l.datos_anteriores IS NOT NULL;
-- =====================================================================================
