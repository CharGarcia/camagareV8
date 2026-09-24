-- =============================================================================
-- ASAMED (1792708389001): retornos de consignación MIGRADOS que devuelven más unidades de las que
-- salieron con su consignación (datos del sistema anterior). Dejan la línea de la consignación en
-- negativo en Reporte de inventarios › Consignaciones, PDF y Excel.
--
--   Retorno 001-101-000020464  KCSU 04     NUP 3670    línea consig. 210535  2 → 1
--   Retorno 001-101-000020562  MAMA 1725I  NUP 113686  línea consig. 212039  4 → 1
--   Retorno 001-101-000020995  PRH 20      NUP 282990  línea consig. 216738  4 → 1
--   Retorno 001-101-000021205  MAMA 3025I  NUP 96037   línea consig. 219198  3 → 1
--   Retorno 001-101-000012782  KCSW 02     NUP 24778   línea consig. 133207  duplicado de 12781 → línea anulada
--   Retorno 001-101-000012993  MAMA 3025I  NUP 44517   línea consig. 135489  línea repetida → una anulada
--
-- Qué hace: corrige la CANTIDAD de esas líneas de retorno (subtotal, IVA y total en proporción) o
-- las marca eliminadas (eliminación lógica), y descuenta la diferencia de los totales de la cabecera.
-- Registra cada cambio en log_sistema (acción CORRECCION_MIGRACION).
--
-- Qué NO hace: NO toca inventario_kardex ni productos_bodegas. El kardex conserva las entradas que
-- registró el sistema anterior (+11 u. en CENTRAL 1); la diferencia contra el stock físico se corrige
-- aparte con un AJUSTE DE INVENTARIO desde el módulo, después del conteo. Tampoco toca asientos: los
-- retornos migrados no se contabilizan (el PASO 1 muestra si alguno tiene asiento, por si acaso).
--
-- Seguridad: cada línea se identifica por retorno + producto + NUP + línea de consignación + cantidad
-- actual esperada. Si algo no coincide (ya se corrigió, o cambió), esa línea se salta con un aviso.
-- Idempotente. Respaldo en la tabla PERMANENTE respaldo_correccion_retornos_20260923 (línea y
-- cabecera antes del cambio); el bloque REVERTIR del final lo restaura.
--
-- Uso en pgAdmin (MISMA ventana):
--   PASO 1  seleccionar desde DROP TABLE hasta el SELECT final y F5 (solo lectura). Deben salir 6
--           filas con estado_linea = 'OK'.
--   PASO 2  el bloque DO, F5.
--   Verificación al final (comentada).
-- =============================================================================

-- ---------------------------------------------------------------------------
-- PASO 1 · VISTA PREVIA (solo lectura). Deja _cmg_corr_ret.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS _cmg_corr_ret;
CREATE TEMP TABLE _cmg_corr_ret AS
WITH emp AS (SELECT id FROM empresas WHERE ruc = '1792708389001'),
obj (secuencial, producto, nup, linea_consig, cant_actual, accion, cant_nueva) AS (
    VALUES ('20464', 'KCSU 04',    '3670',   210535, 2::numeric, 'REDUCIR', 1::numeric),
           ('20562', 'MAMA 1725I', '113686', 212039, 4,          'REDUCIR', 1),
           ('20995', 'PRH 20',     '282990', 216738, 4,          'REDUCIR', 1),
           ('21205', 'MAMA 3025I', '96037',  219198, 3,          'REDUCIR', 1),
           ('12782', 'KCSW 02',    '24778',  133207, 1,          'ANULAR',  0),
           ('12993', 'MAMA 3025I', '44517',  135489, 1,          'ANULAR',  0)
),
cand AS (
    SELECT obj.*, r.id AS id_retorno, r.id_empresa, r.serie || '-' || r.secuencial AS retorno,
           r.id_asiento_contable, d.id AS id_linea, d.cantidad,
           COUNT(*) OVER (PARTITION BY obj.secuencial, obj.producto, obj.nup) AS coincidencias,
           ROW_NUMBER() OVER (PARTITION BY obj.secuencial, obj.producto, obj.nup ORDER BY d.id DESC) AS rn
      FROM obj
      JOIN retornos_cv r ON r.id_empresa IN (SELECT id FROM emp) AND r.eliminado = false AND r.estado = 'Emitida'
                        AND r.serie = '001-101' AND regexp_replace(r.secuencial, '^0+', '') = obj.secuencial
      JOIN retornos_cv_detalles d ON d.id_retorno = r.id AND d.eliminado = false
                                 AND d.id_consignacion_detalle = obj.linea_consig
                                 AND TRIM(d.nup) = obj.nup AND d.cantidad = obj.cant_actual
      JOIN productos pr ON pr.id = d.id_producto AND pr.codigo = obj.producto
)
SELECT obj.secuencial, obj.producto, obj.nup, obj.linea_consig, obj.accion, obj.cant_actual, obj.cant_nueva,
       c.id_retorno, c.id_empresa, c.retorno, c.id_linea, c.id_asiento_contable, c.coincidencias,
       CASE WHEN c.id_linea IS NULL THEN 'NO ENCONTRADA (¿ya corregida?)'
            WHEN obj.secuencial = '12993' AND c.coincidencias <> 2 THEN 'REVISAR: se esperaban 2 líneas repetidas'
            WHEN obj.secuencial <> '12993' AND c.coincidencias <> 1 THEN 'REVISAR: más de una línea coincide'
            ELSE 'OK' END AS estado_linea
  FROM obj
  LEFT JOIN cand c ON c.secuencial = obj.secuencial AND c.producto = obj.producto AND c.nup = obj.nup AND c.rn = 1;

