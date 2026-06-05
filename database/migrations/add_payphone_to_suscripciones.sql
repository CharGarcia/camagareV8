-- Método de pago con tarjeta (Payphone) para suscripciones.
-- No se almacenan datos sensibles de tarjeta (PCI): solo referencia y datos no sensibles.
ALTER TABLE suscripciones
    ADD COLUMN IF NOT EXISTS payphone_client_tx_id  VARCHAR(120),
    ADD COLUMN IF NOT EXISTS payphone_estado        VARCHAR(20) NOT NULL DEFAULT 'sin_registrar',
    ADD COLUMN IF NOT EXISTS payphone_card_last4     VARCHAR(4),
    ADD COLUMN IF NOT EXISTS payphone_card_brand     VARCHAR(40),
    ADD COLUMN IF NOT EXISTS payphone_fecha_registro TIMESTAMP;
