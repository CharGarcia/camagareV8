-- =============================================================================
-- Reparar UNA nota de crédito que devolvió el stock sin lote / NUP / caducidad
-- =============================================================================
-- QUÉ HACE
--   Corrige las entradas de inventario (inventario_kardex) de UNA sola nota de crédito.
--   Tiene dos modos, según los parámetros que llenes:
--
--   * MODO AUTOMÁTICO (v_lote, v_nup y v_caducidad quedan en NULL)
--     Asigna el lote / NUP / caducidad con que la factura que la nota modifica sacó ese
--     producto, descontando lo que otras notas de crédito de la misma factura ya
--     devolvieron. Si la devolución abarca varios lotes, parte la fila del kardex en una
--     por lote (la suma de cantidades y el saldo no cambian). Solo toca las filas que hoy
--     no tienen lote, NUP ni caducidad, así que se puede volver a ejecutar sin duplicar.
--
--   * MODO MANUAL (llenas v_lote y/o v_nup y/o v_caducidad)
--     Fija esos valores en las filas de la nota, aunque ya tuvieran algo. Es el modo a usar
--     cuando sabes exactamente qué lote o qué serial regresó, o cuando la asignación
--     automática puso uno equivocado. Con v_producto puedes limitar el cambio a un producto.
--
-- TOCA DATOS: sí, y SOLO inventario_kardex (entradas con referencia_tipo = 'nota_credito'
--   de esa nota). No cambia cantidades, ni productos_bodegas.stock_actual, ni asientos
--   contables, ni el documento de la nota.
--
-- REVERSIBLE: cada fila se respalda en bak_nc_kardex_lote_manual antes de tocarla. Al final
--   de este archivo, comentado, está el bloque REVERTIR.
--
-- OJO: si después vuelves a editar esa nota de crédito (solo se puede en borrador), el
--   sistema rehace sus movimientos de inventario y esta corrección se pierde.
--
-- EJECUCIÓN: edita la sección PARÁMETROS —empresa por RUC, nota por su número y, si quieres,
--   producto por código—, pega todo en el Query Tool de pgAdmin y ejecuta (F5). El resumen sale
--   en la pestaña "Messages". Antes conviene correr el PASO 0 para ver qué va a hacer.
-- =============================================================================

