<?php
/**
 * Worker CLI — Procesa una emisión en bloque de Condominios (recibos o facturas a los
 * condóminos) en segundo plano.
 *
 * Uso:
 *   php scripts/procesar_emision_condominios.php --emision=123
 *
 * Lo lanza CondominiosConfigController::emitirAjax() desligado del request y, como respaldo,
 * el cron (CondominioCobroService::retomarAbiertas) si una emisión quedó abierta. Es idempotente
 * y de un solo worker por emisión (candado): si ya terminó, la cancelaron u otro worker la
 * procesa, sale sin hacer nada.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo se ejecuta por línea de comandos (CLI).\n");
    exit(1);
}

@set_time_limit(0);
ignore_user_abort(true);

require_once dirname(__DIR__) . '/bootstrap.php';

$opts = getopt('', ['emision:']);
$id   = (int) ($opts['emision'] ?? 0);
if ($id <= 0) {
    fwrite(STDERR, "Falta el parámetro --emision=ID.\n");
    exit(1);
}

try {
    \App\Services\modulos\CondominioCobroService::crear()->procesarEmision($id);
    fwrite(STDOUT, "Emisión {$id} procesada.\n");
    exit(0);
} catch (\Throwable $e) {
    error_log('[procesar_emision_condominios] Emisión ' . $id . ': ' . $e->getMessage());
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
