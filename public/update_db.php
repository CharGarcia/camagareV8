<?php
define('MVC_CONFIG', __DIR__ . '/../config');
require __DIR__ . '/../app/core/Database.php';
try {
    $db = \App\core\Database::getConnection();
    $db->exec("ALTER TABLE clientes ADD COLUMN IF NOT EXISTS tipo_operacion_bancaria_predeterminada VARCHAR(50) DEFAULT NULL;");
    echo "Exito";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
