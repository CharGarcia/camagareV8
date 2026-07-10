-- ============================================================================
--  Tratamiento de atrasos POR EMPLEADO (override del default de la empresa).
--  NULL = hereda el modo configurado en la empresa (asistencia_config.atraso_modo).
--  Valores: 'informativo' | 'descuento' | 'dias'.
-- ============================================================================

ALTER TABLE empleados
    ADD COLUMN IF NOT EXISTS atraso_modo VARCHAR(20);

COMMENT ON COLUMN empleados.atraso_modo IS
    'Tratamiento de atrasos del empleado (override): informativo|descuento|dias. NULL = hereda de la empresa.';
