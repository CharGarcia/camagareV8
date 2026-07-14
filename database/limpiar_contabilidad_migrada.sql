-- =============================================================================
-- Limpia la CONTABILIDAD MIGRADA de una empresa para volver a migrarla desde cero
-- con las reglas nuevas (plan de cuentas en formato casa, códigos convertidos, etc.).
--
-- Qué borra (solo lo que trajo la migración; NO toca lo nativo):
--   1. Enlaces id_asiento_contable de los documentos que apuntan a asientos 'migracion'.
--   2. Asientos migrados: asientos_contables_cabecera + _detalle con modulo_origen='migracion'.
--   3. El mapa de migración de contabilidad (para que la próxima corrida re-inserte, no reconcilie).
--   4. (Opcional) Cuentas del plan con formato VIEJO (3er segmento de 2 dígitos) que queden SIN USO,
--      para que al re-migrar `migrarPlanCuentas` arme el plan limpio en formato casa.
--
-- ⚠ Es un BORRADO FÍSICO de artefactos de migración (no datos capturados por el usuario).
-- NO toca asientos nativos (modulo_origen <> 'migracion') ni cuentas en uso por ellos.
-- Ajustar v_emp. Correr el PREVIEW primero. Idempotente.
-- =============================================================================

-- PREVIEW (solo lectura). Cambiar el 1 por tu empresa.
/*
SELECT
  (SELECT COUNT(*) FROM asientos_contables_cabecera WHERE id_empresa = 1 AND modulo_origen = 'migracion')            AS asientos_migracion,
  (SELECT COUNT(*) FROM asientos_contables_detalle d JOIN asientos_contables_cabecera c ON c.id = d.id_asiento
     WHERE c.id_empresa = 1 AND c.modulo_origen = 'migracion')                                                        AS detalle,
  (SELECT COUNT(*) FROM migracion_mysql_map WHERE id_empresa = 1 AND entidad = 'contabilidad')                        AS map_contabilidad,
  (SELECT COUNT(*) FROM plan_cuentas pc WHERE pc.id_empresa = 1 AND pc.eliminado = false
     AND pc.codigo ~ '^[0-9]+\.[0-9]+\.[0-9]{2}\.'
     AND NOT EXISTS (SELECT 1 FROM asientos_contables_detalle d WHERE d.id_cuenta_contable = pc.id))                  AS cuentas_viejas_sin_uso;
*/

DO $$
DECLARE
  v_emp   INT := 1;   -- <<< AJUSTAR: id de la empresa
  v_user  INT := 1;   -- usuario que queda como deleted_by
  v_asi   INT[];
  v_docs  TEXT[] := ARRAY[
    'compras_cabecera','ventas_cabecera','ingresos_cabecera','egresos_cabecera',
    'retencion_venta_cabecera','retencion_compra_cabecera','notas_credito_cabecera',
    'recibos_venta_cabecera','liquidaciones_cabecera','consignaciones_ventas'
  ];
  t       TEXT;
  n_det   INT := 0;
  n_cab   INT := 0;
  n_map   INT := 0;
  n_cta   INT := 0;
BEGIN
  SELECT array_agg(id) INTO v_asi
  FROM asientos_contables_cabecera
  WHERE id_empresa = v_emp AND modulo_origen = 'migracion';

  IF v_asi IS NOT NULL THEN
    -- 1) limpiar los enlaces de los documentos hacia esos asientos
    FOREACH t IN ARRAY v_docs LOOP
      EXECUTE format(
        'UPDATE %I SET id_asiento_contable = NULL WHERE id_empresa = %s AND id_asiento_contable = ANY($1)',
        t, v_emp
      ) USING v_asi;
    END LOOP;

    -- 2) borrar detalle y cabecera de los asientos migrados
    DELETE FROM asientos_contables_detalle WHERE id_asiento = ANY(v_asi);
    GET DIAGNOSTICS n_det = ROW_COUNT;
    DELETE FROM asientos_contables_cabecera WHERE id = ANY(v_asi);
    GET DIAGNOSTICS n_cab = ROW_COUNT;
  END IF;

  -- 3) borrar el mapa de contabilidad
  DELETE FROM migracion_mysql_map WHERE id_empresa = v_emp AND entidad = 'contabilidad';
  GET DIAGNOSTICS n_map = ROW_COUNT;

  -- 4) (opcional) SOFT-DELETE de cuentas con formato viejo que quedaron SIN USO (no rompe FKs).
  --    Se excluyen las referenciadas por asientos (sobrevivientes) o por la config de asientos
  --    programados, para no ocultar cuentas en uso. Al re-migrar, `migrarPlanCuentas` re-arma el
  --    plan en formato casa (el listado y el matcheo ignoran las eliminadas).
  UPDATE plan_cuentas pc
     SET eliminado = true, deleted_at = now(), deleted_by = v_user
   WHERE pc.id_empresa = v_emp AND pc.eliminado = false
     AND pc.codigo ~ '^[0-9]+\.[0-9]+\.[0-9]{2}\.'
     AND NOT EXISTS (SELECT 1 FROM asientos_contables_detalle d WHERE d.id_cuenta_contable = pc.id)
     AND NOT EXISTS (SELECT 1 FROM asientos_programados ap WHERE ap.id_cuenta = pc.id);
  GET DIAGNOSTICS n_cta = ROW_COUNT;

  RAISE NOTICE 'Borrados -> asientos: %, detalle: %, mapa contabilidad: %, cuentas viejas sin uso: %.', n_cab, n_det, n_map, n_cta;
END $$;
