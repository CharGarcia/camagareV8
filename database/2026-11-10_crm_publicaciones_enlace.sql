-- ============================================================================
--  CRM — Publicaciones: formato «Enlace» de LinkedIn (tarjeta con imagen que
--  lleva a una dirección web)
-- ----------------------------------------------------------------------------
--  Requiere antes: database/2026-11-08_crm_fase3_publicaciones.sql.
--
--  - crm_publicaciones_destinos.link_url / link_titulo: la dirección y el título
--    de la tarjeta (solo se usan con formato = 'enlace').
--  - El CHECK de formato pasa a admitir 'enlace'.
--
--  Toca datos: no (solo agrega dos columnas vacías y amplía el CHECK).
--  Reversible: sí (solo si no hay destinos con formato 'enlace'):
--      ALTER TABLE crm_publicaciones_destinos DROP CONSTRAINT IF EXISTS crm_publicaciones_destinos_formato_check;
--      ALTER TABLE crm_publicaciones_destinos ADD CONSTRAINT crm_publicaciones_destinos_formato_check
--          CHECK (formato IN ('imagen', 'carrusel', 'reel', 'texto', 'documento'));
--      ALTER TABLE crm_publicaciones_destinos DROP COLUMN IF EXISTS link_url, DROP COLUMN IF EXISTS link_titulo;
--  Idempotente: se puede ejecutar varias veces sin efecto.
--  Listo para pegar en el Query Tool de pgAdmin y ejecutar de una vez (F5).
-- ============================================================================

ALTER TABLE crm_publicaciones_destinos ADD COLUMN IF NOT EXISTS link_url    VARCHAR(2000) NULL;
ALTER TABLE crm_publicaciones_destinos ADD COLUMN IF NOT EXISTS link_titulo VARCHAR(200)  NULL;

COMMENT ON COLUMN crm_publicaciones_destinos.link_url    IS 'Formato enlace (LinkedIn): dirección web a la que lleva la tarjeta.';
COMMENT ON COLUMN crm_publicaciones_destinos.link_titulo IS 'Formato enlace (LinkedIn): título que se ve en la tarjeta.';

ALTER TABLE crm_publicaciones_destinos DROP CONSTRAINT IF EXISTS crm_publicaciones_destinos_formato_check;
ALTER TABLE crm_publicaciones_destinos ADD CONSTRAINT crm_publicaciones_destinos_formato_check
    CHECK (formato IN ('imagen', 'carrusel', 'reel', 'texto', 'documento', 'enlace'));

-- Comprobación (deben salir 2 filas):
-- SELECT column_name, data_type, character_maximum_length FROM information_schema.columns
--  WHERE table_name = 'crm_publicaciones_destinos' AND column_name IN ('link_url', 'link_titulo');
