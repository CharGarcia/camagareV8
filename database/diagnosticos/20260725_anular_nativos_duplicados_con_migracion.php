<?php

declare(strict_types=1);

/**
 * Anula los asientos NATIVOS de documentos que ya tienen su contabilidad traída por la
 * migración, dejando vivo el de migración (decisión del usuario: los de migración traen la
 * clasificación de cuentas correcta del sistema viejo; los nativos usan la cuenta general
 * mal configurada de «Subtotal documento en compras»).
 *
 * Caso confirmado: compra 175 (001-102-000000013) con CO-000031 (nativo, gasto a
 * «HORAS EXTRAORDINARIAS») y COM335617 (migración, gasto a «HONORARIOS REPRESENTACIÓN»).
 *
 * Además de anular el nativo, revincula el documento al asiento de migración, porque
 * AsientoContableService::anular() no desvincula 'compra' automáticamente.
 *
 * Uso:
 *   php database/diagnosticos/20260725_anular_nativos_duplicados_con_migracion.php <id_empresa> <id_usuario> [--dry-run]
 */

require __DIR__ . '/../../bootstrap.php';

$idEmpresa = isset($argv[1]) ? (int) $argv[1] : 0;
$idUsuario = isset($argv[2]) ? (int) $argv[2] : 0;
$dryRun    = in_array('--dry-run', $argv, true);

if ($idEmpresa <= 0 || $idUsuario <= 0) {
    fwrite(STDERR, "Uso: php " . basename(__FILE__) . " <id_empresa> <id_usuario> [--dry-run]\n");
    exit(1);
}

/** origen operativo => [tabla del documento, tipo_comprobante que graba la migración] */
$mapa = [
    'compra'             => ['compras_cabecera',          'compras'],
    'liquidacion_compra' => ['liquidaciones_cabecera',    'compras'],
    'retencion_compra'   => ['retencion_compra_cabecera', 'retenciones_compras'],
    'factura_venta'      => ['ventas_cabecera',           'ventas'],
    'nota_credito'       => ['notas_credito_cabecera',    'ventas'],
    'recibo_venta'       => ['recibos_venta_cabecera',    'ventas'],
    'retencion_venta'    => ['retencion_venta_cabecera',  'retenciones_ventas'],
    'ingreso'            => ['ingresos_cabecera',         'ingresos'],
    'egreso'             => ['egresos_cabecera',          'egresos'],
];

$pdo = \App\core\Database::getConnection();

$casos = [];
foreach ($mapa as $origen => [$tabla, $tipoMig]) {
    $sql = "SELECT '{$origen}' AS origen, '{$tabla}' AS tabla,
                   a_nat.id  AS id_nativo,     a_nat.numero_comprobante AS comp_nativo,
                   a_mig.id  AS id_migracion,  a_mig.numero_comprobante AS comp_migracion,
                   a_nat.id_referencia_origen  AS id_documento,
                   a_nat.total_debe            AS monto,
                   a_nat.fecha_asiento
            FROM asientos_contables_cabecera a_nat
            JOIN asientos_contables_cabecera a_mig
                 ON a_mig.id_empresa           = a_nat.id_empresa
                AND a_mig.modulo_origen        = 'migracion'
                AND a_mig.tipo_comprobante     = :tipo_mig
                AND a_mig.id_referencia_origen = a_nat.id_referencia_origen
                AND a_mig.eliminado = false AND a_mig.estado <> 'anulado'
            WHERE a_nat.id_empresa = :id_empresa
              AND a_nat.modulo_origen = '{$origen}'
              AND a_nat.eliminado = false AND a_nat.estado <> 'anulado'
              AND a_nat.id_referencia_origen IS NOT NULL
            ORDER BY a_nat.id";
    try {
        $st = $pdo->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':tipo_mig' => $tipoMig]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $casos[] = $r; }
    } catch (\Throwable $e) {
        echo "  (omitido {$origen}: " . $e->getMessage() . ")\n";
    }
}

echo "Encontrados " . count($casos) . " documentos contabilizados DOS veces (nativo + migración).\n";
if (!$casos) { exit(0); }

$totalDup = 0.0;
foreach ($casos as $c) {
    echo "  - [{$c['origen']}] doc {$c['id_documento']}: nativo {$c['id_nativo']} ({$c['comp_nativo']}) "
       . "vs migración {$c['id_migracion']} ({$c['comp_migracion']}) — \${$c['monto']}\n";
    $totalDup += (float) $c['monto'];
}
echo "Monto duplicado total: $" . number_format($totalDup, 2) . "\n";

if ($dryRun) {
    echo "\n--dry-run: no se aplicó ningún cambio.\n";
    exit(0);
}

echo "\nAplicando (se anula el NATIVO, se deja el de migración)...\n";

$asientoService = new \App\Services\modulos\AsientoContableService(
    new \App\repositories\modulos\AsientoContableRepository(),
    new \App\Rules\modulos\AsientoContableRules(),
    new \App\Services\LogSistemaService()
);

$ok = 0;
$fallos = 0;
foreach ($casos as $c) {
    echo "Anulando nativo {$c['id_nativo']} ({$c['comp_nativo']}) de [{$c['origen']}] doc {$c['id_documento']}... ";
    try {
        try {
            $asientoService->anular((int) $c['id_nativo'], $idEmpresa, $idUsuario);
        } catch (\Throwable $eA) {
            if (stripos($eA->getMessage(), 'ya se encuentra anulado') === false) { throw $eA; }
        }

        // Deja el documento apuntando al asiento de migración (el que queda vivo).
        $upd = $pdo->prepare("UPDATE {$c['tabla']} SET id_asiento_contable = :id_asiento
                              WHERE id = :id_doc AND id_empresa = :id_empresa");
        $upd->execute([
            ':id_asiento' => (int) $c['id_migracion'],
            ':id_doc'     => (int) $c['id_documento'],
            ':id_empresa' => $idEmpresa,
        ]);

        echo "OK (documento revinculado a {$c['id_migracion']})\n";
        $ok++;
    } catch (\Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $fallos++;
    }
}

echo "\nResumen: {$ok} anulados correctamente, {$fallos} con error.\n";
if ($fallos > 0) {
    echo "Si el error menciona 'período contable cerrado', hay que reabrir ese período\n";
    echo "temporalmente (modulos/periodos-contables) antes de reintentar.\n";
}
