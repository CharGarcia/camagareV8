<?php
define('MVC_ROOT',   dirname(__DIR__, 2));
define('MVC_CONFIG', MVC_ROOT . '/config');
$c   = require MVC_CONFIG . '/database.php';
$pdo = new PDO('pgsql:host='.$c['host'].';port='.$c['port'].';dbname='.$c['name'], $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Ver qué id_icono usa WhatsApp como referencia
$ref = $pdo->query("SELECT id_icono FROM submodulos_menu WHERE id=61")->fetchColumn();
echo "id_icono de WhatsApp config: $ref\n";

$existe = $pdo->query("SELECT id FROM submodulos_menu WHERE ruta = 'modulos/configuracion-payphone'")->fetchColumn();
if ($existe) { echo "Ya existe (id=$existe).\n"; exit; }

$maxOrden = (int) $pdo->query("SELECT COALESCE(MAX(orden),0) FROM submodulos_menu WHERE id_modulo = 310")->fetchColumn();

$st = $pdo->prepare(
    "INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
     VALUES (:nombre, :ruta, 310, :orden, :icono, 1)
     RETURNING id"
);
$st->execute([
    ':nombre' => 'Configuración Payphone',
    ':ruta'   => 'modulos/configuracion-payphone',
    ':orden'  => $maxOrden + 1,
    ':icono'  => $ref,
]);
$newId = $st->fetchColumn();
echo "Submódulo creado con id=$newId.\n";
