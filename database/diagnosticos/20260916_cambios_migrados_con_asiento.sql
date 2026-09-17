-- =============================================================================
-- Cambios de productos MIGRADOS del sistema anterior que recibieron un asiento contable.
--
-- Regla: los cambios que trajo la migración NO llevan asiento, porque el sistema anterior no
-- contabilizaba los cambios de productos. Hasta el 16-09-2026 igual se les podía generar uno
-- automático al abrir Estados Financieros / Asientos contables (sincronización), al abrir el
-- módulo de Cambios de productos, desde Auditoría contable o al abrir la pestaña "Asiento
-- contable" del cambio. Suelen ser de 0.01: el costo de lo devuelto y de lo entregado casi se
-- cancela y queda el centavo del redondeo. Desde ese día el sistema ya no los genera por ninguna
-- vía; este script DETECTA y QUITA los que se generaron antes.
--
-- Actualizado 17-09-2026: también detecta los asientos HUÉRFANOS, es decir, asientos de cambios
-- que ya no existen. "Eliminar migrados" (módulo Migración) borra los cambios migrados y su fila
-- del mapa, pero no sus asientos; en este sistema los cambios se eliminan de forma lógica, así
-- que un cambio inexistente solo puede venir de ahí. Se agregaron además la fecha en que se
-- generó cada asiento y si tiene líneas de 0.01.
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
--           bloque del PASO 1 (desde DROP TABLE hasta el resumen). Deja la tabla temporal
--           _cmg_cambios_asiento y la lista. Mire:
--             · generado_despues_del_arreglo: debería ser false en todos. Si alguno sale true,
--               NO corra el PASO 2 todavía y avise a soporte (habría otra vía que los genera).
--             · en_periodo_cerrado: el asiento cae en un periodo contable cerrado. Quitarlo
--               cambia ese periodo en centavos. Si de ese periodo ya se presentaron estados
--               financieros desde este sistema, confírmelo con contabilidad antes del PASO 2.
--   (17-09-2026: se agregó en_periodo_cerrado; antes había que revisar fecha_asiento a mano.)
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
),
asientos AS (
    -- a) Asientos de cambios migrados (enlazados al cambio o que lo tienen como origen)
    SELECT 'cambio migrado'::varchar       AS motivo,
           mg.id_empresa,
           mg.id::bigint                   AS id_cambio,
           mg.numero::varchar              AS numero_cambio,
           mg.fecha_cambio::date           AS fecha_cambio,
           mg.estado::varchar              AS estado_cambio,
           a.id                            AS id_asiento,
           a.numero_comprobante,
           a.fecha_asiento,
           a.created_at                    AS asiento_generado_el,
           a.estado                        AS estado_asiento,
           COALESCE(a.id = mg.id_asiento_contable, false) AS enlazado_al_cambio
      FROM migrados mg
      JOIN asientos_contables_cabecera a
        ON a.id_empresa = mg.id_empresa
       AND a.eliminado = false
       AND a.modulo_origen <> 'migracion'
       AND (a.id = mg.id_asiento_contable
            OR (a.modulo_origen = 'cambio_producto_cv' AND a.id_referencia_origen = mg.id))
    UNION ALL
    -- b) Huérfanos: asientos de cambios que ya no existen (borrados por "Eliminar migrados")
    SELECT 'huérfano: el cambio ya no existe'::varchar,
           a.id_empresa,
           a.id_referencia_origen,
           NULL::varchar, NULL::date, NULL::varchar,
           a.id, a.numero_comprobante, a.fecha_asiento, a.created_at, a.estado,
           false
      FROM asientos_contables_cabecera a
     CROSS JOIN p
     WHERE a.modulo_origen = 'cambio_producto_cv'
       AND a.eliminado = false
       AND a.id_referencia_origen IS NOT NULL
       AND (p.v_emp = 0 OR a.id_empresa = p.v_emp)
       AND NOT EXISTS (SELECT 1 FROM cambios_producto_cv c WHERE c.id = a.id_referencia_origen)
)
SELECT x.*,
       (SELECT COALESCE(SUM(d.debe), 0)
          FROM asientos_contables_detalle d
         WHERE d.id_asiento = x.id_asiento AND d.eliminado = false)                     AS total_debe,
       EXISTS (SELECT 1
                 FROM asientos_contables_detalle d
                WHERE d.id_asiento = x.id_asiento AND d.eliminado = false
                  AND GREATEST(d.debe, d.haber) = 0.01)                                AS tiene_linea_de_0_01,
       (x.asiento_generado_el::timestamp >= TIMESTAMP '2026-09-17 00:00')              AS generado_despues_del_arreglo,
       EXISTS (SELECT 1
                 FROM periodos_contables pc
                WHERE pc.id_empresa = x.id_empresa AND pc.eliminado = false
                  AND pc.status = 0                                     -- 0 = cerrado
                  AND x.fecha_asiento BETWEEN pc.fecha_inicial AND pc.fecha_final)     AS en_periodo_cerrado
  FROM asientos x;

-- Lista de los asientos que se quitarían:
SELECT * FROM _cmg_cambios_asiento ORDER BY id_empresa, motivo, fecha_asiento, id_asiento;

-- Resumen por empresa:
SELECT id_empresa,
       COUNT(*) FILTER (WHERE motivo = 'cambio migrado')  AS asientos_de_cambios_migrados,
       COUNT(*) FILTER (WHERE motivo <> 'cambio migrado') AS asientos_huerfanos,
       COUNT(*) FILTER (WHERE tiene_linea_de_0_01)        AS con_linea_de_0_01,
       SUM(total_debe)                                    AS total_debe,
       MIN(asiento_generado_el)                           AS primero_generado,
       MAX(asiento_generado_el)                           AS ultimo_generado,
       BOOL_OR(generado_despues_del_arreglo)              AS alguno_despues_del_arreglo,
       COUNT(*) FILTER (WHERE en_periodo_cerrado)         AS en_periodos_cerrados
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

  -- 2) El cambio deja de apuntar al asiento quitado (los huérfanos no tienen cambio).
  UPDATE cambios_producto_cv c
     SET id_asiento_contable = NULL, updated_at = now(), updated_by = v_user
   WHERE c.id IN (SELECT id_cambio FROM _cmg_cambios_asiento)
     AND c.id_asiento_contable IN (SELECT id_asiento FROM _cmg_cambios_asiento);
  GET DIAGNOSTICS n_enl = ROW_COUNT;

  RAISE NOTICE 'Asientos quitados (eliminación lógica): % cabeceras, % líneas; cambios desenlazados: %',
               n_cab, n_det, n_enl;
END $$;

-- Verificación: volver a correr el PASO 1; la lista debe salir vacía (0 filas).
