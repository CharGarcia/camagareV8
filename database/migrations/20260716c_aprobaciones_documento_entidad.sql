-- ============================================================
-- Aprobaciones: columnas propias de documento (para que la bandeja se vea
-- como un listado real, no una fila genérica) — idempotente
-- ============================================================
-- Cualquier módulo que se engancha puede informar, además de descripcion/monto:
--   entidad_nombre    → proveedor/cliente/empleado relacionado al documento origen.
--   documento_numero  → número/código correlativo del documento origen (ej. "001-001-000123").
-- Son opcionales: si el módulo no los envía, quedan NULL y la bandeja sigue
-- funcionando igual (solo con descripcion/monto).
-- ============================================================

ALTER TABLE aprobaciones_solicitudes
    ADD COLUMN IF NOT EXISTS entidad_nombre VARCHAR(200);

ALTER TABLE aprobaciones_solicitudes
    ADD COLUMN IF NOT EXISTS documento_numero VARCHAR(60);
