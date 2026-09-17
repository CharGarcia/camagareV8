-- ============================================================================
-- Cargas de Inventario: ajuste por conteo físico (modulos/cargas-inventario)
-- ----------------------------------------------------------------------------
-- Contexto:
--   Una carga de tipo "Ajuste" SUMABA la cantidad al stock, igual que una
--   entrada. Desde esta versión es un conteo físico: la cantidad es lo contado
--   y, al aprobar, se registra solo la diferencia con el saldo de ese momento
--   (entrada si sobra, salida si falta). Cada línea guarda el saldo que tenía
--   el sistema y la diferencia registrada, para verlos en el detalle, el PDF y
--   el Excel de la carga.
--
-- Qué hace:
--   Agrega a inventario_cargas_detalle las columnas saldo_sistema y diferencia.
--
--   NO modifica ni borra datos. NO toca ninguna fila existente: las cargas de
--   ajuste aprobadas antes quedan con esas columnas vacías (se aplicaron con la
--   regla anterior). Para revisarlas, ver
--   database/diagnosticos/20260917_cargas_inventario_nup_y_ajuste.sql.
--
-- Orden de despliegue: ejecutar ESTE SQL ANTES de subir el código. Si el código
--   llega primero, las cargas de entrada y salida funcionan igual, pero aprobar
--   una carga de ajuste responde que falta aplicar este script (sin tocar stock).
--
-- Reversible: sí (ver bloque REVERTIR al final). Se pierde el saldo y la
--   diferencia guardados de los ajustes aprobados después de aplicarlo.
--
-- ----------------------------------------------------------------------------
-- CÓMO EJECUTARLO EN pgAdmin
-- ----------------------------------------------------------------------------
--   Query Tool → pegar TODO este archivo → ejecutar (F5), de una sola vez.
--   Es idempotente: se puede volver a ejecutar sin error.
-- ============================================================================

ALTER TABLE public.inventario_cargas_detalle ADD COLUMN IF NOT EXISTS saldo_sistema NUMERIC(18,6);
ALTER TABLE public.inventario_cargas_detalle ADD COLUMN IF NOT EXISTS diferencia    NUMERIC(18,6);

COMMENT ON COLUMN public.inventario_cargas_detalle.saldo_sistema IS
    'Carga de ajuste: saldo que tenía el sistema al aprobar (del lote de la línea o, sin lote, del producto en la bodega)';
COMMENT ON COLUMN public.inventario_cargas_detalle.diferencia IS
    'Carga de ajuste: diferencia registrada al aprobar (contado - saldo_sistema; 0 = sin movimiento)';


-- ============================================================================
-- COMPROBACIÓN (ejecutar aparte)
-- ============================================================================
-- Deben salir 2 filas:
-- SELECT column_name, data_type
--   FROM information_schema.columns
--  WHERE table_name = 'inventario_cargas_detalle'
--    AND column_name IN ('saldo_sistema', 'diferencia');


-- ============================================================================
-- REVERTIR
-- ============================================================================
-- ALTER TABLE public.inventario_cargas_detalle
--     DROP COLUMN IF EXISTS diferencia,
--     DROP COLUMN IF EXISTS saldo_sistema;
