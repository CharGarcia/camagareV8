<?php
/**
 * Migración: Eliminar duplicados en plan_cuentas_modelo
 * Mantiene una fila por cada código (la de menor id).
 * Elimina en lotes para evitar timeouts.
 */
define('ROOT_PATH', __DIR__ . '/../..');
require ROOT_PATH . '/bootstrap.php';

$db = App\core\Database::getConnection();

// Obtener ids a eliminar (duplicados que no son el mínimo por codigo)
$sql = "SELECT p1.id FROM plan_cuentas_modelo p1
        INNER JOIN (SELECT codigo, MIN(id) as min_id FROM plan_cuentas_modelo GROUP BY codigo) p2
        ON p1.codigo = p2.codigo AND p1.id > p2.min_id";
$r = $db->query($sql);
$idsEliminar = [];
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $idsEliminar[] = (int) $row['id'];
    }
}

if (empty($idsEliminar)) {
    echo "No hay duplicados en plan_cuentas_modelo.\n";
    exit(0);
}

$total = count($idsEliminar);
echo "Se eliminarán {$total} filas duplicadas.\n";

$lote = 500;
$eliminados = 0;
foreach (array_chunk($idsEliminar, $lote) as $chunk) {
    $ids = implode(',', array_map('intval', $chunk));
    $db->query("DELETE FROM plan_cuentas_modelo WHERE id IN ({$ids})");
    $n = $db->affected_rows;
    $eliminados += $n;
    if ($n > 0) echo "Eliminadas {$eliminados}/{$total}...\n";
}

// Verificar si quedan duplicados
$r2 = $db->query("SELECT COUNT(*) as dup FROM (SELECT codigo FROM plan_cuentas_modelo GROUP BY codigo HAVING COUNT(*) > 1) t");
$duplicadosRestantes = $r2 ? (int) $r2->fetch_assoc()['dup'] : 0;

echo "Migración completada. Total eliminadas: {$eliminados} filas.";
if ($duplicadosRestantes > 0) {
    echo " Quedan {$duplicadosRestantes} códigos aún duplicados (ejecute de nuevo).";
} else {
    echo " Sin duplicados restantes.";
}
echo "\n";
