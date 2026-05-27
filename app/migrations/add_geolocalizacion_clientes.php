<?php
/**
 * Migración: Agregar campos de geolocalización a la tabla clientes.
 *
 * ALTER TABLE clientes
 *   ADD COLUMN IF NOT EXISTS latitud          DECIMAL(10, 8) DEFAULT NULL,
 *   ADD COLUMN IF NOT EXISTS longitud         DECIMAL(11, 8) DEFAULT NULL,
 *   ADD COLUMN IF NOT EXISTS geocodificado_en TIMESTAMP      DEFAULT NULL;
 */

define('ROOT_PATH', __DIR__ . '/../..');
require ROOT_PATH . '/bootstrap.php';

$db = App\core\Database::getConnection();

try {
    echo "Iniciando migración: add_geolocalizacion_clientes...\n";

    $db->exec("ALTER TABLE clientes ADD COLUMN IF NOT EXISTS latitud          DECIMAL(10, 8) DEFAULT NULL");
    $db->exec("ALTER TABLE clientes ADD COLUMN IF NOT EXISTS longitud         DECIMAL(11, 8) DEFAULT NULL");
    $db->exec("ALTER TABLE clientes ADD COLUMN IF NOT EXISTS geocodificado_en TIMESTAMP      DEFAULT NULL");

    echo "Migración completada: campos latitud, longitud y geocodificado_en agregados a clientes.\n";
} catch (\Throwable $e) {
    echo "Error en migración: " . $e->getMessage() . "\n";
    exit(1);
}
