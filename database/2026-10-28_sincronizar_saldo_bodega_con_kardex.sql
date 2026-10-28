-- =====================================================================================
-- Corrección: igualar el saldo de bodega (productos_bodegas.stock_actual) al kardex.
--
-- Por qué: el kardex (inventario_kardex) es el que el sistema usa para validar ventas,
-- transferencias, etc. (InventarioRepository::getStockActual: movimientos vigentes del
-- ambiente actual de la empresa, redondeado a 2 decimales). El saldo de bodega es una copia
-- que muestran Existencias, el Reporte de inventarios y Trazabilidad. Varios procesos lo
-- escribían mal (tope en 0 de ajustes, revert "tolerante" que forzaba 0, saldo de un solo lote
-- con lotes obligatorios, edición de facturas con dos líneas del mismo producto, migración
-- MySQL que corregía el signo del kardex sin recalcular el saldo). Desde el cambio del
-- 2026-10-28 el saldo se toma siempre del kardex; este script corrige lo que quedó antes.
--
-- ORDEN: desplegar primero el código (git pull) y después correr esto. Con el código viejo
-- algunos procesos volverían a desfasar el saldo.
--
-- Qué hace (todo o nada, en un solo bloque):
--   1. Crea (si no existe) la tabla de respaldo respaldo_saldo_bodega_20261028.
--   2. Por cada producto/bodega desfasado: toma el candado de stock del sistema
--      ('stock:' || empresa || ':' || producto || ':' || bodega), guarda el saldo anterior y
--      el nuevo en el respaldo y deja stock_actual = kardex.
--   3. Registra una línea por empresa en log_sistema.
--
-- v_ruc NULL = todas las empresas; con un RUC, solo esa. v_excluir: RUCs que no se tocan. v_usuario: quién queda en
-- updated_by y en log_sistema.
--
-- REVERTIR (solo si hiciera falta): ver el bloque al final del archivo.
-- =====================================================================================

DO $$
DECLARE
  v_ruc     VARCHAR := NULL;   -- p. ej. '1793209472001'; NULL = todas
  -- Empresas que NO se tocan (revisar primero con 20261028_saldo_bodega_vs_kardex_por_origen.sql):
  -- 1792708389001 (ASAMED) revisada el 28-10: su saldo era el de un lote (lotes obligatorios); se sincroniza.
  v_excluir VARCHAR[] := ARRAY['1793209472001'];
  v_usuario INT     := 0;      -- <<< id_usuario que firma la corrección
  r         RECORD;
  v_real    NUMERIC;
  v_n       INT := 0;
  v_emp     INT := -1;
  v_emp_n   INT := 0;
