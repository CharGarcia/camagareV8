-- Eliminar columna casilleros_sri de la tabla productos
-- Este campo fue removido del módulo (pestaña SRI eliminada)
ALTER TABLE productos DROP COLUMN IF EXISTS casilleros_sri;
