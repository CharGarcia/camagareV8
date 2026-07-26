-- ============================================================================
-- Impresión de Cheques: nombre a imprimir en el cheque (override).
-- Permite cambiar el nombre que aparece en el cheque SIN alterar el beneficiario
-- del egreso. Si es NULL/vacío, se usa el beneficiario del egreso.
-- (También se autocrea en runtime desde EgresoRepository.)
-- ============================================================================

ALTER TABLE egresos_pagos ADD COLUMN IF NOT EXISTS beneficiario_cheque VARCHAR(255) NULL;

COMMENT ON COLUMN egresos_pagos.beneficiario_cheque IS 'Nombre a imprimir en el cheque (override); NULL = usar el beneficiario del egreso.';
