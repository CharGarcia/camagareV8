-- =====================================================================
-- Retenciones de venta (recibidas): documento de sustento OPCIONAL
-- ---------------------------------------------------------------------
-- En la versión 1.0.0 del comprobante de retención del SRI,
-- <codDocSustento>, <numDocSustento> y <fechaEmisionDocSustento> son
-- opcionales. Los bancos los omiten (retenciones sobre intereses con
-- sustento 12 y número en ceros, ISD por transferencias al exterior con
-- sustento 00, o retenciones sin código de sustento). El sistema guarda los
-- datos tal como vienen en el XML: si no vienen, quedan en NULL. Para eso
-- las columnas de código y fecha no pueden ser NOT NULL (num_doc_sustento
-- ya admite NULL).
--
-- Idempotente: se puede reejecutar sin error. Ejecutar ANTES de desplegar
-- el código (sin esto, el registro de esas retenciones falla con
-- "null value in column ...").
-- =====================================================================
ALTER TABLE retencion_venta_detalle
    ALTER COLUMN fecha_emision_doc_sustento DROP NOT NULL;

ALTER TABLE retencion_venta_detalle
    ALTER COLUMN cod_doc_sustento DROP NOT NULL;

-- Comprobación (deben salir dos filas con is_nullable = 'YES'):
-- SELECT column_name, is_nullable
--   FROM information_schema.columns
--  WHERE table_name = 'retencion_venta_detalle'
--    AND column_name IN ('cod_doc_sustento', 'fecha_emision_doc_sustento');
