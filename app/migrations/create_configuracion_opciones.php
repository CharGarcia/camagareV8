<?php
/**
 * Crea las tablas configuracion_opciones y configuracion_opcion_enlaces si no existen.
 * Ejecutar: php app/migrations/create_configuracion_opciones.php
 */
require_once dirname(__DIR__) . '/../bootstrap.php';

use App\core\Database;

$db = Database::getConnection();

$sql1 = "CREATE TABLE IF NOT EXISTS configuracion_opciones (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    icono VARCHAR(50) DEFAULT 'gear',
    clase_color VARCHAR(50) DEFAULT 'primary',
    nivel_minimo INT(11) NOT NULL DEFAULT 1,
    orden INT(11) NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$sqlOmitidas = "CREATE TABLE IF NOT EXISTS config_opciones_base_omitidas (ruta VARCHAR(255) PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$db->query($sqlOmitidas);

$sql2 = "CREATE TABLE IF NOT EXISTS configuracion_opcion_enlaces (
    id INT(11) NOT NULL AUTO_INCREMENT,
    id_opcion INT(11) NOT NULL,
    etiqueta VARCHAR(100) NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    clase_btn VARCHAR(50) DEFAULT 'outline-primary',
    orden INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_opcion (id_opcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$ok = $db->query($sql1) && $db->query($sql2);
if ($ok) {
    echo "OK: Tablas configuracion_opciones creadas o ya existían.\n";
} else {
    echo "Error: " . $db->error . "\n";
}
