-- =============================================================================
-- Reformatea los códigos de cuenta al formato del plan NUEVO (de la casa):
-- el nivel 3 pasa de 2 dígitos '0X' a 1 dígito 'X' (p. ej. 3.1.01.01.001 -> 3.1.1.01.001).
--
-- Para qué: la migración de contabilidad creó cuentas con el código del sistema viejo
-- (nivel 3 con 2 dígitos). Este script las deja en el formato de la casa SIN re-migrar:
-- como los asientos enlazan la cuenta por su id (no por el texto del código), al cambiar
-- solo la columna `codigo` los asientos ya migrados muestran el código nuevo automáticamente.
--
-- Seguridad:
--   - Solo toca cuentas cuyo 3er segmento sea 0X (valores 01–09). Los >= 10 no caben en 1
--     dígito y se dejan igual.
--   - Si el código destino YA EXISTE en otra cuenta de la misma empresa (colisión), NO cambia
--     esa cuenta y la reporta por NOTICE, para revisión manual (no fusiona a ciegas).
--   - Ajustar v_emp. Idempotente (correrlo dos veces no hace daño).
--
-- RECOMENDADO: correr primero el PREVIEW de abajo para ver qué cambiaría y qué colisiona.
-- =============================================================================

-- PREVIEW (solo lectura): qué se reformatearía y qué colisiona. Cambiar el 1 por tu empresa.
/*
SELECT pc.codigo AS actual,
       regexp_replace(pc.codigo, '^([0-9]+\.[0-9]+\.)0([1-9])\.', '\1\2.') AS nuevo,
       CASE WHEN EXISTS (
               SELECT 1 FROM plan_cuentas x
               WHERE x.id_empresa = pc.id_empresa AND x.eliminado = false AND x.id <> pc.id
                 AND x.codigo = regexp_replace(pc.codigo, '^([0-9]+\.[0-9]+\.)0([1-9])\.', '\1\2.')
            ) THEN 'COLISIÓN (no se cambia)' ELSE 'ok' END AS estado,
       pc.nombre
FROM plan_cuentas pc
WHERE pc.id_empresa = 1                              -- <<< tu empresa
  AND pc.eliminado = false
  AND pc.codigo ~ '^[0-9]+\.[0-9]+\.0[1-9]\.'
ORDER BY pc.codigo;
*/

DO $$
DECLARE
  v_emp   INT := 1;   -- <<< AJUSTAR: id de la empresa
  r       RECORD;
  v_nuevo TEXT;
  n_ok    INT := 0;
  n_colis INT := 0;
BEGIN
  FOR r IN
    SELECT id, codigo, nombre
    FROM plan_cuentas
    WHERE id_empresa = v_emp AND eliminado = false
      AND codigo ~ '^[0-9]+\.[0-9]+\.0[1-9]\.'   -- 3er segmento = 0X (01–09)
  LOOP
    v_nuevo := regexp_replace(r.codigo, '^([0-9]+\.[0-9]+\.)0([1-9])\.', '\1\2.');
    IF EXISTS (
        SELECT 1 FROM plan_cuentas x
        WHERE x.id_empresa = v_emp AND x.eliminado = false AND x.id <> r.id AND x.codigo = v_nuevo
    ) THEN
      n_colis := n_colis + 1;
      RAISE NOTICE 'COLISIÓN: % -> % ("%") ya existe otra cuenta con ese código; NO se cambia.', r.codigo, v_nuevo, r.nombre;
    ELSE
      UPDATE plan_cuentas SET codigo = v_nuevo, updated_at = now() WHERE id = r.id;
      n_ok := n_ok + 1;
    END IF;
  END LOOP;
  RAISE NOTICE 'Reformateadas: %. Colisiones sin cambiar (revisar a mano): %.', n_ok, n_colis;
END $$;
