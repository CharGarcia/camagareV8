<?php
$config = require 'C:/xampp/htdocs/sistema/config/database.php';
$pdo = new PDO("pgsql:host={$config['host']};port=5432;dbname={$config['name']}", $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$stmt = $pdo->query("SELECT estado, COUNT(*) FROM asientos_contables_cabecera GROUP BY estado");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
