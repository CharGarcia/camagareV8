-- Migración: Agregar campo valor_limite_consumidor_final en empresa_establecimiento
-- Fecha: 2026-04-16
-- Descripción: Establece el valor máximo que se puede facturar (incluido impuestos)
--              a un consumidor final, configurable por establecimiento.

ALTER TABLE empresa_establecimiento
    ADD COLUMN IF NOT EXISTS valor_limite_consumidor_final NUMERIC(12,2) DEFAULT 200.00;

COMMENT ON COLUMN empresa_establecimiento.valor_limite_consumidor_final
    IS 'Valor máximo permitido para facturar a un consumidor final (incluido impuestos). NULL = sin límite.';
