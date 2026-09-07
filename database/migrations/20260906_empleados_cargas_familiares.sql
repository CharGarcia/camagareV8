-- ============================================================================
-- Empleados: número de cargas familiares (pestaña General de la ficha)
-- ----------------------------------------------------------------------------
-- Dato informativo del empleado, editable en el modal y cargable por Excel
-- (plantilla del módulo Empleados y entidad Empleados del Importador).
-- No reemplaza a empleado_gastos_personales.numero_cargas_familiares, que es
-- el valor declarado por AÑO en el formulario SRI-GP para la rebaja del IR.
-- Idempotente: se puede ejecutar más de una vez.
-- ============================================================================
ALTER TABLE empleados
    ADD COLUMN IF NOT EXISTS cargas_familiares SMALLINT NOT NULL DEFAULT 0;

COMMENT ON COLUMN empleados.cargas_familiares IS
    'Número de cargas familiares del empleado (dato general de la ficha).';
