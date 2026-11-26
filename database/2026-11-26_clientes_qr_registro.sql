-- =============================================================================
-- QR de registro de clientes (autorregistro por QR)
-- =============================================================================
-- Un QR fijo por EMPRESA (sirve para todos sus establecimientos). El cliente lo
-- escanea, llena sus datos y el sistema lo crea en Clientes al instante; si su
-- identificación ya existe en la empresa, actualiza nombre, correo, teléfono y
-- dirección (así puede corregir un registro mal hecho volviendo a enviarlo).
--
--   clientes_qr_config     → una fila por empresa: token del enlace público, si
--                            está activo y el título/mensaje del impreso.
--   clientes_qr_registros  → bitácora de cada envío (qué se creó o actualizó,
--                            desde qué IP). Sirve también para el límite por hora.
--
-- Se administra desde Clientes → botón «QR de registro» (junto a Mapa).
-- Ejecutar en pgAdmin ANTES de desplegar el código (pegar todo y F5).
--
-- Toca datos: NO (solo crea dos tablas nuevas y sus índices).
-- Idempotente: SÍ (se puede ejecutar de nuevo sin error).
-- Reversible: SÍ, mientras no se haya usado. Para revertir:
--     DROP TABLE IF EXISTS clientes_qr_registros;
--     DROP TABLE IF EXISTS clientes_qr_config;
-- =============================================================================

CREATE TABLE IF NOT EXISTS clientes_qr_config (
    id          SERIAL PRIMARY KEY,
    id_empresa  INTEGER      NOT NULL REFERENCES empresas(id),
    token       VARCHAR(64)  NOT NULL,
    activo      BOOLEAN      NOT NULL DEFAULT true,
    titulo      VARCHAR(150) NULL,
    mensaje     VARCHAR(600) NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMP    NULL,
    created_by  INTEGER      NULL REFERENCES usuarios(id),
    updated_by  INTEGER      NULL REFERENCES usuarios(id),
    eliminado   BOOLEAN      NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP    NULL,
    deleted_by  INTEGER      NULL REFERENCES usuarios(id)
);

-- Un QR vigente por empresa, y tokens únicos.
CREATE UNIQUE INDEX IF NOT EXISTS ux_clientes_qr_config_empresa
    ON clientes_qr_config (id_empresa) WHERE eliminado = false;
CREATE UNIQUE INDEX IF NOT EXISTS ux_clientes_qr_config_token
    ON clientes_qr_config (token);

CREATE TABLE IF NOT EXISTS clientes_qr_registros (
    id          SERIAL PRIMARY KEY,
    id_empresa  INTEGER      NOT NULL REFERENCES empresas(id),
    id_config   INTEGER      NOT NULL REFERENCES clientes_qr_config(id),
    id_cliente  INTEGER      NULL REFERENCES clientes(id),
    accion      VARCHAR(20)  NOT NULL,            -- 'creado' | 'actualizado'
    datos       JSONB        NOT NULL DEFAULT '{}'::jsonb,
    ip_origen   VARCHAR(64)  NULL,
    user_agent  VARCHAR(500) NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMP    NULL,
    created_by  INTEGER      NULL REFERENCES usuarios(id),
    updated_by  INTEGER      NULL REFERENCES usuarios(id),
    eliminado   BOOLEAN      NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP    NULL,
    deleted_by  INTEGER      NULL REFERENCES usuarios(id)
);

-- Límite de envíos por IP en la última hora y consultas por empresa.
CREATE INDEX IF NOT EXISTS ix_clientes_qr_registros_ip
    ON clientes_qr_registros (id_config, ip_origen, created_at);
CREATE INDEX IF NOT EXISTS ix_clientes_qr_registros_empresa
    ON clientes_qr_registros (id_empresa, created_at DESC) WHERE eliminado = false;

COMMENT ON TABLE clientes_qr_config    IS 'QR de autorregistro de clientes: uno por empresa (token público, título y mensaje del impreso)';
COMMENT ON TABLE clientes_qr_registros IS 'Bitácora de envíos del QR de registro de clientes (cliente creado o actualizado, IP)';

-- -----------------------------------------------------------------------------
-- Comprobación (opcional): deben salir 2 filas, clientes_qr_config y
-- clientes_qr_registros.
-- -----------------------------------------------------------------------------
-- SELECT table_name FROM information_schema.tables
--  WHERE table_schema = 'public' AND table_name LIKE 'clientes_qr%'
--  ORDER BY table_name;
