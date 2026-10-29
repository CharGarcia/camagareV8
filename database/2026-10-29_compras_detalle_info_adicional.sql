-- =====================================================================================
-- Compras: "Adicional" por ítem del detalle (compras_detalle.info_adicional).
--
-- QUÉ HACE
--   Agrega la columna donde se guarda la información adicional de cada línea de la
--   compra: la que trae el XML del proveedor en <detallesAdicionales><detAdicional>
--   (nombre: valor, varios unidos con " | ") o la que el usuario escribe en la
--   columna "Adicional" del modal. Mismo criterio que facturas_venta_detalle.
--
-- TOCA DATOS: no (solo esquema). Idempotente: se puede correr varias veces.
-- El código degrada si la columna aún no existe (no la guarda ni la muestra),
-- así que el orden recomendado es: correr este SQL y luego desplegar.
-- =====================================================================================

ALTER TABLE compras_detalle ADD COLUMN IF NOT EXISTS info_adicional VARCHAR(300);
