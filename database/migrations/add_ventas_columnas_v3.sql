-- Migración v3: columnas faltantes en ventas_cabecera y ventas_detalle
-- Idempotente: usa ADD COLUMN IF NOT EXISTS

-- ============================================================
-- ventas_cabecera
-- ============================================================
ALTER TABLE ventas_cabecera ADD COLUMN IF NOT EXISTS total_ice       NUMERIC(14,2) NOT NULL DEFAULT 0;
ALTER TABLE ventas_cabecera ADD COLUMN IF NOT EXISTS tipo_ambiente   VARCHAR(5);
ALTER TABLE ventas_cabecera ADD COLUMN IF NOT EXISTS tipo_emision    VARCHAR(5);
ALTER TABLE ventas_cabecera ADD COLUMN IF NOT EXISTS estado_correo   VARCHAR(20) NOT NULL DEFAULT 'pendiente';

-- plazo: puede ser integer o varchar según el estado de la BD
-- Si ya es integer se convierte a varchar; si ya es varchar no hace nada
DO $$ BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'ventas_cabecera' AND column_name = 'plazo'
          AND data_type = 'integer'
    ) THEN
        ALTER TABLE ventas_cabecera ALTER COLUMN plazo TYPE VARCHAR(20) USING plazo::text;
        ALTER TABLE ventas_cabecera ALTER COLUMN plazo DROP DEFAULT;
        ALTER TABLE ventas_cabecera ALTER COLUMN plazo DROP NOT NULL;
    END IF;
END $$;

-- ============================================================
-- ventas_detalle
-- ============================================================
ALTER TABLE ventas_detalle ADD COLUMN IF NOT EXISTS casillero        VARCHAR(10);
ALTER TABLE ventas_detalle ADD COLUMN IF NOT EXISTS info_adicional   TEXT;
ALTER TABLE ventas_detalle ADD COLUMN IF NOT EXISTS numero_lote      VARCHAR(100);
ALTER TABLE ventas_detalle ADD COLUMN IF NOT EXISTS fecha_caducidad  DATE;
ALTER TABLE ventas_detalle ADD COLUMN IF NOT EXISTS nup              VARCHAR(100);
