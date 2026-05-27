<?php
/**
 * Migración: Restricción única en clientes (id_empresa, tipo_id, identificacion)
 * No permite duplicados de identificación del mismo tipo para la misma empresa
 * que no estén marcados como eliminados.
 */
define('ROOT_PATH', __DIR__ . '/../..');
require ROOT_PATH . '/bootstrap.php';

$db = App\core\Database::getConnection();

try {
    echo "Iniciando migración: add_unique_cliente_id...\n";
    
    // 1. Verificar si el índice ya existe en PostgreSQL
    $sqlCheck = "SELECT count(*) FROM pg_indexes WHERE indexname = 'uk_cliente_empresa_identificacion'";
    $st = $db->query($sqlCheck);
    $exists = (int) $st->fetchColumn();

    if ($exists === 0) {
        // 2. Crear el índice único parcial (solo para registros NO eliminados)
        $sqlIndex = "CREATE UNIQUE INDEX uk_cliente_empresa_identificacion 
                     ON clientes (id_empresa, tipo_id, identificacion) 
                     WHERE (eliminado = false)";
        
        $db->exec($sqlIndex);
        echo "Índice único 'uk_cliente_empresa_identificacion' creado exitosamente.\n";
    } else {
        echo "El índice 'uk_cliente_empresa_identificacion' ya existe.\n";
    }

} catch (\PDOException $e) {
    echo "ERROR en la migración: " . $e->getMessage() . "\n";
    if (strpos($e->getMessage(), 'duplicate key value') !== false) {
        echo "AVISO: Se encontraron registros duplicados existentes. Debe limpiarlos antes de aplicar esta restricción.\n";
    }
    exit(1);
}

echo "Migración completada.\n";
