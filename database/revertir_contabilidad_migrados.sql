-- =============================================================================
-- Revertir la contabilidad AUTO-GENERADA sobre documentos traídos por la MIGRACIÓN
-- del sistema viejo (MySQL). Se usa cuando por error se corrió "generar contabilidad"
-- (sincronizador de asientos) sobre los documentos migrados: esos documentos NO deben
-- tener asiento propio, porque su contabilidad se migra aparte como HISTÓRICO
-- (asientos_contables_cabecera.modulo_origen = 'migracion').
--
-- Qué hace, de forma NO destructiva:
--   1) Marca como eliminados (soft-delete) los asientos generados sobre documentos
--      migrados (cabecera + detalle).  NUNCA toca los asientos con
--      modulo_origen='migracion' (el histórico que sí queremos conservar).
--   2) Limpia el enlace del documento (id_asiento_contable = NULL) para que, con la
--      corrección del sincronizador ya desplegada, no se le vuelva a generar asiento.
--
-- Ajustar v_emp al id de la empresa a corregir. Para procesar TODAS las empresas
-- migradas, poner v_emp := 0 (0 = sin filtro de empresa).
-- Es idempotente: correrlo dos veces no hace daño.
-- =============================================================================

-- (Opcional) PREVIEW: cuántos asientos se revertirían por entidad, sin cambiar nada.
-- Cambiar 1 por el id de empresa (o quitar "AND m.id_empresa = 1" para todas).
-- -----------------------------------------------------------------------------
-- PREVIEW / DIAGNÓSTICO (solo lectura). Correr ESTO PRIMERO y revisar la lista.
-- Muestra fila por fila EXACTAMENTE lo que el DO block de abajo revertiría:
-- documentos INSERTADOS por la migración (vinculado=false) cuyo asiento es
-- autogenerado (modulo_origen<>'migracion'). Cambiar el 1 por tu id de empresa.
-- Si una fila te sorprende, NO corras el revert y avísame.
-- -----------------------------------------------------------------------------
/*
WITH docs AS (
            SELECT 'facturas'::text AS entidad, id, id_asiento_contable, id_empresa FROM ventas_cabecera          WHERE eliminado = false
  UNION ALL SELECT 'liquidaciones',            id, id_asiento_contable, id_empresa FROM liquidaciones_cabecera     WHERE eliminado = false
  UNION ALL SELECT 'compras',                  id, id_asiento_contable, id_empresa FROM compras_cabecera           WHERE eliminado = false
  UNION ALL SELECT 'notas_credito',            id, id_asiento_contable, id_empresa FROM notas_credito_cabecera     WHERE eliminado = false
  UNION ALL SELECT 'retenciones_venta',        id, id_asiento_contable, id_empresa FROM retencion_venta_cabecera   WHERE eliminado = false
  UNION ALL SELECT 'retenciones_compra',       id, id_asiento_contable, id_empresa FROM retencion_compra_cabecera  WHERE eliminado = false
  UNION ALL SELECT 'ingresos',                 id, id_asiento_contable, id_empresa FROM ingresos_cabecera          WHERE eliminado = false
  UNION ALL SELECT 'egresos',                  id, id_asiento_contable, id_empresa FROM egresos_cabecera           WHERE eliminado = false
  UNION ALL SELECT 'consignaciones',           id, id_asiento_contable, id_empresa FROM consignaciones_ventas      WHERE eliminado = false
)
SELECT m.entidad,
       m.id_origen  AS id_viejo,
       m.id_destino AS id_nuevo,
       m.clave_natural,
       d.id_asiento_contable AS asiento,
       a.modulo_origen,
       a.total_debe
FROM migracion_mysql_map m
JOIN docs d ON d.entidad = m.entidad AND d.id = m.id_destino
JOIN asientos_contables_cabecera a
     ON a.id = d.id_asiento_contable AND a.eliminado = false
WHERE m.id_empresa = 1                 -- <<< tu empresa
  AND m.vinculado IS NOT TRUE          -- solo INSERTADOS (protege nativos vinculados)
  AND a.modulo_origen <> 'migracion'   -- solo asientos autogenerados
ORDER BY m.entidad, m.id_destino;

-- Resumen del mapa (cuántos insertados vs vinculados por entidad):
-- SELECT entidad, COUNT(*) total, COUNT(*) FILTER (WHERE vinculado IS NOT TRUE) insertados,
--        COUNT(*) FILTER (WHERE vinculado) vinculados
-- FROM migracion_mysql_map WHERE id_empresa = 1 GROUP BY entidad ORDER BY entidad;
*/

