-- =============================================================================
-- ASAMED (1792708389001): facturaciones de consignación (Facturación CV) MIGRADAS que dejan líneas
-- de consignación en negativo en Reporte de inventarios › Consignaciones, PDF y Excel.
--
--   Consignación 001-101-000004176 (EUCLAMU 4503-H) — se facturó dos veces:
--     · Facturación 001-101-000000138 (factura 975, COC SOCIEDAD, 05-06-2020) = venta real. Su línea
--       del lote S090266790 quedó en la línea 2153 (lote S060257318) → pasa a la 2154 (REENLAZAR).
--     · Facturación 001-101-000015067 (factura 13563 de ASAMED a sí misma, 06-12-2023, anulada con la
--       NC 1239) = cierre masivo de ~70 consignaciones viejas. Solo sus 2 líneas de la 4176 duplican la
--       venta real → se anulan ESAS 2 LÍNEAS (ANULAR_LINEA). El resto del documento no se toca: si se
--       anulara entero, ~180 unidades de otras consignaciones volverían a figurar en poder del cliente.
--   Consignación 001-101-000049572 — facturas 29137 y 29524 anuladas con las NC 2500 y 2560 (sin
--     movimiento de stock); la unidad se devolvió después con el retorno 21964:
--     · Facturación 001-101-000034460 y 001-101-000034461 → documento 'anulada' (ANULAR_DOC), igual
--       que hace el sistema al anular la factura de origen (reversarPorFactura).
--   Consignación 001-101-000033999 — Facturación 001-101-000022205, CMSW 3885 NUP 58606: 4 → 1.
--   Consignación 001-101-000050707 — Facturación 001-101-000035630, ILCM 3511N NUP 105418: 2 → 1.
--     (Sus facturas de venta 19262 / 25290 no contienen esas unidades: el número de factura del sistema
--      anterior está mal. Queda para contabilidad; aquí solo se ajusta la cantidad a lo consignado.)
--
-- NO se corrige aquí: consignación 48726 (factura 28904 sin nota de crédito) → decisión de contabilidad.
--
-- Qué toca: consignaciones_facturas_detalles (id_consignacion_detalle / cantidad e importes / eliminado)
-- y consignaciones_facturas (estado o totales + observaciones). NO toca kardex, stock, facturas de venta,
-- notas de crédito ni asientos (el PASO 1 muestra que ninguno tiene asiento de reingreso ni kardex propio).
-- Cada cambio queda en log_sistema (CORRECCION_MIGRACION). Respaldo en la tabla PERMANENTE
-- respaldo_correccion_facturaciones_cv_20260923; el bloque REVERTIR del final lo restaura.
-- Cada fila se identifica por facturación + producto + lote + NUP + línea de consignación + cantidad
-- actual esperada; si algo no coincide, se salta con un aviso. Idempotente.
--
-- Uso en pgAdmin (MISMA ventana): PASO 1 (desde DROP TABLE hasta el SELECT final, F5) → deben salir
-- 8 filas con estado = 'OK'. PASO 2: el bloque DO. Verificación al final.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- PASO 1 · VISTA PREVIA (solo lectura). Deja _cmg_corr_fac.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS _cmg_corr_fac;
CREATE TEMP TABLE _cmg_corr_fac AS
WITH emp AS (SELECT id FROM empresas WHERE ruc = '1792708389001'),
obj (orden, accion, fact_sec, producto, lote, nup, linea_consig, cant_actual, cant_nueva, linea_nueva) AS (
    VALUES (1, 'REENLAZAR',    '138',   'EUCLAMU 4503-H', 'S090266790', '2',      2153,   1::numeric, 1::numeric, 2154),
           (2, 'ANULAR_LINEA', '15067', 'EUCLAMU 4503-H', 'S060257318', '1',      2153,   3,          0,          NULL),
           (3, 'ANULAR_LINEA', '15067', 'EUCLAMU 4503-H', 'S090266790', '1',      2154,   1,          0,          NULL),
           (4, 'REDUCIR',      '22205', 'CMSW 3885',      'X050494747', '58606',  150823, 4,          1,          NULL),
           (5, 'REDUCIR',      '35630', 'ILCM 3511N',     'Y110586757', '105418', 225638, 2,          1,          NULL),
           (6, 'ANULAR_DOC',   '34460', 'PTM 4800-3',     'Z020601571', '625030', 220824, 1,          NULL,       NULL),
           (7, 'ANULAR_DOC',   '34461', 'MAMU 4855',      'Y060561024', '55488',  220822, 1,          NULL,       NULL),
           (8, 'ANULAR_DOC',   '34461', 'PTM 4800-3',     'Z020601571', '625174', 220823, 1,          NULL,       NULL)
),
cand AS (
    SELECT obj.orden, cf.id AS id_doc, cf.id_empresa, cf.serie || '-' || cf.secuencial AS facturacion,
           cf.estado AS estado_doc, cf.id_asiento_reingreso,
           (SELECT COUNT(*) FROM inventario_kardex k
             WHERE k.id_empresa = cf.id_empresa AND k.eliminado = false
               AND k.referencia_tipo = 'FACTURACION_CV' AND k.referencia_id = cf.id) AS movs_kardex,
           (SELECT COUNT(*) FROM consignaciones_facturas_detalles z
             WHERE z.id_consignacion_factura = cf.id AND COALESCE(z.eliminado, false) = false) AS lineas_doc,
           d.id AS id_linea,
           COUNT(*) OVER (PARTITION BY obj.orden) AS coincidencias
      FROM obj
      JOIN consignaciones_facturas cf ON cf.id_empresa IN (SELECT id FROM emp) AND cf.eliminado = false
                                     AND cf.serie = '001-101' AND regexp_replace(cf.secuencial, '^0+', '') = obj.fact_sec
      JOIN consignaciones_facturas_detalles d ON d.id_consignacion_factura = cf.id AND COALESCE(d.eliminado, false) = false
                                             AND d.id_consignacion_detalle = obj.linea_consig
                                             AND TRIM(d.lote) = obj.lote AND TRIM(d.nup) = obj.nup
                                             AND d.cantidad = obj.cant_actual
      JOIN productos pr ON pr.id = d.id_producto AND pr.codigo = obj.producto
)
SELECT obj.*, c.id_doc, c.id_empresa, c.facturacion, c.estado_doc, c.id_asiento_reingreso, c.movs_kardex,
       c.lineas_doc, c.id_linea,
       CASE WHEN c.id_linea IS NULL THEN 'NO ENCONTRADA (¿ya corregida?)'
            WHEN c.coincidencias <> 1 THEN 'REVISAR: más de una línea coincide'
            WHEN c.estado_doc <> 'facturada' THEN 'REVISAR: el documento no está facturada'
            WHEN c.id_asiento_reingreso IS NOT NULL OR c.movs_kardex > 0 THEN 'REVISAR: tiene asiento o kardex propio'
            WHEN obj.accion = 'ANULAR_DOC' AND obj.fact_sec = '34460' AND c.lineas_doc <> 1 THEN 'REVISAR: el documento tiene otras líneas'
            WHEN obj.accion = 'ANULAR_DOC' AND obj.fact_sec = '34461' AND c.lineas_doc <> 2 THEN 'REVISAR: el documento tiene otras líneas'
            ELSE 'OK' END AS estado
  FROM obj
  LEFT JOIN cand c ON c.orden = obj.orden;

