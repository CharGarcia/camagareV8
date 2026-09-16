-- =============================================================================
-- Documentos cuya contabilidad vino de la MIGRACIÓN (asiento modulo_origen='migracion')
-- y que ADEMÁS recibieron un asiento AUTOMÁTICO de su módulo → contabilidad DUPLICADA.
--
-- Por qué pasa: la exclusión del sincronizador miraba solo `migracion_mysql_map`
-- (documentos que la migración INSERTÓ, vinculado IS NOT TRUE). Quedaban fuera:
--   · documentos NATIVOS que la migración solo ENLAZÓ por número (vinculado = true),
--     p. ej. facturas descargadas del SRI / importadas por XML antes de migrar;
--   · documentos que perdieron su fila del mapa (revert de la entidad, purga, etc.);
--   · Facturas / Recibos / NC con asiento migrado enlazado pero sin fila en
--     ventas_costeo_seguimiento: la rama "reprocesar costo" los regeneraba igual.
-- Desde el 2026-09-15 el sistema mira además el ENLACE del documento
-- (documento.id_asiento_contable → asiento 'migracion' vivo) en el sincronizador, en
-- AsientoContableService::guardarAsiento() y en Auditoría Contable. Este script sirve
-- para DETECTAR y CORREGIR lo que ya se duplicó antes de ese cambio.
--
-- Uso en pgAdmin (misma sesión, en este orden):
--   PASO 1  Diagnóstico (solo lectura). Cambiar el id de empresa en `v_emp` y ejecutar
--           el bloque completo del PASO 1. Deja una tabla temporal `_cmg_dup` y la lista.
--   PASO 2  Corrección (DO block, NO destructivo): soft-delete de los asientos AUTOMÁTICOS
--           duplicados (cabecera + detalle + fila de costeo) y re-enlace del documento a
--           su asiento migrado. NUNCA toca asientos con modulo_origen='migracion'.
--           Solo procesa las filas que dejó el PASO 1 → correr siempre el PASO 1 antes.
--
-- Columna `via` (cómo se estableció que el documento tiene contabilidad migrada):
--   enlace     documento.id_asiento_contable apunta al asiento migrado → seguro.
--   mapa       el mapa de migración cruza el documento (id_origen viejo) con el asiento
--              migrado (codigo_unico del diario viejo = PREFIJO + id viejo) → seguro.
--   referencia solo coincide asiento.id_referencia_origen + tipo_comprobante. Es la
--              señal que deja `migrarContabilidad`, pero ese id puede colisionar entre
--              módulos del mismo tipo (factura 5 vs. nota de crédito 5). Revisar la fila
--              antes de corregir; el DO block las omite salvo `v_por_referencia := true`.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- PASO 1 · DIAGNÓSTICO (solo lectura)
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS _cmg_dup;
CREATE TEMP TABLE _cmg_dup AS
WITH p AS (
    SELECT 1::int AS v_emp                      -- <<< AJUSTAR: id de la empresa (0 = todas)
),
docs AS (
              SELECT 'facturas'::text AS entidad, 'factura_venta'::text AS modulo, 'ventas'::text AS tipo_mig, 'FAC'::text AS prefijo, 'ventas_cabecera'::text AS tabla,
                     id, id_empresa, id_asiento_contable, fecha_emision::date AS fecha,
                     concat_ws('-', establecimiento, punto_emision, secuencial) AS numero
                FROM ventas_cabecera WHERE eliminado = false
    UNION ALL SELECT 'recibos', 'recibo_venta', 'ventas', 'REC', 'recibos_venta_cabecera',
                     id, id_empresa, id_asiento_contable, fecha_emision::date,
                     concat_ws('-', establecimiento, punto_emision, secuencial)
                FROM recibos_venta_cabecera WHERE eliminado = false
    UNION ALL SELECT 'notas_credito', 'nota_credito', 'ventas', 'NCV', 'notas_credito_cabecera',
                     id, id_empresa, id_asiento_contable, fecha_emision::date,
                     concat_ws('-', establecimiento, punto_emision, secuencial)
                FROM notas_credito_cabecera WHERE eliminado = false
    UNION ALL SELECT 'compras', 'compra', 'compras', 'COM', 'compras_cabecera',
                     id, id_empresa, id_asiento_contable, fecha_emision::date,
                     concat_ws('-', establecimiento_prov, punto_emision_prov, secuencial_prov)
                FROM compras_cabecera WHERE eliminado = false
    UNION ALL SELECT 'ingresos', 'ingreso', 'ingresos', 'ING', 'ingresos_cabecera',
                     id, id_empresa, id_asiento_contable, fecha_emision::date,
                     COALESCE(numero_ingreso::text, concat_ws('-', establecimiento, punto_emision, secuencial))
                FROM ingresos_cabecera WHERE eliminado = false
    UNION ALL SELECT 'egresos', 'egreso', 'egresos', 'EGR', 'egresos_cabecera',
                     id, id_empresa, id_asiento_contable, fecha_emision::date,
                     COALESCE(numero_egreso::text, concat_ws('-', establecimiento, punto_emision, secuencial))
                FROM egresos_cabecera WHERE eliminado = false
    UNION ALL SELECT 'retenciones_venta', 'retencion_venta', 'retenciones_ventas', 'RET', 'retencion_venta_cabecera',
                     id, id_empresa, id_asiento_contable, fecha_emision::date,
                     concat_ws('-', establecimiento, punto_emision, secuencial)
                FROM retencion_venta_cabecera WHERE eliminado = false
    UNION ALL SELECT 'retenciones_compra', 'retencion_compra', 'retenciones_compras', 'RET', 'retencion_compra_cabecera',
                     id, id_empresa, id_asiento_contable, fecha_emision::date,
                     concat_ws('-', establecimiento, punto_emision, secuencial)
                FROM retencion_compra_cabecera WHERE eliminado = false
),
-- Asiento migrado de cada documento, con la vía más segura disponible.
mig AS (
    SELECT d.entidad, d.id, d.id_empresa,
           COALESCE(m_enl.id, m_map.id, m_ref.id) AS id_asiento_migrado,
           CASE WHEN m_enl.id IS NOT NULL THEN 'enlace'
                WHEN m_map.id IS NOT NULL THEN 'mapa'
                WHEN m_ref.id IS NOT NULL THEN 'referencia' END AS via
      FROM docs d
      CROSS JOIN p
      -- (1) enlace directo del documento
      LEFT JOIN LATERAL (
            SELECT a.id FROM asientos_contables_cabecera a
             WHERE a.id = d.id_asiento_contable AND a.modulo_origen = 'migracion'
               AND a.eliminado = false AND a.estado <> 'anulado'
      ) m_enl ON true
      -- (2) cruce exacto por el mapa: documento (id viejo) ↔ asiento (codigo_unico = PREFIJO + id viejo)
      LEFT JOIN LATERAL (
            SELECT a.id
              FROM migracion_mysql_map md
              JOIN migracion_mysql_map mc
                ON mc.entidad = 'contabilidad' AND mc.id_empresa = md.id_empresa
               AND upper(regexp_replace(mc.clave_natural, '[^A-Za-z]', '', 'g')) = d.prefijo
               AND ltrim(regexp_replace(mc.clave_natural, '\D', '', 'g'), '0') = ltrim(md.id_origen::text, '0')
              JOIN asientos_contables_cabecera a
                ON a.id = mc.id_destino AND a.modulo_origen = 'migracion'
               AND a.tipo_comprobante = d.tipo_mig
               AND a.eliminado = false AND a.estado <> 'anulado'
             WHERE md.entidad = d.entidad AND md.id_destino = d.id AND md.id_empresa = d.id_empresa
             ORDER BY a.id LIMIT 1
      ) m_map ON true
      -- (3) referencia inversa que deja migrarContabilidad (puede colisionar entre módulos del mismo tipo)
      LEFT JOIN LATERAL (
            SELECT a.id FROM asientos_contables_cabecera a
             WHERE a.id_empresa = d.id_empresa AND a.modulo_origen = 'migracion'
               AND a.id_referencia_origen = d.id AND a.tipo_comprobante = d.tipo_mig
               AND a.eliminado = false AND a.estado <> 'anulado'
             ORDER BY a.id LIMIT 1
      ) m_ref ON true
     WHERE (p.v_emp = 0 OR d.id_empresa = p.v_emp)
       AND COALESCE(m_enl.id, m_map.id, m_ref.id) IS NOT NULL
)
SELECT d.id_empresa,
       d.entidad,
       d.modulo,
       d.tabla,
       d.id                    AS id_documento,
       d.numero,
       d.fecha,
       CASE WHEN EXISTS (SELECT 1 FROM migracion_mysql_map mm WHERE mm.entidad = d.entidad AND mm.id_destino = d.id AND mm.vinculado IS NOT TRUE) THEN 'insertado'
            WHEN EXISTS (SELECT 1 FROM migracion_mysql_map mm WHERE mm.entidad = d.entidad AND mm.id_destino = d.id AND mm.vinculado)             THEN 'vinculado'
            ELSE 'sin mapa' END AS en_mapa,
       mg.id_asiento_migrado,
       mg.via,
       d.id_asiento_contable   AS enlace_actual,
       (SELECT string_agg(a.id::text || ' (' || a.numero_comprobante || ', ' || a.estado || ', ' || a.created_at::date || ')', ' ; ' ORDER BY a.id)
          FROM asientos_contables_cabecera a
         WHERE a.id_empresa = d.id_empresa AND a.modulo_origen = d.modulo AND a.id_referencia_origen = d.id
           AND a.eliminado = false AND a.estado <> 'anulado') AS asientos_automaticos,
       (SELECT array_agg(a.id ORDER BY a.id)
          FROM asientos_contables_cabecera a
         WHERE a.id_empresa = d.id_empresa AND a.modulo_origen = d.modulo AND a.id_referencia_origen = d.id
           AND a.eliminado = false AND a.estado <> 'anulado') AS ids_auto
  FROM docs d
  JOIN mig mg ON mg.entidad = d.entidad AND mg.id = d.id AND mg.id_empresa = d.id_empresa
 WHERE EXISTS (SELECT 1 FROM asientos_contables_cabecera a
                WHERE a.id_empresa = d.id_empresa AND a.modulo_origen = d.modulo AND a.id_referencia_origen = d.id
                  AND a.eliminado = false AND a.estado <> 'anulado');

