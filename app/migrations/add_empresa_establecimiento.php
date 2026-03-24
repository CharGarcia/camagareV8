<?php
/**
 * Migración: Agregar columna establecimiento a empresas (única)
 * RUC puede repetirse, establecimiento no.
 */
define('ROOT_PATH', __DIR__ . '/../..');
require ROOT_PATH . '/bootstrap.php';

$db = App\core\Database::getConnection();

$check = $db->query("SHOW COLUMNS FROM empresas LIKE 'establecimiento'");
if ($check && $check->num_rows === 0) {
    $db->query("ALTER TABLE empresas ADD COLUMN establecimiento VARCHAR(50) NULL AFTER ruc");
    echo "Columna establecimiento agregada.\n";
    // Actualizar registros existentes con valor único
    $r = $db->query("SELECT id FROM empresas");
    if ($r && $r->num_rows > 0) {
        while ($row = $r->fetch_assoc()) {
            $id = (int)$row['id'];
            $est = $db->real_escape_string(sprintf('EST-%d', $id));
            $db->query("UPDATE empresas SET establecimiento = '{$est}' WHERE id = {$id}");
        }
        echo "Registros existentes actualizados.\n";
    }
    $db->query("ALTER TABLE empresas MODIFY establecimiento VARCHAR(50) NOT NULL DEFAULT ''");
    $db->query("ALTER TABLE empresas ADD UNIQUE KEY uk_establecimiento (establecimiento)");
    echo "Índice único creado.\n";
} else {
    echo "Columna establecimiento ya existe.\n";
}
echo "Registros existentes actualizados.\n";

echo "Migración completada.\n";
