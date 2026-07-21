-- ============================================================================
-- Corrige inventario_kardex.tipo_ambiente
-- ============================================================================
-- PROBLEMA
--   El listado del kardex (InventarioRepository::getKardex) filtra por:
--       k.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1))
--                            FROM empresas WHERE id = :id_empresa)
--   pero NINGÚN punto de inserción guardaba la columna, así que todas las filas
--   quedaban con el DEFAULT '1'. En una empresa con ambiente '2' (producción)
--   los movimientos existen en la tabla pero NO aparecen en Inventario
--   (síntoma reportado: "registro inventario desde una compra y no aparece
--   en las entradas del inventario").
--
--   El código ya fue corregido para guardar el ambiente correcto en:
--     - app/repositories/modulos/InventarioRepository.php  (registrarMovimiento)
--     - app/controllers/modulos/ComprasController.php      (procesarInventarioAjax)
--   Este script repara los registros HISTÓRICOS.
--
-- USO
--   1) Ejecutar primero el SELECT de diagnóstico para ver cuántas filas se
--      corregirán (y en qué empresas).
--   2) Ejecutar el UPDATE.
-- ============================================================================

-- ── 1. DIAGNÓSTICO (solo lectura): filas cuyo ambiente no coincide ──────────
SELECT k.id_empresa,
       e.tipo_ambiente          AS ambiente_empresa,
       k.tipo_ambiente          AS ambiente_kardex,
       COUNT(*)                 AS filas_a_corregir
  FROM inventario_kardex k
  JOIN empresas e ON e.id = k.id_empresa
 WHERE k.tipo_ambiente IS DISTINCT FROM CAST(e.tipo_ambiente AS VARCHAR(1))
 GROUP BY k.id_empresa, e.tipo_ambiente, k.tipo_ambiente
 ORDER BY k.id_empresa;

-- ── 2. CORRECCIÓN: alinear el ambiente del kardex con el de su empresa ──────
-- No modifica ninguna otra columna ni borra nada (no destructivo).
UPDATE inventario_kardex k
   SET tipo_ambiente = CAST(e.tipo_ambiente AS VARCHAR(1)),
       updated_at    = CURRENT_TIMESTAMP
  FROM empresas e
 WHERE e.id = k.id_empresa
   AND k.tipo_ambiente IS DISTINCT FROM CAST(e.tipo_ambiente AS VARCHAR(1));

-- ── 3. VERIFICACIÓN: debe devolver 0 filas ─────────────────────────────────
SELECT COUNT(*) AS pendientes_tras_correccion
  FROM inventario_kardex k
  JOIN empresas e ON e.id = k.id_empresa
 WHERE k.tipo_ambiente IS DISTINCT FROM CAST(e.tipo_ambiente AS VARCHAR(1));
