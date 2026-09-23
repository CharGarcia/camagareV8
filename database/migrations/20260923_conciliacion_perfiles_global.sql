-- ============================================================
-- Conciliación de Cobros: los perfiles de mapeo pasan a ser un
-- catálogo GLOBAL (config/conciliacion-perfiles, solo nivel 3).
--
-- Antes cada empresa creaba sus perfiles desde el propio módulo
-- (modulos/conciliacion-cobros). El formato del extracto depende del
-- banco, no de la empresa, así que ahora se configuran una sola vez y
-- todas las empresas eligen entre ellos al subir el extracto.
--
-- No destructivo: la columna id_empresa se conserva (solo deja de ser
-- obligatoria) como rastro de qué empresa creó cada perfil antiguo; el
-- código ya no filtra por ella. Los perfiles existentes quedan
-- disponibles para todas las empresas tal cual están.
-- Idempotente: se puede ejecutar más de una vez.
-- ============================================================

ALTER TABLE conciliacion_perfiles ALTER COLUMN id_empresa DROP NOT NULL;

COMMENT ON COLUMN conciliacion_perfiles.id_empresa IS
    'Obsoleto (catálogo global desde 2026-09-23): empresa que creó el perfil cuando era por empresa. No se filtra por esta columna.';

CREATE INDEX IF NOT EXISTS idx_concper_global ON conciliacion_perfiles(eliminado, activo);
