-- =============================================================================
-- Firmas Electrónicas — autoservicio del cliente (portal, pago, confirmación de
-- correo, registro manual en Uanataca e historial del trámite).
-- Idempotente: se puede ejecutar más de una vez.
-- =============================================================================

-- ── Trámite de la firma ──────────────────────────────────────────────────────
-- estado      : borrador → pendiente (falta el pago) → por_registrar (pagada)
--               → en_proceso (registrada en Uanataca) → emitida.
--               Además: observada (el cliente debe corregir) y cancelada.
-- estado_pago : pendiente → en_revision (subió comprobante) → confirmado | rechazado
ALTER TABLE firmas_electronicas
    ADD COLUMN IF NOT EXISTS origen                         VARCHAR(20) NOT NULL DEFAULT 'interno',
    ADD COLUMN IF NOT EXISTS id_solicitud                   INTEGER,
    ADD COLUMN IF NOT EXISTS id_ingreso                     INTEGER,
    ADD COLUMN IF NOT EXISTS pago_motivo_rechazo            TEXT,
    ADD COLUMN IF NOT EXISTS pago_revisado_by               INTEGER,
    ADD COLUMN IF NOT EXISTS pago_revisado_at               TIMESTAMP,
    ADD COLUMN IF NOT EXISTS correo_confirmacion_token      CHAR(64),
    ADD COLUMN IF NOT EXISTS correo_confirmacion_enviado_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS correo_confirmado_at           TIMESTAMP,
    ADD COLUMN IF NOT EXISTS uanataca_referencia            VARCHAR(100),
    ADD COLUMN IF NOT EXISTS uanataca_registrada_at         TIMESTAMP,
    ADD COLUMN IF NOT EXISTS emitida_at                     TIMESTAMP,
    ADD COLUMN IF NOT EXISTS observacion_cliente            TEXT;

CREATE UNIQUE INDEX IF NOT EXISTS uq_firmas_correo_confirmacion_token
    ON firmas_electronicas (correo_confirmacion_token)
    WHERE correo_confirmacion_token IS NOT NULL;

-- Una solicitud (enlace) genera una sola firma: frena el doble envío del formulario.
CREATE UNIQUE INDEX IF NOT EXISTS uq_firmas_id_solicitud
    ON firmas_electronicas (id_solicitud)
    WHERE id_solicitud IS NOT NULL AND eliminado = false;

-- Bandeja "por registrar en Uanataca" (aviso del navbar).
CREATE INDEX IF NOT EXISTS idx_firmas_por_registrar
    ON firmas_electronicas (id_empresa, estado)
    WHERE eliminado = false;

-- El formulario público guardaba dos adjuntos con un tipo distinto al del modal
-- interno ('constitucion' y 'selfie_cedula'), así que no aparecían en su casilla.
UPDATE firmas_electronicas_adjuntos SET tipo = 'constitucion_compania' WHERE tipo = 'constitucion';
UPDATE firmas_electronicas_adjuntos SET tipo = 'selfie'                WHERE tipo = 'selfie_cedula';

-- ── Enlaces enviados al cliente ──────────────────────────────────────────────
ALTER TABLE firma_solicitudes
    ADD COLUMN IF NOT EXISTS telefono_destino  VARCHAR(20),
    ADD COLUMN IF NOT EXISTS origen            VARCHAR(20) NOT NULL DEFAULT 'operador',
    ADD COLUMN IF NOT EXISTS ip_origen         VARCHAR(45),
    ADD COLUMN IF NOT EXISTS correo_enviado_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS whatsapp_enviado_at TIMESTAMP;

-- El enlace fijo de la empresa crea la solicitud sin correo de destino.
ALTER TABLE firma_solicitudes ALTER COLUMN correo_destino DROP NOT NULL;

CREATE INDEX IF NOT EXISTS idx_firma_solicitudes_ip
    ON firma_solicitudes (ip_origen, created_at)
    WHERE ip_origen IS NOT NULL;

-- ── Historial del trámite (lo ve el sistema y, si visible_cliente, el cliente) ──
CREATE TABLE IF NOT EXISTS firmas_electronicas_eventos (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL,
    id_firma        INTEGER NOT NULL REFERENCES firmas_electronicas(id),
    tipo            VARCHAR(40) NOT NULL,
    titulo          VARCHAR(200) NOT NULL,
    detalle         TEXT,
    visible_cliente BOOLEAN NOT NULL DEFAULT true,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP,
    created_by      INTEGER,
    updated_by      INTEGER,
    eliminado       BOOLEAN NOT NULL DEFAULT false,
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER
);

CREATE INDEX IF NOT EXISTS idx_firmas_eventos_firma
    ON firmas_electronicas_eventos (id_firma, created_at)
    WHERE eliminado = false;

-- ── Configuración del módulo por empresa ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS firmas_electronicas_config (
    id                     SERIAL PRIMARY KEY,
    id_empresa             INTEGER NOT NULL,
    id_punto_emision       INTEGER,
    id_usuario_responsable INTEGER,
    payphone_activo        BOOLEAN NOT NULL DEFAULT true,
    transferencia_activa   BOOLEAN NOT NULL DEFAULT true,
    datos_transferencia    TEXT,
    enlace_fijo_activo     BOOLEAN NOT NULL DEFAULT false,
    enlace_fijo_codigo     CHAR(32),
    created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP,
    created_by             INTEGER,
    updated_by             INTEGER,
    eliminado              BOOLEAN NOT NULL DEFAULT false,
    deleted_at             TIMESTAMP,
    deleted_by             INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_firmas_config_empresa
    ON firmas_electronicas_config (id_empresa)
    WHERE eliminado = false;

CREATE UNIQUE INDEX IF NOT EXISTS uq_firmas_config_enlace
    ON firmas_electronicas_config (enlace_fijo_codigo)
    WHERE enlace_fijo_codigo IS NOT NULL;
