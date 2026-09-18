-- =============================================================================
-- Reparación: notas de crédito de venta que devolvieron stock SIN lote / NUP / caducidad
-- =============================================================================
-- QUÉ HACE
--   Hasta ahora una nota de crédito reingresaba el producto al inventario con
--   numero_lote, nup y fecha_caducidad vacíos. Este script recorre las entradas de
--   kardex de NC vigentes que quedaron así y les asigna el lote / NUP / caducidad
--   con que salió el producto en la factura que la NC modifica (num_doc_modificado):
--     * toma lo que la factura sacó del inventario (kardex 'factura_venta');
--       si no hay salida en el kardex, usa los datos de la línea de la factura;
--     * descuenta lo que otras NC vigentes de la misma factura ya devolvieron a cada lote;
--     * si la devolución abarca varios lotes/NUP, parte la fila del kardex en una fila por
--       lote (la suma de cantidades y el stock final no cambian);
--     * lo que no se pueda atribuir (factura no encontrada, sin lote en la factura, o
--       exceso sobre lo vendido) queda como estaba.
--
-- TOCA DATOS: sí, SOLO la tabla inventario_kardex (entradas con referencia_tipo = 'nota_credito').
--   No modifica productos_bodegas.stock_actual ni asientos contables.
--
-- ES IDEMPOTENTE: solo procesa filas que aún no tienen lote, NUP ni caducidad; se puede
--   volver a ejecutar sin duplicar nada.
--
-- REVERSIBLE: antes de tocar cada fila la copia a bak_nc_kardex_lote_20260918. Al final
--   de este archivo, comentado, está el bloque REVERTIR.
--   Las filas nuevas (partidas por lote) llevan en observaciones la marca
--   '[lote/NUP NC reparado]', igual que las filas modificadas.
--
-- EJECUCIÓN: pegar completo en el Query Tool de pgAdmin y ejecutar (F5). Aplicar ANTES
--   o DESPUÉS del despliegue del código: no depende de él. Correr primero en una copia
--   si se prefiere; el resumen sale en la pestaña "Messages" (RAISE NOTICE).
-- =============================================================================

CREATE TABLE IF NOT EXISTS bak_nc_kardex_lote_20260918 AS
SELECT * FROM inventario_kardex WHERE false;

DO $$
DECLARE
    k        RECORD;
    o        RECORD;
    v_venta  bigint;
    v_rest   numeric;
    v_toma   numeric;
    v_stock  numeric;
    v_primero boolean;
    n_filas    integer := 0;
    n_partidas integer := 0;
    n_sin_orig integer := 0;
    v_marca  constant text := ' [lote/NUP NC reparado]';
