<?php
require_once __DIR__ . '/../../config/database.php';
$cfg = require __DIR__ . '/../../config/database.php';

try {
    $dsn = "pgsql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};";
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $pdo->exec("ALTER TABLE empresas ADD COLUMN IF NOT EXISTS max_usuarios INTEGER NOT NULL DEFAULT 3");
    $pdo->exec("COMMENT ON COLUMN empresas.max_usuarios IS 'Número máximo de usuarios permitidos. Default 3.'");

    echo "OK: columna max_usuarios agregada a empresas (o ya existia).\n";

    // Verificar
    $r = $pdo->query("SELECT column_name, data_type, column_default FROM information_schema.columns WHERE table_name='empresas' AND column_name='max_usuarios'")->fetchAll(PDO::FETCH_ASSOC);
    if ($r) {
        echo "Columna verificada: " . json_encode($r[0]) . "\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
