-- Ubicación física del producto/servicio (texto libre, general por producto).
-- Se usa en el modal de Productos (pestaña Detalles) y como filtro/columna
-- en el listado y reportes (PDF/Excel) del módulo.
ALTER TABLE productos ADD COLUMN IF NOT EXISTS ubicacion VARCHAR(150);
