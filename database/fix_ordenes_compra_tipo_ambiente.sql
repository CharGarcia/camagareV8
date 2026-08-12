-- ============================================================================
-- Corrige ordenes_compra.tipo_ambiente
-- ============================================================================
-- PROBLEMA
--   El listado de Órdenes de Compra (OrdenCompraRepository::getListado /
--   OrdenesCompraController::countBorradoresAjax) filtra por:
--       oc.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1))
--                             FROM empresas WHERE id = :id_empresa)
--   pero el INSERT de OrdenCompraRepository::insertar() nunca guardaba la
--   columna, así que las órdenes nuevas quedaban con el DEFAULT (o sin la
--   columna siquiera) en vez del ambiente real de la empresa. Si no coincide,
--   la orden se guarda correctamente pero NO aparece en el listado (mismo
--   síntoma que ya ocurrió antes en Pedidos, ver fix_pedidos_tipo_ambiente.sql).
--
--   El código ya fue corregido para guardar el ambiente correcto en:
--     - app/Services/modulos/OrdenCompraService.php (crear)
--     - app/repositories/modulos/OrdenCompraRepository.php (insertar)
--   Este script agrega la columna si falta y repara los registros HISTÓRICOS.
--
-- USO
--   1) Ejecutar el ALTER (no destructivo, IF NOT EXISTS).
--   2) Ejecutar el SELECT de diagnóstico para ver cuántas filas se corregirán.
--   3) Ejecutar el UPDATE.
-- ============================================================================

-- ── 0. Asegurar que la columna existe ───────────────────────────────────────
ALTER TABLE ordenes_compra ADD COLUMN IF NOT EXISTS tipo_ambiente VARCHAR(1) NOT NULL DEFAULT '1';

-- ── 1. DIAGNÓSTICO (solo lectura): filas cuyo ambiente no coincide ──────────
SELECT oc.id_empresa,
       e.tipo_ambiente          AS ambiente_empresa,
       oc.tipo_ambiente         AS ambiente_orden,
       COUNT(*)                 AS filas_a_corregir
  FROM ordenes_compra oc
  JOIN empresas e ON e.id = oc.id_empresa
 WHERE oc.tipo_ambiente IS DISTINCT FROM CAST(e.tipo_ambiente AS VARCHAR(1))
 GROUP BY oc.id_empresa, e.tipo_ambiente, oc.tipo_ambiente
 ORDER BY oc.id_empresa;

-- ── 2. CORRECCIÓN: alinear el ambiente de la orden con el de su empresa ────
-- No modifica ninguna otra columna ni borra nada (no destructivo).
UPDATE ordenes_compra oc
   SET tipo_ambiente = CAST(e.tipo_ambiente AS VARCHAR(1)),
       updated_at    = CURRENT_TIMESTAMP
  FROM empresas e
 WHERE e.id = oc.id_empresa
   AND oc.tipo_ambiente IS DISTINCT FROM CAST(e.tipo_ambiente AS VARCHAR(1));

-- ── 3. VERIFICACIÓN: debe devolver 0 filas ─────────────────────────────────
SELECT COUNT(*) AS pendientes_tras_correccion
  FROM ordenes_compra oc
  JOIN empresas e ON e.id = oc.id_empresa
 WHERE oc.tipo_ambiente IS DISTINCT FROM CAST(e.tipo_ambiente AS VARCHAR(1));
