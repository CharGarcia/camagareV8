-- Enlace del asiento contable generado automáticamente para Ingresos y Egresos.
-- Permite mostrar/abrir el asiento desde el documento y anularlo al anular el documento.
ALTER TABLE ingresos_cabecera ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER NULL;
ALTER TABLE egresos_cabecera  ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER NULL;
