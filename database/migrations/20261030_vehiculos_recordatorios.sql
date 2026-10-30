-- ============================================================================
--  Vehículos: recordatorios agregados a mano (pestaña "Recordatorios").
--
--  Además de la "próxima cita" de cada orden de Car-Wash, el usuario puede agregar
--  recordatorios propios del vehículo (fecha + motivo, p. ej. "Cambio de aceite"),
--  sin necesidad de una orden. Se avisan igual que las citas: a mano (Correo /
--  WhatsApp) o con la automatización "Car-Wash → Recordatorio de próxima cita".
--
--  Los envíos siguen en carwash_recordatorios: id_orden pasa a ser opcional y se
--  agrega id_recordatorio (uno de los dos va siempre lleno).
--
--  Requiere 20260929_carwash_recordatorios.sql. Idempotente.
-- ============================================================================

CREATE TABLE IF NOT EXISTS vehiculos_recordatorios (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL,
    id_vehiculo     INTEGER NOT NULL REFERENCES vehiculos(id),
    fecha           DATE NOT NULL,
    motivo          VARCHAR(200) NOT NULL,
    observacion     TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by      INTEGER,
    updated_by      INTEGER,
    eliminado       BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER
);

CREATE INDEX IF NOT EXISTS idx_vehiculos_rec_vehiculo ON vehiculos_recordatorios (id_empresa, id_vehiculo, fecha DESC) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_vehiculos_rec_fecha    ON vehiculos_recordatorios (id_empresa, fecha) WHERE eliminado = false;

-- Envíos: de una orden (id_orden) o de un recordatorio agregado (id_recordatorio).
ALTER TABLE carwash_recordatorios ALTER COLUMN id_orden DROP NOT NULL;
ALTER TABLE carwash_recordatorios ADD COLUMN IF NOT EXISTS id_recordatorio INTEGER REFERENCES vehiculos_recordatorios(id);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_carwash_rec_origen_cita') THEN
        ALTER TABLE carwash_recordatorios ADD CONSTRAINT chk_carwash_rec_origen_cita
            CHECK (id_orden IS NOT NULL OR id_recordatorio IS NOT NULL);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_carwash_rec_recordatorio
    ON carwash_recordatorios (id_recordatorio, fecha_cita, canal) WHERE eliminado = false AND id_recordatorio IS NOT NULL;

-- El envío AUTOMÁTICO avisa una sola vez cada recordatorio agregado por canal
-- (el índice uq_carwash_rec_automatico cubre las citas de las órdenes).
CREATE UNIQUE INDEX IF NOT EXISTS uq_carwash_rec_automatico_manual
    ON carwash_recordatorios (id_empresa, id_recordatorio, fecha_cita, canal)
    WHERE estado = 'enviado' AND origen = 'automatico' AND eliminado = false AND id_recordatorio IS NOT NULL;
