-- ============================================================================
--  Vehículos: avisos de mantenimiento por kilometraje (Fase 3).
--
--  Los avisos al cliente del próximo cambio de aceite (motor, caja y demás servicios)
--  se registran en la misma tabla que los recordatorios de cita (carwash_recordatorios),
--  con una fila por servicio avisado:
--    id_vehiculo_mantenimiento + proximo_km  → a qué cambio corresponde el aviso
--    aviso_n                                 → 1 = primer aviso, 2.. = recordatorios
--                                              al seguir vencido (solo el automático)
--  El índice único evita que el envío automático mande dos veces el mismo aviso.
--
--  Requiere 20260929_carwash_recordatorios.sql y 20261030_vehiculos_mantenimientos.sql.
--  Idempotente.
-- ============================================================================

ALTER TABLE carwash_recordatorios ALTER COLUMN id_orden DROP NOT NULL;
ALTER TABLE carwash_recordatorios ADD COLUMN IF NOT EXISTS id_vehiculo_mantenimiento INTEGER REFERENCES vehiculos_mantenimientos(id);
ALTER TABLE carwash_recordatorios ADD COLUMN IF NOT EXISTS id_servicio INTEGER;
ALTER TABLE carwash_recordatorios ADD COLUMN IF NOT EXISTS proximo_km  INTEGER;
ALTER TABLE carwash_recordatorios ADD COLUMN IF NOT EXISTS aviso_n     SMALLINT;

-- Origen del envío: una orden, un recordatorio agregado a mano o un mantenimiento.
DO $$
DECLARE
    con_manual BOOLEAN := EXISTS (SELECT 1 FROM information_schema.columns
                                   WHERE table_name = 'carwash_recordatorios' AND column_name = 'id_recordatorio');
BEGIN
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_carwash_rec_origen_cita') THEN
        ALTER TABLE carwash_recordatorios DROP CONSTRAINT chk_carwash_rec_origen_cita;
    END IF;
    IF con_manual THEN
        ALTER TABLE carwash_recordatorios ADD CONSTRAINT chk_carwash_rec_origen_cita
            CHECK (id_orden IS NOT NULL OR id_recordatorio IS NOT NULL OR id_vehiculo_mantenimiento IS NOT NULL);
    ELSE
        ALTER TABLE carwash_recordatorios ADD CONSTRAINT chk_carwash_rec_origen_cita
            CHECK (id_orden IS NOT NULL OR id_vehiculo_mantenimiento IS NOT NULL);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_carwash_rec_mantenimiento
    ON carwash_recordatorios (id_vehiculo_mantenimiento, proximo_km, canal)
    WHERE eliminado = false AND id_vehiculo_mantenimiento IS NOT NULL;

-- El envío AUTOMÁTICO manda cada aviso (primero y cada recordatorio) una sola vez por canal.
CREATE UNIQUE INDEX IF NOT EXISTS uq_carwash_rec_mant_automatico
    ON carwash_recordatorios (id_vehiculo_mantenimiento, proximo_km, canal, aviso_n)
    WHERE origen = 'automatico' AND estado = 'enviado' AND eliminado = false AND id_vehiculo_mantenimiento IS NOT NULL;

-- Lecturas de km de las órdenes (estimación del km de hoy de cada vehículo).
CREATE INDEX IF NOT EXISTS idx_carwash_ordenes_vehiculo_km
    ON carwash_ordenes (id_vehiculo, fecha_ingreso DESC) WHERE eliminado = false AND kilometraje IS NOT NULL;
