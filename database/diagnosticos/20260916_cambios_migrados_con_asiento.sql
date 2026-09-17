-- =============================================================================
-- Cambios de productos MIGRADOS del sistema anterior que recibieron un asiento contable.
--
-- Regla: los cambios que trajo la migración NO llevan asiento, porque el sistema anterior no
-- contabilizaba los cambios de productos. Hasta el 16-09-2026 igual se les podía generar uno
-- automático al abrir Estados Financieros / Asientos contables (sincronización), desde Auditoría
-- contable o al abrir la pestaña "Asiento contable" del cambio. Desde ese cambio el sistema ya no
-- lo hace; este script DETECTA y QUITA los que se generaron antes.
--
-- Toca datos: solo el PASO 2. No destructivo: los asientos se eliminan de forma lógica
-- (eliminado = true, deleted_at, deleted_by) y el cambio deja de apuntar a ellos
-- (id_asiento_contable = NULL). Nunca toca asientos migrados (modulo_origen = 'migracion') ni
-- los cambios hechos en este sistema. Idempotente: volver a correrlo no hace nada.
-- Revertir: en las cabeceras y líneas de los asientos listados en el PASO 1, volver a poner
-- eliminado = false, deleted_at = NULL, deleted_by = NULL, y volver a enlazar
-- cambios_producto_cv.id_asiento_contable con los pares id_cambio / id_asiento de esa lista.
--
-- Uso en pgAdmin (misma sesión, en este orden):
--   PASO 1  Diagnóstico (solo lectura). Ajustar `v_emp` (0 = todas las empresas) y ejecutar el
--           bloque del PASO 1. Deja la tabla temporal _cmg_cambios_asiento y la lista.
--           Mire fecha_asiento: si algún asiento cae en un periodo contable ya cerrado,
--           revíselo con contabilidad antes del PASO 2.
--   PASO 2  Corrección. Ajustar `v_user` y ejecutar el DO block.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- PASO 1 · DIAGNÓSTICO (solo lectura)
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS _cmg_cambios_asiento;
CREATE TEMP TABLE _cmg_cambios_asiento AS
WITH p AS (
    SELECT 0::int AS v_emp                      -- <<< AJUSTAR: id de la empresa (0 = todas)
),
migrados AS (
    SELECT c.id, c.id_empresa, c.serie || '-' || c.secuencial AS numero, c.fecha_cambio, c.estado, c.id_asiento_contable
      FROM cambios_producto_cv c
      JOIN migracion_mysql_map m
        ON m.entidad = 'cambios_producto' AND m.id_destino = c.id
       AND m.id_empresa = c.id_empresa AND m.vinculado IS NOT TRUE
     CROSS JOIN p
     WHERE c.eliminado = false
       AND (p.v_emp = 0 OR c.id_empresa = p.v_emp)
)
SELECT mg.id_empresa,
       mg.id                                               AS id_cambio,
       mg.numero                                           AS numero_cambio,
       mg.fecha_cambio,
       mg.estado                                           AS estado_cambio,
       a.id                                                AS id_asiento,
       a.numero_comprobante,
       a.fecha_asiento,
       a.estado                                            AS estado_asiento,
       COALESCE(a.id = mg.id_asiento_contable, false)      AS enlazado_al_cambio,
       (SELECT COALESCE(SUM(d.debe), 0)
          FROM asientos_contables_detalle d
         WHERE d.id_asiento = a.id AND d.eliminado = false) AS total_debe
  FROM migrados mg
  JOIN asientos_contables_cabecera a
    ON a.id_empresa = mg.id_empresa
   AND a.eliminado = false
   AND a.modulo_origen <> 'migracion'
   AND (a.id = mg.id_asiento_contable
        OR (a.modulo_origen = 'cambio_producto_cv' AND a.id_referencia_origen = mg.id));

-- Lista de los asientos que se quitarían:
SELECT * FROM _cmg_cambios_asiento ORDER BY id_empresa, fecha_cambio, id_cambio;

-- Resumen por empresa:
SELECT id_empresa, COUNT(DISTINCT id_cambio) AS cambios, COUNT(*) AS asientos, SUM(total_debe) AS total_debe
  FROM _cmg_cambios_asiento
 GROUP BY id_empresa
 ORDER BY id_empresa;


-- ---------------------------------------------------------------------------
-- PASO 2 · CORRECCIÓN (DO block, NO destructivo, idempotente)
--   Requiere haber corrido el PASO 1 en esta misma sesión.
-- ---------------------------------------------------------------------------
DO $$
DECLARE
  v_user INT := 1;   -- <<< AJUSTAR: usuario que queda como deleted_by / updated_by
  n_cab  INT := 0;
  n_det  INT := 0;
  n_enl  INT := 0;
BEGIN
  IF to_regclass('pg_temp._cmg_cambios_asiento') IS NULL THEN
    RAISE EXCEPTION 'Corra primero el PASO 1 (diagnóstico) en esta misma sesión: falta la tabla temporal _cmg_cambios_asiento.';
  END IF;

  -- 1) Líneas y cabeceras de esos asientos: eliminación lógica (nunca los 'migracion').
  UPDATE asientos_contables_detalle
     SET eliminado = true, deleted_at = now(), deleted_by = v_user
   WHERE id_asiento IN (SELECT id_asiento FROM _cmg_cambios_asiento)
     AND eliminado = false;
  GET DIAGNOSTICS n_det = ROW_COUNT;

  UPDATE asientos_contables_cabecera
     SET eliminado = true, deleted_at = now(), deleted_by = v_user
   WHERE id IN (SELECT id_asiento FROM _cmg_cambios_asiento)
     AND modulo_origen <> 'migracion'
     AND eliminado = false;
  GET DIAGNOSTICS n_cab = ROW_COUNT;

  -- 2) El cambio deja de apuntar al asiento quitado.
  UPDATE cambios_producto_cv c
     SET id_asiento_contable = NULL, updated_at = now(), updated_by = v_user
   WHERE c.id IN (SELECT id_cambio FROM _cmg_cambios_asiento)
     AND c.id_asiento_contable IN (SELECT id_asiento FROM _cmg_cambios_asiento);
  GET DIAGNOSTICS n_enl = ROW_COUNT;

  RAISE NOTICE 'Asientos quitados (eliminación lógica): % cabeceras, % líneas; cambios desenlazados: %',
               n_cab, n_det, n_enl;
END $$;

-- Verificación: volver a correr el PASO 1; la lista debe salir vacía (0 filas).