-- Lista de lo duplicado (revisar `via` y `en_mapa` antes de corregir):
SELECT id_empresa, entidad, id_documento, numero, fecha, en_mapa, id_asiento_migrado, via, enlace_actual, asientos_automaticos
  FROM _cmg_dup
 ORDER BY id_empresa, entidad, id_documento;

-- Resumen por entidad y vía:
SELECT id_empresa, entidad, via, en_mapa, COUNT(*) AS documentos
  FROM _cmg_dup GROUP BY 1, 2, 3, 4 ORDER BY 1, 2, 3, 4;


-- ---------------------------------------------------------------------------
-- PASO 2 · CORRECCIÓN (DO block, NO destructivo, idempotente)
--   Requiere haber corrido el PASO 1 en esta misma sesión.
-- ---------------------------------------------------------------------------
DO $$
DECLARE
  v_user           INT     := 1;      -- <<< AJUSTAR: usuario que queda como deleted_by / updated_by
  v_por_referencia BOOLEAN := false;  -- true = corregir también las filas con via = 'referencia' (revisarlas antes)
  r        RECORD;
  n_cab    INT := 0;
  n_det    INT := 0;
  n_cost   INT := 0;
  n_enl    INT := 0;
  n_omit   INT := 0;
  v_tmp    INT;