-- PASO 0 (ejecutar SIEMPRE primero, aparte): ver cómo está la nota y qué sacó su factura.
--   Cambia el RUC, el número de la nota y, si quieres, el código del producto.
/*
WITH p AS (SELECT '1717136574001'::text AS ruc, '001-001-000000123'::text AS nc, NULL::text AS cod),
emp   AS (SELECT e.id FROM empresas e, p WHERE e.ruc = BTRIM(p.ruc) AND e.eliminado = false),
prod  AS (SELECT pr.id FROM productos pr, emp, p
           WHERE pr.id_empresa = emp.id AND pr.eliminado = false AND p.cod IS NOT NULL
             AND UPPER(BTRIM(p.cod)) IN (UPPER(BTRIM(COALESCE(pr.codigo,''))), UPPER(BTRIM(COALESCE(pr.codigo_auxiliar,''))), UPPER(BTRIM(COALESCE(pr.codigo_barras,''))))),
ncc   AS (SELECT c.id, c.num_doc_modificado, c.estado, c.fecha_emision FROM notas_credito_cabecera c, emp, p
           WHERE c.id_empresa = emp.id AND c.eliminado = false
             AND LPAD(c.establecimiento::text,3,'0')||'-'||LPAD(c.punto_emision::text,3,'0')||'-'||LPAD(c.secuencial::text,9,'0') = p.nc),
venta AS (SELECT v.id FROM ventas_cabecera v, emp, ncc
           WHERE v.id_empresa = emp.id AND v.eliminado = false
             AND LPAD(v.establecimiento::text,3,'0') = LPAD(split_part(ncc.num_doc_modificado,'-',1),3,'0')
             AND LPAD(v.punto_emision::text,  3,'0') = LPAD(split_part(ncc.num_doc_modificado,'-',2),3,'0')
             AND LPAD(v.secuencial::text,     9,'0') = LPAD(split_part(ncc.num_doc_modificado,'-',3),9,'0')
           ORDER BY v.id DESC LIMIT 1)
SELECT '0 resuelve' AS que, (SELECT id FROM emp) AS id,
       'empresa=' || COALESCE((SELECT id FROM emp)::text,'?') || ' nota=' || COALESCE((SELECT id FROM ncc)::text,'no encontrada')
       || ' factura=' || COALESCE((SELECT id FROM venta)::text,'no encontrada')
       || ' producto=' || COALESCE((SELECT id FROM prod)::text,'(todos)') AS detalle,
       NULL::numeric AS cantidad, NULL::text AS lote, NULL::text AS nup, NULL::date AS caducidad
UNION ALL
SELECT '1 devuelve la nota', k.id, 'producto ' || k.id_producto || ' / bodega ' || k.id_bodega,
       k.cantidad, k.numero_lote, k.nup, k.fecha_caducidad
  FROM inventario_kardex k, ncc
 WHERE k.referencia_tipo = 'nota_credito' AND k.referencia_id = ncc.id AND k.eliminado = false
   AND (NOT EXISTS (SELECT 1 FROM prod) OR k.id_producto = (SELECT id FROM prod))
UNION ALL
SELECT '2 sacó la factura', k.id, 'producto ' || k.id_producto || ' / bodega ' || k.id_bodega,
       ABS(k.cantidad), k.numero_lote, k.nup, k.fecha_caducidad
  FROM inventario_kardex k, venta
 WHERE k.referencia_tipo = 'factura_venta' AND k.referencia_id = venta.id
   AND k.tipo_movimiento = 'salida' AND k.eliminado = false
   AND (NOT EXISTS (SELECT 1 FROM prod) OR k.id_producto = (SELECT id FROM prod))
UNION ALL
SELECT '3 ya devuelto por otras notas', k.referencia_id, 'producto ' || k.id_producto,
       SUM(k.cantidad), k.numero_lote, k.nup, k.fecha_caducidad
  FROM inventario_kardex k, emp, ncc
 WHERE k.id_empresa = emp.id AND k.referencia_tipo = 'nota_credito' AND k.eliminado = false
   AND k.referencia_id <> ncc.id
   AND k.referencia_id IN (SELECT n2.id FROM notas_credito_cabecera n2
                            WHERE n2.id_empresa = emp.id AND n2.eliminado = false
                              AND n2.num_doc_modificado = ncc.num_doc_modificado)
   AND (NOT EXISTS (SELECT 1 FROM prod) OR k.id_producto = (SELECT id FROM prod))
 GROUP BY k.referencia_id, k.id_producto, k.numero_lote, k.nup, k.fecha_caducidad
ORDER BY 1, 2;
*/

CREATE TABLE IF NOT EXISTS bak_nc_kardex_lote_manual AS
SELECT * FROM inventario_kardex WHERE false;

DO $$
DECLARE
    -- ── PARÁMETROS (editar estas líneas) ─────────────────────────────────────
    v_ruc       text    := '1717136574001';      -- RUC de la empresa
    v_nc        text    := '001-001-000000123';  -- número de la nota de crédito
    v_cod_prod  text    := NULL;                 -- opcional: código del producto (NULL = todos los de la nota)
    v_lote      text    := NULL;                 -- MODO MANUAL: lote que regresó, p. ej. 'L-2026-04'
    v_nup       text    := NULL;                 -- MODO MANUAL: serial que regresó, p. ej. 'S3'
    v_caducidad date    := NULL;                 -- MODO MANUAL: caducidad, p. ej. '2027-06-30'
    -- ─────────────────────────────────────────────────────────────────────────

    v_empresa  integer;
    v_producto integer;
    v_cuantos  integer;
    v_nc_id    bigint;
    v_num_doc  text;
    v_venta    bigint;
    k          RECORD;
    o          RECORD;
    v_rest     numeric;
    v_toma     numeric;
    v_stock    numeric;
    v_primero  boolean;
    n_filas    integer := 0;
    n_partidas integer := 0;
    n_sin_orig integer := 0;
    v_marca constant text := ' [lote/NUP NC reparado]';
