-- ============================================================
-- Migración: Columnas de cobro predeterminado en tabla clientes
-- Fecha: 2026-06-04
-- ============================================================

ALTER TABLE clientes
    ADD COLUMN IF NOT EXISTS id_forma_cobro_predeterminada    INTEGER      DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS tipo_operacion_bancaria_predeterminada VARCHAR(20) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS monto_maximo_auto_cobro           NUMERIC(12,2) DEFAULT NULL;

-- Comentarios descriptivos
COMMENT ON COLUMN clientes.id_forma_cobro_predeterminada          IS 'Forma de cobro predeterminada para el cliente (FK a formas_cobro)';
COMMENT ON COLUMN clientes.tipo_operacion_bancaria_predeterminada IS 'Tipo de operación bancaria predeterminada: DEPOSITO, TRANSFERENCIA, CHEQUE';
COMMENT ON COLUMN clientes.monto_maximo_auto_cobro                IS 'Monto máximo permitido para cobro automático';
