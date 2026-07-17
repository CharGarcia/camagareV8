-- ============================================================
-- Aprobaciones: payload de la acción bloqueada — idempotente
-- ============================================================
-- Para checkpoints BLOQUEANTES (ej. "Pago de facturas de compra"): el módulo
-- llamante frena la acción real y guarda aquí los datos originales de la
-- petición (ya validados) para poder ejecutarla tal cual al aprobarse, vía el
-- callback registrado en config/aprobaciones_registry.php.
-- ============================================================

ALTER TABLE aprobaciones_solicitudes
    ADD COLUMN IF NOT EXISTS payload JSONB;
