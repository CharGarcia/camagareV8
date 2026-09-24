-- =============================================================================
-- ASAMED (1792708389001): consignaciones 001-101-000004171 y 001-101-000004183 con NUP REPETIDO.
-- Cada una tiene dos líneas idénticas (mismo producto, lote y NUP) y los dos documentos migrados
-- quedaron en la primera (saldo −1) mientras la gemela sigue con saldo 1:
--   4171: línea 2123 (retornado 2) / gemela 2124 (libre) — NUP 2643, lote S040252484
--   4183: línea 2271 (retornado 1 + facturado 1) / gemela 2272 (libre) — NUP "1", lote CMN18P1
-- Corrección: mueve UNA línea de retorno de cada una a su gemela. Solo cambia
-- retornos_cv_detalles.id_consignacion_detalle; no toca inventario, kardex ni asientos.
-- Verifica antes que la gemela siga libre y sea idéntica; si no, avisa y no toca nada.
-- Respaldo en respaldo_reenlace_lote_20260923 (el mismo del re-enlace por lote); el bloque REVERTIR
-- de database/20260923_reenlazar_retornos_facturaciones_por_lote.sql también lo deshace.
-- pgAdmin: pegar y F5. Idempotente.
-- =============================================================================
DO $$
DECLARE
  c   RECORD;
  v_linea   bigint;
  v_n       int;
  v_hechos  int := 0;
BEGIN
  CREATE TABLE IF NOT EXISTS respaldo_reenlace_lote_20260923 (
      documento_tipo  varchar   NOT NULL,
      id_linea        bigint    NOT NULL,
      id_consignacion bigint,
      lote            varchar,
      nup             varchar,
      linea_anterior  bigint,
      linea_nueva     bigint    NOT NULL,
      respaldado_at   timestamp NOT NULL DEFAULT now(),
      PRIMARY KEY (documento_tipo, id_linea)
  );

  -- origen = línea negativa con dos retornos del mismo NUP; destino = línea gemela (mismo producto,
  -- lote y NUP en la misma consignación) que no tiene nada enlazado.
  FOR c IN SELECT * FROM (VALUES (2123::bigint, 2124::bigint), (2271::bigint, 2272::bigint)) AS t(origen, destino) LOOP
    -- la gemela debe seguir libre y ser realmente gemela
    SELECT COUNT(*) INTO v_n
      FROM consignaciones_ventas_detalles o
      JOIN consignaciones_ventas_detalles d ON d.id = c.destino AND d.eliminado = false
     WHERE o.id = c.origen AND o.eliminado = false
       AND d.id_consignacion = o.id_consignacion AND d.id_producto = o.id_producto
       AND UPPER(TRIM(COALESCE(d.lote,''))) = UPPER(TRIM(COALESCE(o.lote,'')))
       AND UPPER(TRIM(COALESCE(d.nup,'')))  = UPPER(TRIM(COALESCE(o.nup,'')))
       AND NOT EXISTS (SELECT 1 FROM retornos_cv_detalles x JOIN retornos_cv xr ON xr.id = x.id_retorno AND xr.eliminado = false
                        WHERE x.id_consignacion_detalle = d.id AND x.eliminado = false)
       AND NOT EXISTS (SELECT 1 FROM consignaciones_facturas_detalles x JOIN consignaciones_facturas xf ON xf.id = x.id_consignacion_factura AND xf.eliminado = false
                        WHERE x.id_consignacion_detalle = d.id AND COALESCE(x.eliminado, false) = false);
    IF v_n = 0 THEN
      RAISE NOTICE 'Línea %: la gemela % ya no está libre o no coincide; no se toca.', c.origen, c.destino;
      CONTINUE;
    END IF;

    SELECT d.id INTO v_linea
      FROM retornos_cv_detalles d
      JOIN retornos_cv r ON r.id = d.id_retorno AND r.eliminado = false AND r.estado = 'Emitida'
     WHERE d.id_consignacion_detalle = c.origen AND d.eliminado = false
     ORDER BY d.id DESC LIMIT 1;
    IF v_linea IS NULL THEN
      RAISE NOTICE 'Línea %: no tiene retorno que mover; no se toca.', c.origen;
      CONTINUE;
    END IF;

    INSERT INTO respaldo_reenlace_lote_20260923 (documento_tipo, id_linea, id_consignacion, lote, nup, linea_anterior, linea_nueva)
    SELECT 'Retorno', d.id, d.id_consignacion, d.lote, d.nup, c.origen, c.destino
      FROM retornos_cv_detalles d WHERE d.id = v_linea
    ON CONFLICT (documento_tipo, id_linea) DO NOTHING;

    UPDATE retornos_cv_detalles SET id_consignacion_detalle = c.destino, updated_at = now()
     WHERE id = v_linea AND id_consignacion_detalle = c.origen;
    GET DIAGNOSTICS v_n = ROW_COUNT;
    v_hechos := v_hechos + v_n;
    RAISE NOTICE 'Línea de retorno % movida de la línea % a la %.', v_linea, c.origen, c.destino;
  END LOOP;
  RAISE NOTICE 'Total re-enlazadas: %', v_hechos;
END $$;
