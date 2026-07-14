-- =============================================================================
-- RESET de la CONTABILIDAD MIGRADA de una empresa (para re-migrar desde cero con las reglas nuevas).
-- Hace, en orden y en UNA transacción:
--   1. Limpia los enlaces id_asiento_contable de los documentos hacia asientos 'migracion'.
--   2. Borra los asientos migrados (asientos_contables_cabecera + _detalle, modulo_origen='migracion').
--   3. Borra el mapa de migración de contabilidad.
--   4. FUSIONA las cuentas duplicadas del formato viejo (nivel 3 = 0X) hacia su equivalente NATIVA
--      (formato casa): repunta las 8 columnas que referencian plan_cuentas y soft-borra la vieja.
--   5. SOFT-BORRA las cuentas viejas restantes que quedaron SIN uso (sin equivalente nativa).
--
-- NO toca asientos nativos (modulo_origen <> 'migracion'). Ajustar v_emp. Idempotente.
-- SUGERENCIA: correr primero con la última línea (ROLLBACK) activa para ver los NOTICE; si todo
-- luce bien, cambiar ROLLBACK por COMMIT.
-- =============================================================================

BEGIN;

DO $$
DECLARE
  v_emp   INT := 1;   -- <<< AJUSTAR: id de la empresa
  v_user  INT := 1;   -- deleted_by
  v_asi   INT[];
  v_docs  TEXT[] := ARRAY[
    'compras_cabecera','ventas_cabecera','ingresos_cabecera','egresos_cabecera',
    'retencion_venta_cabecera','retencion_compra_cabecera','notas_credito_cabecera',
    'recibos_venta_cabecera','liquidaciones_cabecera','consignaciones_ventas'
  ];
  v_refs  TEXT[][] := ARRAY[
    ARRAY['asientos_contables_detalle','id_cuenta_contable'],
    ARRAY['asientos_programados','id_cuenta'],
    ARRAY['clientes','id_cuenta_ingreso'],
    ARRAY['egresos_conceptos','id_cuenta_contable'],
    ARRAY['empresa_formas_pago','id_cuenta_contable'],
    ARRAY['empresa_opciones_ingreso_egreso','id_cuenta_contable'],
    ARRAY['productos','id_cuenta_ingreso'],
    ARRAY['proveedores','id_cuenta_gasto']
  ];
  t       TEXT;
  cc      TEXT[];
  r       RECORD;
  v_new   INT;
  v_house TEXT;
  n_cab INT := 0; n_det INT := 0; n_map INT := 0; n_fus INT := 0; n_sd INT := 0;
BEGIN
  -- ---- 1..3: borrar los asientos migrados, sus enlaces y el mapa ----
  SELECT array_agg(id) INTO v_asi
  FROM asientos_contables_cabecera
  WHERE id_empresa = v_emp AND modulo_origen = 'migracion';

  IF v_asi IS NOT NULL THEN
    FOREACH t IN ARRAY v_docs LOOP
      EXECUTE format('UPDATE %I SET id_asiento_contable = NULL WHERE id_empresa = %s AND id_asiento_contable = ANY($1)', t, v_emp) USING v_asi;
    END LOOP;
    DELETE FROM asientos_contables_detalle WHERE id_asiento = ANY(v_asi);
    GET DIAGNOSTICS n_det = ROW_COUNT;
    DELETE FROM asientos_contables_cabecera WHERE id = ANY(v_asi);
    GET DIAGNOSTICS n_cab = ROW_COUNT;
  END IF;

  DELETE FROM migracion_mysql_map WHERE id_empresa = v_emp AND entidad = 'contabilidad';
  GET DIAGNOSTICS n_map = ROW_COUNT;

  -- ---- 4: fusionar duplicados viejos hacia la nativa equivalente ----
  FOR r IN
    SELECT id, codigo FROM plan_cuentas
    WHERE id_empresa = v_emp AND eliminado = false AND codigo ~ '^[0-9]+\.[0-9]+\.0[1-9]'
  LOOP
    v_house := regexp_replace(r.codigo, '^([0-9]+\.[0-9]+\.)0([1-9])', '\1\2');
    IF v_house = r.codigo THEN CONTINUE; END IF;
    SELECT id INTO v_new FROM plan_cuentas
     WHERE id_empresa = v_emp AND eliminado = false AND id <> r.id AND codigo = v_house LIMIT 1;
    IF v_new IS NULL THEN CONTINUE; END IF;
    FOREACH cc SLICE 1 IN ARRAY v_refs LOOP
      EXECUTE format('UPDATE %I SET %I = %s WHERE %I = %s', cc[1], cc[2], v_new, cc[2], r.id);
    END LOOP;
    UPDATE plan_cuentas SET eliminado = true, deleted_at = now(), deleted_by = v_user WHERE id = r.id;
    n_fus := n_fus + 1;
    RAISE NOTICE 'Fusionada % (id %) -> % (id %)', r.codigo, r.id, v_house, v_new;
  END LOOP;

  -- ---- 5: soft-borrar las viejas restantes sin uso (sin equivalente nativa) ----
  UPDATE plan_cuentas pc
     SET eliminado = true, deleted_at = now(), deleted_by = v_user
   WHERE pc.id_empresa = v_emp AND pc.eliminado = false
     AND pc.codigo ~ '^[0-9]+\.[0-9]+\.[0-9]{2}\.'
     AND NOT EXISTS (SELECT 1 FROM asientos_contables_detalle d WHERE d.id_cuenta_contable = pc.id)
     AND NOT EXISTS (SELECT 1 FROM asientos_programados ap WHERE ap.id_cuenta = pc.id);
  GET DIAGNOSTICS n_sd = ROW_COUNT;

  RAISE NOTICE 'RESET empresa %: asientos=%, detalle=%, mapa=%, cuentas fusionadas=%, cuentas viejas soft-borradas=%.', v_emp, n_cab, n_det, n_map, n_fus, n_sd;
END $$;

-- Revisa los NOTICE. Si todo luce bien, cambia ROLLBACK por COMMIT y vuelve a ejecutar.
ROLLBACK;
