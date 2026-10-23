-- =============================================================================
-- Firmas Electrónicas — avisos de vencimiento al cliente (correo y WhatsApp) y
-- enlace de renovación con los datos ya llenos.
-- Requiere antes: database/20261023_firmas_electronicas_autoservicio.sql
-- Idempotente: se puede ejecutar más de una vez.
-- =============================================================================

-- ── Configuración por empresa (apagado por defecto) ──────────────────────────
-- Días antes del vencimiento en que se avisa (0 = el mismo día), separados por coma.
ALTER TABLE firmas_electronicas_config
    ADD COLUMN IF NOT EXISTS avisos_vencimiento_activo BOOLEAN     NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS avisos_dias_correo        VARCHAR(60) NOT NULL DEFAULT '30,15,5,0',
    ADD COLUMN IF NOT EXISTS avisos_dias_whatsapp      VARCHAR(60) NOT NULL DEFAULT '30,5,0';

-- ── Renovación: la firma vieja apunta a la solicitud de renovación y viceversa ─
ALTER TABLE firmas_electronicas
    ADD COLUMN IF NOT EXISTS id_firma_renovacion INTEGER,
    ADD COLUMN IF NOT EXISTS id_firma_anterior   INTEGER;

-- ── Bitácora de avisos: cada aviso automático sale una sola vez ──────────────
CREATE TABLE IF NOT EXISTS firmas_avisos_vencimiento (
    id               SERIAL PRIMARY KEY,
    id_empresa       INTEGER NOT NULL,
    id_firma         INTEGER NOT NULL,
    fecha_caducidad  DATE    NOT NULL,
    dias             INTEGER NOT NULL,              -- etapa: días antes del vencimiento
    canal            VARCHAR(10) NOT NULL CHECK (canal IN ('correo', 'whatsapp')),
    manual           BOOLEAN NOT NULL DEFAULT false, -- "Enviar aviso ahora" desde la ficha
    enviado          BOOLEAN NOT NULL DEFAULT false,
    destino          VARCHAR(150),
    error            TEXT,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP,
    created_by       INTEGER,
    updated_by       INTEGER,
    eliminado        BOOLEAN NOT NULL DEFAULT false,
    deleted_at       TIMESTAMP,
    deleted_by       INTEGER
);

-- Un aviso automático por firma, vencimiento, etapa y canal (aunque falle: no se reintenta en bucle).
CREATE UNIQUE INDEX IF NOT EXISTS uq_firmas_avisos_auto
    ON firmas_avisos_vencimiento (id_firma, fecha_caducidad, dias, canal)
    WHERE manual = false AND eliminado = false;

CREATE INDEX IF NOT EXISTS idx_firmas_avisos_firma
    ON firmas_avisos_vencimiento (id_firma, created_at)
    WHERE eliminado = false;

-- Búsqueda diaria de firmas emitidas por vencer.
CREATE INDEX IF NOT EXISTS idx_firmas_por_vencer
    ON firmas_electronicas (id_empresa, fecha_caducidad)
    WHERE eliminado = false AND estado = 'emitida';
