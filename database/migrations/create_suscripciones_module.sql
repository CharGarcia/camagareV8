-- ============================================================
-- MÓDULO DE SUSCRIPCIONES
-- ============================================================
-- Tablas:
--   suscripcion_periodicidades  (global, sin id_empresa)
--   suscripciones               (contrato principal)
--   suscripciones_detalle       (productos/servicios de cada suscripción)
--   suscripciones_pagos         (historial de cobros)
--   suscripciones_notificaciones (log de emails)
-- ============================================================

-- ------------------------------------------------------------
-- 1. Periodicidades (tabla global, sin id_empresa)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS suscripcion_periodicidades (
    id          SERIAL PRIMARY KEY,
    nombre      VARCHAR(50)  NOT NULL,
    codigo      VARCHAR(20)  NOT NULL UNIQUE,
    meses       INTEGER      NOT NULL CHECK (meses > 0),
    descripcion VARCHAR(200),
    orden       INTEGER      NOT NULL DEFAULT 0,
    estado      BOOLEAN      NOT NULL DEFAULT true
);

INSERT INTO suscripcion_periodicidades (nombre, codigo, meses, descripcion, orden) VALUES
    ('Mensual',    'mensual',    1,  'Cobro cada mes',     1),
    ('Trimestral', 'trimestral', 3,  'Cobro cada 3 meses', 2),
    ('Semestral',  'semestral',  6,  'Cobro cada 6 meses', 3),
    ('Anual',      'anual',      12, 'Cobro cada año',     4),
    ('Bianual',    'bianual',    24, 'Cobro cada 2 años',  5)
ON CONFLICT (codigo) DO NOTHING;

-- ------------------------------------------------------------
-- 2. Suscripciones (contrato principal)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS suscripciones (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER      NOT NULL,
    id_cliente          INTEGER      NOT NULL,
    id_periodicidad     INTEGER      NOT NULL REFERENCES suscripcion_periodicidades(id),

    fecha_inicio        DATE         NOT NULL,
    fecha_fin           DATE,
    proximo_cobro       DATE         NOT NULL,

    forma_cobro         VARCHAR(20)  NOT NULL DEFAULT 'credito'
                                     CHECK (forma_cobro IN ('credito', 'tarjeta')),
    estado              VARCHAR(20)  NOT NULL DEFAULT 'activo'
                                     CHECK (estado IN ('activo', 'pausado', 'suspendido', 'cancelado')),

    -- Datos Kushki (solo si forma_cobro = 'tarjeta')
    kushki_token        VARCHAR(200),
    kushki_card_last4   VARCHAR(4),
    kushki_card_brand   VARCHAR(30),
    kushki_card_name    VARCHAR(150),

    observaciones       TEXT,
    intentos_fallidos   INTEGER      NOT NULL DEFAULT 0,
    ultimo_intento_at   TIMESTAMP,

    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

CREATE INDEX IF NOT EXISTS idx_suscripciones_empresa       ON suscripciones (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_suscripciones_cliente       ON suscripciones (id_cliente, id_empresa);
CREATE INDEX IF NOT EXISTS idx_suscripciones_proximo_cobro ON suscripciones (proximo_cobro, estado, eliminado);

-- ------------------------------------------------------------
-- 3. Detalle de suscripción (productos/servicios por suscripción)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS suscripciones_detalle (
    id               SERIAL PRIMARY KEY,
    id_suscripcion   INTEGER        NOT NULL REFERENCES suscripciones(id) ON DELETE CASCADE,
    id_empresa       INTEGER        NOT NULL,
    id_producto      INTEGER        NOT NULL,
    descripcion      VARCHAR(300),
    cantidad         NUMERIC(18,6)  NOT NULL DEFAULT 1,
    precio_unitario  NUMERIC(14,2)  NOT NULL DEFAULT 0,
    porcentaje_iva   NUMERIC(5,2)   NOT NULL DEFAULT 0,
    orden            INTEGER        NOT NULL DEFAULT 0,

    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

CREATE INDEX IF NOT EXISTS idx_susc_detalle_suscripcion ON suscripciones_detalle (id_suscripcion, eliminado);

-- ------------------------------------------------------------
-- 4. Pagos / historial de cobros
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS suscripciones_pagos (
    id                      SERIAL PRIMARY KEY,
    id_suscripcion          INTEGER        NOT NULL REFERENCES suscripciones(id),
    id_empresa              INTEGER        NOT NULL,

    fecha_cobro             DATE           NOT NULL DEFAULT CURRENT_DATE,
    monto                   NUMERIC(14,2)  NOT NULL,
    estado                  VARCHAR(20)    NOT NULL DEFAULT 'pendiente'
                                           CHECK (estado IN ('pendiente', 'exitoso', 'fallido')),

    id_factura              INTEGER,   -- → ventas_cabecera.id

    kushki_transaction_id   VARCHAR(100),
    kushki_response         JSONB,

    intentos                INTEGER    NOT NULL DEFAULT 0,
    ultimo_intento_at       TIMESTAMP,
    proximo_intento_at      TIMESTAMP,

    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

CREATE INDEX IF NOT EXISTS idx_susc_pagos_suscripcion ON suscripciones_pagos (id_suscripcion, eliminado);
CREATE INDEX IF NOT EXISTS idx_susc_pagos_empresa     ON suscripciones_pagos (id_empresa, estado);
CREATE INDEX IF NOT EXISTS idx_susc_pagos_factura     ON suscripciones_pagos (id_factura);

-- ------------------------------------------------------------
-- 5. Notificaciones (log de emails)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS suscripciones_notificaciones (
    id              SERIAL PRIMARY KEY,
    id_suscripcion  INTEGER      NOT NULL REFERENCES suscripciones(id),
    id_empresa      INTEGER      NOT NULL,
    id_pago         INTEGER      REFERENCES suscripciones_pagos(id),

    tipo            VARCHAR(50)  NOT NULL
                                 CHECK (tipo IN ('factura_generada','cobro_exitoso','cobro_fallido','vencimiento_proximo','suspension')),
    destinatario    VARCHAR(255) NOT NULL,
    asunto          VARCHAR(300),
    estado          VARCHAR(20)  NOT NULL DEFAULT 'enviado'
                                 CHECK (estado IN ('enviado','fallido')),
    error_detalle   TEXT,
    enviado_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

CREATE INDEX IF NOT EXISTS idx_susc_notif_suscripcion ON suscripciones_notificaciones (id_suscripcion);
CREATE INDEX IF NOT EXISTS idx_susc_notif_empresa     ON suscripciones_notificaciones (id_empresa);
