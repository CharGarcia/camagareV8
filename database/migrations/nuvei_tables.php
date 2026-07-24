<?php
/**
 * Migración: tablas Nuvei (Paymentez) — Fase 1 (infraestructura base)
 * Ejecutar: php database/migrations/nuvei_tables.php
 */

define('MVC_ROOT',   dirname(__DIR__, 2));
define('MVC_CONFIG', MVC_ROOT . '/config');

$config = require MVC_CONFIG . '/database.php';
$dsn    = sprintf('pgsql:host=%s;port=%d;dbname=%s', $config['host'], $config['port'], $config['name']);
$pdo    = new PDO($dsn, $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$sql = "
-- Configuración de Nuvei (Paymentez) por empresa.
-- app_key_server / app_key_linktopay se guardan CIFRADOS (App\\Helpers\\CryptoHelper).
CREATE TABLE IF NOT EXISTS nuvei_config (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL UNIQUE,
    ambiente            VARCHAR(20) NOT NULL DEFAULT 'sandbox',
    app_code_server     TEXT,
    app_key_server      TEXT,
    app_code_linktopay  TEXT,
    app_key_linktopay   TEXT,
    activo_checkout     BOOLEAN NOT NULL DEFAULT true,
    activo_addcard      BOOLEAN NOT NULL DEFAULT true,
    activo_linktopay    BOOLEAN NOT NULL DEFAULT false,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

-- Registro de transacciones (global, reutilizable por cualquier módulo) — checkout, débito con token o link to pay.
CREATE TABLE IF NOT EXISTS nuvei_transacciones (
    id                    SERIAL PRIMARY KEY,
    id_empresa            INTEGER      NOT NULL,
    tipo                  VARCHAR(20)  NOT NULL,        -- checkout | debito_token | linktopay
    dev_reference         VARCHAR(120) NOT NULL UNIQUE, -- referencia interna propia
    reference             VARCHAR(60),                  -- 'reference'/orden que devuelve Nuvei
    nuvei_transaction_id  VARCHAR(30),                  -- ej. CB-81011 / CI-489 / KOI-10
    modulo                VARCHAR(60)  NOT NULL,
    id_referencia         INTEGER,
    id_ingreso            INTEGER,
    id_forma_cobro        INTEGER,
    id_cliente            INTEGER,
    id_nuvei_tarjeta      INTEGER,
    descripcion           TEXT,
    monto                 NUMERIC(14,2) NOT NULL,
    moneda                VARCHAR(3)   NOT NULL DEFAULT 'USD',
    estado                VARCHAR(30)  NOT NULL DEFAULT 'pendiente',
    status_detail         INTEGER,
    authorization_code    VARCHAR(100),
    response_data         JSONB,
    url_exito             TEXT,
    created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by            INTEGER,
    updated_by            INTEGER,
    eliminado             BOOLEAN      NOT NULL DEFAULT false,
    deleted_at            TIMESTAMP,
    deleted_by            INTEGER
);

CREATE INDEX IF NOT EXISTS idx_nv_trans_empresa   ON nuvei_transacciones(id_empresa);
CREATE INDEX IF NOT EXISTS idx_nv_trans_modulo    ON nuvei_transacciones(modulo, id_referencia);
CREATE INDEX IF NOT EXISTS idx_nv_trans_estado    ON nuvei_transacciones(estado);
CREATE INDEX IF NOT EXISTS idx_nv_trans_devref    ON nuvei_transacciones(dev_reference);
CREATE INDEX IF NOT EXISTS idx_nv_trans_reference ON nuvei_transacciones(reference);

-- Tarjetas tokenizadas guardadas por cliente (reutilizables entre suscripciones/documentos).
CREATE TABLE IF NOT EXISTS nuvei_tarjetas_cliente (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER      NOT NULL,
    id_cliente      INTEGER      NOT NULL,
    nuvei_user_id   VARCHAR(60)  NOT NULL,   -- 'emp{id_empresa}-cli{id_cliente}'
    token           VARCHAR(60)  NOT NULL UNIQUE,
    bin             VARCHAR(6),
    ultimos4        VARCHAR(4),
    marca           VARCHAR(10),
    holder_name     VARCHAR(120),
    expiry_month    VARCHAR(2),
    expiry_year     VARCHAR(4),
    status          VARCHAR(20),             -- valid | review | pending | rejected
    predeterminada  BOOLEAN      NOT NULL DEFAULT false,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

CREATE INDEX IF NOT EXISTS idx_nv_tarjetas_empresa_cliente ON nuvei_tarjetas_cliente(id_empresa, id_cliente);

-- Log crudo del webhook mientras se confirma con Nuvei el schema/firma exactos.
CREATE TABLE IF NOT EXISTS nuvei_webhook_log (
    id             SERIAL PRIMARY KEY,
    payload        JSONB,
    headers        JSONB,
    dev_reference  VARCHAR(120),
    recibido_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    procesado      BOOLEAN   NOT NULL DEFAULT false,
    error          TEXT
);

CREATE INDEX IF NOT EXISTS idx_nv_webhook_devref ON nuvei_webhook_log(dev_reference);
";

try {
    $pdo->exec($sql);
    echo "✓ Tablas nuvei_config, nuvei_transacciones, nuvei_tarjetas_cliente y nuvei_webhook_log creadas correctamente.\n";
} catch (Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
