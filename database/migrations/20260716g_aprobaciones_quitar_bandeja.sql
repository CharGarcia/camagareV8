-- ============================================================
-- Aprobaciones: quitar la bandeja de solicitudes — idempotente
-- ============================================================
-- Se retiró el módulo modulos/aprobaciones (bandeja de solicitudes) y su
-- integración con Compras/Cuentas por Pagar. Por ahora solo queda la
-- configuración (modulos/aprobaciones-config): qué checkpoints existen y
-- quién los aprueba. aprobaciones_tipos y aprobaciones_config se conservan.
-- ============================================================

DROP TABLE IF EXISTS aprobaciones_solicitudes;
