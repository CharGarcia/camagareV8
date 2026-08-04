-- ============================================================================
-- Evita pedidos duplicados con el mismo número de secuencial
-- ============================================================================
-- PROBLEMA
--   PedidoService::guardarPedido() insertaba el pedido con el secuencial que
--   enviaba el navegador (calculado al abrir el modal) sin recalcularlo ni
--   bloquear la numeración al guardar. Dos usuarios abriendo el modal casi al
--   mismo tiempo (o el mismo usuario con el modal abierto un rato) recibían el
--   MISMO "siguiente secuencial" y ambos guardados terminaban insertando el
--   mismo número — no había ninguna restricción en la base de datos que lo
--   impidiera.
--
--   El código ya fue corregido para:
--     - Recalcular el secuencial de forma AUTORITATIVA en el servidor al
--       guardar (no confiar en el valor del navegador).
--     - Serializar la generación por punto de emisión dentro de la
--       transacción con un advisory lock (mismo patrón usado en Comandas).
--   Este script agrega el ÚLTIMO respaldo: un índice ÚNICO parcial en la
--   base de datos, igual al que ya protege Proformas, Facturas de venta,
--   Órdenes de Taller y Órdenes de Car-Wash.
--
-- USO (en orden, cada paso por separado)
--   1) Ver el detalle de los pedidos duplicados (para decidir con calma si
--      alguno merece revisión manual antes de tocarlo).
--   2) Renumerar automáticamente: conserva el pedido MÁS ANTIGUO (id menor)
--      de cada grupo duplicado con su número original, y a los demás les
--      asigna el siguiente número LIBRE de esa misma serie (por encima del
--      máximo ya usado). No borra ni oculta nada — solo corrige el número.
--      "numero_pedido" se arma al vuelo desde esta columna en todos lados
--      (listado, Consignaciones, etc.), así que renumerar aquí es suficiente.
--   3) Verificar que ya no hay duplicados (debe devolver 0 filas).
--   4) Crear el índice único (falla si el paso 3 no dio 0 filas).
-- ============================================================================

-- ── 1. DIAGNÓSTICO DETALLADO (solo lectura) ─────────────────────────────────
SELECT p.id, p.id_empresa, p.id_punto_emision, p.tipo_ambiente, p.secuencial,
       p.estado, p.id_cliente, c.nombre AS cliente, p.created_at, p.created_by
  FROM pedidos_cabecera p
  JOIN clientes c ON c.id = p.id_cliente
 WHERE p.eliminado = false
   AND EXISTS (
       SELECT 1 FROM pedidos_cabecera p2
        WHERE p2.eliminado = false
          AND p2.id <> p.id
          AND p2.id_empresa = p.id_empresa
          AND p2.id_punto_emision = p.id_punto_emision
          AND p2.tipo_ambiente = p.tipo_ambiente
          AND p2.secuencial = p.secuencial
   )
 ORDER BY p.id_empresa, p.id_punto_emision, p.tipo_ambiente, p.secuencial, p.id;

-- ── 2. CORRECCIÓN: renumerar los duplicados (no destructivo) ────────────────
WITH max_actual AS (
    SELECT id_empresa, id_punto_emision, tipo_ambiente,
           MAX(CAST(secuencial AS BIGINT)) AS max_sec
      FROM pedidos_cabecera
     WHERE eliminado = false
     GROUP BY id_empresa, id_punto_emision, tipo_ambiente
),
marcados AS (
    SELECT p.id, p.id_empresa, p.id_punto_emision, p.tipo_ambiente,
           ROW_NUMBER() OVER (
               PARTITION BY p.id_empresa, p.id_punto_emision, p.tipo_ambiente, p.secuencial
               ORDER BY p.id
           ) AS orden_en_secuencial
      FROM pedidos_cabecera p
     WHERE p.eliminado = false
),
a_renumerar AS (
    SELECT m.id, m.id_empresa, m.id_punto_emision, m.tipo_ambiente,
           ROW_NUMBER() OVER (
               PARTITION BY m.id_empresa, m.id_punto_emision, m.tipo_ambiente
               ORDER BY m.id
           ) AS offset
      FROM marcados m
     WHERE m.orden_en_secuencial > 1   -- todo lo que NO fue el primero en usar ese número
)
UPDATE pedidos_cabecera p
   SET secuencial = LPAD((ma.max_sec + ar.offset)::text, 9, '0'),
       updated_at = CURRENT_TIMESTAMP
  FROM a_renumerar ar
  JOIN max_actual ma ON ma.id_empresa = ar.id_empresa
                     AND ma.id_punto_emision = ar.id_punto_emision
                     AND ma.tipo_ambiente = ar.tipo_ambiente
 WHERE p.id = ar.id;

-- ── 3. VERIFICACIÓN: debe devolver 0 filas ─────────────────────────────────
SELECT id_empresa, id_punto_emision, tipo_ambiente, secuencial,
       array_agg(id ORDER BY id) AS ids_duplicados,
       COUNT(*) AS repeticiones
  FROM pedidos_cabecera
 WHERE eliminado = false
 GROUP BY id_empresa, id_punto_emision, tipo_ambiente, secuencial
HAVING COUNT(*) > 1
 ORDER BY id_empresa, id_punto_emision;

-- ── 4. ÍNDICE ÚNICO (ejecutar solo si el paso 3 dio 0 filas) ────────────────
CREATE UNIQUE INDEX IF NOT EXISTS uq_pedidos_secuencial
    ON pedidos_cabecera (id_empresa, id_punto_emision, secuencial, tipo_ambiente)
    WHERE (eliminado = false);
