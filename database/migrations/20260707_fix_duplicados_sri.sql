-- ============================================================================
-- Fix de duplicados en cargas de documentos desde el SRI
-- Fecha: 2026-07-07
--
-- CAUSA: la deduplicación al registrar comprobantes del SRI era solo a nivel PHP
-- (existeEnTabla), sin índice UNIQUE de respaldo en varias tablas. Si dos lotes de
-- la extensión se solapaban (doble envío/clic/reintento), ambos pasaban el chequeo
-- de existencia antes de que el otro comiteara e insertaban la misma clave dos veces.
-- El endpoint del agente no usa sesión PHP, así que nada los serializaba.
--
-- CORRECCIÓN EN CÓDIGO (ya desplegada por git):
--   1) advisory lock por empresa en SriDescargaAutomaticaService::registrarClaves
--      (serializa los lotes -> elimina el TOCTOU en origen).
--   2) manejo de unique_violation (23505) en DocumentoAutomatedRegisterService
--      (si el índice frena un insert, se reporta "ya registrado", no error).
--   3) normalización de num_doc_sustento en retenciones (evita retención duplicada).
--
-- ESTE SCRIPT añade la RED DE SEGURIDAD en la base de datos: índices UNIQUE parciales
-- que impiden físicamente dos filas VIVAS con la misma clave por empresa. Los índices
-- de liquidaciones y retenciones (compra/venta) ya existen; aquí se añaden los que
-- faltan: compras_cabecera, ventas_cabecera y notas_credito_cabecera.
--
-- EJECUTAR EN ORDEN. Los índices NO se pueden crear si hay duplicados VIVOS, por eso
-- primero se diagnostica (PASO 1) y, si hay, se limpian (PASO 2), y recién luego se
-- crean (PASO 3). Hacer respaldo antes del PASO 2.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- PASO 1 — DIAGNÓSTICO (solo lectura). Cuántos grupos de duplicados VIVOS hay.
-- Si todo devuelve 0 filas, puedes saltar directo al PASO 3.
-- ----------------------------------------------------------------------------
SELECT 'compras_cabecera' AS tabla, id_empresa, numero_autorizacion AS clave, COUNT(*) AS copias
FROM compras_cabecera
WHERE eliminado = false AND numero_autorizacion IS NOT NULL AND numero_autorizacion <> ''
GROUP BY id_empresa, numero_autorizacion HAVING COUNT(*) > 1
ORDER BY copias DESC;

SELECT 'ventas_cabecera' AS tabla, id_empresa, clave_acceso AS clave, COUNT(*) AS copias
FROM ventas_cabecera
WHERE eliminado = false AND clave_acceso IS NOT NULL AND clave_acceso <> ''
GROUP BY id_empresa, clave_acceso HAVING COUNT(*) > 1
ORDER BY copias DESC;

SELECT 'notas_credito_cabecera' AS tabla, id_empresa, clave_acceso AS clave, COUNT(*) AS copias
FROM notas_credito_cabecera
WHERE eliminado = false AND clave_acceso IS NOT NULL AND clave_acceso <> ''
GROUP BY id_empresa, clave_acceso HAVING COUNT(*) > 1
ORDER BY copias DESC;

-- Retenciones de compra duplicadas por documento de sustento (auto-retención vs 07),
-- comparando el número normalizado (sin guiones). Revisar manualmente antes de tocar:
-- las retenciones pueden tener asiento contable asociado.
SELECT 'retencion_compra_cabecera' AS tabla, id_empresa, tipo_doc_sustento,
       regexp_replace(num_doc_sustento, '\D', '', 'g') AS nds_norm,
       COUNT(*) AS copias, string_agg(id::text, ',') AS ids,
       string_agg(DISTINCT num_doc_sustento, ' | ') AS formatos
FROM retencion_compra_cabecera
WHERE eliminado = false AND COALESCE(estado,'') <> 'anulada'
  AND num_doc_sustento IS NOT NULL AND num_doc_sustento <> ''
