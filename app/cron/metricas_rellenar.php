<?php
/**
 * Rellena (o recalcula) el resumen diario de Métricas del sistema para un rango de fechas.
 *
 * Uso (desde la raíz del sistema):
 *   php app/cron/metricas_rellenar.php 2024-01-01 2026-11-04
 *   php app/cron/metricas_rellenar.php 2026-10-01            (hasta ayer)
 *
 * Seguro de repetir: cada día se reemplaza con lo que dicen las tablas de origen. Va por
 * tramos de 31 días, cada uno en su transacción. El día de hoy nunca se guarda (está en curso).
 * Correrlo en horario de baja actividad si el rango es de varios años.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo por consola.');
}

define('ROOT_PATH', __DIR__ . '/../..');
require ROOT_PATH . '/bootstrap.php';

set_time_limit(0);
ini_set('memory_limit', '512M');

$desde = $argv[1] ?? '';
$hasta = $argv[2] ?? date('Y-m-d', strtotime('-1 day'));

if ($desde === '') {
    fwrite(STDERR, "Uso: php app/cron/metricas_rellenar.php DESDE [HASTA]   (fechas AAAA-MM-DD)\n");
    exit(1);
}

try {
    $svc = new \App\Services\MetricasDiariasService();
    $t0  = microtime(true);
    $res = $svc->recalcular($desde, $hasta, static function (string $d, string $h, int $filas): void {
        echo "  {$d} .. {$h}: {$filas} fila(s)\n";
    });
    echo "Listo: {$res['dias']} día(s), {$res['filas']} fila(s) en {$res['tramos']} tramo(s), "
        . round(microtime(true) - $t0, 1) . " s.\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
