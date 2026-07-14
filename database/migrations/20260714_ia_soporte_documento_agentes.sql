-- ============================================================================
--  IA Soporte — relación explícita documento PDF ↔ agente/prompt
--
--  Un documento SIN filas aquí está disponible para TODOS los agentes de la
--  empresa (comportamiento actual, retrocompatible con documentos ya subidos).
--  Un documento CON filas queda restringido solo a los agentes listados.
--  Tabla puramente relacional (no es un registro de negocio en sí mismo): no
--  lleva id_empresa (se deriva de ia_documentos) ni auditoría/soft-delete.
-- ============================================================================

CREATE TABLE IF NOT EXISTS ia_documento_agentes (
    id_documento INTEGER NOT NULL REFERENCES ia_documentos(id) ON DELETE CASCADE,
    id_agente    INTEGER NOT NULL REFERENCES ia_agentes(id) ON DELETE CASCADE,
    PRIMARY KEY (id_documento, id_agente)
);
CREATE INDEX IF NOT EXISTS idx_ia_doc_agentes_agente ON ia_documento_agentes(id_agente);
