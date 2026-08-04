-- ============================================================================
-- Corrige pedidos_cabecera.tipo_ambiente
-- ============================================================================
-- PROBLEMA
--   El listado de Pedidos (PedidoRepository::getListado / countPendientesAjax)
--   filtra por:
--       p.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1))
--                            FROM empresas WHERE id = :id_empresa)
--   pero el INSERT de PedidoService::guardarPedido nunca guardaba la columna,
--   así que los pedidos nuevos quedaban con el DEFAULT de la columna (o NULL)
--   en vez del ambiente real de la empresa. Si no coincide, el pedido se
--   guarda correctamente pero NO aparece en el listado (síntoma: "se guarda
--   el pedido pero no se muestra en la lista de pedidos").
--
--   El código ya fue corregido para guardar el ambiente correcto en:
--     - app/Services/modulos/PedidoService.php (guardarPedido)
--   Este script repara los registros HISTÓRICOS.
--
-- USO
--   1) Ejecutar primero el SELECT de diagnóstico para ver cuántas filas se
--      corregirán (y en qué empresas).
--   2) Ejecutar el UPDATE.
-- ============================================================================

-- ── 1. DIAGNÓSTICO (solo lectura): filas cuyo ambiente no coincide ──────────
SELECT p.id_empresa,
       e.tipo_ambiente          AS ambiente_empresa,
       p.tipo_ambiente          AS ambiente_pedido,
       COUNT(*)                 AS filas_a_corregir
  FROM pedidos_cabecera p
  JOIN empresas e ON e.id = p.id_empresa
 WHERE p.tipo_ambiente IS DISTINCT FROM CAST(e.tipo_ambiente AS VARCHAR(1))
 GROUP BY p.id_empresa, e.tipo_ambiente, p.tipo_ambiente
 ORDER BY p.id_empresa;

-- ── 2. CORRECCIÓN: alinear el ambiente del pedido con el de su empresa ──────
-- No modifica ninguna otra columna ni borra nada (no destructivo).
UPDATE pedidos_cabecera p
   SET tipo_ambiente = CAST(e.tipo_ambiente AS VARCHAR(1)),
       updated_at    = CURRENT_TIMESTAMP
  FROM empresas e
 WHERE e.id = p.id_empresa
   AND p.tipo_ambiente IS DISTINCT FROM CAST(e.tipo_ambiente AS VARCHAR(1));

-- ── 3. VERIFICACIÓN: debe devolver 0 filas ─────────────────────────────────
SELECT COUNT(*) AS pendientes_tras_correccion
  FROM pedidos_cabecera p
  JOIN empresas e ON e.id = p.id_empresa
 WHERE p.tipo_ambiente IS DISTINCT FROM CAST(e.tipo_ambiente AS VARCHAR(1));
