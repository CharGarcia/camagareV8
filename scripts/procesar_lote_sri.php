<?php
/**
 * Worker CLI — Procesa un lote de envío de comprobantes al SRI en segundo plano.
 *
 * Uso:
 *   php scripts/procesar_lote_sri.php --lote=123
 *
 * Lo lanza automáticamente EnvioLoteSriController::crearLoteAjax() de forma
 * desligada del request (Windows: start /B, Linux: nohup ... &). También puede
 * ejecutarse a mano o desde una tarea programada / cron como respaldo:
 *   - si un lote quedó en 'pendiente' (no se pudo lanzar el worker), basta con
 *     volver a ejecutar este script con su --lote=ID para procesarlo.
 *
 * Es idempotente: si el lote ya está completado/cancelado, sale sin hacer nada.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo se ejecuta por línea de comandos (CLI).\n");
    exit(1);
}

// Sin límite de tiempo: un lote puede tardar varios minutos.
@set_time_limit(0);
ignore_user_abort(true);

$root = dirname(__DIR__);
require_once $root . '/bootstrap.php';

use App\Services\modulos\EnvioLoteSriService;

$opts   = getopt('', ['lote:']);
$idLote = (int) ($opts['lote'] ?? 0);

if ($idLote <= 0) {
    fwrite(STDERR, "Falta el parámetro --lote=ID.\n");
    exit(1);
}

try {
    $service = new EnvioLoteSriService();
    $service->procesarLote($idLote);
    fwrite(STDOUT, "Lote {$idLote} procesado.\n");
    exit(0);
} catch (\Throwable $e) {
    error_log('[procesar_lote_sri] Lote ' . $idLote . ': ' . $e->getMessage());
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
