-- =====================================================================================
-- Suscripciones: "Adicional" por ítem del detalle (suscripciones_detalle.info_adicional).
--
-- QUÉ HACE
--   Agrega la columna donde se guarda la información adicional de cada línea de la
--   suscripción (columna "Adicional" del modal, igual que en Facturas de Venta). Al
--   generar la factura del período, ese texto pasa al "Adicional" de la línea de la
--   factura (facturas_venta_detalle.info_adicional → <detallesAdicionales> del XML).
--
-- TOCA DATOS: no (solo esquema). Idempotente: se puede correr varias veces.
-- REVERSIBLE: ALTER TABLE suscripciones_detalle DROP COLUMN IF EXISTS info_adicional;
-- El código degrada si la columna aún no existe (no la guarda ni la muestra),
-- así que el orden recomendado es: correr este SQL y luego desplegar.
-- =====================================================================================

ALTER TABLE suscripciones_detalle ADD COLUMN IF NOT EXISTS info_adicional VARCHAR(300);

-- Comprobación (debe salir 1 fila):
-- SELECT column_name, data_type, character_maximum_length
--   FROM information_schema.columns
--  WHERE table_name = 'suscripciones_detalle' AND column_name = 'info_adicional';