DO $$
DECLARE
  v_emp  INT := 1;   -- <<< AJUSTAR: id de la empresa a corregir (0 = todas las migradas)
  v_user INT := 1;   -- usuario que queda como deleted_by del revert
  pares TEXT[][] := ARRAY[
    ARRAY['facturas',            'ventas_cabecera'],
    ARRAY['liquidaciones',       'liquidaciones_cabecera'],
    ARRAY['compras',             'compras_cabecera'],
    ARRAY['notas_credito',       'notas_credito_cabecera'],
    ARRAY['retenciones_venta',   'retencion_venta_cabecera'],
    ARRAY['retenciones_compra',  'retencion_compra_cabecera'],
    ARRAY['ingresos',            'ingresos_cabecera'],
    ARRAY['egresos',             'egresos_cabecera'],
    ARRAY['consignaciones',      'consignaciones_ventas']
  ];
  par TEXT[];
  v_ent TEXT;
  v_tab TEXT;
  v_asi INT[];
  v_filtro_emp TEXT;
  v_tot_asi INT := 0;
  v_tot_doc INT := 0;
  v_cnt INT;
BEGIN
  v_filtro_emp := CASE WHEN v_emp > 0 THEN format(' AND m.id_empresa = %s', v_emp) ELSE '' END;

  FOREACH par SLICE 1 IN ARRAY pares LOOP
    v_ent := par[1];
    v_tab := par[2];

    -- Asientos AUTO-GENERADOS (modulo_origen<>'migracion') enlazados a documentos que la
    -- migración INSERTÓ. Se EXCLUYEN los 'vinculado'=true: esos son documentos NATIVOS del
    -- sistema nuevo que la migración solo enlazó por número SRI; su asiento es legítimo y NO
    -- se debe revertir.
    EXECUTE format(
      'SELECT array_agg(a.id)
         FROM migracion_mysql_map m
         JOIN %I t ON t.id = m.id_destino
         JOIN asientos_contables_cabecera a ON a.id = t.id_asiento_contable
        WHERE m.entidad = %L %s
          AND m.vinculado IS NOT TRUE
          AND t.id_asiento_contable IS NOT NULL
          AND a.modulo_origen <> ''migracion''',
      v_tab, v_ent, v_filtro_emp
    ) INTO v_asi;

    IF v_asi IS NULL THEN
      CONTINUE;
    END IF;

    -- 1) soft-delete del detalle de esos asientos
    UPDATE asientos_contables_detalle
       SET eliminado = true, deleted_at = now(), deleted_by = v_user
     WHERE id_asiento = ANY(v_asi) AND eliminado = false;

    -- 2) soft-delete de la cabecera (blindado: nunca el histórico migrado)
    UPDATE asientos_contables_cabecera
       SET eliminado = true, deleted_at = now(), deleted_by = v_user
     WHERE id = ANY(v_asi) AND modulo_origen <> 'migracion' AND eliminado = false;
    GET DIAGNOSTICS v_cnt = ROW_COUNT;
    v_tot_asi := v_tot_asi + v_cnt;

    -- 3) limpiar el enlace del documento migrado
    EXECUTE format(
      'UPDATE %I SET id_asiento_contable = NULL WHERE id_asiento_contable = ANY($1)',
      v_tab
    ) USING v_asi;
    GET DIAGNOSTICS v_cnt = ROW_COUNT;
    v_tot_doc := v_tot_doc + v_cnt;

    RAISE NOTICE 'Entidad %: % asiento(s) revertidos, % documento(s) desvinculados.', v_ent, array_length(v_asi,1), v_cnt;
  END LOOP;

  RAISE NOTICE 'TOTAL: % asiento(s) marcados eliminados, % documento(s) con id_asiento_contable = NULL.', v_tot_asi, v_tot_doc;
END $$;
