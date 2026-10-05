-- ============================================================================
-- Suscripciones: asistente de apertura del devengado.
--
-- Las facturas/recibos de suscripción emitidos ANTES de activar el devengado ya
-- acreditaron todo su valor al ingreso. El asistente de apertura (Suscripciones →
-- Devengar mes → Apertura) arma su cronograma para los meses que faltan y registra
-- un asiento de reclasificación: DEBE ingreso / HABER Ingresos diferidos.
--
-- Esas filas se marcan con origen = 'apertura' porque su pasivo lo creó el asiento
-- de apertura, NO el asiento de la factura: si la factura se vuelve a contabilizar
-- (Sincronizar, Auditoría Contable), su asiento NO debe restarlas otra vez.
--
-- Idempotente. Listo para pgAdmin (F5). Requiere 20261004_suscripciones_devengo.sql.
-- ============================================================================

BEGIN;

ALTER TABLE suscripciones_devengos
    ADD COLUMN IF NOT EXISTS origen VARCHAR(10) NOT NULL DEFAULT 'documento';

-- Asiento de reclasificación con que la apertura creó el pasivo de la fila.
ALTER TABLE suscripciones_devengos
    ADD COLUMN IF NOT EXISTS id_asiento_apertura INTEGER;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_susc_devengos_origen') THEN
        ALTER TABLE suscripciones_devengos ADD CONSTRAINT chk_susc_devengos_origen
            CHECK (origen IN ('documento', 'apertura'));
    END IF;
END $$;

COMMENT ON COLUMN suscripciones_devengos.origen IS
    'documento: el asiento de la factura/recibo acreditó el pasivo. apertura: lo creó el asiento de apertura (documento emitido antes de activar el devengado).';

COMMIT;

-- ── Comprobación (ejecutar aparte) ──────────────────────────────────────────
-- Deben salir 2 filas:
-- SELECT column_name, column_default FROM information_schema.columns
--  WHERE table_name = 'suscripciones_devengos' AND column_name IN ('origen', 'id_asiento_apertura');
