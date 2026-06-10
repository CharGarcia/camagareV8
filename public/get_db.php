<?php
define('MVC_CONFIG', true);
require '../config/config.php';
require '../app/core/Database.php';
$db = \App\core\Database::getConnection();
$st = $db->query('SELECT * FROM casilleros_declaracion_sri');
echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
