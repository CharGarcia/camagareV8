<?php
$config = require '../config/database.php';
$pdo = new PDO("pgsql:host={$config['host']};port={$config['port']};dbname={$config['name']}", $config['user'], $config['pass']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->exec("DROP TABLE IF EXISTS empresa_casilleros_iva_sri");
    
    $sql = "
    CREATE TABLE empresa_casilleros_iva_sri (
        id SERIAL PRIMARY KEY,
        id_empresa INTEGER NOT NULL,
        codigo INTEGER NOT NULL,
        tipo_documento VARCHAR(50) NOT NULL,
        casillero_bruto VARCHAR(20),
        casillero_neto VARCHAR(20),
        casillero_impuesto VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP,
        created_by INTEGER,
        updated_by INTEGER,
        eliminado BOOLEAN DEFAULT FALSE,
        deleted_at TIMESTAMP,
        deleted_by INTEGER
    );
    ";
    
    $pdo->exec($sql);
    echo "Tabla empresa_casilleros_iva_sri reemplazada correctamente.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
