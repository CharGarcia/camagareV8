-- Anular cheque de un egreso dejando registro histórico (sin borrar la fila).
-- El egreso sigue vigente; solo se marca el cheque puntual como anulado, con
-- motivo y auditoría. El asiento contable se regenera excluyendo su monto
-- (AsientoBuilderService::lineasFormas), así que se recalcula solo, sin asiento
-- de reversión. Ver docs/manual/modulos/egresos.md.

ALTER TABLE egresos_pagos ADD COLUMN IF NOT EXISTS estado_cheque VARCHAR(20) NOT NULL DEFAULT 'vigente';
ALTER TABLE egresos_pagos ADD COLUMN IF NOT EXISTS motivo_anulacion_cheque VARCHAR(255) NULL;
ALTER TABLE egresos_pagos ADD COLUMN IF NOT EXISTS anulado_cheque_at TIMESTAMP NULL;
ALTER TABLE egresos_pagos ADD COLUMN IF NOT EXISTS anulado_cheque_by INTEGER NULL REFERENCES usuarios(id);
