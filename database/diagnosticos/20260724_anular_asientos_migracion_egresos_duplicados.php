<?php

declare(strict_types=1);

/**
 * Complemento de 20260724_anular_egresos_migracion_duplicados.php: ese script marcó los 20
 * egresos como 'anulado', pero su asiento contable (modulo_origen='migracion') NO se anuló,
 * porque EgresoService::anularAsientoContable() busca con getAsientoPorOrigen('egreso', ...)
 * -- que filtra por modulo_origen='egreso', y estos asientos migrados tienen
 * modulo_origen='migracion'. Este script anula directamente esos asientos.
 *
 * Uso: php database/diagnosticos/20260724_anular_asientos_migracion_egresos_duplicados.php <id_empresa> <id_usuario> [--dry-run]
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
    "SELECT id, numero_comprobante, id_referencia_origen, estado, total_debe
     FROM asientos_contables_cabecera
     WHERE id_empresa = :id_empresa
       AND modulo_origen = 'migracion'
       AND tipo_comprobante = 'egresos'
       AND id_referencia_origen = ANY(:ids)
     ORDER BY id_referencia_origen"
);
$stmt->execute([':id_empresa' => $idEmpresa, ':ids' => '{' . implode(',', $idsEgresos) . '}']);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Encontrados " . count($rows) . " de " . count($idsEgresos) . " asientos esperados.\n";
foreach ($rows as $r) {
    echo "  - asiento {$r['id']} ({$r['numero_comprobante']}) de egreso {$r['id_referencia_origen']}, estado={$r['estado']}, \${$r['total_debe']}\n";
}

if ($dryRun) {
    echo "\n--dry-run: no se aplicó ningún cambio.\n";
    exit(0);
}

echo "\nAplicando...\n";

$asientoService = new \App\Services\modulos\AsientoContableService(
    new \App\repositories\modulos\AsientoContableRepository(),
    new \App\Rules\modulos\AsientoContableRules(),
    new \App\Services\LogSistemaService()
);

$ok = 0;
$fallos = 0;
foreach ($rows as $r) {
    if ($r['estado'] === 'anulado') {
        echo "Asiento {$r['id']}: ya estaba anulado, se omite.\n";
        continue;
    }
    echo "Anulando asiento {$r['id']} ({$r['numero_comprobante']})... ";
    try {
        $asientoService->anular((int) $r['id'], $idEmpresa, $idUsuario);
        echo "OK\n";
        $ok++;
    } catch (\Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $fallos++;
    }
}

echo "\nResumen: {$ok} anulados correctamente, {$fallos} con error.\n";
