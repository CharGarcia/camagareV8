<?php
/**
 * Script temporal para inspeccionar tablas configuracion_opciones y configuracion_opcion_enlaces.
 * Usa config/parametros.xml para la conexion.
 * Ejecutar: php temp_describe_tablas.php
 */
define('ROOT_PATH', __DIR__);
$xml = simplexml_load_file(ROOT_PATH . '/config/parametros.xml');
$host = (string) $xml->host_db;
$user = (string) $xml->user_db;
$pass = (string) $xml->pass_db;
$db   = (string) $xml->db_name;

$con = new mysqli($host, $user, $pass, $db);
if (mysqli_connect_errno()) {
    die('Error de conexion: ' . mysqli_connect_error());
}
mysqli_set_charset($con, 'utf8');

$tablas = ['configuracion_opciones', 'configuracion_opcion_enlaces'];

$sugerencias = [
    'configuracion_opciones' => "CREATE TABLE configuracion_opciones (
  id int(11) NOT NULL AUTO_INCREMENT,
  nombre varchar(100) NOT NULL,
  descripcion varchar(255) DEFAULT NULL,
  icono varchar(80) DEFAULT 'fas fa-cog',
  clase_color varchar(50) DEFAULT 'primary',
  nivel_minimo tinyint(4) NOT NULL DEFAULT 1 COMMENT '1=Usuario, 2=Admin, 3=SuperAdmin',
  orden int(11) NOT NULL DEFAULT 0,
  activo tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_nivel (nivel_minimo),
  KEY idx_orden (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    'configuracion_opcion_enlaces' => "CREATE TABLE configuracion_opcion_enlaces (
  id int(11) NOT NULL AUTO_INCREMENT,
  id_opcion int(11) NOT NULL,
  etiqueta varchar(80) NOT NULL,
  ruta varchar(255) NOT NULL,
  clase_btn varchar(50) DEFAULT 'outline-primary',
  orden int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY fk_enlace_opcion (id_opcion),
  CONSTRAINT fk_config_enlace_opcion FOREIGN KEY (id_opcion) REFERENCES configuracion_opciones (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
];

foreach ($tablas as $tabla) {
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "TABLA: $tabla\n";
    echo str_repeat('=', 70) . "\n";

    $existe = mysqli_query($con, "SHOW TABLES LIKE '$tabla'");
    if (mysqli_num_rows($existe) == 0) {
        echo "La tabla NO EXISTE.\n";
        echo "\n--- ESTRUCTURA SUGERIDA ---\n";
        echo $sugerencias[$tabla] . "\n";
        continue;
    }

    echo "\n--- DESCRIBE ---\n";
    $r = mysqli_query($con, "DESCRIBE $tabla");
    while ($row = mysqli_fetch_assoc($r)) {
        printf("%-25s %-20s %-5s %-5s %-10s %s\n",
            $row['Field'], $row['Type'], $row['Null'], $row['Key'], $row['Default'] ?? 'NULL', $row['Extra'] ?? '');
    }

    echo "\n--- SHOW CREATE TABLE ---\n";
    $r = mysqli_query($con, "SHOW CREATE TABLE $tabla");
    $row = mysqli_fetch_row($r);
    echo $row[1] . "\n";
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "FIN\n";

$con->close();
