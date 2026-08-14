-- =============================================================================
-- Declaración de IVA (F104): casillero 902 "Total consolidado de impuesto a pagar"
-- ----------------------------------------------------------------------------
-- El egreso generado desde la declaración usaba iva_a_pagar directo como monto a
-- pagar. El propio Formulario 104 separa ese valor calculado del casillero 902
-- "Total consolidado de impuesto a pagar" (= iva_a_pagar - impuesto ya imputado a
-- un pago anterior de este mismo período, p. ej. una rectificativa). Mismo
-- criterio que ya se aplicó en Declaración de Retenciones (ver migración
-- 20260813_declaracion_retenciones_total_a_pagar.sql). Hoy el sistema no soporta
-- rectificativas con pago previo, así que numéricamente 902 y iva_a_pagar
-- coinciden en todas las declaraciones existentes: el backfill de abajo
-- simplemente copia el valor ya guardado, sin recalcular nada.
--
-- Aditiva y no destructiva. Idempotente.
-- =============================================================================

BEGIN;

ALTER TABLE declaracion_iva_cabecera
    ADD COLUMN IF NOT EXISTS total_a_pagar NUMERIC(14,2) NOT NULL DEFAULT 0;

UPDATE declaracion_iva_cabecera
   SET total_a_pagar = iva_a_pagar
 WHERE total_a_pagar = 0;

COMMIT;
