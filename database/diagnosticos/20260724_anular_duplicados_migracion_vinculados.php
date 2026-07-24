<?php

declare(strict_types=1);

/**
 * Anula los asientos contables de MIGRACIÓN duplicados sobre documentos NATIVOS ya
 * vinculados (migracion_mysql_map.vinculado=true), y revincula el documento al asiento
 * nativo correcto. Ver 20260724_duplicados_migracion_documentos_vinculados.sql para el
 * diagnóstico que originó esto (caso confirmado: compra id=157 empresa 8, asientos 109
 * nativo + 7719 migración duplicados).
 *
 * Por cada caso:
 *   1. AsientoContableService::anular($idAsientoMigracion, ...) -- marca 'anulado' y, para
 *      los tipos que ya lo soporta (retencion_venta/retencion_compra/ingreso/egreso/
 *      factura_venta), desvincula id_asiento_contable del documento.
 *   2. UPDATE explícito de <tabla_nativa>.id_asiento_contable = id_asiento_nativo -- sin
 *      esto, 'compra' quedaría con id_asiento_contable apuntando al asiento YA ANULADO
 *      (o en NULL para los tipos que sí desvincula anular()), en vez del asiento nativo
 *      que sigue activo.
 *
 * Uso (en el servidor, desde la raíz del proyecto):
 *   php database/diagnosticos/20260724_anular_duplicados_migracion_vinculados.php <id_usuario> [--dry-run]
 *
 * No requiere id_empresa: la propia consulta de diagnóstico ya trae todas las empresas.
 */

require __DIR__ . '/../../bootstrap.php';

$idUsuario = isset($argv[1]) ? (int) $argv[1] : 0;
$dryRun = in_array('--dry-run', $argv, true);

if ($idUsuario <= 0) {
    fwrite(STDERR, "Uso: php " . basename(__FILE__) . " <id_usuario> [--dry-run]\n");
    exit(1);
}

$tablaPorEntidad = [
    'compras'            => 'compras_cabecera',
    'retenciones_venta'  => 'retencion_venta_cabecera',
    'retenciones_compra' => 'retencion_compra_cabecera',
    'ingresos'           => 'ingresos_cabecera',
    'egresos'            => 'egresos_cabecera',
    'facturas'           => 'ventas_cabecera',
    'notas_credito'      => 'notas_credito_cabecera',
    'recibos'            => 'recibos_venta_cabecera',
];

$pdo = \App\core\Database::getConnection();

$sql = "
WITH vinculados AS (
    SELECT id_empresa, entidad, id_destino
    FROM migracion_mysql_map
    WHERE vinculado = true
),
mapa_entidad_modulo (entidad, modulo_origen_nativo, tipo_comprobante_migracion) AS (
    VALUES
        ('compras', 'compra', 'compras'),
        ('retenciones_venta', 'retencion_venta', 'retenciones_ventas'),
        ('retenciones_compra', 'retencion_compra', 'retenciones_compras'),
        ('ingresos', 'ingreso', 'ingresos'),
        ('egresos', 'egreso', 'egresos'),
        ('facturas', 'factura_venta', 'ventas'),
        ('notas_credito', 'nota_credito', 'ventas'),
        ('recibos', 'recibo_venta', 'ventas')
)
SELECT
    v.id_empresa,
    v.entidad,
    v.id_destino          AS id_documento,
    ac_nat.id              AS id_asiento_nativo,
    ac_nat.numero_comprobante AS comprobante_nativo,
    ac_mig.id              AS id_asiento_migracion,
    ac_mig.numero_comprobante AS comprobante_migracion,
    ac_mig.total_debe      AS monto
FROM vinculados v
JOIN mapa_entidad_modulo me ON me.entidad = v.entidad
JOIN asientos_contables_cabecera ac_nat
     ON ac_nat.id_empresa = v.id_empresa
    AND ac_nat.modulo_origen = me.modulo_origen_nativo
    AND ac_nat.id_referencia_origen = v.id_destino
    AND ac_nat.eliminado = false
    AND ac_nat.estado <> 'anulado'
JOIN asientos_contables_cabecera ac_mig
     ON ac_mig.id_empresa = v.id_empresa
    AND ac_mig.modulo_origen = 'migracion'
    AND ac_mig.tipo_comprobante = me.tipo_comprobante_migracion
    AND ac_mig.id_referencia_origen = v.id_destino
    AND ac_mig.eliminado = false
    AND ac_mig.estado <> 'anulado'
    AND ac_mig.total_debe = ac_nat.total_debe
ORDER BY v.id_empresa, v.entidad, v.id_destino";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "Encontrados " . count($rows) . " documentos con asiento de migración duplicado.\n";
if (!$rows) {
    exit(0);
}

foreach ($rows as $r) {
    echo "  - [{$r['entidad']}] doc {$r['id_documento']} (empresa {$r['id_empresa']}): nativo {$r['id_asiento_nativo']} ({$r['comprobante_nativo']}) vs migración {$r['id_asiento_migracion']} ({$r['comprobante_migracion']}), \${$r['monto']}\n";
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
    $tabla = $tablaPorEntidad[$r['entidad']] ?? null;
    if (!$tabla) {
        echo "SKIP: entidad desconocida '{$r['entidad']}' (doc {$r['id_documento']})\n";
        $fallos++;
        continue;
    }

    echo "Anulando asiento migración {$r['id_asiento_migracion']} ({$r['comprobante_migracion']}) de [{$r['entidad']}] doc {$r['id_documento']}... ";
    try {
        try {
            $asientoService->anular((int) $r['id_asiento_migracion'], (int) $r['id_empresa'], $idUsuario);
        } catch (\Throwable $eA) {
            if (stripos($eA->getMessage(), 'ya se encuentra anulado') === false) {
                throw $eA;
            }
        }

        // Revincula el documento al asiento NATIVO (el que sigue activo), sin importar a
        // cuál apuntaba antes -- necesario porque anular() no desvincula 'compra'/'nota_credito'/
        // 'liquidacion_compra'/'recibo_venta', y para los que sí desvincula, dejaría NULL en
        // vez del asiento nativo correcto.
        $upd = $pdo->prepare("UPDATE {$tabla} SET id_asiento_contable = :id_asiento, updated_at = CURRENT_TIMESTAMP, updated_by = :id_usuario WHERE id = :id_doc AND id_empresa = :id_empresa");
        $upd->execute([
            ':id_asiento' => (int) $r['id_asiento_nativo'],
            ':id_usuario' => $idUsuario,
            ':id_doc'     => (int) $r['id_documento'],
            ':id_empresa' => (int) $r['id_empresa'],
        ]);

        echo "OK (revinculado a asiento nativo {$r['id_asiento_nativo']})\n";
        $ok++;
    } catch (\Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $fallos++;
    }
}

echo "\nResumen: {$ok} corregidos correctamente, {$fallos} con error.\n";
