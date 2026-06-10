<?php
$host = '127.0.0.1';
$db   = 'sistema_cmg';
$user = 'postgres';
$pass = 'root';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$db", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'sri_casilleros_etiquetas'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);
} catch (\Throwable $e) {
    echo "ERROR PDO: " . $e->getMessage() . "\n";
}
