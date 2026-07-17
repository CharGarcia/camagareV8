-- ============================================================
-- Aprobaciones: unificar Compras en UN solo checkpoint — idempotente
-- ============================================================
-- Reemplaza los dos checkpoints previos ("Registro de compra" y "Pago de
-- facturas de compra") por uno solo: "Aprobación de compras" (revisión
-- posterior al registro, no bloquea el guardado ni el pago).
-- ============================================================

-- Limpia config y solicitudes de los tipos anteriores antes de borrarlos
-- (idempotente: no falla si ya no existen).
DELETE FROM aprobaciones_solicitudes
WHERE id_tipo IN (SELECT id FROM aprobaciones_tipos WHERE codigo IN ('registro_compras', 'pago_compras'));

DELETE FROM aprobaciones_config
WHERE id_tipo IN (SELECT id FROM aprobaciones_tipos WHERE codigo IN ('registro_compras', 'pago_compras'));

DELETE FROM aprobaciones_tipos
WHERE codigo IN ('registro_compras', 'pago_compras');

INSERT INTO aprobaciones_tipos (codigo, nombre, descripcion, modulo_ruta)
VALUES (
    'aprobacion_compras',
    'Aprobación de compras',
    'Revisión y aprobación de las compras ya registradas en el sistema.',
    'modulos/compras'
)
ON CONFLICT (codigo) DO NOTHING;
