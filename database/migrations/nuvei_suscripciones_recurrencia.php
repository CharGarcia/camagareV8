<?php
/**
 * Migración: soporte de cobro recurrente con Nuvei (Add Card) en Suscripciones.
 * Ejecutar: C:\xampp\php\php.exe database/migrations/nuvei_suscripciones_recurrencia.php
 */

define('MVC_ROOT',   dirname(__DIR__, 2));
define('MVC_CONFIG', MVC_ROOT . '/config');

$config = require MVC_CONFIG . '/database.php';
$dsn    = sprintf('pgsql:host=%s;port=%d;dbname=%s', $config['host'], $config['port'], $config['name']);
$pdo    = new PDO($dsn, $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$sql = "
-- Qué pasarela gestiona la tarjeta tokenizada de la suscripción ('kushki' ya
-- existente vía columnas kushki_*, o 'nuvei' vía nuvei_tarjetas_cliente).
ALTER TABLE suscripciones ADD COLUMN IF NOT EXISTS pasarela_tarjeta VARCHAR(20);
ALTER TABLE suscripciones ADD COLUMN IF NOT EXISTS id_nuvei_tarjeta INTEGER REFERENCES nuvei_tarjetas_cliente(id);

COMMENT ON COLUMN suscripciones.pasarela_tarjeta IS
    'kushki | nuvei — solo relevante cuando forma_cobro = tarjeta.';
COMMENT ON COLUMN suscripciones.id_nuvei_tarjeta IS
    'Tarjeta guardada del cliente (nuvei_tarjetas_cliente) usada para el débito recurrente.';

-- Trazabilidad del cargo real de Nuvei en el historial de pagos de la suscripción
-- (mismo patrón que las columnas kushki_transaction_id/kushki_response ya existentes).
ALTER TABLE suscripciones_pagos ADD COLUMN IF NOT EXISTS nuvei_transaction_id VARCHAR(30);
ALTER TABLE suscripciones_pagos ADD COLUMN IF NOT EXISTS nuvei_response JSONB;

-- Solicitudes de registro de tarjeta (enlace público enviado al cliente para
-- tokenizar una tarjeta vía el SDK Add Card de Nuvei). Polimórfico igual que
-- nuvei_transacciones: hoy solo lo usa el módulo 'suscripcion'.
CREATE TABLE IF NOT EXISTS nuvei_solicitudes_tarjeta (
    id               SERIAL PRIMARY KEY,
    id_empresa       INTEGER      NOT NULL,
    id_cliente       INTEGER      NOT NULL,
    modulo           VARCHAR(60)  NOT NULL,
    id_referencia    INTEGER,
    token            VARCHAR(120) NOT NULL UNIQUE,
    estado           VARCHAR(20)  NOT NULL DEFAULT 'pendiente', -- pendiente | completado | cancelado
    id_nuvei_tarjeta INTEGER      REFERENCES nuvei_tarjetas_cliente(id),
    email            VARCHAR(150),
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by       INTEGER,
    eliminado        BOOLEAN      NOT NULL DEFAULT false
);

CREATE INDEX IF NOT EXISTS idx_nv_sol_tarjeta_empresa_cliente ON nuvei_solicitudes_tarjeta(id_empresa, id_cliente);
CREATE INDEX IF NOT EXISTS idx_nv_sol_tarjeta_modulo          ON nuvei_solicitudes_tarjeta(modulo, id_referencia);
CREATE INDEX IF NOT EXISTS idx_nv_sol_tarjeta_token           ON nuvei_solicitudes_tarjeta(token);
";

try {
    $pdo->exec($sql);
    echo "✓ Soporte de recurrencia Nuvei en Suscripciones creado correctamente.\n";
} catch (Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
