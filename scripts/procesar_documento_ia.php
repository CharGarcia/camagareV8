<?php
/**
 * Worker CLI — Extrae texto e indexa (chunking) un documento PDF de IA Soporte.
 *
 * Uso:
 *   php scripts/procesar_documento_ia.php --documento=123
 *
 * Lo lanza automáticamente IaSoporteController::documentoSubir() de forma
 * desligada del request (Windows: start /B, Linux: nohup ... &). También puede
 * ejecutarse a mano como respaldo si un documento quedó en 'pendiente'/'error'.
 * Es idempotente: si el documento ya está 'listo', sale sin hacer nada.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo se ejecuta por línea de comandos (CLI).\n");
    exit(1);
}

@set_time_limit(0);
ignore_user_abort(true);

$root = dirname(__DIR__);
require_once $root . '/bootstrap.php';

use App\Services\modulos\IaDocumentoProcesadorService;

$opts        = getopt('', ['documento:']);
$idDocumento = (int) ($opts['documento'] ?? 0);

if ($idDocumento <= 0) {
    fwrite(STDERR, "Falta el parámetro --documento=ID.\n");
    exit(1);
}

try {
    $service = new IaDocumentoProcesadorService();
    $service->procesar($idDocumento);
    fwrite(STDOUT, "Documento {$idDocumento} procesado.\n");
    exit(0);
} catch (\Throwable $e) {
    error_log('[procesar_documento_ia] Documento ' . $idDocumento . ': ' . $e->getMessage());
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
