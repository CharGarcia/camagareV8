-- Clientes: campo libre de Observaciones (pestaña Comercial de la ficha).
-- Ej.: quién solicitó crear el cliente, "cliente VIP", indicaciones de trato.
-- Quién la guardó o la cambió y cuándo queda en log_sistema, como el resto de la ficha.
-- Aplicar ANTES de desplegar el código: el guardado del cliente ya escribe esta columna.

ALTER TABLE clientes ADD COLUMN IF NOT EXISTS observaciones TEXT NULL;

COMMENT ON COLUMN clientes.observaciones IS 'Observaciones internas del cliente (pestaña Comercial de la ficha)';
