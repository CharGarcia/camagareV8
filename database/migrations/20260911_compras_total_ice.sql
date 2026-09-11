-- ============================================================================
-- Compras: columna total_ice en compras_cabecera
-- ----------------------------------------------------------------------------
-- compras_detalle_impuestos ya distingue el impuesto por código (2=IVA, 3=ICE,
-- 5=ISD — ver comentario de columna en create_compras_module.sql), pero
-- compras_cabecera nunca tuvo una columna propia para el ICE total del
-- documento — a diferencia de ventas_cabecera, que sí la tiene (total_ice).
-- Sin ella, cualquier listado/reporte que necesite el ICE por separado del IVA
-- tenía que derivarlo restando importe_total - total_sin_impuestos - propina,
-- lo que en realidad da IVA+ICE mezclados.
--
-- Este script SOLO agrega la columna (con DEFAULT 0, no rompe filas existentes).
-- No recalcula el histórico: las compras ya guardadas quedan con total_ice=0
-- aunque tengan ICE en sus impuestos, hasta que se editen y regraben, o hasta
-- que corras el PASO 2 (opcional) para poblarlo retroactivamente desde
-- compras_detalle_impuestos.
-- ============================================================================

-- PASO 1 — columna nueva (obligatorio)
ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS total_ice NUMERIC(12,2) NOT NULL DEFAULT 0;

-- PASO 2 — opcional: poblar el histórico ya guardado, sumando el ICE (código 3)
-- de cada línea de cada compra viva. Es idempotente (se puede re-correr).
UPDATE compras_cabecera c
SET total_ice = COALESCE((
    SELECT SUM(cdi.valor)
    FROM compras_detalle_impuestos cdi
    JOIN compras_detalle cd ON cd.id = cdi.id_compra_detalle
    WHERE cd.id_compra = c.id AND cdi.codigo_impuesto = '3'
), 0)
WHERE c.eliminado = false;
