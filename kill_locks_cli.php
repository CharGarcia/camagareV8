<?php
$config = require 'c:/xampp/htdocs/sistema/app/Config/database.php';
$pdo = new PDO("pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}", $config['username'], $config['password']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE pid <> pg_backend_pid()";
$st = $pdo->query($sql);
echo "Conexiones terminadas: " . $st->rowCount() . "\n";
