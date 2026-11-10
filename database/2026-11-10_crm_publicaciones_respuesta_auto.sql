-- ============================================================================
--  CRM — Publicaciones: respuesta automática a comentarios de Instagram
--  («Comenta INFO y te escribimos»)
-- ----------------------------------------------------------------------------
--  Requiere antes: database/2026-11-08_crm_fase3_publicaciones.sql.
--
--  - crm_publicaciones.auto_palabra: palabra clave que debe traer el comentario.
--  - crm_publicaciones.auto_mensaje: mensaje privado que el sistema envía solo a
--    quien comenta esa palabra en la publicación de Instagram.
--    Las dos vacías = sin respuesta automática.
--
--  Toca datos: no (solo agrega dos columnas vacías).
--  Reversible: sí:
--      ALTER TABLE crm_publicaciones DROP COLUMN IF EXISTS auto_palabra, DROP COLUMN IF EXISTS auto_mensaje;
--  Idempotente: se puede ejecutar varias veces sin efecto.
--  Listo para pegar en el Query Tool de pgAdmin y ejecutar de una vez (F5).
-- ============================================================================

ALTER TABLE crm_publicaciones ADD COLUMN IF NOT EXISTS auto_palabra VARCHAR(40) NULL;
ALTER TABLE crm_publicaciones ADD COLUMN IF NOT EXISTS auto_mensaje TEXT NULL;

COMMENT ON COLUMN crm_publicaciones.auto_palabra IS 'Respuesta automática (Instagram): palabra clave que debe traer el comentario.';
COMMENT ON COLUMN crm_publicaciones.auto_mensaje IS 'Respuesta automática (Instagram): mensaje privado que se envía a quien comenta la palabra clave.';

-- Comprobación (deben salir 2 filas):
-- SELECT column_name, data_type FROM information_schema.columns
--  WHERE table_name = 'crm_publicaciones' AND column_name IN ('auto_palabra', 'auto_mensaje');
