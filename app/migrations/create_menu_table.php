<?php
/**
 * Crea la tabla menu si no existe.
 * Ejecutar: php app/migrations/create_menu_table.php
 */

// Si se ejecuta desde CLI, definimos MVC_CONFIG si no lo está.
if (!defined('MVC_CONFIG')) {
    define('MVC_CONFIG', dirname(dirname(__DIR__)) . '/config');
}
require_once dirname(dirname(__DIR__)) . '/app/core/Database.php';

use App\core\Database;

try {
    $pdo = Database::getConnection();

    $sql = "CREATE TABLE IF NOT EXISTS menu (
      id SERIAL PRIMARY KEY,
      etiqueta VARCHAR(100) NOT NULL,
      ruta VARCHAR(255) NOT NULL,
      nivel INTEGER NOT NULL DEFAULT 1,
      estado SMALLINT NOT NULL DEFAULT 1,
      icono VARCHAR(50) DEFAULT NULL
    )";

    $pdo->exec($sql);
    echo "OK: Tabla menu creada o ya existía.\n";

    $stmt = $pdo->query("SELECT COUNT(*) as n FROM menu");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row['n'] == 0) {
        $insert = "INSERT INTO menu (etiqueta, ruta, nivel, estado) VALUES 
            ('Empresa', '/sistema/empresa.php', 1, 1),
            ('Clientes', '/sistema/cliente.php', 2, 1),
            ('Proveedores', '/sistema/proveedor.php', 2, 1),
            ('Productos', '/sistema/producto.php', 2, 1)";
        $pdo->exec($insert);
        echo "OK: Opciones básicas insertadas.\n";
    } else {
        $pdo->exec("UPDATE menu SET ruta='/sistema/empresa.php' WHERE ruta='/sistema/empresa'");
        $pdo->exec("UPDATE menu SET ruta='/sistema/cliente.php' WHERE ruta='/sistema/cliente'");
        $pdo->exec("UPDATE menu SET ruta='/sistema/proveedor.php' WHERE ruta='/sistema/proveedor'");
        $pdo->exec("UPDATE menu SET ruta='/sistema/producto.php' WHERE ruta='/sistema/producto'");
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Excepcion: " . $e->getMessage() . "\n";
}
