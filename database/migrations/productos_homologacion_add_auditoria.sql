-- Agrega los campos de auditoría faltantes a productos_homologacion.
-- Necesarios para el borrado lógico (softDeleteHomologacion) y la regla §5 de CLAUDE.md.
-- Sin ellos, eliminar una homologación falla: "no existe la columna deleted_at".

ALTER TABLE productos_homologacion
  ADD COLUMN IF NOT EXISTS updated_at timestamp without time zone,
  ADD COLUMN IF NOT EXISTS updated_by integer,
  ADD COLUMN IF NOT EXISTS deleted_at timestamp without time zone,
  ADD COLUMN IF NOT EXISTS deleted_by integer;
