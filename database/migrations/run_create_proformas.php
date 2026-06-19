<?php
/**
 * Script de ejecución de migración: Módulo Proformas
 * Ejecutar desde la raíz del proyecto:
 *   php database/migrations/run_create_proformas.php
 */

define('MVC_ROOT', dirname(__DIR__, 2));
require_once MVC_ROOT . '/app/core/Database.php';

// Cargar config de BD
$config = require MVC_ROOT . '/config/database.php';
$dbConf = $config['connections'][$config['default']] ?? $config;

try {
    $dsn = "pgsql:host={$dbConf['host']};port={$dbConf['port']};dbname={$dbConf['dbname']}";
    $pdo = new PDO($dsn, $dbConf['username'], $dbConf['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $sql = file_get_contents(__DIR__ . '/20260619_create_proformas.sql');
    $pdo->exec($sql);

    echo "✅ Migración ejecutada correctamente.\n";
    echo "   Tablas creadas: proformas_cabecera, proformas_detalle, proformas_detalle_impuestos, proformas_adicional\n";
    echo "\nRecuerde:\n";
    echo "  1. Actualizar 'modulos/proformas' => ['id_submodulo' => X] en config/modulos_mvc.php\n";
    echo "     con el ID del submodulo insertado en submodulos_menu.\n";
    echo "  2. Asignar permisos al módulo de proformas desde /config/permisos-modulos\n";
} catch (\Throwable $e) {
    echo "❌ Error al ejecutar migración: " . $e->getMessage() . "\n";
    exit(1);
}
