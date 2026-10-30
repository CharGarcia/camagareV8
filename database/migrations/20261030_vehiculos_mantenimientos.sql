-- ============================================================================
--  Vehículos: control de mantenimientos por KILOMETRAJE (Fase 1).
--
--  Aceite de motor, aceite de caja y cualquier otro servicio que se haga cada X km.
--  Todo se maneja desde la orden de Car-Wash (pestaña "Mantenimiento") y también desde
--  la ficha del vehículo. No se liga a productos: el cambio lo marca el operador en la
--  orden y el control se hace solo con los km registrados.
--
--   mantenimiento_servicios               catálogo de la empresa (nombre, cada cuántos km,
--                                          cuántos km antes avisar). Al usarlo por primera
--                                          vez se crean "Aceite de motor" y "Aceite de caja".
--   vehiculos_mantenimientos              por vehículo y servicio: su intervalo propio y el
--                                          último / próximo cambio (copia del historial).
--   vehiculos_mantenimientos_historial    cada cambio hecho (desde una orden o a mano).
--
--  Requiere la tabla carwash_ordenes (20260705_create_carwash.sql). Idempotente.
-- ============================================================================

-- Columnas del vehículo que usa el control (ya existen si se aplicó 20260727_create_taller.sql).
ALTER TABLE vehiculos ADD COLUMN IF NOT EXISTS modelo             VARCHAR(100);
ALTER TABLE vehiculos ADD COLUMN IF NOT EXISTS motor              VARCHAR(100);
ALTER TABLE vehiculos ADD COLUMN IF NOT EXISTS kilometraje_actual INTEGER;

-- ─── Catálogo de servicios de la empresa ────────────────────────────────────
CREATE TABLE IF NOT EXISTS mantenimiento_servicios (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL,
    nombre          VARCHAR(100) NOT NULL,
    intervalo_km    INTEGER NOT NULL CHECK (intervalo_km > 0),
    aviso_km        INTEGER NOT NULL DEFAULT 500 CHECK (aviso_km >= 0),
    orden           INTEGER NOT NULL DEFAULT 0,
    activo          BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by      INTEGER,
    updated_by      INTEGER,
    eliminado       BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_mant_servicios_nombre
    ON mantenimiento_servicios (id_empresa, lower(nombre)) WHERE eliminado = false;

-- ─── Estado por vehículo y servicio ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vehiculos_mantenimientos (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL,
    id_vehiculo     INTEGER NOT NULL REFERENCES vehiculos(id),
    id_servicio     INTEGER NOT NULL REFERENCES mantenimiento_servicios(id),
    intervalo_km    INTEGER CHECK (intervalo_km IS NULL OR intervalo_km > 0),  -- NULL = usa el sugerido
    ultimo_km       INTEGER,
    ultima_fecha    DATE,
    proximo_km      INTEGER,
    id_orden        INTEGER,                                                   -- orden del último cambio
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by      INTEGER,
    updated_by      INTEGER,
    eliminado       BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_vehiculos_mant_servicio
    ON vehiculos_mantenimientos (id_vehiculo, id_servicio) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_vehiculos_mant_empresa
    ON vehiculos_mantenimientos (id_empresa, id_servicio) WHERE eliminado = false;

-- ─── Historial de cambios ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vehiculos_mantenimientos_historial (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    id_vehiculo         INTEGER NOT NULL REFERENCES vehiculos(id),
    id_servicio         INTEGER NOT NULL REFERENCES mantenimiento_servicios(id),
    km                  INTEGER NOT NULL CHECK (km >= 0),
    fecha               DATE NOT NULL,
    intervalo_km        INTEGER,
    proximo_km          INTEGER NOT NULL,
    origen              VARCHAR(20) NOT NULL DEFAULT 'orden',   -- orden | manual
    id_orden            INTEGER REFERENCES carwash_ordenes(id),
    observacion         TEXT,
    -- La orden se anuló: el cambio queda dado de baja y vuelve si la orden se reactiva.
    baja_por_anulacion  BOOLEAN NOT NULL DEFAULT FALSE,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER,
    CONSTRAINT chk_vehiculos_mant_hist_origen CHECK (origen IN ('orden', 'manual')),
    CONSTRAINT chk_vehiculos_mant_hist_proximo CHECK (proximo_km > km)
);

CREATE INDEX IF NOT EXISTS idx_vehiculos_mant_hist_vehiculo
    ON vehiculos_mantenimientos_historial (id_empresa, id_vehiculo, id_servicio, km DESC) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_vehiculos_mant_hist_orden
    ON vehiculos_mantenimientos_historial (id_orden) WHERE id_orden IS NOT NULL;
-- Un cambio por servicio en cada orden.
CREATE UNIQUE INDEX IF NOT EXISTS uq_vehiculos_mant_hist_orden_servicio
    ON vehiculos_mantenimientos_historial (id_orden, id_servicio) WHERE eliminado = false AND id_orden IS NOT NULL;
