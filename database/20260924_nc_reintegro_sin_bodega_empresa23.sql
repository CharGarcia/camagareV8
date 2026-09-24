-- ============================================================================
-- Corrección: notas de crédito de Maria Fernanda Vera Bustamante (usuario 23,
-- empresa 23 / RUC 1792708389001) que se guardaron SIN bodega de reintegro y
-- no devolvieron la mercadería al inventario. Se reintegra en CENTRAL 1 (id 30).
--
-- Replica InventarioService::registrarEntradaPorNC(): candado de stock, costo
-- promedio, lote/caducidad/NUP de la factura original, kardex + productos_bodegas
-- y log_sistema. Idempotente: solo toma líneas SIN movimiento vigente en el kardex.
-- ============================================================================

-- ─── PASO 1: VISTA PREVIA (no modifica nada) ────────────────────────────────
SELECT nc.id AS id_nc,
       nc.establecimiento || '-' || nc.punto_emision || '-' || LPAD(nc.secuencial::text, 9, '0') AS numero_nc,
       nc.fecha_emision, nc.estado, nc.num_doc_modificado,
       d.id AS id_linea, p.nombre AS producto, d.cantidad
FROM notas_credito_cabecera nc
JOIN empresa_establecimiento ee ON ee.id = nc.id_establecimiento
JOIN notas_credito_detalle d    ON d.id_nota_credito = nc.id
JOIN productos p                ON p.id = d.id_producto AND p.id_empresa = nc.id_empresa AND p.eliminado = false
WHERE nc.id_empresa = 23
  AND nc.created_by = 23
  AND nc.eliminado  = false
  AND nc.estado    <> 'anulado'
  AND LOWER(COALESCE(ee.facturacion_inventario::text, '')) IN ('true', 't', '1')
  AND LOWER(COALESCE(p.inventariable::text, '')) IN ('true', 't', '1')
  AND d.cantidad > 0
  AND NOT EXISTS (SELECT 1 FROM inventario_kardex k
                   WHERE k.referencia_tipo = 'nota_credito' AND k.referencia_id = nc.id
                     AND k.id_producto = d.id_producto AND k.eliminado = false)
ORDER BY nc.id, d.id;


-- ─── PASO 2: CORRECCIÓN (ejecutar el bloque completo, de BEGIN a COMMIT) ────
BEGIN;

DO $$
DECLARE
    v_empresa  int := 23;   -- empresa RUC 1792708389001
    v_usuario  int := 23;   -- Maria Fernanda Vera Bustamante (creadora de las NC)
    v_bodega   int := 30;   -- CENTRAL 1
    v_amb      varchar(1);
    r          record;
    t          record;
    v_stock    numeric;
    v_costo    numeric;
    v_idventa  int;
    v_rest     numeric;
    v_toma     numeric;
    v_obs      text;
    v_lineas   int := 0;
    v_omitidas int := 0;