BEGIN
    SELECT COUNT(*), MIN(e.id) INTO v_cuantos, v_empresa
      FROM empresas e WHERE e.ruc = BTRIM(v_ruc) AND e.eliminado = false;
    IF v_cuantos = 0 THEN
        RAISE EXCEPTION 'No hay ninguna empresa con RUC %.', v_ruc;
    ELSIF v_cuantos > 1 THEN
        RAISE EXCEPTION 'Hay % empresas con RUC %; este script necesita una sola.', v_cuantos, v_ruc;
    END IF;

    IF v_cod_prod IS NOT NULL THEN
        SELECT COUNT(*), MIN(pr.id) INTO v_cuantos, v_producto
          FROM productos pr
         WHERE pr.id_empresa = v_empresa AND pr.eliminado = false
           AND UPPER(BTRIM(v_cod_prod)) IN (UPPER(BTRIM(COALESCE(pr.codigo, ''))),
                                            UPPER(BTRIM(COALESCE(pr.codigo_auxiliar, ''))),
                                            UPPER(BTRIM(COALESCE(pr.codigo_barras, ''))));
        IF v_cuantos = 0 THEN
            RAISE EXCEPTION 'No hay ningún producto con código % en la empresa %.', v_cod_prod, v_ruc;
        ELSIF v_cuantos > 1 THEN
            RAISE EXCEPTION 'Hay % productos con código % en la empresa %; revisa el catálogo.', v_cuantos, v_cod_prod, v_ruc;
        END IF;
    END IF;

    SELECT c.id, c.num_doc_modificado INTO v_nc_id, v_num_doc
      FROM notas_credito_cabecera c
     WHERE c.id_empresa = v_empresa AND c.eliminado = false
       AND LPAD(c.establecimiento::text,3,'0') || '-' || LPAD(c.punto_emision::text,3,'0') || '-' || LPAD(c.secuencial::text,9,'0') = v_nc
     ORDER BY c.id DESC LIMIT 1;

    IF v_nc_id IS NULL THEN
        RAISE EXCEPTION 'No se encontró la nota de crédito % en la empresa %.', v_nc, v_empresa;
    END IF;

    -- Respaldo de las filas de esta nota, antes de tocar nada. Se excluyen las que ya llevan
    -- la marca del script: o las creó él mismo al partir por lotes, o ya se respaldaron en su
    -- estado original en una corrida anterior. Si entraran al respaldo, el bloque REVERTIR las
    -- daría por originales y dejaría duplicada la cantidad devuelta.
    INSERT INTO bak_nc_kardex_lote_manual
    SELECT kb.* FROM inventario_kardex kb
     WHERE kb.referencia_tipo = 'nota_credito' AND kb.referencia_id = v_nc_id
       AND kb.id_empresa = v_empresa AND kb.eliminado = false
       AND COALESCE(kb.observaciones, '') NOT LIKE '%' || v_marca
       AND NOT EXISTS (SELECT 1 FROM bak_nc_kardex_lote_manual b WHERE b.id = kb.id);

    -- ── MODO MANUAL ──────────────────────────────────────────────────────────
    IF v_lote IS NOT NULL OR v_nup IS NOT NULL OR v_caducidad IS NOT NULL THEN
        UPDATE inventario_kardex
           SET numero_lote     = COALESCE(v_lote, numero_lote),
               nup             = COALESCE(v_nup, nup),
               fecha_caducidad = COALESCE(v_caducidad, fecha_caducidad),
               observaciones   = CASE WHEN COALESCE(observaciones,'') LIKE '%' || v_marca
                                      THEN observaciones ELSE COALESCE(observaciones,'') || v_marca END,
               updated_at      = CURRENT_TIMESTAMP
         WHERE referencia_tipo = 'nota_credito' AND referencia_id = v_nc_id
           AND id_empresa = v_empresa AND tipo_movimiento = 'entrada' AND eliminado = false
           AND (v_producto IS NULL OR id_producto = v_producto);
        GET DIAGNOSTICS n_filas = ROW_COUNT;

        RAISE NOTICE 'NC % (id %): % fila(s) fijadas a mano — lote=%, NUP=%, caducidad=%.',
            v_nc, v_nc_id, n_filas, COALESCE(v_lote,'(sin cambio)'), COALESCE(v_nup,'(sin cambio)'), COALESCE(v_caducidad::text,'(sin cambio)');
        RETURN;
    END IF;

    -- ── MODO AUTOMÁTICO: tomar el lote / NUP / caducidad de la factura ───────
    SELECT v.id INTO v_venta
      FROM ventas_cabecera v
     WHERE v.id_empresa = v_empresa AND v.eliminado = false
       AND LPAD(v.establecimiento::text,3,'0') = LPAD(split_part(v_num_doc,'-',1),3,'0')
       AND LPAD(v.punto_emision::text,  3,'0') = LPAD(split_part(v_num_doc,'-',2),3,'0')
       AND LPAD(v.secuencial::text,     9,'0') = LPAD(split_part(v_num_doc,'-',3),9,'0')
     ORDER BY v.id DESC LIMIT 1;

    IF v_venta IS NULL THEN
        RAISE EXCEPTION 'La nota % modifica el documento %, que no corresponde a ninguna factura de la empresa. Usa el MODO MANUAL.', v_nc, v_num_doc;
    END IF;

    FOR k IN
        SELECT kx.* FROM inventario_kardex kx
         WHERE kx.referencia_tipo = 'nota_credito' AND kx.referencia_id = v_nc_id
           AND kx.id_empresa = v_empresa AND kx.tipo_movimiento = 'entrada' AND kx.eliminado = false
           AND kx.numero_lote IS NULL AND kx.nup IS NULL AND kx.fecha_caducidad IS NULL
           AND (v_producto IS NULL OR kx.id_producto = v_producto)
         ORDER BY kx.id
    LOOP
        v_rest    := k.cantidad;
        v_stock   := k.stock_anterior;
        v_primero := true;

        FOR o IN
            WITH sal AS (
                SELECT id AS ord, COALESCE(numero_lote,'') AS lote, COALESCE(nup,'') AS nup,
                       fecha_caducidad AS cad, ABS(cantidad) AS cant
                  FROM inventario_kardex
                 WHERE id_empresa = v_empresa AND id_producto = k.id_producto
                   AND referencia_id = v_venta AND referencia_tipo = 'factura_venta'
                   AND tipo_movimiento = 'salida' AND eliminado = false
            ),
            det AS (
                SELECT id AS ord, COALESCE(numero_lote,'') AS lote, COALESCE(nup,'') AS nup,
                       fecha_caducidad AS cad, cantidad AS cant
                  FROM ventas_detalle
                 WHERE id_venta = v_venta AND id_producto = k.id_producto
            ),
            origen AS (
                SELECT * FROM sal
                UNION ALL
                SELECT * FROM det WHERE NOT EXISTS (SELECT 1 FROM sal)
            ),
            disp AS (
                SELECT lote, nup, MAX(cad) AS cad, MIN(ord) AS ord, SUM(cant) AS cant
                  FROM origen
                 WHERE lote <> '' OR nup <> '' OR cad IS NOT NULL
                 GROUP BY lote, nup
            ),
            dev AS (
                SELECT COALESCE(kd.numero_lote,'') AS lote, COALESCE(kd.nup,'') AS nup, SUM(kd.cantidad) AS cant
                  FROM inventario_kardex kd
                 WHERE kd.id_empresa = v_empresa AND kd.id_producto = k.id_producto
                   AND kd.referencia_tipo = 'nota_credito' AND kd.eliminado = false
                   AND kd.id <> k.id
                   AND kd.referencia_id IN (
                        SELECT n2.id FROM notas_credito_cabecera n2
                         WHERE n2.id_empresa = v_empresa AND n2.num_doc_modificado = v_num_doc
                           AND n2.eliminado = false)
                 GROUP BY 1, 2
            )
            SELECT d.lote, d.nup, d.cad, d.cant - COALESCE(dv.cant, 0) AS disponible
              FROM disp d
              LEFT JOIN dev dv ON dv.lote = d.lote AND dv.nup = d.nup
             ORDER BY d.ord
        LOOP
            EXIT WHEN v_rest <= 0;
            IF o.disponible <= 0 THEN CONTINUE; END IF;
            v_toma := LEAST(v_rest, o.disponible);

            IF v_primero THEN
                UPDATE inventario_kardex
                   SET numero_lote     = NULLIF(o.lote, ''),
                       nup             = NULLIF(o.nup, ''),
                       fecha_caducidad = o.cad,
                       cantidad        = v_toma,
                       costo_total     = ROUND(k.costo_unitario * v_toma, 2),
                       stock_posterior = v_stock + v_toma,
                       observaciones   = COALESCE(k.observaciones,'') || v_marca,
                       updated_at      = CURRENT_TIMESTAMP
                 WHERE id = k.id;
                n_filas := n_filas + 1;
            ELSE
                INSERT INTO inventario_kardex (
                    id_empresa, id_producto, id_bodega, tipo_movimiento, referencia_tipo, referencia_id,
                    fecha_movimiento, cantidad, costo_unitario, costo_total, stock_anterior, stock_posterior,
                    numero_lote, fecha_caducidad, nup, observaciones, created_by, updated_by, id_medida, tipo_ambiente
                ) VALUES (
                    k.id_empresa, k.id_producto, k.id_bodega, k.tipo_movimiento, k.referencia_tipo, k.referencia_id,
                    k.fecha_movimiento, v_toma, k.costo_unitario, ROUND(k.costo_unitario * v_toma, 2), v_stock, v_stock + v_toma,
                    NULLIF(o.lote,''), o.cad, NULLIF(o.nup,''), COALESCE(k.observaciones,'') || v_marca,
                    k.created_by, k.updated_by, k.id_medida, k.tipo_ambiente
                );
                n_partidas := n_partidas + 1;
            END IF;

            v_stock   := v_stock + v_toma;
            v_rest    := v_rest - v_toma;
            v_primero := false;
        END LOOP;

        IF v_primero THEN
            n_sin_orig := n_sin_orig + 1;          -- la factura tampoco tiene lote/NUP/caducidad
        ELSIF v_rest > 0 THEN
            -- Devolvió más de lo que salió: el excedente queda sin lote, como estaba.
            INSERT INTO inventario_kardex (
                id_empresa, id_producto, id_bodega, tipo_movimiento, referencia_tipo, referencia_id,
                fecha_movimiento, cantidad, costo_unitario, costo_total, stock_anterior, stock_posterior,
                numero_lote, fecha_caducidad, nup, observaciones, created_by, updated_by, id_medida, tipo_ambiente
            ) VALUES (
                k.id_empresa, k.id_producto, k.id_bodega, k.tipo_movimiento, k.referencia_tipo, k.referencia_id,
                k.fecha_movimiento, v_rest, k.costo_unitario, ROUND(k.costo_unitario * v_rest, 2), v_stock, v_stock + v_rest,
                NULL, NULL, NULL, COALESCE(k.observaciones,'') || v_marca,
                k.created_by, k.updated_by, k.id_medida, k.tipo_ambiente
            );
            n_partidas := n_partidas + 1;
        END IF;
    END LOOP;

    IF n_filas = 0 AND n_partidas = 0 AND n_sin_orig = 0 THEN
        RAISE NOTICE 'NC % (id %): no hay filas por reparar (ya tienen lote, NUP o caducidad). Si el lote asignado es el equivocado, usa el MODO MANUAL.', v_nc, v_nc_id;
    ELSE
        RAISE NOTICE 'NC % (id %): % fila(s) corregidas, % fila(s) nuevas por reparto entre lotes, % sin origen atribuible.',
            v_nc, v_nc_id, n_filas, n_partidas, n_sin_orig;
    END IF;