BEGIN
  IF to_regclass('pg_temp._cmg_dup') IS NULL THEN
    RAISE EXCEPTION 'Corra primero el PASO 1 (diagnóstico) en esta misma sesión: falta la tabla temporal _cmg_dup.';
  END IF;

  FOR r IN SELECT * FROM _cmg_dup ORDER BY id_empresa, entidad, id_documento LOOP
    IF r.via = 'referencia' AND NOT v_por_referencia THEN
      n_omit := n_omit + 1;
      CONTINUE;
    END IF;

    -- 1) Soft-delete de los asientos AUTOMÁTICOS duplicados (nunca los 'migracion').
    UPDATE asientos_contables_detalle
       SET eliminado = true, deleted_at = now(), deleted_by = v_user
     WHERE id_asiento = ANY (r.ids_auto) AND eliminado = false;
    GET DIAGNOSTICS v_tmp = ROW_COUNT;  n_det := n_det + v_tmp;

    UPDATE asientos_contables_cabecera
       SET eliminado = true, deleted_at = now(), deleted_by = v_user
     WHERE id = ANY (r.ids_auto) AND modulo_origen <> 'migracion' AND eliminado = false;
    GET DIAGNOSTICS v_tmp = ROW_COUNT;  n_cab := n_cab + v_tmp;

    -- 2) Fila de costeo de ventas del asiento automático (Facturas / Recibos / NC), para que
    --    no quede un "costo generado" apuntando a un asiento eliminado.
    IF r.modulo IN ('factura_venta', 'recibo_venta', 'nota_credito') AND to_regclass('public.ventas_costeo_seguimiento') IS NOT NULL THEN
      UPDATE ventas_costeo_seguimiento
         SET eliminado = true, deleted_at = now(), deleted_by = v_user
       WHERE id_empresa = r.id_empresa AND id_documento = r.id_documento AND eliminado = false
         AND tipo_documento = CASE r.modulo WHEN 'factura_venta' THEN 'factura_venta'
                                            WHEN 'recibo_venta'  THEN 'recibo_venta'
                                            ELSE 'nota_credito_venta' END;
      GET DIAGNOSTICS v_tmp = ROW_COUNT;  n_cost := n_cost + v_tmp;
    END IF;

    -- 3) Re-enlazar el documento a su asiento migrado (si apuntaba al automático o a nada).
    EXECUTE format(
      'UPDATE %I SET id_asiento_contable = $1, updated_at = now(), updated_by = $2
        WHERE id = $3 AND id_empresa = $4 AND id_asiento_contable IS DISTINCT FROM $1',
      r.tabla)
      USING r.id_asiento_migrado, v_user, r.id_documento, r.id_empresa;
    GET DIAGNOSTICS v_tmp = ROW_COUNT;  n_enl := n_enl + v_tmp;

    -- 4) El asiento migrado queda con la referencia inversa al documento (informativa).
    UPDATE asientos_contables_cabecera
       SET id_referencia_origen = r.id_documento
     WHERE id = r.id_asiento_migrado AND id_referencia_origen IS DISTINCT FROM r.id_documento;
  END LOOP;

  RAISE NOTICE 'Asientos automáticos eliminados (lógico): % cabeceras, % líneas; filas de costeo: %; documentos re-enlazados: %; omitidos (via=referencia): %',
               n_cab, n_det, n_cost, n_enl, n_omit;
END $$;

-- Verificación: debe devolver 0 filas (si v_por_referencia = false, quedan solo las 'referencia').
-- Volver a correr el PASO 1 y mirar la lista.
