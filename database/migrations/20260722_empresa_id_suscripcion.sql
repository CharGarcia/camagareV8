-- ============================================================================
-- Vínculo explícito entre una empresa y SU suscripción (casos de reventa).
--
-- Problema: cuando se factura a un revendedor (id_cliente_facturado) y ese
-- cliente tiene VARIAS suscripciones (una por cada empresa suya), la ficha de
-- "Suscripción y Vigencia" mostraba TODAS las suscripciones del cliente en
-- CADA empresa, porque el filtro era solo por cliente.
--
-- `suscripciones` no tiene ningún campo que apunte a la empresa atendida
-- (id_empresa = controladora, id_cliente = a quién se factura), por lo que se
-- agrega el vínculo del lado de la empresa.
--
-- Idempotente: se puede correr varias veces sin error.
-- ============================================================================

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS id_suscripcion INTEGER;

COMMENT ON COLUMN empresas.id_suscripcion IS
    'Suscripción específica que cubre a esta empresa. Se usa cuando el cliente facturado (reventa) tiene varias suscripciones. Si es NULL y el cliente tiene una sola, se resuelve automáticamente.';

CREATE INDEX IF NOT EXISTS idx_empresas_id_suscripcion
    ON empresas (id_suscripcion) WHERE id_suscripcion IS NOT NULL;
