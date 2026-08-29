-- ============================================================================
-- Comandas: normalizar las líneas SIN estación de preparación.
--
-- Un ítem con "Enviar a: Ninguna" (Menú) no pasa por cocina ni barra: no hay
-- nada que preparar ni que esperar. Desde ahora nace ya 'entregado'
-- (ComandaService::agregarLinea), pero las líneas creadas antes quedaron en
-- 'pendiente' o 'enviado' esperando una confirmación de cocina que no va a
-- llegar: sin el botón "Entregar" (que ya no se ofrece para ellas) se quedarían
-- trabadas, y el cliente no podría pagarlas desde el QR de la mesa, que exige
-- que todo esté entregado.
--
-- Solo toca líneas de comandas ABIERTAS: las de comandas ya cerradas o anuladas
-- son historia y no se reescriben.
-- ============================================================================

BEGIN;

UPDATE comanda_detalle d
   SET estado_linea = 'entregado',
       entregado_at = COALESCE(d.entregado_at, CURRENT_TIMESTAMP)
  FROM comandas c
 WHERE c.id = d.id_comanda
   AND c.estado = 'abierta'
   AND d.eliminado = false
   AND d.id_estacion_impresion IS NULL
   AND d.estado_linea NOT IN ('entregado', 'anulado');

COMMIT;

-- Verificación (debe devolver 0 filas):
-- SELECT d.id, d.descripcion, d.estado_linea
--   FROM comanda_detalle d JOIN comandas c ON c.id = d.id_comanda
--  WHERE c.estado = 'abierta' AND d.eliminado = false
--    AND d.id_estacion_impresion IS NULL
--    AND d.estado_linea NOT IN ('entregado', 'anulado');
