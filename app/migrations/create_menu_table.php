<?php
/**
 * Crea la tabla menu si no existe.
 * Ejecutar: php app/migrations/create_menu_table.php
 */
require_once dirname(dirname(__DIR__)) . '/conexiones/conectalogin.php';
$con = conenta_login();

$sql = "CREATE TABLE IF NOT EXISTS menu (
  id int(11) NOT NULL AUTO_INCREMENT,
  etiqueta varchar(100) NOT NULL,
  ruta varchar(255) NOT NULL,
  nivel int(11) NOT NULL DEFAULT 1,
  estado tinyint(1) NOT NULL DEFAULT 1,
  icono varchar(50) DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";

if (mysqli_query($con, $sql)) {
    echo "OK: Tabla menu creada o ya existía.\n";
    $r = mysqli_query($con, "SELECT COUNT(*) as n FROM menu");
    $row = mysqli_fetch_assoc($r);
    if ($row['n'] == 0) {
        mysqli_query($con, "INSERT INTO menu (etiqueta, ruta, nivel, estado) VALUES 
            ('Empresa', '/sistema/empresa.php', 1, 1),
            ('Clientes', '/sistema/cliente.php', 2, 1),
            ('Proveedores', '/sistema/proveedor.php', 2, 1),
            ('Productos', '/sistema/producto.php', 2, 1)");
        echo "OK: Opciones básicas insertadas.\n";
    } else {
        mysqli_query($con, "UPDATE menu SET ruta='/sistema/empresa.php' WHERE ruta='/sistema/empresa'");
        mysqli_query($con, "UPDATE menu SET ruta='/sistema/cliente.php' WHERE ruta='/sistema/cliente'");
        mysqli_query($con, "UPDATE menu SET ruta='/sistema/proveedor.php' WHERE ruta='/sistema/proveedor'");
        mysqli_query($con, "UPDATE menu SET ruta='/sistema/producto.php' WHERE ruta='/sistema/producto'");
    }
} else {
    echo "Error: " . mysqli_error($con) . "\n";
}
