-- ============================================================
-- Migración: Historial de envíos al SRI
-- Registra cada acción enviada y cada respuesta recibida
-- del SRI para todos los tipos de comprobantes.
-- ============================================================

CREATE TABLE IF NOT EXISTS sri_envio_log (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    tipo_comprobante    VARCHAR(30)  NOT NULL DEFAULT 'factura_venta',
    id_comprobante      INTEGER      NOT NULL,
    clave_acceso        VARCHAR(49),
    tipo_ambiente       VARCHAR(2)   NOT NULL DEFAULT '1',   -- 1=Pruebas  2=Producción
    accion              VARCHAR(40)  NOT NULL,               -- enviando | recibida | devuelta | autorizado | no_autorizado | error
    estado_sri          VARCHAR(30),                         -- valor devuelto por el WS del SRI
    mensaje             TEXT,
    detalle_json        TEXT,                                -- JSON con errores/advertencias del SRI
    numero_autorizacion VARCHAR(49),
    fecha_autorizacion  TIMESTAMP,
    created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER      DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_sri_log_comprobante
    ON sri_envio_log (tipo_comprobante, id_comprobante);

CREATE INDEX IF NOT EXISTS idx_sri_log_empresa
    ON sri_envio_log (id_empresa);

COMMENT ON TABLE sri_envio_log IS 'Historial completo de envios y respuestas SRI por comprobante. Solo se permite eliminacion en registros de ambiente pruebas (tipo_ambiente=1).';
