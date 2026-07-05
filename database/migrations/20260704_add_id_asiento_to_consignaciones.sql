-- Enlace del asiento contable (reclasificación de inventario a costo) de una
-- consignación de venta. Permite abrir el asiento desde el documento y anularlo
-- al eliminar la consignación.
ALTER TABLE consignaciones_ventas ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER NULL;
