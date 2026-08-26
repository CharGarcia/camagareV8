<?php
/**
 * Verificador de esquema — Módulo "Envío en lote al SRI".
 *
 * Uso:
 *   php scripts/verificar_esquema_lote_sri.php
 *
 * POR QUÉ EXISTE
 * --------------
 * El listado de comprobantes enviables hace un UNION ALL sobre las cabeceras de
 * los 6 tipos soportados. Si a UNA sola tabla le falta UNA sola columna, el
 * endpoint buscarAjax revienta entero y el módulo no lista NADA — ni siquiera
 * los tipos cuyas tablas sí están completas. Ya ocurrió en producción cuando
 * faltaba la migración add_sri_columns_to_liquidaciones.sql.
 *
 * Las migraciones NO se aplican solas con `git pull`: este script dice, en un
 * segundo y sin pedir credenciales a mano, si a esta base de datos le falta algo
 * para que el módulo funcione.
 *
 * Es de SOLO LECTURA: consulta information_schema y no modifica nada.
 * Sale con código 0 si todo está bien, 1 si falta algo.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo se ejecuta por línea de comandos (CLI).\n");
    exit(1);
}

require_once dirname(__DIR__) . '/bootstrap.php';

use App\core\Database;

/**
 * Tabla => columnas que el módulo referencia en sus consultas.
 * Mantener sincronizado con EnvioLoteSriRepository::subqueryEnviables().
 */
$requerido = [
    // Cabeceras de los comprobantes enviables (UNION del listado)
    'ventas_cabecera' => [
        'id', 'id_empresa', 'id_cliente', 'id_usuario', 'establecimiento', 'punto_emision',
        'secuencial', 'fecha_emision', 'clave_acceso', 'estado', 'tipo_ambiente', 'eliminado',
        'importe_total',
    ],
    'notas_credito_cabecera' => [
        'id', 'id_empresa', 'id_cliente', 'id_usuario', 'establecimiento', 'punto_emision',
        'secuencial', 'fecha_emision', 'clave_acceso', 'estado', 'tipo_ambiente', 'eliminado',
        'importe_total',
    ],
    'nota_debito_cabecera' => [
        'id', 'id_empresa', 'id_cliente', 'id_usuario', 'establecimiento', 'punto_emision',
        'secuencial', 'fecha_emision', 'clave_acceso', 'estado', 'tipo_ambiente', 'eliminado',
        'importe_total',
    ],
    'retencion_compra_cabecera' => [
        'id', 'id_empresa', 'id_proveedor', 'id_usuario', 'establecimiento', 'punto_emision',
        'secuencial', 'fecha_emision', 'clave_acceso', 'estado', 'tipo_ambiente', 'eliminado',
        'total_retenido',
    ],
    'liquidaciones_cabecera' => [
        'id', 'id_empresa', 'id_proveedor', 'id_usuario', 'establecimiento', 'punto_emision',
        'secuencial', 'fecha_emision', 'clave_acceso', 'estado', 'tipo_ambiente', 'eliminado',
        'importe_total',
    ],
    // Guía de remisión: no tiene importe, y suma fecha_inicio_transporte (la
    // fecha que el SRI toma como fecha del comprobante).
    'guias_remision_cabecera' => [
        'id', 'id_empresa', 'id_cliente', 'id_usuario', 'establecimiento', 'punto_emision',
        'secuencial', 'fecha_emision', 'fecha_inicio_transporte', 'clave_acceso', 'estado',
        'tipo_ambiente', 'eliminado',
    ],
    // Contrapartes (JOIN del listado)
    'clientes'    => ['id', 'nombre'],
    'proveedores' => ['id', 'razon_social'],
    // Cola del módulo
    'sri_lotes' => [
        'id', 'id_empresa', 'estado', 'tipo_ambiente', 'total', 'procesados', 'exitosos',
        'fallidos', 'filtros_json', 'iniciado_at', 'finalizado_at', 'created_at', 'updated_at',
        'created_by', 'updated_by', 'eliminado',
    ],
    'sri_lote_items' => [
        'id', 'id_lote', 'id_empresa', 'tipo_comprobante', 'id_comprobante', 'numero',
        'fecha_emision', 'estado', 'mensaje', 'numero_autorizacion', 'intentos', 'processed_at',
    ],
];

/** Migración que crea/corrige cada tabla, para orientar al que corre el script. */
$migracion = [
    'guias_remision_cabecera'   => 'database/migrations/create_guias_remision.sql',
    'liquidaciones_cabecera'    => 'database/migrations/add_sri_columns_to_liquidaciones.sql',
    'sri_lotes'                 => 'database/migrations/create_sri_lotes.sql',
    'sri_lote_items'            => 'database/migrations/create_sri_lotes.sql',
];

$db = Database::getConnection();

$st = $db->prepare(
    "SELECT column_name FROM information_schema.columns
      WHERE table_schema = current_schema() AND table_name = :t"
);

$problemas = [];

echo "Verificando esquema del módulo 'Envío en lote al SRI'…\n\n";

foreach ($requerido as $tabla => $columnas) {
    $st->execute([':t' => $tabla]);
    $existentes = $st->fetchAll(\PDO::FETCH_COLUMN);

    if (empty($existentes)) {
        $problemas[] = ['tabla' => $tabla, 'falta' => ['(la tabla no existe)']];
        printf("  [FALTA] %-28s la tabla no existe\n", $tabla);
        continue;
    }

    $faltantes = array_values(array_diff($columnas, $existentes));
    if ($faltantes) {
        $problemas[] = ['tabla' => $tabla, 'falta' => $faltantes];
        printf("  [FALTA] %-28s columnas: %s\n", $tabla, implode(', ', $faltantes));
    } else {
        printf("  [  OK ] %-28s %d columnas\n", $tabla, count($columnas));
    }
}

// tipo_comprobante debe aceptar el valor más largo ('liquidacion_compra' = 18).
try {
    $st2 = $db->prepare(
        "SELECT character_maximum_length FROM information_schema.columns
          WHERE table_schema = current_schema()
            AND table_name = 'sri_lote_items' AND column_name = 'tipo_comprobante'"
    );
    $st2->execute();
    $largo = $st2->fetchColumn();
    if ($largo !== false && $largo !== null && (int) $largo < 20) {
        $problemas[] = ['tabla' => 'sri_lote_items', 'falta' => ["tipo_comprobante es VARCHAR({$largo}), necesita al menos 20"]];
        printf("\n  [FALTA] sri_lote_items.tipo_comprobante es VARCHAR(%d): necesita al menos 20.\n", (int) $largo);
    }
} catch (\Throwable) {
    // Si la tabla no existe ya se reportó arriba.
}

echo "\n";

if (empty($problemas)) {
    echo "Todo correcto: el módulo puede listar y enviar los 6 tipos de comprobante.\n";
    exit(0);
}

echo "Faltan " . count($problemas) . " tabla(s) por corregir. Mientras tanto el listado\n";
echo "del módulo NO cargará ningún tipo de comprobante, no solo los afectados.\n\n";
echo "Aplique estas migraciones (no se ejecutan solas con git pull):\n";

$vistas = [];
foreach ($problemas as $p) {
    $archivo = $migracion[$p['tabla']] ?? "(revisar migraciones de {$p['tabla']})";
    if (isset($vistas[$archivo])) { continue; }
    $vistas[$archivo] = true;
    echo "  - {$archivo}\n";
}

exit(1);
