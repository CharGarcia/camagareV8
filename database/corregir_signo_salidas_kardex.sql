-- ============================================================================
-- Corrección de SIGNO de las salidas en el kardex (inventario_kardex).
--
-- Convención del sistema: cantidad va CON SIGNO (entrada +, salida −). getStockActual
-- y el Reporte de Movimientos clasifican entrada/salida por el SIGNO de cantidad, NO por
-- tipo_movimiento. Las migraciones de Inventario ANTERIORES al fix de signo guardaron las
-- salidas en POSITIVO → el reporte las muestra como ENTRADA aunque tipo_movimiento diga
-- 'salida', y getStockActual (SUM(cantidad)) queda inflado.
--
-- Este UPDATE niega la cantidad de las salidas guardadas en positivo. Todas las empresas.
--
-- SEGURO e IDEMPOTENTE: solo toca tipo_movimiento='salida' con cantidad>0. Las salidas
-- nativas ya están en negativo (no se tocan) y tras negar quedan <0 (re-correr no las
-- vuelve a tocar). No modifica stock_anterior/posterior (son niveles absolutos, ya
-- correctos) ni productos_bodegas (que ya trae el saldo bien). No toca 'entrada',
-- 'ajuste' ni 'transferencia'.
-- ============================================================================

UPDATE inventario_kardex
   SET cantidad = -cantidad, updated_at = now()
 WHERE tipo_movimiento = 'salida'
   AND cantidad > 0
   AND eliminado = false;

-- Verificación (debe quedar en 0):
-- SELECT COUNT(*) FROM inventario_kardex WHERE tipo_movimiento='salida' AND cantidad>0 AND eliminado=false;