BEGIN
    SELECT CAST(tipo_ambiente AS VARCHAR(1)) INTO v_amb FROM empresas WHERE id = v_empresa;

    IF NOT EXISTS (SELECT 1 FROM bodegas WHERE id = v_bodega AND id_empresa = v_empresa AND eliminado = false) THEN
        RAISE EXCEPTION 'La bodega % no pertenece a la empresa %', v_bodega, v_empresa;
    END IF;

    FOR r IN
        SELECT nc.id AS id_nc, nc.num_doc_modificado, COALESCE(nc.cod_doc_modificado, '01') AS cod_doc,
               nc.establecimiento || '-' || nc.punto_emision || '-' || LPAD(nc.secuencial::text, 9, '0') AS numero_nc,
               d.id AS id_det, d.id_producto, ABS(d.cantidad) AS cantidad, p.id_medida
        FROM notas_credito_cabecera nc
        JOIN empresa_establecimiento ee ON ee.id = nc.id_establecimiento
        JOIN notas_credito_detalle d    ON d.id_nota_credito = nc.id
        JOIN productos p                ON p.id = d.id_producto AND p.id_empresa = nc.id_empresa AND p.eliminado = false
        WHERE nc.id_empresa = v_empresa
          AND nc.created_by = v_usuario
          AND nc.eliminado  = false
          AND nc.estado    <> 'anulado'
          AND LOWER(COALESCE(ee.facturacion_inventario::text, '')) IN ('true', 't', '1')
          AND LOWER(COALESCE(p.inventariable::text, '')) IN ('true', 't', '1')
          AND d.cantidad > 0
          AND NOT EXISTS (SELECT 1 FROM inventario_kardex k
                           WHERE k.referencia_tipo = 'nota_credito' AND k.referencia_id = nc.id
                             AND k.id_producto = d.id_producto AND k.eliminado = false)
        ORDER BY nc.id, d.id
    LOOP
        -- Factura original (solo NC sobre factura '01', igual que el módulo).
        v_idventa := NULL;
        IF r.cod_doc = '01' AND r.num_doc_modificado ~ '^\d+-\d+-\d+$' THEN
            SELECT vc.id INTO v_idventa
            FROM ventas_cabecera vc
            WHERE vc.id_empresa = v_empresa AND vc.eliminado = false
              AND LPAD(vc.establecimiento::text, 3, '0') = LPAD(split_part(r.num_doc_modificado, '-', 1), 3, '0')
              AND LPAD(vc.punto_emision::text, 3, '0')   = LPAD(split_part(r.num_doc_modificado, '-', 2), 3, '0')
              AND LPAD(vc.secuencial::text, 9, '0')      = LPAD(split_part(r.num_doc_modificado, '-', 3), 9, '0')
            ORDER BY vc.id DESC LIMIT 1;
        END IF;

        -- Vendido en otra unidad (p. ej. CAJA): la conversión no se replica aquí; se deja para
        -- corregir a mano desde Inventarios.
        IF v_idventa IS NOT NULL AND EXISTS (
            SELECT 1 FROM ventas_detalle vd
            WHERE vd.id_venta = v_idventa AND vd.id_producto = r.id_producto
              AND vd.id_unidad_medida IS NOT NULL AND r.id_medida IS NOT NULL
              AND vd.id_unidad_medida <> r.id_medida
        ) THEN
            RAISE NOTICE 'OMITIDA NC % línea % (producto %): vendida en otra unidad, corregir a mano',
                r.numero_nc, r.id_det, r.id_producto;
            v_omitidas := v_omitidas + 1;
            CONTINUE;
        END IF;

        PERFORM pg_advisory_xact_lock(hashtext('stock:' || v_empresa || ':' || r.id_producto || ':' || v_bodega));

        SELECT ROUND(COALESCE(SUM(cantidad), 0), 2) INTO v_stock
        FROM inventario_kardex
        WHERE id_empresa = v_empresa AND id_producto = r.id_producto AND id_bodega = v_bodega
          AND eliminado = false AND tipo_ambiente = v_amb;

        SELECT CASE WHEN SUM(cantidad) > 0 THEN ROUND(SUM(costo_total)::numeric / SUM(cantidad)::numeric, 6) ELSE 0 END
          INTO v_costo
        FROM inventario_kardex
        WHERE id_empresa = v_empresa AND id_producto = r.id_producto AND id_bodega = v_bodega
          AND tipo_movimiento = 'entrada' AND eliminado = false;

        v_obs  := 'Devolución NC ' || r.numero_nc || ' (corrección: se guardó sin bodega de reintegro)';
        v_rest := r.cantidad;

        -- Tramos por lote/NUP que salieron con la factura, menos lo ya devuelto por otras NC.
        FOR t IN
            WITH salidas AS (
                SELECT numero_lote, fecha_caducidad, nup, ABS(cantidad) AS cantidad, id AS orden
                FROM inventario_kardex
                WHERE v_idventa IS NOT NULL AND id_empresa = v_empresa AND id_producto = r.id_producto
                  AND referencia_id = v_idventa AND referencia_tipo = 'factura_venta'
                  AND tipo_movimiento = 'salida' AND eliminado = false
            ), origen AS (
                SELECT * FROM salidas
                UNION ALL
                SELECT numero_lote, fecha_caducidad, nup, ABS(cantidad), id
                FROM ventas_detalle
                WHERE v_idventa IS NOT NULL AND id_venta = v_idventa AND id_producto = r.id_producto
                  AND NOT EXISTS (SELECT 1 FROM salidas)
            ), grupos AS (
                SELECT COALESCE(TRIM(numero_lote), '') AS lote, COALESCE(TRIM(nup), '') AS nup,
                       MAX(fecha_caducidad) AS caducidad, SUM(cantidad) AS cantidad, MIN(orden) AS orden
                FROM origen
                WHERE COALESCE(TRIM(numero_lote), '') <> '' OR COALESCE(TRIM(nup), '') <> '' OR fecha_caducidad IS NOT NULL
                GROUP BY 1, 2
            ), devuelto AS (
                SELECT COALESCE(numero_lote, '') AS lote, COALESCE(nup, '') AS nup, SUM(cantidad) AS cantidad
                FROM inventario_kardex
                WHERE id_empresa = v_empresa AND id_producto = r.id_producto
                  AND referencia_tipo = 'nota_credito' AND eliminado = false
                  AND referencia_id IN (SELECT id FROM notas_credito_cabecera
                                         WHERE id_empresa = v_empresa AND num_doc_modificado = r.num_doc_modificado
                                           AND eliminado = false)
                GROUP BY 1, 2
            )
            SELECT NULLIF(g.lote, '') AS lote, NULLIF(g.nup, '') AS nup, g.caducidad,
                   g.cantidad - COALESCE(dv.cantidad, 0) AS disponible
            FROM grupos g
            LEFT JOIN devuelto dv ON dv.lote = g.lote AND dv.nup = g.nup
            WHERE g.cantidad - COALESCE(dv.cantidad, 0) > 0
            ORDER BY g.orden
        LOOP
            EXIT WHEN v_rest <= 0;
            v_toma := LEAST(v_rest, t.disponible);

            INSERT INTO inventario_kardex (id_empresa, id_producto, id_bodega, tipo_movimiento, referencia_tipo, referencia_id,
                   fecha_movimiento, cantidad, costo_unitario, costo_total, stock_anterior, stock_posterior,
                   numero_lote, fecha_caducidad, nup, observaciones, created_by, updated_by, tipo_ambiente, id_medida)
            VALUES (v_empresa, r.id_producto, v_bodega, 'entrada', 'nota_credito', r.id_nc,
                   CURRENT_TIMESTAMP, v_toma, v_costo, ROUND(v_costo * v_toma, 2), v_stock, v_stock + v_toma,
                   t.lote, t.caducidad, t.nup, v_obs, v_usuario, v_usuario, v_amb, r.id_medida);

            v_stock := v_stock + v_toma;
            v_rest  := v_rest - v_toma;
        END LOOP;

        -- Lo que no tiene lote/NUP de origen entra sin lote.
        IF v_rest > 0 THEN
            INSERT INTO inventario_kardex (id_empresa, id_producto, id_bodega, tipo_movimiento, referencia_tipo, referencia_id,
                   fecha_movimiento, cantidad, costo_unitario, costo_total, stock_anterior, stock_posterior,
                   numero_lote, fecha_caducidad, nup, observaciones, created_by, updated_by, tipo_ambiente, id_medida)
            VALUES (v_empresa, r.id_producto, v_bodega, 'entrada', 'nota_credito', r.id_nc,
                   CURRENT_TIMESTAMP, v_rest, v_costo, ROUND(v_costo * v_rest, 2), v_stock, v_stock + v_rest,
                   NULL, NULL, NULL, v_obs, v_usuario, v_usuario, v_amb, r.id_medida);
            v_stock := v_stock + v_rest;
        END IF;

        INSERT INTO productos_bodegas (id_empresa, id_producto, id_bodega, stock_actual, created_by, updated_by)
        VALUES (v_empresa, r.id_producto, v_bodega, v_stock, v_usuario, v_usuario)
        ON CONFLICT (id_producto, id_bodega) DO UPDATE SET
            stock_actual = EXCLUDED.stock_actual,
            updated_by   = EXCLUDED.updated_by,
            updated_at   = CURRENT_TIMESTAMP,
            eliminado    = false;

        INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro, datos_anteriores, datos_nuevos, ip_usuario, user_agent)
        VALUES (v_usuario, v_empresa, 'correccion_reintegro_nc', 'notas_credito_cabecera', r.id_nc, NULL,
                jsonb_build_object('motivo', 'NC guardada sin bodega de reintegro', 'id_bodega', v_bodega,
                                   'id_producto', r.id_producto, 'cantidad', r.cantidad, 'stock_final', v_stock),
                'sql-manual', 'correccion NC sin bodega 2026-09-24');

        RAISE NOTICE 'OK NC % línea % producto %: +% → stock CENTRAL 1 = %',
            r.numero_nc, r.id_det, r.id_producto, r.cantidad, v_stock;
        v_lineas := v_lineas + 1;
    END LOOP;

    RAISE NOTICE 'Líneas reintegradas: %, omitidas: %', v_lineas, v_omitidas;
END $$;

-- ─── PASO 3: revisar los NOTICE (pestaña "Mensajes" de pgAdmin) y luego ejecutar
--     SOLO una de estas dos líneas (seleccionarla y F5):
-- COMMIT;     -- si todo cuadra
-- ROLLBACK;   -- si algo no cuadra
