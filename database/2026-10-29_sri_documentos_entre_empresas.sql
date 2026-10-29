-- =============================================================================
-- Documentos entre empresas del sistema (registro automático en la receptora)
-- =============================================================================
-- Cuando una empresa del sistema emite un comprobante (factura, NC, ND, retención,
-- liquidación) y el SRI lo autoriza en PRODUCCIÓN, si el receptor (comprador,
-- sujeto retenido o proveedor de la liquidación) es otra empresa del sistema, el
-- comprobante se encola aquí y el cron (app/cron/cron_runner.php) lo registra en
-- la empresa receptora con el mismo registrador de Descargas SRI
-- (DocumentoAutomatedRegisterService::procesarYRegistrar).
--
-- Tabla operativa: id_empresa = empresa RECEPTORA (dueña del registro que se crea).
-- Idempotente: se puede ejecutar varias veces.
-- =============================================================================

CREATE TABLE IF NOT EXISTS sri_documentos_entre_empresas (
    id                   SERIAL PRIMARY KEY,
    id_empresa           INTEGER      NOT NULL REFERENCES empresas(id),   -- receptora
    id_empresa_emisora   INTEGER      NOT NULL REFERENCES empresas(id),
    tipo_comprobante     VARCHAR(30)  NOT NULL,  -- factura_venta, nota_credito, ... (tipo del emisor)
    tabla_origen         VARCHAR(60)  NOT NULL,  -- tabla del documento en la emisora
    id_origen            INTEGER      NOT NULL,
    clave_acceso         VARCHAR(49)  NOT NULL,
    numero_documento     VARCHAR(20),
    estado               VARCHAR(20)  NOT NULL DEFAULT 'pendiente', -- pendiente | registrado | existente | ignorado | error
    mensaje              TEXT,
    id_registro          INTEGER,                -- id creado en la receptora (compra, retención de venta, ...)
    intentos             INTEGER      NOT NULL DEFAULT 0,
    ultimo_intento       TIMESTAMP,
    procesado_at         TIMESTAMP,
    created_at           TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at           TIMESTAMP    NOT NULL DEFAULT NOW(),
    created_by           INTEGER      NOT NULL DEFAULT 0,
    updated_by           INTEGER,
    eliminado            BOOLEAN      NOT NULL DEFAULT FALSE,
    deleted_at           TIMESTAMP,
    deleted_by           INTEGER
);

-- Un comprobante se encola una sola vez por empresa receptora.
CREATE UNIQUE INDEX IF NOT EXISTS ux_sri_doc_entre_emp_clave
    ON sri_documentos_entre_empresas (id_empresa, clave_acceso)
    WHERE eliminado = FALSE;

-- Cola del cron: solo pendientes / con error reintentable.
CREATE INDEX IF NOT EXISTS ix_sri_doc_entre_emp_pendientes
    ON sri_documentos_entre_empresas (estado, ultimo_intento)
    WHERE eliminado = FALSE AND estado IN ('pendiente', 'error');

COMMENT ON TABLE sri_documentos_entre_empresas IS
    'Cola de comprobantes autorizados emitidos por una empresa del sistema a otra empresa del sistema; el cron los registra en la receptora.';

-- -----------------------------------------------------------------------------
-- Configuración por empresa receptora. Sin fila = recibe (activado por defecto).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sri_recepcion_entre_empresas_config (
    id           SERIAL PRIMARY KEY,
    id_empresa   INTEGER     NOT NULL REFERENCES empresas(id),
    recibir      BOOLEAN     NOT NULL DEFAULT TRUE,
    created_at   TIMESTAMP   NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMP   NOT NULL DEFAULT NOW(),
    created_by   INTEGER     NOT NULL DEFAULT 0,
    updated_by   INTEGER,
    eliminado    BOOLEAN     NOT NULL DEFAULT FALSE,
    deleted_at   TIMESTAMP,
    deleted_by   INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_sri_recepcion_entre_emp_config
    ON sri_recepcion_entre_empresas_config (id_empresa)
    WHERE eliminado = FALSE;

COMMENT ON TABLE sri_recepcion_entre_empresas_config IS
    'Por empresa: si recibe automáticamente los comprobantes que le emiten otras empresas del sistema. Sin fila = sí.';
