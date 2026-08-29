-- ============================================================================
-- Corrige las líneas SIN estación que quedaron marcadas 'entregado' al crearse.
--
-- Durante un rato, los ítems con "Preparar en: Ninguna" nacían ya entregados.
-- Eso dejaba al cliente del QR sin poder quitarlos (la x solo aparece mientras
-- están pendientes) y sin poder confirmar su pedido (el botón cuenta las
-- pendientes). El comportamiento correcto es: nacen 'pendiente' y recién AL
-- CONFIRMAR pasan a 'entregado', sin pasar por cocina.
--
-- Este script devuelve a 'pendiente' solo las que nunca se confirmaron: las que
-- no tienen enviado_at NI entregado_at, es decir, las que nunca recorrieron el
-- flujo y fueron marcadas así al nacer. Una línea realmente entregada tiene su
-- marca de tiempo y no se toca.
--
-- Solo comandas abiertas y líneas que no estén ya en una cuenta de cobro.
--
-- NOTA: deja sin efecto a comandas_lineas_sin_estacion_entregadas.sql, que hacía
-- lo contrario y ya no debe ejecutarse.
-- ============================================================================

BEGIN;

UPDATE comanda_detalle d
   SET estado_linea = 'pendiente'
  FROM comandas c
 WHERE c.id = d.id_comanda
   AND c.estado = 'abierta'
   AND d.eliminado = false
   AND d.id_estacion_impresion IS NULL
   AND d.estado_linea = 'entregado'
   AND d.enviado_at IS NULL
   AND d.entregado_at IS NULL
   AND d.id_grupo_cobro IS NULL
   -- La propina queda fuera: esa SÍ nace entregada a propósito (no se prepara
   -- ni se confirma; se administra desde su campo del pie).
   AND d.id_producto IS DISTINCT FROM (
        SELECT ee.id_producto_propina
          FROM empresa_establecimiento ee
         WHERE ee.id_empresa = d.id_empresa AND ee.eliminado = false
         ORDER BY ee.id LIMIT 1
   );

COMMIT;

-- Verificación (deben quedar en 'pendiente' y con la x disponible en el QR):
-- SELECT d.id, d.descripcion, d.estado_linea, d.id_estacion_impresion
--   FROM comanda_detalle d JOIN comandas c ON c.id = d.id_comanda
--  WHERE c.estado = 'abierta' AND d.eliminado = false AND d.id_estacion_impresion IS NULL;