SELECT orden, accion, fact_sec AS facturacion, producto, lote, nup, linea_consig, cant_actual, cant_nueva,
       linea_nueva, id_linea, lineas_doc, estado
  FROM _cmg_corr_fac ORDER BY orden;

-- ---------------------------------------------------------------------------
-- PASO 2 · RESPALDO + CORRECCIÓN (DO block). Requiere el PASO 1 en esta misma ventana.
-- ---------------------------------------------------------------------------
DO $$
DECLARE
  v_user   CONSTANT int := 2;          -- usuario que queda en auditoría
  x        RECORD;
  v_lin    consignaciones_facturas_detalles%ROWTYPE;
  v_cab    consignaciones_facturas%ROWTYPE;
  v_factor numeric;
  v_nota   text;
  v_hechas int := 0;
BEGIN
  IF to_regclass('pg_temp._cmg_corr_fac') IS NULL THEN
    RAISE EXCEPTION 'Corra primero el PASO 1 en esta misma ventana: falta la tabla temporal _cmg_corr_fac.';
  END IF;

  CREATE TABLE IF NOT EXISTS respaldo_correccion_facturaciones_cv_20260923 (
      tipo           varchar   NOT NULL,     -- 'linea' | 'documento'
      id_registro    bigint    NOT NULL,
      id_doc         bigint    NOT NULL,
      accion         varchar   NOT NULL,
      antes          jsonb     NOT NULL,
      cabecera_antes jsonb     NOT NULL,
      respaldado_at  timestamp NOT NULL DEFAULT now(),
      PRIMARY KEY (tipo, id_registro)
  );

  FOR x IN SELECT * FROM _cmg_corr_fac ORDER BY orden LOOP
    IF x.estado <> 'OK' THEN
      RAISE NOTICE 'Fila % (% % NUP %): % — no se toca.', x.orden, x.fact_sec, x.producto, x.nup, x.estado;
      CONTINUE;
    END IF;

    SELECT * INTO v_lin FROM consignaciones_facturas_detalles WHERE id = x.id_linea FOR UPDATE;
    SELECT * INTO v_cab FROM consignaciones_facturas WHERE id = x.id_doc FOR UPDATE;
    -- la 34461 tiene dos filas: la primera ya anuló el documento en esta misma corrida
    IF x.accion = 'ANULAR_DOC' AND v_cab.estado = 'anulada'
       AND EXISTS (SELECT 1 FROM respaldo_correccion_facturaciones_cv_20260923
                    WHERE tipo = 'documento' AND id_registro = v_cab.id) THEN
      v_hechas := v_hechas + 1;
      RAISE NOTICE 'Facturación % (línea %): documento ya anulado arriba.', v_cab.serie || '-' || v_cab.secuencial, v_lin.id;
      CONTINUE;
    END IF;
    IF COALESCE(v_lin.eliminado, false) OR v_lin.cantidad <> x.cant_actual
       OR v_lin.id_consignacion_detalle <> x.linea_consig OR v_cab.estado <> 'facturada' THEN
      RAISE NOTICE 'Fila %: cambió desde el PASO 1 — no se toca.', x.orden;
      CONTINUE;
    END IF;

    IF x.accion = 'ANULAR_DOC' THEN
      INSERT INTO respaldo_correccion_facturaciones_cv_20260923 (tipo, id_registro, id_doc, accion, antes, cabecera_antes)
      VALUES ('documento', v_cab.id, v_cab.id, x.accion, to_jsonb(v_cab), to_jsonb(v_cab))
      ON CONFLICT (tipo, id_registro) DO NOTHING;
      v_nota := format('Corrección migración 23-09-2026: anulada — la factura %s quedó anulada por nota de crédito y la unidad se devolvió con un retorno.', v_cab.numero_factura);
      UPDATE consignaciones_facturas
         SET estado = 'anulada', updated_at = now(), updated_by = v_user,
             observaciones = CONCAT_WS(E'\n', NULLIF(observaciones, ''), v_nota)
       WHERE id = v_cab.id AND estado = 'facturada';
      INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                               datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
      SELECT v_user, x.id_empresa, 'CORRECCION_MIGRACION', 'consignaciones_facturas', v_cab.id,
             to_jsonb(v_cab), to_jsonb(n), 'pgAdmin', 'database/20260923_corregir_facturaciones_cv_migradas.sql', now()
        FROM consignaciones_facturas n WHERE n.id = v_cab.id;
      v_hechas := v_hechas + 1;
      RAISE NOTICE 'Facturación % (línea %): %', v_cab.serie || '-' || v_cab.secuencial, v_lin.id, v_nota;
      CONTINUE;
    END IF;

    INSERT INTO respaldo_correccion_facturaciones_cv_20260923 (tipo, id_registro, id_doc, accion, antes, cabecera_antes)
    VALUES ('linea', v_lin.id, v_cab.id, x.accion, to_jsonb(v_lin), to_jsonb(v_cab))
    ON CONFLICT (tipo, id_registro) DO NOTHING;

    IF x.accion = 'REENLAZAR' THEN
      UPDATE consignaciones_facturas_detalles SET id_consignacion_detalle = x.linea_nueva
       WHERE id = v_lin.id AND id_consignacion_detalle = x.linea_consig;
      v_nota := format('Corrección migración 23-09-2026: línea %s (%s lote %s) re-enlazada de la línea de consignación %s a la %s (su lote).',
                       v_lin.id, x.producto, x.lote, x.linea_consig, x.linea_nueva);
    ELSE
      v_factor := CASE WHEN x.accion = 'ANULAR_LINEA' THEN 0 ELSE x.cant_nueva / v_lin.cantidad END;
      IF x.accion = 'ANULAR_LINEA' THEN
        UPDATE consignaciones_facturas_detalles
           SET eliminado = true, deleted_at = now(), deleted_by = v_user
         WHERE id = v_lin.id;
        v_nota := format('Corrección migración 23-09-2026: línea %s (%s lote %s, %s u.) anulada — esas unidades ya se habían facturado en la factura 975 (2020).',
                         v_lin.id, x.producto, x.lote, trim_scale(v_lin.cantidad));
      ELSE
        UPDATE consignaciones_facturas_detalles
           SET cantidad = x.cant_nueva,
               subtotal = COALESCE(subtotal, 0) * v_factor,
               valor_impuesto = COALESCE(valor_impuesto, 0) * v_factor,
               total = COALESCE(total, 0) * v_factor
         WHERE id = v_lin.id;
        v_nota := format('Corrección migración 23-09-2026: línea %s (%s NUP %s) de %s a %s unidades (lo consignado).',
                         v_lin.id, x.producto, x.nup, trim_scale(v_lin.cantidad), trim_scale(x.cant_nueva));
      END IF;
      UPDATE consignaciones_facturas
         SET subtotal = COALESCE(subtotal, 0) - COALESCE(v_lin.subtotal, 0)       * (1 - v_factor),
             impuesto = COALESCE(impuesto, 0) - COALESCE(v_lin.valor_impuesto, 0) * (1 - v_factor),
             total    = COALESCE(total, 0)    - COALESCE(v_lin.total, 0)          * (1 - v_factor)
       WHERE id = v_cab.id;
    END IF;

    UPDATE consignaciones_facturas
       SET observaciones = CONCAT_WS(E'\n', NULLIF(observaciones, ''), v_nota),
           updated_at = now(), updated_by = v_user
     WHERE id = v_cab.id;

    INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                             datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
    SELECT v_user, x.id_empresa, 'CORRECCION_MIGRACION', 'consignaciones_facturas_detalles', v_lin.id,
           to_jsonb(v_lin), to_jsonb(n), 'pgAdmin', 'database/20260923_corregir_facturaciones_cv_migradas.sql', now()
      FROM consignaciones_facturas_detalles n WHERE n.id = v_lin.id;

    v_hechas := v_hechas + 1;
    RAISE NOTICE '%', v_nota;
  END LOOP;
  RAISE NOTICE 'Filas corregidas: % de 8.', v_hechas;
