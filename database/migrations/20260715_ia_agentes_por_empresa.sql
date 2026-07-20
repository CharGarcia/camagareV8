-- ============================================================================
--  IA Soporte — prompts propios por empresa
--
--  ia_agentes.id_empresa NULL  = plantilla GLOBAL (las 5 base), visible para
--                                 todas las empresas, solo editable por superadmin.
--  ia_agentes.id_empresa = N   = prompt propio de la empresa N: solo esa
--                                 empresa lo ve/usa, y solo sus administradores
--                                 (nivel 2) o el superadmin pueden editarlo.
-- ============================================================================

ALTER TABLE ia_agentes ADD COLUMN IF NOT EXISTS id_empresa INTEGER NULL REFERENCES empresas(id);
CREATE INDEX IF NOT EXISTS idx_ia_agentes_empresa ON ia_agentes(id_empresa);