GROUP BY id_empresa, tipo_doc_sustento, regexp_replace(num_doc_sustento, '\D', '', 'g')
HAVING COUNT(*) > 1
ORDER BY copias DESC;


-- ----------------------------------------------------------------------------
-- PASO 2 — LIMPIEZA de duplicados VIVOS (solo si el PASO 1 devolvió filas).
-- Estrategia conservadora: se conserva la fila de MENOR id de cada grupo (la
-- original, que normalmente conserva sus relaciones: retención/egreso/asiento) y
-- se marca el resto como eliminado lógico. NO se borra nada físicamente.
-- HACER RESPALDO ANTES. Ejecutar dentro de una transacción para poder revisar.
-- ----------------------------------------------------------------------------
-- BEGIN;

UPDATE compras_cabecera c
SET eliminado = true, deleted_at = NOW()
WHERE c.eliminado = false
  AND c.numero_autorizacion IS NOT NULL AND c.numero_autorizacion <> ''
  AND EXISTS (
      SELECT 1 FROM compras_cabecera c2
      WHERE c2.id_empresa = c.id_empresa
        AND c2.numero_autorizacion = c.numero_autorizacion
        AND c2.eliminado = false
        AND c2.id < c.id
  );

UPDATE ventas_cabecera v
SET eliminado = true, deleted_at = NOW()
WHERE v.eliminado = false
  AND v.clave_acceso IS NOT NULL AND v.clave_acceso <> ''
  AND EXISTS (
      SELECT 1 FROM ventas_cabecera v2
      WHERE v2.id_empresa = v.id_empresa
        AND v2.clave_acceso = v.clave_acceso
        AND v2.eliminado = false
        AND v2.id < v.id
  );

UPDATE notas_credito_cabecera n
SET eliminado = true, deleted_at = NOW()
WHERE n.eliminado = false
  AND n.clave_acceso IS NOT NULL AND n.clave_acceso <> ''
  AND EXISTS (
      SELECT 1 FROM notas_credito_cabecera n2
      WHERE n2.id_empresa = n.id_empresa
        AND n2.clave_acceso = n.clave_acceso
        AND n2.eliminado = false
        AND n2.id < n.id
  );

-- Revisar los conteos; si todo bien:  COMMIT;   si algo se ve mal:  ROLLBACK;


-- ----------------------------------------------------------------------------
-- PASO 3 — ÍNDICES UNIQUE parciales (red de seguridad definitiva).
-- Alineados con los ya existentes en retenciones/liquidaciones: por (empresa, clave)
-- sobre filas no eliminadas. Permiten re-registrar un documento que se eliminó, pero
-- impiden dos filas VIVAS de la misma clave.
-- ----------------------------------------------------------------------------
CREATE UNIQUE INDEX IF NOT EXISTS uq_compras_numaut_activo
    ON compras_cabecera (id_empresa, numero_autorizacion)
    WHERE eliminado = false AND numero_autorizacion IS NOT NULL AND numero_autorizacion <> '';

CREATE UNIQUE INDEX IF NOT EXISTS uq_ventas_clave_activo
    ON ventas_cabecera (id_empresa, clave_acceso)
    WHERE eliminado = false AND clave_acceso IS NOT NULL AND clave_acceso <> '';

CREATE UNIQUE INDEX IF NOT EXISTS uq_nc_clave_activo
    ON notas_credito_cabecera (id_empresa, clave_acceso)
    WHERE eliminado = false AND clave_acceso IS NOT NULL AND clave_acceso <> '';

-- Verificación final: listar todos los índices UNIQUE de las tablas de documentos SRI.
-- SELECT tablename, indexname, indexdef FROM pg_indexes
-- WHERE schemaname='public' AND indexdef ILIKE '%UNIQUE%'
--   AND tablename IN ('compras_cabecera','ventas_cabecera','notas_credito_cabecera',
--                     'liquidaciones_cabecera','retencion_compra_cabecera','retencion_venta_cabecera')
-- ORDER BY tablename, indexname;
