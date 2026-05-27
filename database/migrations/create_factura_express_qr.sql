-- ============================================================
-- MÓDULO: FACTURA EXPRESS QR
-- ============================================================
-- Permite a las empresas generar un QR con una URL pública
-- donde sus clientes finales pueden solicitar facturas sin
-- necesidad de tener una cuenta en el sistema.
--
-- Tablas:
--   factura_express_plantillas   (configuración por empresa)
--   factura_express_items        (productos/servicios por plantilla)
--   factura_express_solicitudes  (solicitudes recibidas del cliente final)
-- ============================================================

-- ------------------------------------------------------------
-- 1. Plantillas QR (una empresa puede tener varias plantillas)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS factura_express_plantillas (
    id                    SERIAL PRIMARY KEY,
    id_empresa            INTEGER      NOT NULL,
    nombre                VARCHAR(150) NOT NULL,
    descripcion           TEXT,

    -- Token inmutable que forma la URL pública del QR
    token                 VARCHAR(64)  NOT NULL UNIQUE,

    activo                BOOLEAN      NOT NULL DEFAULT true,
    requiere_aprobacion   BOOLEAN      NOT NULL DEFAULT true,

    -- Campos del formulario público
    mensaje_bienvenida    TEXT,
    mensaje_gracias       TEXT,

    -- Control de spam: máx. solicitudes por IP por hora
    max_solicitudes_hora  INTEGER      NOT NULL DEFAULT 10,

    -- Configuración de campos obligatorios (JSON)
    -- Ejemplo: {"nombre":true,"identificacion":true,"correo":true,"telefono":false}
    campos_config         JSONB        NOT NULL DEFAULT '{"nombre":true,"identificacion":true,"correo":true,"telefono":false}',

    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

CREATE INDEX IF NOT EXISTS idx_fexpress_plantillas_empresa ON factura_express_plantillas (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_fexpress_plantillas_token   ON factura_express_plantillas (token);

-- ------------------------------------------------------------
-- 2. Ítems / productos por plantilla
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS factura_express_items (
    id                  SERIAL PRIMARY KEY,
    id_plantilla        INTEGER        NOT NULL REFERENCES factura_express_plantillas(id) ON DELETE CASCADE,
    id_empresa          INTEGER        NOT NULL,

    -- Referencia opcional al catálogo de productos
    id_producto         INTEGER,

    descripcion         VARCHAR(300)   NOT NULL,
    precio_unitario     NUMERIC(14,2)  NOT NULL DEFAULT 0,
    porcentaje_iva      NUMERIC(5,2)   NOT NULL DEFAULT 0,
    cantidad_default    NUMERIC(18,6)  NOT NULL DEFAULT 1,
    cantidad_editable   BOOLEAN        NOT NULL DEFAULT false,
    seleccionado_default BOOLEAN       NOT NULL DEFAULT true,
    orden               INTEGER        NOT NULL DEFAULT 0,
    activo              BOOLEAN        NOT NULL DEFAULT true,

    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

CREATE INDEX IF NOT EXISTS idx_fexpress_items_plantilla ON factura_express_items (id_plantilla, eliminado);
CREATE INDEX IF NOT EXISTS idx_fexpress_items_empresa   ON factura_express_items (id_empresa, eliminado);

-- ------------------------------------------------------------
-- 3. Solicitudes recibidas del cliente final
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS factura_express_solicitudes (
    id               SERIAL PRIMARY KEY,
    id_plantilla     INTEGER        NOT NULL REFERENCES factura_express_plantillas(id),
    id_empresa       INTEGER        NOT NULL,

    -- Datos del cliente final (ingresados en el formulario público)
    nombre_cliente       VARCHAR(200) NOT NULL,
    identificacion       VARCHAR(20)  NOT NULL,
    tipo_identificacion  VARCHAR(20)  NOT NULL DEFAULT 'cedula'
                                      CHECK (tipo_identificacion IN ('cedula','ruc','pasaporte','sin_ruc')),
    correo_cliente       VARCHAR(255),
    telefono_cliente     VARCHAR(20),

    -- Snapshot de los ítems solicitados (JSON)
    -- [{id_item, id_producto, descripcion, cantidad, precio_unitario, porcentaje_iva}]
    items_json       JSONB          NOT NULL DEFAULT '[]',
    monto_total      NUMERIC(14,2)  NOT NULL DEFAULT 0,

    -- Flujo de aprobación
    estado           VARCHAR(20)    NOT NULL DEFAULT 'pendiente'
                                    CHECK (estado IN ('pendiente','aprobada','rechazada','facturada')),

    -- Factura generada (si fue aprobada/facturada)
    id_factura       INTEGER,   -- → ventas_cabecera.id
    id_cliente_sys   INTEGER,   -- → clientes.id (creado o encontrado al aprobar)

    -- Notas del dueño del negocio al aprobar/rechazar
    nota_aprobacion  TEXT,

    -- Auditoría de quién aprobó/rechazó
    aprobado_por     INTEGER,
    aprobado_at      TIMESTAMP,

    -- Anti-spam
    ip_origen        VARCHAR(45),
    user_agent       VARCHAR(500),

    -- Token para que el cliente final consulte el estado
    token_cliente    VARCHAR(64) UNIQUE,

    -- Correos enviados
    correo_enviado_dueno   BOOLEAN NOT NULL DEFAULT false,
    correo_enviado_cliente BOOLEAN NOT NULL DEFAULT false,

    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

CREATE INDEX IF NOT EXISTS idx_fexpress_solic_empresa   ON factura_express_solicitudes (id_empresa, estado, eliminado);
CREATE INDEX IF NOT EXISTS idx_fexpress_solic_plantilla ON factura_express_solicitudes (id_plantilla, eliminado);
CREATE INDEX IF NOT EXISTS idx_fexpress_solic_token     ON factura_express_solicitudes (token_cliente);
CREATE INDEX IF NOT EXISTS idx_fexpress_solic_factura   ON factura_express_solicitudes (id_factura);
