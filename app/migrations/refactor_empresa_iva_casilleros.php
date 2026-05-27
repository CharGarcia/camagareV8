<?php
/**
 * Migración: Reestructurar empresa_iva_casillero a empresa_casilleros_iva_sri
 * 
 * Ejecutar: php app/migrations/refactor_empresa_iva_casilleros.php
 */

require_once dirname(__DIR__) . '/../bootstrap.php';

use App\core\Database;

$db = Database::getConnection();

try {
    $db->beginTransaction();

    // 1. Renombrar tabla si existe el nombre anterior
    $sqlCheck = "SELECT tablename FROM pg_catalog.pg_tables WHERE tablename = 'empresa_iva_casillero'";
    $exists = $db->query($sqlCheck)->fetch();

    if ($exists) {
        $db->exec("ALTER TABLE empresa_iva_casillero RENAME TO empresa_casilleros_iva_sri");
        echo "OK: Tabla renombrada a 'empresa_casilleros_iva_sri'.\n";
    } else {
        // Asegurarse de que exista
        $sqlTable = "CREATE TABLE IF NOT EXISTS empresa_casilleros_iva_sri (
            id SERIAL PRIMARY KEY,
            id_empresa INTEGER NOT NULL,
            id_tarifa_iva INTEGER NOT NULL,
            casillero_subtotal_ventas VARCHAR(10),
            casillero_iva_ventas VARCHAR(10),
            casillero_subtotal_compras VARCHAR(10),
            casillero_iva_compras VARCHAR(10),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INTEGER,
            updated_by INTEGER,
            eliminado BOOLEAN DEFAULT FALSE,
            deleted_at TIMESTAMP,
            deleted_by INTEGER
        )";
        $db->exec($sqlTable);
        echo "INFO: La tabla 'empresa_casilleros_iva_sri' ya existe o fue creada.\n";
    }

    // 2. Renombrar campos si existen los anteriores
    $cols = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'empresa_casilleros_iva_sri'")->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('casillero_ventas', $cols)) {
        $db->exec("ALTER TABLE empresa_casilleros_iva_sri RENAME COLUMN casillero_ventas TO casillero_subtotal_ventas");
        echo "OK: Columna 'casillero_ventas' renombrada.\n";
    }
    if (in_array('casillero_compras', $cols)) {
        $db->exec("ALTER TABLE empresa_casilleros_iva_sri RENAME COLUMN casillero_compras TO casillero_subtotal_compras");
        echo "OK: Columna 'casillero_compras' renombrada.\n";
    }

    // 3. Agregar nuevos campos si no existen
    if (!in_array('casillero_iva_ventas', $cols)) {
        $db->exec("ALTER TABLE empresa_casilleros_iva_sri ADD COLUMN casillero_iva_ventas VARCHAR(10)");
        echo "OK: Columna 'casillero_iva_ventas' agregada.\n";
    }
    if (!in_array('casillero_iva_compras', $cols)) {
        $db->exec("ALTER TABLE empresa_casilleros_iva_sri ADD COLUMN casillero_iva_compras VARCHAR(10)");
        echo "OK: Columna 'casillero_iva_compras' agregada.\n";
    }

    $db->commit();
    echo "Migración de empresa_casilleros_iva_sri completada con éxito.\n";

} catch (\Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
