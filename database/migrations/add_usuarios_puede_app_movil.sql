-- ============================================================================
-- Permiso explícito de acceso a la app móvil.
--
-- Solo un usuario nivel 3 (super administrador) puede activarlo, desde
-- config/usuarios-sistema. Por defecto queda en FALSE: ningún usuario existente
-- ni nuevo puede iniciar sesión en la app móvil hasta que un superadmin se lo
-- habilite explícitamente.
--
-- Idempotente: se puede correr varias veces sin error.
-- ============================================================================

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS puede_app_movil BOOLEAN NOT NULL DEFAULT FALSE;

COMMENT ON COLUMN usuarios.puede_app_movil IS 'TRUE = el usuario puede iniciar sesión en la app móvil (API v1). Editable solo por un usuario nivel 3 desde config/usuarios-sistema.';
