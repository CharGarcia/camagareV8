<?php
/**
 * Migración: tablas Payphone
 * Ejecutar: php database/migrations/payphone_tables.php
 */

define('MVC_ROOT',   dirname(__DIR__, 2));
define('MVC_CONFIG', MVC_ROOT . '/config');

$config = require MVC_CONFIG . '/database.php';
$dsn    = sprintf('pgsql:host=%s;port=%d;dbname=%s', $config['host'], $config['port'], $config['name']);
$pdo    = new PDO($dsn, $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$sql = "
-- Configuración de Payphone por empresa
CREATE TABLE IF NOT EXISTS payphone_config (
    id          SERIAL PRIMARY KEY,
    id_empresa  INTEGER NOT NULL UNIQUE,
    token       TEXT    NOT NULL,
    store_id    TEXT,
    ambiente    VARCHAR(20) NOT NULL DEFAULT 'production',
    activo      BOOLEAN NOT NULL DEFAULT true,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

-- Registro de transacciones (global, reutilizable por cualquier módulo)
CREATE TABLE IF NOT EXISTS payphone_transacciones (
    id                    SERIAL PRIMARY KEY,
    id_empresa            INTEGER      NOT NULL,
    client_transaction_id VARCHAR(120) NOT NULL UNIQUE,
    payment_id            BIGINT,
    transaction_id        BIGINT,
    modulo                VARCHAR(60)  NOT NULL,
    id_referencia         INTEGER,
    descripcion           TEXT,
    monto                 INTEGER      NOT NULL,
    moneda                VARCHAR(3)   NOT NULL DEFAULT 'USD',
    estado                VARCHAR(30)  NOT NULL DEFAULT 'pendiente',
    transaction_status    VARCHAR(30),
    authorization_code    VARCHAR(100),
    response_data         JSONB,
    url_retorno           TEXT,
    url_cancelacion       TEXT,
    created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by            INTEGER,
    updated_by            INTEGER,
    eliminado             BOOLEAN      NOT NULL DEFAULT false,
    deleted_at            TIMESTAMP,
    deleted_by            INTEGER
);

CREATE INDEX IF NOT EXISTS idx_pp_trans_empresa   ON payphone_transacciones(id_empresa);
CREATE INDEX IF NOT EXISTS idx_pp_trans_modulo    ON payphone_transacciones(modulo, id_referencia);
CREATE INDEX IF NOT EXISTS idx_pp_trans_estado    ON payphone_transacciones(estado);
CREATE INDEX IF NOT EXISTS idx_pp_trans_client_id ON payphone_transacciones(client_transaction_id);
";

try {
    $pdo->exec($sql);
    echo "✓ Tablas payphone_config y payphone_transacciones creadas correctamente.\n";
} catch (Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
