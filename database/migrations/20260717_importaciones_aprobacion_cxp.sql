-- ============================================================
-- Importaciones: flujo de aprobación de inventario (igual patrón que
-- Cargas de Inventario, empresa_establecimiento.inv_requiere_aprobacion)
-- + integración con Cuentas por Pagar (proveedor del exterior).
-- Idempotente.
-- ============================================================

BEGIN;

-- 1. Nuevo estado intermedio: 'pendiente_aprobacion' (entre 'borrador' y
--    'nacionalizada', cuando la empresa exige aprobación para inventario).
DO $$
DECLARE
    v_conname text;
BEGIN
    SELECT conname INTO v_conname
    FROM pg_constraint
    WHERE conrelid = 'importaciones_cabecera'::regclass
      AND pg_get_constraintdef(oid) ILIKE '%estado%IN%';

    IF v_conname IS NOT NULL THEN
        EXECUTE format('ALTER TABLE importaciones_cabecera DROP CONSTRAINT %I', v_conname);
    END IF;

    ALTER TABLE importaciones_cabecera
        ADD CONSTRAINT importaciones_cabecera_estado_check
        CHECK (estado IN ('borrador', 'en_transito', 'pendiente_aprobacion', 'nacionalizada', 'cerrada', 'anulada'));
END $$;

-- 2. Columnas de auditoría de la aprobación (mismo patrón que inventario_cargas).
ALTER TABLE importaciones_cabecera
    ADD COLUMN IF NOT EXISTS aprobada_por   INTEGER,
    ADD COLUMN IF NOT EXISTS aprobada_at    TIMESTAMP,
    ADD COLUMN IF NOT EXISTS motivo_rechazo TEXT;

COMMIT;
