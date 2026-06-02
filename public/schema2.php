<?php
$config = require_once __DIR__ . '/../config/database.php';
$dsn = "{$config['driver']}:host={$config['host']};port={$config['port']};dbname={$config['name']}";
$db = new PDO($dsn, $config['user'], $config['pass']);

$q = $db->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'suscripcion_periodicidades'");
print_r($q->fetchAll(PDO::FETCH_ASSOC));
