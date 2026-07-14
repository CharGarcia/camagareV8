-- =============================================================================
-- Arregla TODOS los códigos de cuenta viejos (nivel 3 con 2 dígitos '0X') al formato de la casa
-- (nivel 3 con 1 dígito 'X'). Dos casos, resueltos automáticamente:
--   A) La cuenta vieja tiene GEMELA NATIVA (mismo código en formato casa ya existe) → FUSIÓN:
--      repunta las 8 columnas que referencian plan_cuentas hacia la nativa y soft-borra la vieja.
--   B) NO tiene gemela → REFORMATEA su código en el sitio (no hay colisión).
-- Los asientos enlazan la cuenta por id, así que en ambos casos el balance pasa a mostrar el código
-- nuevo automáticamente. Las de nivel-3 >= 10 (no reformateables) se dejan.
--
-- NO borra asientos. Ajustar v_emp. Idempotente. Viene en modo prueba (ROLLBACK); revisa los NOTICE
-- y si todo bien cambia ROLLBACK por COMMIT.
-- =============================================================================

BEGIN;

DO $$
DECLARE
  v_emp   INT := 1;   -- <<< AJUSTAR: id de la empresa
  v_user  INT := 1;
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
  cc TEXT[]; r RECORD; v_new INT; v_house TEXT;
  n_fus INT := 0; n_ref INT := 0;
BEGIN
  FOR r IN
    SELECT id, codigo FROM plan_cuentas
    WHERE id_empresa = v_emp AND eliminado = false AND codigo ~ '^[0-9]+\.[0-9]+\.0[1-9]'
    ORDER BY codigo
  LOOP
    v_house := regexp_replace(r.codigo, '^([0-9]+\.[0-9]+\.)0([1-9])', '\1\2');
    IF v_house = r.codigo THEN CONTINUE; END IF;

    SELECT id INTO v_new FROM plan_cuentas
     WHERE id_empresa = v_emp AND eliminado = false AND id <> r.id AND codigo = v_house LIMIT 1;

    IF v_new IS NOT NULL THEN
      -- A) FUSIÓN hacia la gemela nativa
      FOREACH cc SLICE 1 IN ARRAY v_refs LOOP
        EXECUTE format('UPDATE %I SET %I = %s WHERE %I = %s', cc[1], cc[2], v_new, cc[2], r.id);
      END LOOP;
      UPDATE plan_cuentas SET eliminado = true, deleted_at = now(), deleted_by = v_user WHERE id = r.id;
      n_fus := n_fus + 1;
      RAISE NOTICE 'Fusionada % (id %) -> % (id %)', r.codigo, r.id, v_house, v_new;
    ELSE
      -- B) REFORMATO en el sitio
      UPDATE plan_cuentas SET codigo = v_house, updated_at = now(), updated_by = v_user WHERE id = r.id;
      n_ref := n_ref + 1;
      RAISE NOTICE 'Reformateada % (id %) -> %', r.codigo, r.id, v_house;
    END IF;
  END LOOP;

  RAISE NOTICE 'TOTAL empresa %: fusionadas=%, reformateadas=%.', v_emp, n_fus, n_ref;
END $$;

-- Revisa los NOTICE. Si todo bien: cambia ROLLBACK por COMMIT y vuelve a ejecutar.
ROLLBACK;
