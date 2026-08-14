-- =============================================================================
-- Importaciones: nuevo estado 'registrada'
-- ----------------------------------------------------------------------------
-- Paso intermedio opcional entre 'borrador' y 'nacionalizada': confirma los
-- datos de la importación (queda bloqueada para edición) sin enviarla todavía
-- al inventario. Se puede volver a 'borrador' para corregir, o procesar el
-- inventario directamente desde 'registrada' (igual que desde 'borrador').
--
-- Idempotente: DROP/ADD del CHECK constraint no falla si ya se aplicó antes
-- (queda igual, solo se recrea con el mismo contenido).
-- =============================================================================

BEGIN;

ALTER TABLE importaciones_cabecera DROP CONSTRAINT IF EXISTS importaciones_cabecera_estado_check;
ALTER TABLE importaciones_cabecera ADD CONSTRAINT importaciones_cabecera_estado_check
    CHECK (estado IN ('borrador', 'en_transito', 'registrada', 'pendiente_aprobacion', 'nacionalizada', 'cerrada', 'anulada'));

COMMIT;