BEGIN
  IF v_usuario = 0 THEN RAISE EXCEPTION 'Coloca el id_usuario en v_usuario'; END IF;

  CREATE TABLE IF NOT EXISTS respaldo_saldo_bodega_20261028 (
      id_empresa      INT,
      id_producto     INT,
      id_bodega       INT,
      stock_anterior  NUMERIC,
      stock_kardex    NUMERIC,
      corregido_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  );

  -- Solo los saldos descuadrados: un candado por fila de TODAS las bodegas agotaba la memoria
  -- de candados de PostgreSQL ("out of shared memory", max_locks_per_transaction). Cada uno se
  -- vuelve a comprobar bajo su candado más abajo.
  FOR r IN
      SELECT pb.id_empresa, pb.id_producto, pb.id_bodega, pb.stock_actual,
             CAST(e.tipo_ambiente AS VARCHAR(1)) AS amb
      FROM productos_bodegas pb
      JOIN empresas e ON e.id = pb.id_empresa
      LEFT JOIN LATERAL (
          SELECT SUM(k.cantidad) AS suma FROM inventario_kardex k
          WHERE k.id_empresa = pb.id_empresa AND k.id_producto = pb.id_producto AND k.id_bodega = pb.id_bodega
            AND k.eliminado = false AND k.tipo_ambiente = CAST(e.tipo_ambiente AS VARCHAR(1))
      ) k ON true
      WHERE pb.eliminado = false
        AND (v_ruc IS NULL OR e.ruc = v_ruc)
        AND NOT (e.ruc = ANY (v_excluir))
        AND ABS(pb.stock_actual - ROUND(COALESCE(k.suma, 0), 2)) > 0.001
      ORDER BY pb.id_empresa, pb.id_producto, pb.id_bodega
  LOOP
    -- Mismo candado que InventarioRepository::lockStock (se libera al terminar el bloque).
    PERFORM pg_advisory_xact_lock(hashtext('stock:' || r.id_empresa || ':' || r.id_producto || ':' || r.id_bodega));

    SELECT ROUND(COALESCE(SUM(k.cantidad), 0), 2) INTO v_real
      FROM inventario_kardex k
     WHERE k.id_empresa = r.id_empresa AND k.id_producto = r.id_producto AND k.id_bodega = r.id_bodega
       AND k.eliminado = false AND k.tipo_ambiente = r.amb;

    -- Releer bajo candado: un movimiento pudo cambiarlo mientras se esperaba.
    SELECT stock_actual INTO r.stock_actual FROM productos_bodegas
     WHERE id_empresa = r.id_empresa AND id_producto = r.id_producto AND id_bodega = r.id_bodega;

    IF ABS(r.stock_actual - v_real) > 0.001 THEN
      INSERT INTO respaldo_saldo_bodega_20261028 (id_empresa, id_producto, id_bodega, stock_anterior, stock_kardex)
      VALUES (r.id_empresa, r.id_producto, r.id_bodega, r.stock_actual, v_real);

      UPDATE productos_bodegas
         SET stock_actual = v_real, updated_by = v_usuario, updated_at = CURRENT_TIMESTAMP
       WHERE id_empresa = r.id_empresa AND id_producto = r.id_producto AND id_bodega = r.id_bodega;

      v_n := v_n + 1;
      IF r.id_empresa <> v_emp THEN
        IF v_emp <> -1 THEN
          INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro, datos_anteriores, datos_nuevos, ip_usuario, user_agent)
          VALUES (v_usuario, v_emp, 'SINCRONIZAR_SALDO_KARDEX', 'productos_bodegas', NULL, NULL,
                  jsonb_build_object('filas_corregidas', v_emp_n, 'respaldo', 'respaldo_saldo_bodega_20261028'), NULL, 'SQL 2026-10-28');
        END IF;
        v_emp := r.id_empresa; v_emp_n := 0;
      END IF;
      v_emp_n := v_emp_n + 1;
    END IF;
  END LOOP;

  IF v_emp <> -1 THEN
    INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro, datos_anteriores, datos_nuevos, ip_usuario, user_agent)
    VALUES (v_usuario, v_emp, 'SINCRONIZAR_SALDO_KARDEX', 'productos_bodegas', NULL, NULL,
            jsonb_build_object('filas_corregidas', v_emp_n, 'respaldo', 'respaldo_saldo_bodega_20261028'), NULL, 'SQL 2026-10-28');
  END IF;

  RAISE NOTICE 'Saldos corregidos: %', v_n;
END $$;

-- Verificación (debe devolver 0 filas): la consulta 3 de
-- database/diagnosticos/20261027_kardex_vivo_documentos_eliminados.sql

-- -------------------------------------------------------------------------------------
-- REVERTIR (deja el saldo como estaba antes de esta corrección). No correr junto con lo de arriba.
-- -------------------------------------------------------------------------------------
-- UPDATE productos_bodegas pb
--    SET stock_actual = r.stock_anterior, updated_at = CURRENT_TIMESTAMP
--   FROM respaldo_saldo_bodega_20261028 r
--  WHERE pb.id_empresa = r.id_empresa AND pb.id_producto = r.id_producto AND pb.id_bodega = r.id_bodega;
