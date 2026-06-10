<?php
define('MVC_CONFIG', true);
require '../config/config.php';
require '../app/core/Database.php';

try {
    $db = \App\core\Database::getConnection();
    
    // Terminar otras conexiones para liberar locks
    $sql = "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE pid <> pg_backend_pid()";
    $st = $db->query($sql);
    echo "Conexiones terminadas: " . $st->rowCount() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
