-- ============================================================================
--  Car-Wash: lote, caducidad, NUP y unidad de medida por línea de la orden.
--
--  La orden debe regirse por la misma configuración de facturación que la
--  Factura de Venta (obligatorio_lotes / obligatorio_caducidad / obligatorio_nup
--  y unidad de medida por línea). Estos datos se guardan en la orden, se usan en
--  su salida de inventario y pasan tal cual a la factura o al recibo que se emite.
--  Mismos tipos que ventas_detalle (numero_lote/nup varchar(100), fecha_caducidad date).
--
--  Idempotente. El código funciona sin estas columnas (no guarda esos datos) hasta
--  que se ejecute.
-- ============================================================================

ALTER TABLE carwash_ordenes_detalle ADD COLUMN IF NOT EXISTS lote             VARCHAR(100);
ALTER TABLE carwash_ordenes_detalle ADD COLUMN IF NOT EXISTS fecha_caducidad  DATE;
ALTER TABLE carwash_ordenes_detalle ADD COLUMN IF NOT EXISTS nup              VARCHAR(100);
ALTER TABLE carwash_ordenes_detalle ADD COLUMN IF NOT EXISTS id_unidad_medida INTEGER;

-- ----------------------------------------------------------------------------
-- Condiciones de ingreso del vehículo: texto libre con formato (editor como el de
-- "Condiciones" de la Proforma). Se imprime en el "Acta de ingreso del vehículo"
-- (PDF aparte que deja constancia de cómo ingresa y qué servicios se esperan).
-- ----------------------------------------------------------------------------
ALTER TABLE carwash_ordenes ADD COLUMN IF NOT EXISTS condiciones_html TEXT;
