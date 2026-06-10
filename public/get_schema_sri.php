<?php
require 'c:/xampp/htdocs/sistema/config/config.php';
$pdo = new PDO('mysql:host=localhost;dbname=sistema', 'root', '');
$st = $pdo->query("DESCRIBE casilleros_declaracion_sri");
echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
