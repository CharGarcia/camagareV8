-- ============================================================================
--  CRM — Instagram con «Unified login» (apps de Meta nuevas)
-- ----------------------------------------------------------------------------
--  Diseño: docs/diseno/crm-redes-unified-login.md (aprobado el 09-11-2026).
--  Requiere antes: database/2026-11-08_crm_fase1.sql.
--
--  - crm_meta_apps.config_id: ID de configuración de inicio de sesión de Meta.
--    Con valor, la empresa usa Unified login (graph.facebook.com, token del
--    portafolio que no vence); vacío, sigue con Instagram Login (apps viejas).
--  - El modo y el portafolio del cliente de cada cuenta van en crm_canales.config
--    (JSONB que ya existe): no hay columnas nuevas ahí.
--
--  Toca datos: no (una columna nula).
--  Reversible: sí →  ALTER TABLE crm_meta_apps DROP COLUMN IF EXISTS config_id;
--  Idempotente: se puede ejecutar varias veces sin efecto.
--  Listo para pegar en el Query Tool de pgAdmin y ejecutar de una vez (F5).
-- ============================================================================

ALTER TABLE crm_meta_apps ADD COLUMN IF NOT EXISTS config_id VARCHAR(40) NULL;
COMMENT ON COLUMN crm_meta_apps.config_id IS
    'ID de configuración de inicio de sesión de Meta (Unified login). Vacío = Instagram Login. No es secreto.';

-- Comprobación (debe salir 1 fila):
-- SELECT column_name, data_type FROM information_schema.columns
--  WHERE table_name = 'crm_meta_apps' AND column_name = 'config_id';
