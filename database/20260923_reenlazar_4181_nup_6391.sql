-- =============================================================================
-- ASAMED (1792708389001): consignación 001-101-000004181, producto MAMU 4835.
-- El retorno migrado 001-101-000000076 trae tres unidades (NUP 6037, 6391 y "6391A") y las tres
-- quedaron en la línea 2247 (NUP 6037, 1 u.) → saldo −2. La consignación tiene DOS líneas con
-- NUP 6391 (2248 y 2250, lote S010243992, libres): "6391A" es la segunda unidad 6391.
-- Corrección: línea de retorno NUP 6391 → 2248; NUP 6391A → 2250. Solo cambia
-- retornos_cv_detalles.id_consignacion_detalle; no toca inventario, kardex ni asientos.
-- Verifica que el destino siga libre; si no, avisa y no toca. Respaldo en
-- respaldo_reenlace_lote_20260923 (lo deshace el bloque REVERTIR del script por lote).
-- pgAdmin: pegar y F5. Idempotente.
-- =============================================================================
DO $$
DECLARE
  c        RECORD;
  v_linea  bigint;
  v_n      int;
  v_hechos int := 0;
BEGIN
  FOR c IN SELECT * FROM (VALUES ('6391'::text, 2248::bigint), ('6391A'::text, 2250::bigint)) AS t(nup, destino) LOOP
    IF EXISTS (SELECT 1 FROM retornos_cv_detalles x JOIN retornos_cv xr ON xr.id = x.id_retorno AND xr.eliminado = false
                WHERE x.id_consignacion_detalle = c.destino AND x.eliminado = false)
       OR EXISTS (SELECT 1 FROM consignaciones_facturas_detalles x JOIN consignaciones_facturas xf ON xf.id = x.id_consignacion_factura AND xf.eliminado = false
                   WHERE x.id_consignacion_detalle = c.destino AND COALESCE(x.eliminado, false) = false) THEN
      RAISE NOTICE 'Destino % ya tiene documentos; no se toca NUP %.', c.destino, c.nup;
      CONTINUE;
    END IF;

    SELECT d.id INTO v_linea
      FROM retornos_cv_detalles d
      JOIN retornos_cv r ON r.id = d.id_retorno AND r.eliminado = false AND r.estado = 'Emitida'
     WHERE d.id_consignacion_detalle = 2247 AND d.eliminado = false
       AND UPPER(TRIM(COALESCE(d.nup, ''))) = c.nup
     ORDER BY d.id LIMIT 1;
    IF v_linea IS NULL THEN
      RAISE NOTICE 'No hay línea de retorno con NUP % en la línea 2247; nada que mover.', c.nup;
      CONTINUE;
    END IF;

    INSERT INTO respaldo_reenlace_lote_20260923 (documento_tipo, id_linea, id_consignacion, lote, nup, linea_anterior, linea_nueva)
    SELECT 'Retorno', d.id, d.id_consignacion, d.lote, d.nup, 2247, c.destino
      FROM retornos_cv_detalles d WHERE d.id = v_linea
    ON CONFLICT (documento_tipo, id_linea) DO NOTHING;

    UPDATE retornos_cv_detalles SET id_consignacion_detalle = c.destino, updated_at = now()
     WHERE id = v_linea AND id_consignacion_detalle = 2247;
    GET DIAGNOSTICS v_n = ROW_COUNT;
    v_hechos := v_hechos + v_n;
    RAISE NOTICE 'Línea de retorno % (NUP %) movida de la línea 2247 a la %.', v_linea, c.nup, c.destino;
  END LOOP;
  RAISE NOTICE 'Total re-enlazadas: %', v_hechos;
END $$;
