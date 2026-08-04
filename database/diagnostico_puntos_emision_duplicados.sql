-- ============================================================================
-- Diagnóstico: puntos de emisión ACTIVOS con el mismo código en un mismo
-- establecimiento (posible causa real de "2 pedidos con el mismo número")
-- ============================================================================
-- CONTEXTO
--   El "número de pedido" que ve el usuario es
--       {establecimiento}-{punto_emision}-{secuencial}
--   pero esos dos primeros valores se guardan como TEXTO (código visible), no
--   como el id real de empresa_punto_emision. Si dos puntos de emisión
--   DISTINTOS (dos filas, dos ids) comparten el mismo código dentro del mismo
--   establecimiento (p. ej. dos filas "102"), el desplegable "Serie" del
--   formulario los muestra como si fueran la misma opción, cada uno numerando
--   por su cuenta en `empresa_secuencial` — dos pedidos guardados contra el
--   punto "equivocado" (o por dos usuarios distintos) pueden terminar
--   mostrando el MISMO número aunque internamente sean dos id_punto_emision
--   diferentes. La corrección de concurrencia en PedidoService (advisory lock
--   + índice único por id_punto_emision) NO cubre este caso porque ahí sí son
--   dos puntos distintos.
--
--   No existe ningún índice único en empresa_punto_emision que impida esta
--   duplicación (verificado: solo hay PK por id).
--
-- USO
--   1) Ejecutar el diagnóstico. Si aparecen filas, revisar en
--      Empresa → Puntos de emisión cuál de los dos códigos duplicados es el
--      correcto y desactivar/eliminar (lógicamente) el otro. Cuál conservar
--      requiere criterio humano (puede haber documentos ya emitidos contra
--      cualquiera de los dos) — este script NO decide ni modifica nada.
--   2) Opcional, una vez resuelto el diagnóstico en 0 filas: crear el índice
--      único que evita que vuelva a pasar (comentado abajo).
-- ============================================================================

-- ── DIAGNÓSTICO (solo lectura) ──────────────────────────────────────────────
SELECT e.id_empresa, p.id_establecimiento, p.codigo_punto,
       array_agg(p.id ORDER BY p.id) AS ids_duplicados,
       COUNT(*) AS repeticiones
  FROM empresa_punto_emision p
  JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
 WHERE p.eliminado = false AND e.eliminado = false AND LOWER(p.estado) = 'activo'
 GROUP BY e.id_empresa, p.id_establecimiento, p.codigo_punto
HAVING COUNT(*) > 1
 ORDER BY e.id_empresa;

-- ── OPCIONAL: índice preventivo (ejecutar solo tras resolver el diagnóstico) ─
-- CREATE UNIQUE INDEX IF NOT EXISTS uq_punto_emision_codigo_activo
--     ON empresa_punto_emision (id_establecimiento, codigo_punto)
--     WHERE (eliminado = false AND LOWER(estado) = 'activo');
