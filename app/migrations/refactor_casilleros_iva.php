<?php
/**
 * Migración: Refactorización de casilleros IVA.
 * - Elimina el campo 'casillero' de 'ventas_detalle'.
 * - Crea la tabla 'casilleros_declaracion_iva'.
 * 
 * Ejecutar: php app/migrations/refactor_casilleros_iva.php
 */

require_once dirname(__DIR__) . '/../bootstrap.php';

use App\core\Database;

$db = Database::getConnection();

try {
    $db->beginTransaction();

    // 1. Eliminar campo 'casillero' de 'ventas_detalle' si existe
    $sqlCheck = "SELECT column_name FROM information_schema.columns WHERE table_name = 'ventas_detalle' AND column_name = 'casillero'";
    $exists = $db->query($sqlCheck)->fetch();

    if ($exists) {
        $db->exec("ALTER TABLE ventas_detalle DROP COLUMN casillero");
        echo "OK: Columna 'casillero' eliminada de 'ventas_detalle'.\n";
    } else {
        echo "INFO: La columna 'casillero' no existe en 'ventas_detalle'.\n";
    }

    // 2. Crear tabla 'casilleros_declaracion_iva'
    $sqlTable = "CREATE TABLE IF NOT EXISTS casilleros_declaracion_iva (
        id SERIAL PRIMARY KEY,
        id_empresa INTEGER NOT NULL,
        id_establecimiento INTEGER NOT NULL,
        origen VARCHAR(50) NOT NULL,
        id_origen INTEGER NOT NULL,
        valor NUMERIC(18,6) NOT NULL DEFAULT 0,
        casillero VARCHAR(10) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INTEGER,
        updated_by INTEGER,
        eliminado BOOLEAN DEFAULT FALSE,
        deleted_at TIMESTAMP,
        deleted_by INTEGER
    )";

    $db->exec($sqlTable);
    echo "OK: Tabla 'casilleros_declaracion_iva' creada o ya existía.\n";

    // 3. Crear índices útiles
    $db->exec("CREATE INDEX IF NOT EXISTS idx_casilleros_empresa ON casilleros_declaracion_iva (id_empresa)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_casilleros_origen ON casilleros_declaracion_iva (origen, id_origen)");
    echo "OK: Índices creados.\n";

    $db->commit();
    echo "Migración completada con éxito.\n";

} catch (\Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
