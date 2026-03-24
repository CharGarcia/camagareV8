<?php
/**
 * Script para mostrar DESCRIBE de tablas
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';

$db = getDB();
$tablas = ['modulos_asignados', 'usuarios', 'usuario_asignado', 'empresa_asignada'];

foreach ($tablas as $tabla) {
    echo "\n=== DESCRIBE $tabla ===\n";
    try {
        $result = $db->select("DESCRIBE $tabla");
        if ($result === false || empty($result)) {
            echo "  (tabla no existe o error)\n";
            continue;
        }
        foreach ($result as $row) {
            $f = isset($row['Field']) ? $row['Field'] : $row['field'];
            $t = isset($row['Type']) ? $row['Type'] : $row['type'];
            $n = isset($row['Null']) ? $row['Null'] : $row['null'];
            $k = isset($row['Key']) ? $row['Key'] : $row['key'];
            $d = isset($row['Default']) ? $row['Default'] : (isset($row['default']) ? $row['default'] : '');
            $e = isset($row['Extra']) ? $row['Extra'] : $row['extra'];
            printf("  %-25s %-25s %-5s %-5s %-15s %s\n", $f, $t, $n, $k, $d, $e);
        }
    } catch (Throwable $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
}
echo "\n";
