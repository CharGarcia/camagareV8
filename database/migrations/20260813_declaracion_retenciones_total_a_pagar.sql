-- =============================================================================
-- Declaración de Retenciones (F103): casillero 902 "Total impuesto a pagar"
-- ----------------------------------------------------------------------------
-- El egreso generado desde la declaración usaba total_retenido (casillero 499,
-- "Total retención") como monto a pagar. El propio formulario 103 del SRI separa
-- ese concepto del casillero 902 "Total impuesto a pagar" (= 499 - 898, donde 898
-- es el impuesto ya imputado a un pago anterior — p. ej. una rectificativa). Hoy
-- el sistema no soporta rectificativas con pago previo (898 siempre es 0), así
-- que numéricamente 902 y 499 coinciden en todas las declaraciones existentes:
-- el backfill de abajo simplemente copia el valor ya guardado, sin recalcular
-- nada. La columna queda lista para el día en que 898 tenga un valor real.
--
-- Aditivo y no destructivo. Idempotente.
-- =============================================================================

BEGIN;

ALTER TABLE declaracion_retenciones_cabecera
    ADD COLUMN IF NOT EXISTS total_a_pagar NUMERIC(14,2) NOT NULL DEFAULT 0;

UPDATE declaracion_retenciones_cabecera
   SET total_a_pagar = total_retenido
 WHERE total_a_pagar = 0;

COMMIT;
