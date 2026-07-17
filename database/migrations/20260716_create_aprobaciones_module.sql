-- ============================================================
-- Módulo de Aprobaciones (motor genérico) — idempotente
-- ============================================================
-- Motor reutilizable para exigir aprobación de un usuario autorizado antes
-- de ejecutar procesos sensibles de cualquier módulo (pago de facturas de
-- compra, roles de pago, ventas, etc.). Cada módulo se "engancha" al motor
-- sin que este conozca su lógica de negocio (ver app/config/aprobaciones_registry.php).
--
--   aprobaciones_tipos        catálogo GLOBAL de procesos aprobables (uno por módulo).
--   aprobaciones_config       por empresa: qué tipos exigen aprobación y quién aprueba.
--   aprobaciones_solicitudes  la "bandeja": una fila por solicitud, referencia
--                              polimórfica (tabla_origen + id_origen) al documento real.
-- ============================================================

CREATE TABLE IF NOT EXISTS aprobaciones_tipos (
    id            SERIAL PRIMARY KEY,
    codigo        VARCHAR(60)  NOT NULL UNIQUE,
    nombre        VARCHAR(150) NOT NULL,
    descripcion   TEXT,
    modulo_ruta   VARCHAR(100),
    activo        BOOLEAN NOT NULL DEFAULT true,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS aprobaciones_config (
    id                    SERIAL PRIMARY KEY,
    id_empresa            INTEGER NOT NULL REFERENCES empresas(id),
    id_tipo               INTEGER NOT NULL REFERENCES aprobaciones_tipos(id),
    requiere_aprobacion   BOOLEAN NOT NULL DEFAULT false,
    usuarios_aprobadores  JSONB   NOT NULL DEFAULT '[]'::jsonb,
    notificar_correo      BOOLEAN NOT NULL DEFAULT true,
    umbral_monto          NUMERIC(14,2),
    created_by            INTEGER,
    updated_by            INTEGER,
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (id_empresa, id_tipo)
);

CREATE TABLE IF NOT EXISTS aprobaciones_solicitudes (
    id                SERIAL PRIMARY KEY,
    id_empresa        INTEGER NOT NULL REFERENCES empresas(id),
    id_tipo           INTEGER NOT NULL REFERENCES aprobaciones_tipos(id),
    tabla_origen      VARCHAR(80)  NOT NULL,
    id_origen         INTEGER      NOT NULL,
    descripcion       VARCHAR(255),
    monto             NUMERIC(14,2),
    estado            VARCHAR(20)  NOT NULL DEFAULT 'pendiente', -- pendiente | aprobado | rechazado | cancelado
    solicitado_by     INTEGER,
    solicitado_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    aprobado_by       INTEGER,
    aprobado_at       TIMESTAMP,
    comentario        TEXT,
    token_aprobacion  VARCHAR(64),
    eliminado         BOOLEAN NOT NULL DEFAULT false,
    deleted_at        TIMESTAMP,
    deleted_by        INTEGER,
    created_by        INTEGER,
    updated_by        INTEGER,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_aprob_solicitudes_bandeja
    ON aprobaciones_solicitudes (id_empresa, estado, eliminado);

CREATE INDEX IF NOT EXISTS idx_aprob_solicitudes_tipo
    ON aprobaciones_solicitudes (id_tipo);

CREATE INDEX IF NOT EXISTS idx_aprob_solicitudes_token
    ON aprobaciones_solicitudes (token_aprobacion) WHERE token_aprobacion IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_aprob_config_empresa
    ON aprobaciones_config (id_empresa);

-- Catálogo inicial. Cada módulo puede tener varios checkpoints (tipos) con
-- aprobadores independientes entre sí. Nuevos tipos se agregan aquí a medida
-- que se enganchen más módulos.
INSERT INTO aprobaciones_tipos (codigo, nombre, descripcion, modulo_ruta)
VALUES
    (
        'registro_compras',
        'Registro de compra',
        'Revisión/aprobación de una compra ya registrada en el sistema (contabilidad la valida a posteriori).',
        'modulos/compras'
    ),
    (
        'pago_compras',
        'Pago de facturas de compra',
        'Aprobación previa al registro de pagos a proveedores en Cuentas por Pagar.',
        'modulos/compras'
    )
ON CONFLICT (codigo) DO NOTHING;

-- Normaliza el modulo_ruta de 'pago_compras' si la fila ya existía con el valor
-- anterior (modulos/cuentas-por-pagar): ambos checkpoints agrupan bajo Compras.
UPDATE aprobaciones_tipos SET modulo_ruta = 'modulos/compras'
WHERE codigo = 'pago_compras' AND modulo_ruta <> 'modulos/compras';
