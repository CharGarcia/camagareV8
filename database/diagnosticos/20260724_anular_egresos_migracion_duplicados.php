<?php

declare(strict_types=1);

/**
 * Anula los egresos de MIGRACIÓN que duplican pagos ya hechos nativamente antes de correr
 * la migración completa (empresa 8: 39 compras "vinculado=true" pagadas dos veces -- una vez
 * en el sistema nuevo antes del 13/07, y otra vez al traer el historial del sistema viejo).
 *
 * Usa EgresoService::anular() -- marca el egreso como 'anulado' Y anula/desvincula su asiento
 * contable, igual que si se anulara desde la propia pantalla de Egresos. Auditado normalmente.
 *
 * IDs a anular (confirmados 100% duplicados/exclusivos de este historial, verificados con SQL
 * antes de ejecutar este script):
 *   483, 490, 491, 492, 493, 494, 495, 496, 497, 498, 499,
 *   500, 501, 502, 503, 504, 505, 506, 507, 508
 *
 * OJO: los egresos 490 y 491 son pagos en lote que ADEMÁS incluyen un pago legítimo no
 * duplicado (compras 1244 y 1245, $5.00 y $11.49). Al anular estos dos egresos completos,
 * ese pago legítimo también se revierte -- hay que volver a registrarlo manualmente después
 * (por la pantalla normal de Egresos) para no perderlo.
 *
 * Uso (en el servidor, desde la raíz del proyecto):
 *   php database/diagnosticos/20260724_anular_egresos_migracion_duplicados.php <id_empresa> <id_usuario> [--dry-run]
 */

require __DIR__ . '/../../bootstrap.php';

$idEmpresa = isset($argv[1]) ? (int) $argv[1] : 0;
$idUsuario = isset($argv[2]) ? (int) $argv[2] : 0;
$dryRun = in_array('--dry-run', $argv, true);

if ($idEmpresa <= 0 || $idUsuario <= 0) {
    fwrite(STDERR, "Uso: php " . basename(__FILE__) . " <id_empresa> <id_usuario> [--dry-run]\n");
    exit(1);
}

$idsEgresos = [483, 490, 491, 492, 493, 494, 495, 496, 497, 498, 499, 500, 501, 502, 503, 504, 505, 506, 507, 508];

$pdo = \App\core\Database::getConnection();

$stmt = $pdo->prepare(
    "SELECT id, fecha_emision, monto_total, estado FROM egresos_cabecera
     WHERE id_empresa = :id_empresa AND id = ANY(:ids)
     ORDER BY id"
);
$stmt->execute([':id_empresa' => $idEmpresa, ':ids' => '{' . implode(',', $idsEgresos) . '}']);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Encontrados " . count($rows) . " de " . count($idsEgresos) . " egresos esperados.\n";
$total = 0.0;
foreach ($rows as $r) {
    echo "  - egreso {$r['id']} ({$r['fecha_emision']}, estado={$r['estado']}): \${$r['monto_total']}\n";
    $total += (float) $r['monto_total'];
}
echo "Monto total a revertir: $" . number_format($total, 2) . "\n";

if ($dryRun) {
    echo "\n--dry-run: no se aplicó ningún cambio.\n";
    exit(0);
}

echo "\nAplicando...\n";

$egresoService = new \App\Services\modulos\EgresoService(
    new \App\repositories\modulos\EgresoRepository(),
    new \App\Rules\modulos\EgresoRules(),
    new \App\Services\LogSistemaService()
);

$ok = 0;
$fallos = 0;
foreach ($rows as $r) {
    $id = (int) $r['id'];
    if ($r['estado'] === 'anulado') {
        echo "Egreso {$id}: ya estaba anulado, se omite.\n";
        continue;
    }
    echo "Anulando egreso {$id} (\${$r['monto_total']})... ";
    try {
        $egresoService->anular($id, $idEmpresa, $idUsuario);
        echo "OK\n";
        $ok++;
    } catch (\Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $fallos++;
    }
}

echo "\nResumen: {$ok} anulados correctamente, {$fallos} con error.\n";
if ($fallos > 0) {
    echo "Si el error es de 'periodo contable cerrado', hay que reabrir temporalmente ese periodo\n";
    echo "(modulos/periodos-contables) antes de reintentar con esos IDs puntuales.\n";
}

echo "\nRECORDATORIO: registra manualmente 2 pagos nuevos (Egresos normales) que se perdieron\n";
echo "al anular el lote 490/491 -- eran legítimos, no duplicados:\n";
echo "  - Compra 001-204-000000313 (id 1244, proveedor 63): \$5.00\n";
echo "  - Compra 001-204-000000362 (id 1245, proveedor 63): \$11.49\n";
