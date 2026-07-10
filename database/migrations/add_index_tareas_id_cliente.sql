-- Índice para tareas.id_cliente: usado intensivamente por el "combo" de obligaciones
-- vigentes por cliente y el listado "Detalle por cliente" (módulo Tareas y Obligaciones).
CREATE INDEX IF NOT EXISTS idx_tareas_id_cliente ON tareas(id_cliente);