END $$;

-- COMPROBACIÓN (ejecutar aparte; cambia el RUC y el número de la nota):
-- SELECT k.id, k.id_producto, k.cantidad, k.numero_lote, k.nup, k.fecha_caducidad, k.observaciones
--   FROM inventario_kardex k
--   JOIN notas_credito_cabecera c ON c.id = k.referencia_id AND c.id_empresa = k.id_empresa
--   JOIN empresas e ON e.id = c.id_empresa AND e.ruc = '1717136574001'
--  WHERE k.referencia_tipo = 'nota_credito' AND k.eliminado = false AND c.eliminado = false
--    AND LPAD(c.establecimiento::text,3,'0')||'-'||LPAD(c.punto_emision::text,3,'0')||'-'||LPAD(c.secuencial::text,9,'0') = '001-001-000000123'
--  ORDER BY k.id;

-- REVERTIR (descomentar y ejecutar; después, si todo quedó bien, DROP TABLE bak_nc_kardex_lote_manual):
-- UPDATE inventario_kardex k
--    SET numero_lote = b.numero_lote, nup = b.nup, fecha_caducidad = b.fecha_caducidad,
--        cantidad = b.cantidad, costo_total = b.costo_total, stock_posterior = b.stock_posterior,
--        observaciones = b.observaciones, updated_at = CURRENT_TIMESTAMP
--   FROM bak_nc_kardex_lote_manual b WHERE b.id = k.id;
-- UPDATE inventario_kardex
--    SET eliminado = true, deleted_at = CURRENT_TIMESTAMP
--  WHERE referencia_tipo = 'nota_credito' AND observaciones LIKE '%[lote/NUP NC reparado]'
--    AND id NOT IN (SELECT id FROM bak_nc_kardex_lote_manual);
