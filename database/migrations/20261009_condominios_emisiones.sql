-- ============================================================================
-- Condominios: emisión en bloque de recibos o facturas desde la pestaña «Condóminos» de
-- Configuración de condominios (multas, cuotas extraordinarias o cualquier servicio), SIN pasar
-- por Suscripciones. Una emisión (lote) tiene un ítem por documento a generar. Un worker en
-- segundo plano (scripts/procesar_emision_condominios.php) genera cada documento, envía las
-- facturas al SRI y, si se pidió, manda cada documento por correo al condómino.
-- Operativa: id_empresa + auditoría + eliminación lógica. Idempotente. Listo para pgAdmin (F5).
-- ============================================================================
BEGIN;

CREATE TABLE IF NOT EXISTS condominios_emisiones (
    id                SERIAL PRIMARY KEY,
    id_empresa        INTEGER       NOT NULL,
    descripcion       VARCHAR(200)  NOT NULL,                      -- «Multa por ruido, octubre 2026»
    tipo_comprobante  VARCHAR(10)   NOT NULL,                      -- factura | recibo
    id_punto_emision  INTEGER       NOT NULL,                      -- serie con la que se emite
    id_producto       INTEGER       NOT NULL,                      -- concepto (servicio de Productos)
    forma_valor       VARCHAR(10)   NOT NULL DEFAULT 'fijo',       -- fijo | inmueble (cuota del inmueble)
    valor             NUMERIC(14,2) NOT NULL DEFAULT 0,            -- monto fijo (si forma_valor = fijo)
    agrupar           VARCHAR(10)   NOT NULL DEFAULT 'cliente',    -- cliente (uno por condómino) | inmueble (uno por inmueble que paga)
    texto_item        VARCHAR(300),                                -- texto adicional de la línea
    enviar_correo     BOOLEAN       NOT NULL DEFAULT false,
    estado            VARCHAR(25)   NOT NULL DEFAULT 'pendiente',  -- pendiente | procesando | completado | completado_con_errores | cancelado
    total_items       INTEGER       NOT NULL DEFAULT 0,
    generados         INTEGER       NOT NULL DEFAULT 0,
    fallidos          INTEGER       NOT NULL DEFAULT 0,
    correos_enviados  INTEGER       NOT NULL DEFAULT 0,
    total_valor       NUMERIC(14,2) NOT NULL DEFAULT 0,
    iniciado_at       TIMESTAMP,
    finalizado_at     TIMESTAMP,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT chk_cond_emi_tipo    CHECK (tipo_comprobante IN ('factura', 'recibo')),
    CONSTRAINT chk_cond_emi_forma   CHECK (forma_valor IN ('fijo', 'inmueble')),
    CONSTRAINT chk_cond_emi_agrupar CHECK (agrupar IN ('cliente', 'inmueble')),
    CONSTRAINT chk_cond_emi_estado  CHECK (estado IN ('pendiente', 'procesando', 'completado', 'completado_con_errores', 'cancelado'))
);
CREATE INDEX IF NOT EXISTS idx_cond_emi_empresa   ON condominios_emisiones (id_empresa, created_at DESC) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_cond_emi_pendiente ON condominios_emisiones (estado) WHERE eliminado = false AND estado IN ('pendiente', 'procesando');

CREATE TABLE IF NOT EXISTS condominios_emisiones_items (
    id                SERIAL PRIMARY KEY,
    id_emision        INTEGER       NOT NULL REFERENCES condominios_emisiones (id),
    id_empresa        INTEGER       NOT NULL,
    id_cliente        INTEGER       NOT NULL,
    id_unidad         INTEGER,                                     -- inmueble (si es uno por inmueble)
    inmueble_texto    VARCHAR(300),                                -- «DPTO-104 · Departamento 104» (info adicional)
    valor             NUMERIC(14,2) NOT NULL,
    email             VARCHAR(300),
    estado            VARCHAR(20)   NOT NULL DEFAULT 'pendiente',  -- pendiente | procesando | generado | autorizado | en_procesamiento | error | revisar
    id_factura        INTEGER,
    id_recibo         INTEGER,
    numero            VARCHAR(30),                                 -- 001-001-000000123
    estado_correo     VARCHAR(12)   NOT NULL DEFAULT 'no_aplica',  -- no_aplica | pendiente | enviado | sin_correo | error
    mensaje           TEXT,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT chk_cond_emi_item_estado CHECK (estado IN ('pendiente', 'procesando', 'generado', 'autorizado', 'en_procesamiento', 'error', 'revisar')),
    CONSTRAINT chk_cond_emi_item_correo CHECK (estado_correo IN ('no_aplica', 'pendiente', 'enviado', 'sin_correo', 'error'))
);
CREATE INDEX IF NOT EXISTS idx_cond_emi_items_emision ON condominios_emisiones_items (id_emision, estado) WHERE eliminado = false;
-- Un condómino (o inmueble) aparece una sola vez por emisión.
CREATE UNIQUE INDEX IF NOT EXISTS uq_cond_emi_items_destino
    ON condominios_emisiones_items (id_emision, id_cliente, COALESCE(id_unidad, 0)) WHERE eliminado = false;

COMMIT;
