-- Migración: Agregar campos recibo_de e id_recibo_cliente a ingresos_cabecera
-- Permite registrar el beneficiario principal del recibo (siempre visible)
-- y opcionalmente vincularlo a un cliente del catálogo.

ALTER TABLE ingresos_cabecera
    ADD COLUMN IF NOT EXISTS recibo_de         VARCHAR(300),
    ADD COLUMN IF NOT EXISTS id_recibo_cliente INTEGER REFERENCES clientes(id);

COMMENT ON COLUMN ingresos_cabecera.recibo_de         IS 'Nombre del beneficiario que aparece en el recibo (siempre requerido)';
COMMENT ON COLUMN ingresos_cabecera.id_recibo_cliente IS 'FK opcional al catálogo de clientes para el campo Recibo de';
