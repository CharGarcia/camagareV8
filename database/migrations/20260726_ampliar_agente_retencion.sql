-- ============================================================================
-- Ajusta empresas.agente_retencion al tamaño CORRECTO según el SRI.
--
-- Ficha técnica / XSD oficial (factura_V2.1.0.xsd), tipo agenteRetencion:
--     <xsd:restriction base="xsd:string">
--         <xsd:pattern value="[0-9]+"/>   -- SOLO dígitos
--         <xsd:maxLength value="8"/>       -- máximo 8
--     </xsd:restriction>
--
-- Es decir: NO admite texto libre; es un número de máximo 8 dígitos. La columna
-- era varchar(5) (demasiado corta: un número válido de 6-8 dígitos no cabía y
-- provocaba el error 22001 al guardar). Se lleva a varchar(8) para calzar con
-- el máximo del SRI.
--
-- Antes de reducir el tamaño, se normalizan valores que no calcen (p. ej. si se
-- guardó texto de la resolución): se dejan solo los dígitos, máx 8. Los valores
-- centinela como 'NO' (empresa que NO es agente) no tienen dígitos y quedan ''.
--
-- Idempotente: se puede correr varias veces sin error.
-- ============================================================================

-- 1) Normalizar cualquier valor que no calce en 8 caracteres (o traiga letras).
UPDATE empresas
   SET agente_retencion = LEFT(regexp_replace(COALESCE(agente_retencion, ''), '\D', '', 'g'), 8)
 WHERE agente_retencion IS NOT NULL
   AND (LENGTH(agente_retencion) > 8 OR agente_retencion ~ '\D');

-- 2) Ajustar el tipo al máximo del SRI.
ALTER TABLE empresas
    ALTER COLUMN agente_retencion TYPE VARCHAR(8);

COMMENT ON COLUMN empresas.agente_retencion IS
    'Número de resolución de agente de retención (SRI: solo dígitos, máx 8). Vacío = no es agente de retención.';
