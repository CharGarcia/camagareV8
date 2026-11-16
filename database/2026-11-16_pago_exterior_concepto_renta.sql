-- ============================================================================
-- Compras y Liquidaciones de compra: concepto de Renta de un pago al exterior
-- sin retención (ATS)
-- ----------------------------------------------------------------------------
-- Contexto:
--   Una compra pagada al exterior que no lleva retención de Renta (p. ej. bienes
--   comprados a Colombia, con convenio de doble tributación por la Decisión 578
--   de la CAN) salía en el ATS con el concepto 332, y el SRI lo rechaza:
--   "El CONCEPTO DE RETENCIÓN EN LA FUENTE ... [332] no es válido para tipo de
--   pago". El 332 es solo para pagos a residentes.
--
-- Qué hace:
--   Agrega a compras_cabecera y liquidaciones_cabecera la columna
--   cod_ret_air_exterior: el concepto de pago a no residentes (500 a 525, ver
--   App\Helpers\ConceptosRentaExterior) que el ATS reporta con la base del
--   documento, 0 % y 0,00 cuando no hay retención registrada. No crea ninguna
--   retención. Nullable: las compras existentes no cambian.
--
-- El código funciona antes de correr este script (simplemente no guarda el
-- concepto), así que no rompe nada si se despliega primero.
--
-- Idempotente: IF NOT EXISTS.
--
-- Reversible:
--   ALTER TABLE compras_cabecera       DROP COLUMN IF EXISTS cod_ret_air_exterior;
--   ALTER TABLE liquidaciones_cabecera DROP COLUMN IF EXISTS cod_ret_air_exterior;
-- ============================================================================

BEGIN;

ALTER TABLE compras_cabecera
    ADD COLUMN IF NOT EXISTS cod_ret_air_exterior VARCHAR(5);

ALTER TABLE liquidaciones_cabecera
    ADD COLUMN IF NOT EXISTS cod_ret_air_exterior VARCHAR(5);

COMMENT ON COLUMN compras_cabecera.cod_ret_air_exterior IS
    'ATS codRetAir de un pago al exterior SIN retención de Renta (500-525, App\Helpers\ConceptosRentaExterior): se reporta al 0 %. NULL si no aplica';
COMMENT ON COLUMN liquidaciones_cabecera.cod_ret_air_exterior IS
    'ATS codRetAir de un pago al exterior SIN retención de Renta (500-525, App\Helpers\ConceptosRentaExterior): se reporta al 0 %. NULL si no aplica';

COMMIT;
