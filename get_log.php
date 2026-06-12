<?php
require 'app/config/config.php';
$pdo = new PDO('pgsql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
$stmt = $pdo->query('SELECT detalle_json FROM sri_descarga_auto_log ORDER BY id DESC LIMIT 1');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
file_put_contents('scraper_log.json', $row['detalle_json']);
echo "Done.";
