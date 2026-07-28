-- ============================================================================
-- PREVENTIVO: alinea al XSD del SRI los campos de texto libre que estaban por
-- DEBAJO del máximo permitido (mismo tipo de problema que agente_retencion,
-- pero de bajo riesgo porque exceder el largo actual es poco común).
--
-- XSD (factura_V2.1.0): razonSocial / nombreComercial / dirMatriz y sus
-- equivalentes del comprador (razonSocialComprador / direccionComprador)
-- admiten maxLength=300. Estas columnas eran más cortas y, con un valor de
-- 256-300 caracteres, provocarían el mismo error 22001 al guardar.
--
-- Solo se AMPLÍAN columnas (operación segura, sin pérdida de datos).
-- Idempotente: reejecutable sin error.
-- ============================================================================

-- Emisor (tabla empresas) — compartido por TODOS los comprobantes.
ALTER TABLE empresas ALTER COLUMN nombre           TYPE VARCHAR(300);
ALTER TABLE empresas ALTER COLUMN nombre_comercial TYPE VARCHAR(300);
ALTER TABLE empresas ALTER COLUMN direccion        TYPE VARCHAR(300);

-- Receptor (tabla clientes) — razón social y dirección del comprador.
ALTER TABLE clientes ALTER COLUMN nombre    TYPE VARCHAR(300);
ALTER TABLE clientes ALTER COLUMN direccion TYPE VARCHAR(300);
