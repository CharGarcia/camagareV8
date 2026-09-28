-- ============================================================================
--  Car-Wash / Vehículos: recordatorios de la próxima cita.
--
--  Cada recordatorio enviado (a mano desde la ficha del vehículo, pestaña
--  "Recordatorios", o automático desde Automatizaciones → Car-Wash) queda
--  registrado aquí: sirve de historial y evita que el envío automático mande dos
--  veces el recordatorio de la misma cita por el mismo canal.
--
--  Tabla operativa (id_empresa + auditoría + eliminación lógica). Idempotente.
-- ============================================================================

CREATE TABLE IF NOT EXISTS carwash_recordatorios (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL,
    id_orden        INTEGER NOT NULL REFERENCES carwash_ordenes(id) ON DELETE CASCADE,
    id_vehiculo     INTEGER,
    id_cliente      INTEGER,
    fecha_cita      DATE NOT NULL,
    canal           VARCHAR(10) NOT NULL,          -- 'correo' | 'whatsapp'
    destinatario    VARCHAR(200),                  -- correo(s) o teléfono
    asunto          VARCHAR(250),
    mensaje         TEXT,
    estado          VARCHAR(10) NOT NULL,          -- 'enviado' | 'error'
    detalle         TEXT,                          -- motivo del error / observación
    origen          VARCHAR(12) NOT NULL DEFAULT 'manual', -- 'manual' | 'automatico'
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by      INTEGER,
    updated_by      INTEGER,
    eliminado       BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER
);

CREATE INDEX IF NOT EXISTS idx_carwash_rec_vehiculo ON carwash_recordatorios (id_empresa, id_vehiculo, created_at DESC) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_carwash_rec_orden    ON carwash_recordatorios (id_orden, fecha_cita, canal) WHERE eliminado = false;

-- El envío AUTOMÁTICO manda una sola vez el recordatorio de una cita por canal.
-- (Los envíos manuales pueden repetirse: el usuario decide.)
CREATE UNIQUE INDEX IF NOT EXISTS uq_carwash_rec_automatico
    ON carwash_recordatorios (id_empresa, id_orden, fecha_cita, canal)
    WHERE estado = 'enviado' AND origen = 'automatico' AND eliminado = false;

-- Búsqueda de citas próximas (pestaña Recordatorios y envío automático).
CREATE INDEX IF NOT EXISTS idx_carwash_ordenes_proxima_cita
    ON carwash_ordenes (id_empresa, proxima_cita) WHERE eliminado = false AND proxima_cita IS NOT NULL;
