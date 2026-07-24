<?php

declare(strict_types=1);

/**
 * Anula los asientos contables huérfanos de Facturas de Venta ELIMINADAS (borradores
 * descartados que quedaron con un asiento "contabilizado" activo por el bug ya corregido
 * en FacturaVentaService::crear()/eliminar()).
 *
 * Usa App\Services\modulos\AsientoContableService::anular() -- el mismo camino que usa la
 * aplicación al anular una factura normal -- así que además de marcar el asiento como
 * 'anulado', desvincula ventas_cabecera.id_asiento_contable y registra auditoría en
 * log_sistema. No es un UPDATE directo a la tabla.
 *
 * Uso (en el servidor, desde la raíz del proyecto):
 *   php database/diagnosticos/20260724_anular_asientos_huerfanos_facturas.php <id_empresa> <id_usuario> [--dry-run]
 *
 * Ejemplo (vista previa, no cambia nada):
 *   php database/diagnosticos/20260724_anular_asientos_huerfanos_facturas.php 8 2 --dry-run
 *
 * Ejemplo (aplica la corrección de verdad):
 *   php database/diagnosticos/20260724_anular_asientos_huerfanos_facturas.php 8 2
 */

require __DIR__ . '/../../bootstrap.php';

$idEmpresa = isset($argv[1]) ? (int) $argv[1] : 0;
$idUsuario = isset($argv[2]) ? (int) $argv[2] : 0;
$dryRun = in_array('--dry-run', $argv, true);

if ($idEmpresa <= 0 || $idUsuario <= 0) {
    fwrite(STDERR, "Uso: php " . basename(__FILE__) . " <id_empresa> <id_usuario> [--dry-run]\n");
    exit(1);
}

$pdo = \App\core\Database::getConnection();

$sql = "SELECT ac.id AS id_asiento, ac.numero_comprobante, ac.total_debe,
               v.id AS id_venta,
               v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial AS numero_factura
        FROM ventas_cabecera v
        JOIN asientos_contables_cabecera ac
             ON ac.id_empresa = v.id_empresa
            AND ac.modulo_origen = 'factura_venta'
            AND ac.id_referencia_origen = v.id
        WHERE v.id_empresa = :id_empresa
          AND v.eliminado = true
          AND ac.eliminado = false
          AND ac.estado <> 'anulado'
        ORDER BY ac.id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id_empresa' => $idEmpresa]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Encontrados " . count($rows) . " asientos huérfanos para empresa {$idEmpresa}.\n";
if (!$rows) {
    exit(0);
}

foreach ($rows as $r) {
    echo "  - asiento {$r['id_asiento']} ({$r['numero_comprobante']}), factura {$r['numero_factura']} (id_venta {$r['id_venta']}), \${$r['total_debe']}\n";
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
    echo "Anulando asiento {$r['id_asiento']} ({$r['numero_comprobante']})... ";
    try {
        $asientoService->anular((int) $r['id_asiento'], $idEmpresa, $idUsuario);
        echo "OK\n";
        $ok++;
    } catch (\Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $fallos++;
    }
}

echo "\nResumen: {$ok} anulados correctamente, {$fallos} con error.\n";
