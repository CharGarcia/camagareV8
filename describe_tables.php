<?php
$xml = simplexml_load_file(__DIR__ . '/config/parametros.xml');
$host = (string)$xml->host_db;
$user = (string)$xml->user_db;
$pass = (string)$xml->pass_db;
$db   = (string)$xml->db_name;

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

foreach (['empresa_asignada', 'usuarios'] as $table) {
    echo "\n=== DESCRIBE $table ===\n";
    $stmt = $pdo->query("DESCRIBE $table");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        printf("%-25s %-20s %-5s %-5s %-10s %s\n",
            $row['Field'],
            $row['Type'],
            $row['Null'],
            $row['Key'],
            $row['Default'] ?? 'NULL',
            $row['Extra'] ?? ''
        );
    }
}
