<?php
/**
 * Migración: Agregar columnas de cobro, vigencia y estado de pago a empresas
 */
define('ROOT_PATH', __DIR__ . '/../..');
require ROOT_PATH . '/bootstrap.php';

$db = App\core\Database::getConnection();

$columns = [
    "ADD COLUMN valor_cobro DECIMAL(10,2) DEFAULT NULL COMMENT 'Valor asignado de cobro'",
    "ADD COLUMN periodo_vigencia_desde DATE DEFAULT NULL COMMENT 'Inicio periodo vigencia'",
    "ADD COLUMN periodo_vigencia_hasta DATE DEFAULT NULL COMMENT 'Fin periodo vigencia'",
    "ADD COLUMN estado_pago VARCHAR(20) DEFAULT 'pendiente' COMMENT 'pendiente|pagado|vencido'",
];

foreach ($columns as $col) {
    $colName = preg_match('/ADD COLUMN (\w+)/', $col, $m) ? $m[1] : '';
    $check = $db->query("SHOW COLUMNS FROM empresas LIKE '{$colName}'");
    if ($check && $check->num_rows === 0) {
        $db->query("ALTER TABLE empresas {$col}");
        echo "Columna {$colName} agregada.\n";
    } else {
        echo "Columna {$colName} ya existe.\n";
    }
}

echo "Migración completada.\n";
