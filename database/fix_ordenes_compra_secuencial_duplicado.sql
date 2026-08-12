-- ============================================================================
-- Corrige secuenciales duplicados en ordenes_compra + agrega el mismo control
-- que ya tiene Factura de Venta (ventas_cabecera.uix_ventas_secuencial_activo).
-- ============================================================================
-- PROBLEMA
--   ordenes_compra nunca tuvo ninguna restricción única sobre
--   (id_empresa, id_establecimiento, id_punto_emision, secuencial): el candado
--   de SecuencialService::obtenerSiguienteSecuencial() (pg_advisory_xact_lock)
--   reduce la ventana de carrera, pero por sí solo NO es una garantía si dos
--   filas de empresa_punto_emision "gemelas" (mismo código visible, distinto
--   id) generan el mismo número — el candado y el índice quedan atados al id
--   numérico, no al código. Resultado: dos órdenes con el mismo numero_orden.
--
--   El código ya fue corregido para:
--     - Verificar el duplicado ANTES de insertar (OrdenCompraRepository::
--       existeSecuencial(), llamado desde OrdenCompraService::crear()) —
--       mismo mecanismo que FacturaVentaService::validarSecuencial().
--     - Convertir el error de índice único (23505) en un mensaje claro en vez
--       de un error crudo de PDO.
--   Este script repara los registros HISTÓRICOS y agrega el índice único.
--
-- USO
--   1) Ejecutar el SELECT de diagnóstico para ver los duplicados existentes.
--   2) Ejecutar el UPDATE de renumeración (no destructivo: no borra nada, solo
--      reasigna el secuencial/numero_orden de las filas "perdedoras" de la
--      carrera, dejando intacta la más antigua de cada grupo).
--   3) Ejecutar el SELECT de verificación (debe devolver 0 filas).
--   4) Crear el índice único.
-- ============================================================================

-- ── 1. DIAGNÓSTICO (solo lectura): filas con secuencial duplicado ──────────
SELECT id_empresa, id_establecimiento, id_punto_emision, tipo_ambiente, secuencial,
       COUNT(*) AS filas_duplicadas,
       array_agg(id ORDER BY id) AS ids
  FROM ordenes_compra
 WHERE eliminado = false
 GROUP BY id_empresa, id_establecimiento, id_punto_emision, tipo_ambiente, secuencial
HAVING COUNT(*) > 1
 ORDER BY id_empresa, id_punto_emision;

-- ── 2. CORRECCIÓN: renumerar los duplicados, conservando el más antiguo ────
-- Para cada grupo duplicado, la fila con el id MÁS BAJO conserva su secuencial
-- (es la que "ganó" la carrera originalmente); las demás se reasignan a
-- max_secuencial_de_la_serie + 1, +2, ... (respetando el mismo punto de
-- emisión y ambiente, para no invadir números ya usados por otras órdenes).
WITH duplicados AS (
    SELECT id, id_empresa, id_establecimiento, id_punto_emision, tipo_ambiente, secuencial,
           ROW_NUMBER() OVER (
               PARTITION BY id_empresa, id_establecimiento, id_punto_emision, tipo_ambiente, secuencial
               ORDER BY id
           ) AS posicion
      FROM ordenes_compra
     WHERE eliminado = false
),
perdedores AS (
    SELECT d.id, d.id_empresa, d.id_establecimiento, d.id_punto_emision, d.tipo_ambiente,
           ROW_NUMBER() OVER (
               PARTITION BY d.id_empresa, d.id_establecimiento, d.id_punto_emision, d.tipo_ambiente
               ORDER BY d.id
           ) AS offset
      FROM duplicados d
     WHERE d.posicion > 1
),
maximos AS (
    SELECT id_empresa, id_establecimiento, id_punto_emision, tipo_ambiente,
           MAX(secuencial::bigint) AS max_secuencial
      FROM ordenes_compra
     WHERE eliminado = false
     GROUP BY id_empresa, id_establecimiento, id_punto_emision, tipo_ambiente
)
UPDATE ordenes_compra oc
   SET secuencial = LPAD((m.max_secuencial + p.offset)::text, 9, '0'),
       updated_at = CURRENT_TIMESTAMP
  FROM perdedores p
  JOIN maximos m
    ON m.id_empresa = p.id_empresa
   AND m.id_establecimiento = p.id_establecimiento
   AND m.id_punto_emision = p.id_punto_emision
   AND m.tipo_ambiente = p.tipo_ambiente
 WHERE oc.id = p.id;

-- ── 3. VERIFICACIÓN: debe devolver 0 filas ─────────────────────────────────
SELECT id_empresa, id_establecimiento, id_punto_emision, tipo_ambiente, secuencial, COUNT(*)
  FROM ordenes_compra
 WHERE eliminado = false
 GROUP BY id_empresa, id_establecimiento, id_punto_emision, tipo_ambiente, secuencial
HAVING COUNT(*) > 1;

-- ── 4. ÍNDICE ÚNICO: solo ejecutar si el paso 3 devolvió 0 filas ───────────
CREATE UNIQUE INDEX IF NOT EXISTS uix_ordenes_compra_secuencial_activo
    ON ordenes_compra (id_empresa, id_establecimiento, id_punto_emision, secuencial, tipo_ambiente)
    WHERE eliminado = false;
