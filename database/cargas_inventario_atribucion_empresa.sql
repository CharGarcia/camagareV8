-- ============================================================================
-- Cargas de Inventario: reasignar "Creado por" y "Aprobado por" de UNA empresa
-- (modulos/cargas-inventario)
-- ----------------------------------------------------------------------------
-- Qué hace:
--   En las cargas de inventario de la empresa con RUC 1792708389001 deja:
--     · "Creado por"   = el usuario con cédula 1711463271
--     · "Aprobado por" = el usuario con cédula 1707597280 (solo en las cargas
--       que están APROBADAS; una carga pendiente o rechazada no tiene aprobador
--       y ponerle uno la dejaría incoherente).
--
--   TOCA DATOS (columnas `created_by` y `aprobada_por` de `inventario_cargas`).
--   No borra nada, no toca el kardex ni el stock, no cambia estados ni montos:
--   solo la atribución que se ve en esas dos columnas del listado.
--
--   Antes de escribir guarda en `log_sistema` el valor ANTERIOR de cada fila
--   (id, created_by, aprobada_por), de modo que el cambio sea reversible y
--   quede auditado (regla §7 del sistema).
--
--   Se aplica SOLO a esa empresa: ninguna otra empresa se ve afectada.
--   Las cargas FUTURAS siguen registrando al usuario real que las crea y
--   aprueba; esto corrige el histórico, no cambia el comportamiento del módulo.
--
-- Seguridad del script:
--   · Si el RUC no existe, o hay MÁS DE UNA empresa activa con ese RUC, aborta
--     sin escribir nada (y dice cuáles son, para elegir).
--   · Si alguna de las dos cédulas no existe, o está repetida en `usuarios`,
--     aborta sin escribir nada.
--   · Es idempotente: reejecutarlo no vuelve a cambiar nada (las filas ya
--     quedan con el valor destino) y no duplica el registro de auditoría útil.
--
-- Reversible: sí. Ver el bloque "CÓMO REVERTIR" al final.
--
-- ----------------------------------------------------------------------------
-- CÓMO EJECUTARLO EN pgAdmin
-- ----------------------------------------------------------------------------
--   Abrir la base en pgAdmin -> clic derecho -> Query Tool -> pegar TODO este
--   archivo -> ejecutar (F5). Se ejecuta de una sola vez.
--   Al terminar, la pestaña "Messages" muestra cuántas filas cambiaron.
-- ============================================================================

DO $$
DECLARE
    -- ── Parámetros ──────────────────────────────────────────────────────────
    v_ruc              text := '1792708389001';  -- empresa a corregir
    v_cedula_creado    text := '1711463271';     -- usuario para "Creado por"
    v_cedula_aprobado  text := '1707597280';     -- usuario para "Aprobado por"
    -- true  = "Aprobado por" solo en las cargas con estado 'aprobada' (recomendado).
    -- false = también en pendientes/rechazadas (no recomendado: quedan incoherentes).
    v_solo_aprobadas   boolean := true;

    v_id_empresa       integer;
    v_id_creado        integer;
    v_id_aprobado      integer;
    v_n                integer;
    v_lista            text;
    v_antes            jsonb;
    v_upd_creado       integer := 0;
    v_upd_aprobado     integer := 0;
