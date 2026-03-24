<?php
/**
 * Migración: Restricción única en (ruc, establecimiento)
 * No puede repetirse el mismo RUC con el mismo establecimiento.
 */
define('ROOT_PATH', __DIR__ . '/../..');
require ROOT_PATH . '/bootstrap.php';

$db = App\core\Database::getConnection();

// Eliminar índice único en establecimiento si existe
$r = $db->query("SHOW INDEX FROM empresas WHERE Key_name = 'uk_establecimiento'");
if ($r && $r->num_rows > 0) {
    $db->query("ALTER TABLE empresas DROP INDEX uk_establecimiento");
    echo "Índice uk_establecimiento eliminado.\n";
}

// Agregar índice único en (ruc, establecimiento) si no existe
$r = $db->query("SHOW INDEX FROM empresas WHERE Key_name = 'uk_ruc_establecimiento'");
if (!$r || $r->num_rows === 0) {
    $db->query("ALTER TABLE empresas ADD UNIQUE KEY uk_ruc_establecimiento (ruc, establecimiento)");
    echo "Índice único uk_ruc_establecimiento creado.\n";
} else {
    echo "Índice uk_ruc_establecimiento ya existe.\n";
}

echo "Migración completada.\n";
