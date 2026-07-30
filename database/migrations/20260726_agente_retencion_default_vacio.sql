-- ============================================================================
-- El default de empresas.agente_retencion era 'NO' → una empresa nueva nacía
-- con 'NO' en el campo Agente de Retención. Debe nacer VACÍO (no es agente de
-- retención salvo que se configure), y así calza con el XSD del SRI (solo
-- dígitos: 'NO' no es válido).
--
-- Se cambia el default a '' y se normalizan las filas que aún tengan 'NO'.
-- Idempotente.
-- ============================================================================

ALTER TABLE empresas
    ALTER COLUMN agente_retencion SET DEFAULT '';

UPDATE empresas
   SET agente_retencion = ''
 WHERE agente_retencion IS NULL
    OR UPPER(agente_retencion) = 'NO';
