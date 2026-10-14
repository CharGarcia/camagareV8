-- Pagos de suscripción REPORTADOS por el cliente desde el modal «Suscripción vencida».
-- El usuario escribe un mensaje y/o adjunta el comprobante; se envía un correo a la
-- empresa que controla las suscripciones y, mientras se revisa (máximo 3 días), el
-- aviso deja de salir. Termina solo cuando la controladora registra el cobro (cambia la
-- fecha límite del período) o cuando la controladora lo rechaza desde Suscripciones.
--   id_empresa              = empresa que reporta (la que debe la suscripción)
--   id_empresa_controladora = empresa dueña de la suscripción (donde se revisa)
-- Idempotente: se puede ejecutar más de una vez.

CREATE TABLE IF NOT EXISTS suscripciones_reportes_pago (
    id                       SERIAL PRIMARY KEY,
    id_empresa               INTEGER      NOT NULL,
    id_empresa_controladora  INTEGER,
    id_suscripcion           INTEGER,
    fecha_limite             DATE         NOT NULL,
    fecha_periodo            DATE,
    documento                VARCHAR(100),
    saldo                    NUMERIC(14,2),
    mensaje                  TEXT,
    archivo_nombre           VARCHAR(255),
    archivo_ruta             VARCHAR(255),
    archivo_mime             VARCHAR(100),
    archivo_tamano           INTEGER,
    estado                   VARCHAR(20)  NOT NULL DEFAULT 'en_revision',
    motivo_rechazo           TEXT,
    revisado_by              INTEGER,
    revisado_at              TIMESTAMP,
    correo_enviado           BOOLEAN      NOT NULL DEFAULT false,
    correo_error             TEXT,
    created_at               TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at               TIMESTAMP,
    created_by               INTEGER,
    updated_by               INTEGER,
    eliminado                BOOLEAN      NOT NULL DEFAULT false,
    deleted_at               TIMESTAMP,
    deleted_by               INTEGER,
    CONSTRAINT chk_susc_rep_pago_estado CHECK (estado IN ('en_revision', 'rechazado'))
);

CREATE INDEX IF NOT EXISTS idx_susc_rep_pago_empresa
    ON suscripciones_reportes_pago (id_empresa, fecha_limite) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_susc_rep_pago_controladora
    ON suscripciones_reportes_pago (id_empresa_controladora, created_at DESC) WHERE eliminado = false;

COMMENT ON TABLE  suscripciones_reportes_pago IS 'Pagos de suscripción reportados por el cliente (mensaje + comprobante) para revisión de la controladora';
COMMENT ON COLUMN suscripciones_reportes_pago.fecha_limite IS 'Fecha límite de pago del aviso al reportar; si cambia (se registró el cobro) el reporte deja de aplicar';
COMMENT ON COLUMN suscripciones_reportes_pago.archivo_ruta IS 'Ruta relativa dentro de storage/suscripciones_reportes';