SELECT secuencial AS retorno, producto, nup, accion, cant_actual, cant_nueva, id_linea,
       id_asiento_contable, estado_linea
  FROM _cmg_corr_ret ORDER BY secuencial;

-- ---------------------------------------------------------------------------
-- PASO 2 · RESPALDO + CORRECCIÓN (DO block). Requiere el PASO 1 en esta misma ventana.
-- ---------------------------------------------------------------------------
DO $$
DECLARE
  v_user   CONSTANT int := 2;          -- usuario que queda en auditoría (deleted_by / log_sistema)
  x        RECORD;
  v_lin    retornos_cv_detalles%ROWTYPE;
  v_cab    retornos_cv%ROWTYPE;
  v_factor numeric;
  d_sub    numeric;
  d_iva    numeric;
  d_tot    numeric;
  v_nota   text;
  v_hechas int := 0;
BEGIN
  IF to_regclass('pg_temp._cmg_corr_ret') IS NULL THEN
    RAISE EXCEPTION 'Corra primero el PASO 1 en esta misma ventana: falta la tabla temporal _cmg_corr_ret.';
  END IF;

  CREATE TABLE IF NOT EXISTS respaldo_correccion_retornos_20260923 (
      id_linea       bigint    PRIMARY KEY,
      id_retorno     bigint    NOT NULL,
      accion         varchar   NOT NULL,
      linea_antes    jsonb     NOT NULL,
      cabecera_antes jsonb     NOT NULL,
      respaldado_at  timestamp NOT NULL DEFAULT now()
  );

  FOR x IN SELECT * FROM _cmg_corr_ret ORDER BY secuencial LOOP
    IF x.estado_linea <> 'OK' THEN
      RAISE NOTICE 'Retorno % / % / NUP %: % — no se toca.', x.secuencial, x.producto, x.nup, x.estado_linea;
      CONTINUE;
    END IF;

    SELECT * INTO v_lin FROM retornos_cv_detalles WHERE id = x.id_linea FOR UPDATE;
    SELECT * INTO v_cab FROM retornos_cv WHERE id = x.id_retorno FOR UPDATE;
    -- sigue como en la vista previa (nadie la cambió entre el PASO 1 y el 2)
    IF v_lin.eliminado OR v_lin.cantidad <> x.cant_actual THEN
      RAISE NOTICE 'Línea % cambió desde el PASO 1 — no se toca.', x.id_linea;
      CONTINUE;
    END IF;
    IF x.id_asiento_contable IS NOT NULL THEN
      RAISE NOTICE 'Aviso: el retorno % tiene asiento % — no se modifica el asiento; revisarlo aparte.', x.retorno, x.id_asiento_contable;
    END IF;

    INSERT INTO respaldo_correccion_retornos_20260923 (id_linea, id_retorno, accion, linea_antes, cabecera_antes)
    VALUES (v_lin.id, v_cab.id, x.accion, to_jsonb(v_lin), to_jsonb(v_cab))
    ON CONFLICT (id_linea) DO NOTHING;

    v_factor := CASE WHEN x.accion = 'ANULAR' THEN 0 ELSE x.cant_nueva / v_lin.cantidad END;
    d_sub := COALESCE(v_lin.subtotal, 0)       * (1 - v_factor);
    d_iva := COALESCE(v_lin.valor_impuesto, 0) * (1 - v_factor);
    d_tot := COALESCE(v_lin.total, 0)          * (1 - v_factor);

    IF x.accion = 'ANULAR' THEN
      UPDATE retornos_cv_detalles
         SET eliminado = true, deleted_at = now(), deleted_by = v_user, updated_at = now()
       WHERE id = v_lin.id;
      v_nota := format('Corrección migración 23-09-2026: línea %s (%s NUP %s) anulada por duplicada.', v_lin.id, x.producto, x.nup);
    ELSE
      UPDATE retornos_cv_detalles
         SET cantidad = x.cant_nueva,
             subtotal = COALESCE(subtotal, 0) * v_factor,
             valor_impuesto = COALESCE(valor_impuesto, 0) * v_factor,
             total = COALESCE(total, 0) * v_factor,
             updated_at = now()
       WHERE id = v_lin.id;
      v_nota := format('Corrección migración 23-09-2026: línea %s (%s NUP %s) de %s a %s unidades (salió %s con la consignación).',
                       v_lin.id, x.producto, x.nup, trim_scale(v_lin.cantidad), trim_scale(x.cant_nueva), trim_scale(x.cant_nueva));
    END IF;

    UPDATE retornos_cv
       SET subtotal = COALESCE(subtotal, 0) - d_sub,
           impuesto = COALESCE(impuesto, 0) - d_iva,
           total    = COALESCE(total, 0) - d_tot,
           observaciones = CONCAT_WS(E'\n', NULLIF(observaciones, ''), v_nota),
           updated_at = now(), updated_by = v_user
     WHERE id = v_cab.id;

    INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                             datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
    SELECT v_user, x.id_empresa, 'CORRECCION_MIGRACION', 'retornos_cv_detalles', v_lin.id,
           to_jsonb(v_lin), to_jsonb(n), 'pgAdmin', 'database/20260923_corregir_retornos_migrados_cantidad_de_mas.sql', now()
      FROM retornos_cv_detalles n WHERE n.id = v_lin.id;

    v_hechas := v_hechas + 1;
    RAISE NOTICE '%', v_nota;
  END LOOP;
  RAISE NOTICE 'Líneas corregidas: % de 6.', v_hechas;