BEGIN
    FOR k IN
        SELECT kx.*, nc.num_doc_modificado AS nc_num_doc
        FROM inventario_kardex kx
        JOIN notas_credito_cabecera nc
          ON nc.id = kx.referencia_id AND nc.id_empresa = kx.id_empresa
        WHERE kx.referencia_tipo = 'nota_credito'
          AND kx.tipo_movimiento = 'entrada'
          AND kx.eliminado = false
          AND kx.numero_lote IS NULL AND kx.nup IS NULL AND kx.fecha_caducidad IS NULL
          AND nc.eliminado = false
          AND COALESCE(nc.cod_doc_modificado, '01') = '01'
        ORDER BY kx.id
    LOOP
        -- Factura que modifica la NC
        SELECT v.id INTO v_venta
        FROM ventas_cabecera v
        WHERE v.id_empresa = k.id_empresa AND v.eliminado = false
          AND LPAD(v.establecimiento::text, 3, '0') = LPAD(split_part(k.nc_num_doc, '-', 1), 3, '0')
          AND LPAD(v.punto_emision::text,   3, '0') = LPAD(split_part(k.nc_num_doc, '-', 2), 3, '0')
          AND LPAD(v.secuencial::text,      9, '0') = LPAD(split_part(k.nc_num_doc, '-', 3), 9, '0')
        ORDER BY v.id DESC LIMIT 1;

        IF v_venta IS NULL THEN
            n_sin_orig := n_sin_orig + 1;
            CONTINUE;
        END IF;

        v_rest    := k.cantidad;
        v_stock   := k.stock_anterior;
        v_primero := true;

        FOR o IN
            WITH sal AS (
                SELECT id AS ord, COALESCE(numero_lote, '') AS lote, COALESCE(nup, '') AS nup,
                       fecha_caducidad AS cad, ABS(cantidad) AS cant
                FROM inventario_kardex
                WHERE id_empresa = k.id_empresa AND id_producto = k.id_producto
                  AND referencia_id = v_venta AND referencia_tipo = 'factura_venta'
                  AND tipo_movimiento = 'salida' AND eliminado = false
            ),
            det AS (
                SELECT id AS ord, COALESCE(numero_lote, '') AS lote, COALESCE(nup, '') AS nup,
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
                SELECT COALESCE(kd.numero_lote, '') AS lote, COALESCE(kd.nup, '') AS nup,
                       SUM(kd.cantidad) AS cant
                FROM inventario_kardex kd
                WHERE kd.id_empresa = k.id_empresa AND kd.id_producto = k.id_producto
                  AND kd.referencia_tipo = 'nota_credito' AND kd.eliminado = false
                  AND kd.id <> k.id
                  AND kd.referencia_id IN (
                      SELECT n2.id FROM notas_credito_cabecera n2
                      WHERE n2.id_empresa = k.id_empresa AND n2.num_doc_modificado = k.nc_num_doc
                        AND n2.eliminado = false
                  )
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
                INSERT INTO bak_nc_kardex_lote_20260918
                SELECT * FROM inventario_kardex WHERE id = k.id
                  AND NOT EXISTS (SELECT 1 FROM bak_nc_kardex_lote_20260918 b WHERE b.id = k.id);

                UPDATE inventario_kardex
                   SET numero_lote     = NULLIF(o.lote, ''),
                       nup             = NULLIF(o.nup, ''),
                       fecha_caducidad = o.cad,
                       cantidad        = v_toma,
                       costo_total     = ROUND(k.costo_unitario * v_toma, 2),
                       stock_posterior = v_stock + v_toma,
                       observaciones   = COALESCE(k.observaciones, '') || v_marca,
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
                    NULLIF(o.lote, ''), o.cad, NULLIF(o.nup, ''), COALESCE(k.observaciones, '') || v_marca,
                    k.created_by, k.updated_by, k.id_medida, k.tipo_ambiente
                );
                n_partidas := n_partidas + 1;
            END IF;

            v_stock   := v_stock + v_toma;
            v_rest    := v_rest - v_toma;
            v_primero := false;
        END LOOP;

        IF v_primero THEN
            n_sin_orig := n_sin_orig + 1;      -- la factura no trae lote/NUP/caducidad
        ELSIF v_rest > 0 THEN
            -- Exceso sobre lo vendido: queda sin lote, como antes
            INSERT INTO inventario_kardex (
                id_empresa, id_producto, id_bodega, tipo_movimiento, referencia_tipo, referencia_id,
                fecha_movimiento, cantidad, costo_unitario, costo_total, stock_anterior, stock_posterior,
                numero_lote, fecha_caducidad, nup, observaciones, created_by, updated_by, id_medida, tipo_ambiente
            ) VALUES (
                k.id_empresa, k.id_producto, k.id_bodega, k.tipo_movimiento, k.referencia_tipo, k.referencia_id,
                k.fecha_movimiento, v_rest, k.costo_unitario, ROUND(k.costo_unitario * v_rest, 2), v_stock, v_stock + v_rest,
                NULL, NULL, NULL, COALESCE(k.observaciones, '') || v_marca,
                k.created_by, k.updated_by, k.id_medida, k.tipo_ambiente
            );
            n_partidas := n_partidas + 1;
        END IF;
    END LOOP;

    RAISE NOTICE 'NC reparadas: % filas de kardex actualizadas, % filas nuevas por reparto entre lotes, % sin origen atribuible (no se tocaron).',
        n_filas, n_partidas, n_sin_orig;
END $$;

-- COMPROBACIÓN (ejecutar aparte; deben quedar solo las NC sin origen atribuible):
-- SELECT k.id, k.id_empresa, nc.secuencial, nc.num_doc_modificado, k.id_producto, k.cantidad
--   FROM inventario_kardex k
--   JOIN notas_credito_cabecera nc ON nc.id = k.referencia_id
--  WHERE k.referencia_tipo = 'nota_credito' AND k.tipo_movimiento = 'entrada' AND k.eliminado = false
--    AND k.numero_lote IS NULL AND k.nup IS NULL AND k.fecha_caducidad IS NULL AND nc.eliminado = false;

-- REVERTIR (descomentar y ejecutar; luego, si todo quedó bien, DROP TABLE bak_nc_kardex_lote_20260918):
-- UPDATE inventario_kardex k
--    SET numero_lote = b.numero_lote, nup = b.nup, fecha_caducidad = b.fecha_caducidad,
--        cantidad = b.cantidad, costo_total = b.costo_total, stock_posterior = b.stock_posterior,
--        observaciones = b.observaciones, updated_at = CURRENT_TIMESTAMP
--   FROM bak_nc_kardex_lote_20260918 b WHERE b.id = k.id;
-- UPDATE inventario_kardex
--    SET eliminado = true, deleted_at = CURRENT_TIMESTAMP
--  WHERE referencia_tipo = 'nota_credito' AND observaciones LIKE '%[lote/NUP NC reparado]'
--    AND id NOT IN (SELECT id FROM bak_nc_kardex_lote_20260918);