BEGIN
    -- ── 1. Resolver la empresa por RUC ──────────────────────────────────────
    SELECT count(*), string_agg(id::text || ' = ' || nombre, ' | ' ORDER BY id)
      INTO v_n, v_lista
      FROM empresas
     WHERE ruc = v_ruc AND eliminado = false;

    IF v_n = 0 THEN
        RAISE EXCEPTION 'No existe ninguna empresa activa con RUC %. No se cambió nada.', v_ruc;
    ELSIF v_n > 1 THEN
        RAISE EXCEPTION 'Hay % empresas activas con RUC %: %. Fije el id a mano en el script (v_id_empresa) y vuelva a ejecutarlo. No se cambió nada.', v_n, v_ruc, v_lista;
    END IF;

    SELECT id INTO v_id_empresa FROM empresas WHERE ruc = v_ruc AND eliminado = false;

    -- ── 2. Resolver los dos usuarios por cédula ─────────────────────────────
    SELECT count(*) INTO v_n FROM usuarios WHERE cedula = v_cedula_creado AND eliminado = false;
    IF v_n <> 1 THEN
        RAISE EXCEPTION 'Se esperaba 1 usuario activo con cédula % y hay %. No se cambió nada.', v_cedula_creado, v_n;
    END IF;
    SELECT id INTO v_id_creado FROM usuarios WHERE cedula = v_cedula_creado AND eliminado = false;

    SELECT count(*) INTO v_n FROM usuarios WHERE cedula = v_cedula_aprobado AND eliminado = false;
    IF v_n <> 1 THEN
        RAISE EXCEPTION 'Se esperaba 1 usuario activo con cédula % y hay %. No se cambió nada.', v_cedula_aprobado, v_n;
    END IF;
    SELECT id INTO v_id_aprobado FROM usuarios WHERE cedula = v_cedula_aprobado AND eliminado = false;

    RAISE NOTICE 'Empresa id=% (RUC %) | Creado por -> usuario id=% (cédula %) | Aprobado por -> usuario id=% (cédula %)',
        v_id_empresa, v_ruc, v_id_creado, v_cedula_creado, v_id_aprobado, v_cedula_aprobado;

    -- ── 3. Foto del ANTES (para poder revertir) ─────────────────────────────
    SELECT jsonb_agg(jsonb_build_object('id', id, 'created_by', created_by, 'aprobada_por', aprobada_por) ORDER BY id)
      INTO v_antes
      FROM inventario_cargas
     WHERE id_empresa = v_id_empresa
       AND eliminado = false
       AND (created_by IS DISTINCT FROM v_id_creado
            OR (estado = 'aprobada' AND aprobada_por IS DISTINCT FROM v_id_aprobado)
            OR (NOT v_solo_aprobadas AND aprobada_por IS DISTINCT FROM v_id_aprobado));

    IF v_antes IS NULL THEN
        RAISE NOTICE 'No hay cargas de inventario que corregir en esta empresa (ya están como se pidió). No se cambió nada.';
        RETURN;
    END IF;

    -- ── 4. "Creado por" ─────────────────────────────────────────────────────
    UPDATE inventario_cargas
       SET created_by = v_id_creado
     WHERE id_empresa = v_id_empresa
       AND eliminado = false
       AND created_by IS DISTINCT FROM v_id_creado;
    GET DIAGNOSTICS v_upd_creado = ROW_COUNT;

    -- ── 5. "Aprobado por" ───────────────────────────────────────────────────
    UPDATE inventario_cargas
       SET aprobada_por = v_id_aprobado
     WHERE id_empresa = v_id_empresa
       AND eliminado = false
       AND (NOT v_solo_aprobadas OR estado = 'aprobada')
       AND aprobada_por IS DISTINCT FROM v_id_aprobado;
    GET DIAGNOSTICS v_upd_aprobado = ROW_COUNT;

    -- ── 6. Auditoría ────────────────────────────────────────────────────────
    INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                             datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
    VALUES (NULL, v_id_empresa, 'ajuste_manual_atribucion', 'inventario_cargas', NULL,
            jsonb_build_object('filas', v_antes),
            jsonb_build_object('created_by', v_id_creado, 'aprobada_por', v_id_aprobado,
                               'solo_aprobadas', v_solo_aprobadas,
                               'filas_creado_por', v_upd_creado, 'filas_aprobado_por', v_upd_aprobado),
            'SQL manual', 'database/cargas_inventario_atribucion_empresa.sql', CURRENT_TIMESTAMP);

    RAISE NOTICE 'Listo: % fila(s) con nuevo "Creado por" y % fila(s) con nuevo "Aprobado por".',
        v_upd_creado, v_upd_aprobado;
END
$$;

-- ============================================================================
-- COMPROBACIÓN (opcional) — quitar los "--" y ejecutar solo este SELECT.
-- Deben salir todas las cargas de la empresa con los dos nombres esperados.
-- ============================================================================
-- SELECT c.numero, c.fecha, c.estado,
--        u.nombre  AS creado_por,  u.cedula  AS cedula_creado,
--        ua.nombre AS aprobado_por, ua.cedula AS cedula_aprobado
--   FROM inventario_cargas c
--   JOIN empresas e       ON e.id = c.id_empresa
--   LEFT JOIN usuarios u  ON u.id = c.created_by
--   LEFT JOIN usuarios ua ON ua.id = c.aprobada_por
--  WHERE e.ruc = '1792708389001' AND c.eliminado = false
--  ORDER BY c.numero DESC;

-- ============================================================================
-- CÓMO REVERTIR — quitar los "--" y ejecutar solo este bloque.
-- Restaura los valores que guardó el registro de auditoría más reciente.
-- ============================================================================
-- OJO con el LIMIT: va en la subconsulta INTERNA (la que elige el registro de
-- auditoría). Si se pusiera junto a jsonb_array_elements(), PostgreSQL expande
-- el JSON ANTES de aplicar el LIMIT y se revertiría una sola fila en silencio.
-- UPDATE inventario_cargas c
--    SET created_by   = (f->>'created_by')::int,
--        aprobada_por = (f->>'aprobada_por')::int
--   FROM (
--        SELECT jsonb_array_elements(l.datos_anteriores->'filas') AS f
--          FROM (SELECT datos_anteriores
--                  FROM log_sistema
--                 WHERE accion = 'ajuste_manual_atribucion'
--                   AND tabla_afectada = 'inventario_cargas'
--                 ORDER BY id DESC
--                 LIMIT 1) l
--   ) s
--  WHERE c.id = (f->>'id')::int;