END $$;

-- ---------------------------------------------------------------------------
-- VERIFICACIÓN: las 6 líneas de consignación deben quedar con saldo 0.
-- ---------------------------------------------------------------------------
-- SELECT cvd.id, cvd.nup, cvd.cantidad,
--        COALESCE((SELECT SUM(d.cantidad) FROM retornos_cv_detalles d
--                    JOIN retornos_cv r ON r.id = d.id_retorno AND r.eliminado = false AND r.estado = 'Emitida'
--                   WHERE d.id_consignacion_detalle = cvd.id AND d.eliminado = false), 0) AS retornado
--   FROM consignaciones_ventas_detalles cvd
--  WHERE cvd.id IN (210535, 212039, 216738, 219198, 133207, 135489);

-- ---------------------------------------------------------------------------
-- REVERTIR (solo si hiciera falta): restaura línea y cabecera tal como estaban.
-- ---------------------------------------------------------------------------
-- UPDATE retornos_cv_detalles d
--    SET cantidad = (r.linea_antes->>'cantidad')::numeric,
--        subtotal = (r.linea_antes->>'subtotal')::numeric,
--        valor_impuesto = (r.linea_antes->>'valor_impuesto')::numeric,
--        total = (r.linea_antes->>'total')::numeric,
--        eliminado = (r.linea_antes->>'eliminado')::boolean,
--        deleted_at = (r.linea_antes->>'deleted_at')::timestamp,
--        deleted_by = (r.linea_antes->>'deleted_by')::int,
--        updated_at = now()
--   FROM respaldo_correccion_retornos_20260923 r
--  WHERE d.id = r.id_linea;
-- UPDATE retornos_cv c
--    SET subtotal = (x.cabecera_antes->>'subtotal')::numeric,
--        impuesto = (x.cabecera_antes->>'impuesto')::numeric,
--        total = (x.cabecera_antes->>'total')::numeric,
--        observaciones = x.cabecera_antes->>'observaciones',
--        updated_at = now()
--   FROM (SELECT DISTINCT ON (id_retorno) id_retorno, cabecera_antes
--           FROM respaldo_correccion_retornos_20260923 ORDER BY id_retorno, respaldado_at) x
--  WHERE c.id = x.id_retorno;