END $$;

-- ---------------------------------------------------------------------------
-- VERIFICACIÓN: las 7 líneas de consignación deben quedar con saldo 0.
-- ---------------------------------------------------------------------------
-- SELECT cvd.id, cvd.lote, cvd.nup, cvd.cantidad,
--        cvd.cantidad
--        - COALESCE((SELECT SUM(x.cantidad) FROM retornos_cv_detalles x
--                      JOIN retornos_cv r ON r.id = x.id_retorno AND r.eliminado = false AND r.estado = 'Emitida'
--                     WHERE x.id_consignacion_detalle = cvd.id AND x.eliminado = false), 0)
--        - COALESCE((SELECT SUM(x.cantidad) FROM consignaciones_facturas_detalles x
--                      JOIN consignaciones_facturas y ON y.id = x.id_consignacion_factura AND y.eliminado = false AND y.estado = 'facturada'
--                     WHERE x.id_consignacion_detalle = cvd.id AND COALESCE(x.eliminado, false) = false), 0) AS saldo
--   FROM consignaciones_ventas_detalles cvd
--  WHERE cvd.id IN (2153, 2154, 150823, 225638, 220822, 220823, 220824);

-- ---------------------------------------------------------------------------
-- REVERTIR (solo si hiciera falta).
-- ---------------------------------------------------------------------------
-- UPDATE consignaciones_facturas_detalles d
--    SET id_consignacion_detalle = (r.antes->>'id_consignacion_detalle')::int,
--        cantidad = (r.antes->>'cantidad')::numeric,
--        subtotal = (r.antes->>'subtotal')::numeric,
--        valor_impuesto = (r.antes->>'valor_impuesto')::numeric,
--        total = (r.antes->>'total')::numeric,
--        eliminado = (r.antes->>'eliminado')::boolean,
--        deleted_at = (r.antes->>'deleted_at')::timestamp,
--        deleted_by = (r.antes->>'deleted_by')::int
--   FROM respaldo_correccion_facturaciones_cv_20260923 r
--  WHERE r.tipo = 'linea' AND d.id = r.id_registro;
-- UPDATE consignaciones_facturas c
--    SET estado = x.cabecera_antes->>'estado',
--        subtotal = (x.cabecera_antes->>'subtotal')::numeric,
--        impuesto = (x.cabecera_antes->>'impuesto')::numeric,
--        total = (x.cabecera_antes->>'total')::numeric,
--        observaciones = x.cabecera_antes->>'observaciones',
--        updated_at = now()
--   FROM (SELECT DISTINCT ON (id_doc) id_doc, cabecera_antes
--           FROM respaldo_correccion_facturaciones_cv_20260923 ORDER BY id_doc, respaldado_at) x
--  WHERE c.id = x.id_doc;
