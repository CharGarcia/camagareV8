-- =============================================================================
-- Fusiona las cuentas DUPLICADAS que dejó la migración vieja (formato viejo, nivel 3 con 2
-- dígitos '0X') hacia su cuenta EQUIVALENTE del plan nativo (formato casa, nivel 3 con 1 dígito).
--
-- Caso: la empresa tiene DOS planes conviviendo — el nativo (1.1.1.02.001) y el migrado
-- (1.1.01.02.001). `reformatear` no pudo cambiar el código viejo porque el destino ya existe
-- (colisión). Este script, en vez de reformatear, REPUNTA todo lo que apunta a la cuenta vieja
-- hacia la nativa y luego SOFT-BORRA la vieja. Así el balance queda solo con los códigos nuevos.
--
-- Repunta estas 8 columnas (todas las que referencian plan_cuentas):
--   asientos_contables_detalle.id_cuenta_contable, asientos_programados.id_cuenta,
--   clientes.id_cuenta_ingreso, egresos_conceptos.id_cuenta_contable,
--   empresa_formas_pago.id_cuenta_contable, empresa_opciones_ingreso_egreso.id_cuenta_contable,
--   productos.id_cuenta_ingreso, proveedores.id_cuenta_gasto
--
-- Solo fusiona cuando existe la cuenta nativa equivalente (3er segmento 0X→X). Las de nivel-3 >= 10
-- (sin equivalente por reformato) se dejan. Ajustar v_emp. Idempotente. Correr PREVIEW primero.
-- =============================================================================

-- PREVIEW: qué se fusionaría (vieja -> nativa). Cambiar el 1 por tu empresa.
/*
SELECT vieja.codigo AS codigo_viejo, vieja.nombre AS nombre_viejo,
       nueva.id AS id_nativa, nueva.codigo AS codigo_nativo, nueva.nombre AS nombre_nativo
FROM plan_cuentas vieja
JOIN plan_cuentas nueva
  ON nueva.id_empresa = vieja.id_empresa AND nueva.eliminado = false AND nueva.id <> vieja.id
 AND nueva.codigo = regexp_replace(vieja.codigo, '^([0-9]+\.[0-9]+\.)0([1-9])', '\1\2')
WHERE vieja.id_empresa = 1 AND vieja.eliminado = false
  AND vieja.codigo ~ '^[0-9]+\.[0-9]+\.0[1-9]'
ORDER BY vieja.codigo;
*/

DO $$
DECLARE
  v_emp   INT := 1;   -- <<< AJUSTAR: id de la empresa
  v_user  INT := 1;   -- deleted_by de las cuentas viejas
  r       RECORD;
  v_new   INT;
  v_house TEXT;
  cols    TEXT[][] := ARRAY[
    ARRAY['asientos_contables_detalle','id_cuenta_contable'],
    ARRAY['asientos_programados','id_cuenta'],
    ARRAY['clientes','id_cuenta_ingreso'],
    ARRAY['egresos_conceptos','id_cuenta_contable'],
    ARRAY['empresa_formas_pago','id_cuenta_contable'],
    ARRAY['empresa_opciones_ingreso_egreso','id_cuenta_contable'],
    ARRAY['productos','id_cuenta_ingreso'],
    ARRAY['proveedores','id_cuenta_gasto']
  ];
  cc      TEXT[];
  n_merge INT := 0;
BEGIN
  FOR r IN
    SELECT id, codigo FROM plan_cuentas
    WHERE id_empresa = v_emp AND eliminado = false
      AND codigo ~ '^[0-9]+\.[0-9]+\.0[1-9]'
  LOOP
    v_house := regexp_replace(r.codigo, '^([0-9]+\.[0-9]+\.)0([1-9])', '\1\2');
    IF v_house = r.codigo THEN CONTINUE; END IF;

    SELECT id INTO v_new FROM plan_cuentas
     WHERE id_empresa = v_emp AND eliminado = false AND id <> r.id AND codigo = v_house
     LIMIT 1;
    IF v_new IS NULL THEN CONTINUE; END IF;   -- sin nativa equivalente: no se fusiona

    FOREACH cc SLICE 1 IN ARRAY cols LOOP
      EXECUTE format('UPDATE %I SET %I = %s WHERE %I = %s', cc[1], cc[2], v_new, cc[2], r.id);
    END LOOP;

    UPDATE plan_cuentas SET eliminado = true, deleted_at = now(), deleted_by = v_user WHERE id = r.id;
    n_merge := n_merge + 1;
    RAISE NOTICE 'Fusionada % (id %) -> % (id %)', r.codigo, r.id, v_house, v_new;
  END LOOP;
  RAISE NOTICE 'Cuentas fusionadas: %.', n_merge;
END $$;
