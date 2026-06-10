<?php
require __DIR__.'/../config/config.php';
require __DIR__.'/../app/core/Database.php';

$db = App\core\Database::getConnection();

function checkTable($table) {
    global $db;
    try {
        $stmt = $db->query("DESCRIBE $table");
        echo "TABLE: $table\n";
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            echo $col['Field'] . " - " . $col['Type'] . "\n";
        }
        echo "\n";
    } catch (Exception $e) {}
}

checkTable('compras_cabecera');
checkTable('compras_detalles');
checkTable('liquidaciones_compra_cabecera');
checkTable('liquidaciones_compra_detalles');
checkTable('retenciones_compra_cabecera');
checkTable('retenciones_venta_cabecera');
