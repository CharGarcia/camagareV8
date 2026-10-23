-- =============================================================================
-- Firmas Electrónicas — integración con la API Precompra de Uanataca Ecuador.
-- Requiere antes: database/20261023_firmas_electronicas_autoservicio.sql
-- Idempotente: se puede ejecutar más de una vez.
-- =============================================================================

-- ── Credenciales y comportamiento de la API (por empresa) ────────────────────
-- La clave se guarda cifrada (App\Helpers\CryptoHelper), nunca en texto plano.
ALTER TABLE firmas_electronicas_config
    ADD COLUMN IF NOT EXISTS uanataca_activo           BOOLEAN     NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS uanataca_ambiente         VARCHAR(12) NOT NULL DEFAULT 'pruebas',
    ADD COLUMN IF NOT EXISTS uanataca_usuario          VARCHAR(150),
    ADD COLUMN IF NOT EXISTS uanataca_clave_cifrada    TEXT,
    ADD COLUMN IF NOT EXISTS uanataca_envio_automatico BOOLEAN     NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS uanataca_exigir_correo    BOOLEAN     NOT NULL DEFAULT true;

-- ── Producto del sistema ↔ producto de Uanataca ──────────────────────────────
CREATE TABLE IF NOT EXISTS firmas_productos_uanataca (
    id                     SERIAL PRIMARY KEY,
    id_empresa             INTEGER NOT NULL,
    id_producto            INTEGER NOT NULL,
    uanataca_product_uuid  VARCHAR(64) NOT NULL,
    uanataca_code          VARCHAR(40),
    uanataca_nombre        VARCHAR(200),
    created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP,
    created_by             INTEGER,
    updated_by             INTEGER,
    eliminado              BOOLEAN NOT NULL DEFAULT false,
    deleted_at             TIMESTAMP,
    deleted_by             INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_firmas_productos_uanataca
    ON firmas_productos_uanataca (id_empresa, id_producto)
    WHERE eliminado = false;

-- ── Datos que exige la API y estado de la solicitud en Uanataca ──────────────
-- tipo_persona: natural | juridica (representante legal) | miembro (miembro de empresa)
ALTER TABLE firmas_electronicas
    ADD COLUMN IF NOT EXISTS apellido_paterno          VARCHAR(100),
    ADD COLUMN IF NOT EXISTS apellido_materno          VARCHAR(100),
    ADD COLUMN IF NOT EXISTS telefono2                 VARCHAR(20),
    ADD COLUMN IF NOT EXISTS correo2                   VARCHAR(150),
    ADD COLUMN IF NOT EXISTS departamento              VARCHAR(150),
    ADD COLUMN IF NOT EXISTS motivo                    VARCHAR(255),
    ADD COLUMN IF NOT EXISTS rep_tipo_identificacion   VARCHAR(20),
    ADD COLUMN IF NOT EXISTS rep_identificacion        VARCHAR(20),
    ADD COLUMN IF NOT EXISTS rep_nombres               VARCHAR(150),
    ADD COLUMN IF NOT EXISTS rep_apellidos             VARCHAR(150),
    ADD COLUMN IF NOT EXISTS uanataca_uuid             VARCHAR(64),
    ADD COLUMN IF NOT EXISTS uanataca_estado           VARCHAR(30),
    ADD COLUMN IF NOT EXISTS uanataca_estado_at        TIMESTAMP,
    ADD COLUMN IF NOT EXISTS uanataca_comentarios      TEXT,
    ADD COLUMN IF NOT EXISTS uanataca_enviada_at       TIMESTAMP,
    ADD COLUMN IF NOT EXISTS uanataca_error            TEXT;

-- Los registros anteriores tenían un solo campo de apellidos: el primero pasa a
-- paterno y el resto a materno (se puede corregir a mano en la ficha).
UPDATE firmas_electronicas
   SET apellido_paterno = split_part(trim(apellidos), ' ', 1),
       apellido_materno = NULLIF(trim(substr(trim(apellidos), length(split_part(trim(apellidos), ' ', 1)) + 1)), '')
 WHERE apellido_paterno IS NULL AND COALESCE(trim(apellidos), '') <> '';

-- Una solicitud de Uanataca pertenece a una sola firma.
CREATE UNIQUE INDEX IF NOT EXISTS uq_firmas_uanataca_uuid
    ON firmas_electronicas (uanataca_uuid)
    WHERE uanataca_uuid IS NOT NULL;

-- Sincronización de estados (cron): solicitudes enviadas y no terminadas.
CREATE INDEX IF NOT EXISTS idx_firmas_uanataca_pendientes
    ON firmas_electronicas (id_empresa)
    WHERE uanataca_uuid IS NOT NULL AND eliminado = false;
