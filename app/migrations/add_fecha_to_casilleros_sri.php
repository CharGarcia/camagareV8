<?php
/**
 * Migración: Agregar campo fecha a casilleros_declaracion_sri
 * 
 * Ejecutar: php app/migrations/add_fecha_to_casilleros_sri.php
 */

require_once dirname(__DIR__) . '/../bootstrap.php';

use App\core\Database;

$db = Database::getConnection();

try {
    $db->beginTransaction();

    // Agregar columna fecha si no existe
    $sqlCheck = "SELECT column_name FROM information_schema.columns WHERE table_name = 'casilleros_declaracion_sri' AND column_name = 'fecha'";
    $exists = $db->query($sqlCheck)->fetch();

    if (!$exists) {
        $db->exec("ALTER TABLE casilleros_declaracion_sri ADD COLUMN fecha DATE");
        echo "OK: Columna 'fecha' agregada a 'casilleros_declaracion_sri'.\n";
    } else {
        echo "INFO: La columna 'fecha' ya existe.\n";
    }

    $db->commit();
    echo "Migración completada con éxito.\n";

} catch (\Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
