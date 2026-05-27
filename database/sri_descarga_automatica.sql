-- =====================================================================
-- Descarga Automática SRI - Tablas de configuración y log
-- =====================================================================

-- Configuración por empresa (una fila por empresa)
CREATE TABLE IF NOT EXISTS sri_config_descarga_auto (
    id                SERIAL PRIMARY KEY,
    id_empresa        INTEGER NOT NULL UNIQUE,
    sri_usuario       VARCHAR(20)  NOT NULL DEFAULT '',
    sri_clave         TEXT         NOT NULL DEFAULT '',
    estado            VARCHAR(10)  NOT NULL DEFAULT 'inactivo',
    tipos_documento   VARCHAR(200) NOT NULL DEFAULT 'todos',
    ultima_descarga   TIMESTAMP,
    ultimo_estado     VARCHAR(20),
    ultimo_mensaje    TEXT,
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by        INTEGER,
    updated_by        INTEGER,
    login_bloqueado        BOOLEAN      NOT NULL DEFAULT FALSE,
    login_bloqueado_motivo TEXT,
    eliminado              BOOLEAN      NOT NULL DEFAULT FALSE,
    deleted_at             TIMESTAMP,
    deleted_by             INTEGER
);

CREATE INDEX IF NOT EXISTS idx_sri_config_descarga_empresa
    ON sri_config_descarga_auto(id_empresa)
    WHERE eliminado = FALSE;

-- Log de ejecuciones automáticas y manuales
CREATE TABLE IF NOT EXISTS sri_descarga_auto_log (
    id                SERIAL PRIMARY KEY,
    id_empresa        INTEGER      NOT NULL,
    fecha_proceso     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_desde       DATE         NOT NULL,
    fecha_hasta       DATE         NOT NULL,
    tipos_documento   VARCHAR(200),
    total_encontrados INTEGER      NOT NULL DEFAULT 0,
    total_nuevos      INTEGER      NOT NULL DEFAULT 0,
    total_existentes  INTEGER      NOT NULL DEFAULT 0,
    total_ignorados   INTEGER      NOT NULL DEFAULT 0,
    total_errores     INTEGER      NOT NULL DEFAULT 0,
    estado            VARCHAR(20)  NOT NULL DEFAULT 'completado',
    detalle_json      TEXT,
    duracion_seg      INTEGER,
    origen            VARCHAR(20)  NOT NULL DEFAULT 'cron',
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by        INTEGER               DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_sri_descarga_auto_log_empresa
    ON sri_descarga_auto_log(id_empresa, fecha_proceso DESC);

COMMENT ON TABLE sri_config_descarga_auto IS 'Configuración de descarga automática de comprobantes SRI por empresa';
COMMENT ON TABLE sri_descarga_auto_log    IS 'Historial de ejecuciones de descarga automática SRI';
COMMENT ON COLUMN sri_config_descarga_auto.sri_clave IS 'Contraseña del portal SRI en Línea, cifrada con AES-256-CBC';
COMMENT ON COLUMN sri_config_descarga_auto.tipos_documento IS 'todos | facturas,retenciones,notas_credito,notas_debito,liquidaciones';
COMMENT ON COLUMN sri_config_descarga_auto.login_bloqueado IS 'TRUE cuando el scraper reportó credenciales incorrectas. Se desbloquea al guardar nueva clave.';
COMMENT ON COLUMN sri_config_descarga_auto.login_bloqueado_motivo IS 'Mensaje del error de credenciales que causó el bloqueo.';
COMMENT ON COLUMN sri_descarga_auto_log.origen IS 'cron = automático nocturno | manual = lanzado desde la interfaz';
