<?php
/**
 * Migración: Normalizar establecimiento a 3 dígitos (000-999)
 * Convierte EST-X a 00X, altera columna a CHAR(3)
 */
define('ROOT_PATH', __DIR__ . '/../..');
require ROOT_PATH . '/bootstrap.php';

$db = App\core\Database::getConnection();

$r = $db->query("SELECT id, establecimiento FROM empresas");
$updates = [];
while ($row = $r->fetch_assoc()) {
    $id = (int) $row['id'];
    $est = trim($row['establecimiento'] ?? '');
    if (preg_match('/^EST-(\d+)$/', $est, $m)) {
        $n = (int) $m[1];
        $nuevo = sprintf('%03d', min(999, max(0, $n)));
        $updates[] = ['id' => $id, 'est' => $nuevo];
    } elseif (preg_match('/^\d{1,3}$/', $est)) {
        $nuevo = sprintf('%03d', min(999, max(0, (int) $est)));
        $updates[] = ['id' => $id, 'est' => $nuevo];
    }
}

foreach ($updates as $u) {
    $estEsc = $db->real_escape_string($u['est']);
    $db->query("UPDATE empresas SET establecimiento = '{$estEsc}' WHERE id = {$u['id']}");
}
echo "Actualizados " . count($updates) . " registros.\n";

$db->query("ALTER TABLE empresas MODIFY establecimiento CHAR(3) NOT NULL");
echo "Columna establecimiento modificada a CHAR(3).\n";

echo "Migración completada.\n";
