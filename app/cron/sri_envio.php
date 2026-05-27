<?php

/**
 * CRON: Envío automático de comprobantes electrónicos al SRI
 * ============================================================
 * Procesa facturas en estado_sri = 'pendiente' o 'en_procesamiento'
 * y las envía/consulta en el SRI.
 *
 * Configuración recomendada (crontab):
 *   # Cada 5 minutos en horario laboral
 *   *\/5 8-20 * * 1-5  php /ruta/al/proyecto/app/cron/sri_envio.php >> /var/log/sri_envio.log 2>&1
 *
 *   # En producción: cada minuto para envío más rápido
 *   * * * * *  php /ruta/al/proyecto/app/cron/sri_envio.php >> /var/log/sri_envio.log 2>&1
 *
 * En Windows (Task Scheduler):
 *   Programa : C:\xampp\php\php.exe
 *   Argumentos: C:\xampp\htdocs\sistema\app\cron\sri_envio.php
 *   Trigger  : Cada 5 minutos
 *
 * Variables de entorno opcionales:
 *   SRI_CRON_LOTE  = número máximo de facturas a procesar por ejecución (default: 20)
 *   SRI_CRON_DEBUG = 1 para salida detallada
 */

declare(strict_types=1);

// ── Bootstrap ─────────────────────────────────────────────────────────────────
define('MVC_ROOT', dirname(__DIR__, 2));
define('MVC_APP',  MVC_ROOT . '/app');

if (!file_exists(MVC_ROOT . '/bootstrap.php')) {
    fwrite(STDERR, "[SRI CRON] No se encontró bootstrap.php en " . MVC_ROOT . "\n");
    exit(1);
}
require_once MVC_ROOT . '/bootstrap.php';

use App\core\Database;
use App\Services\Sri\SriEnvioService;

$debug     = (bool)getenv('SRI_CRON_DEBUG');
$loteTam   = max(1, min(50, (int)(getenv('SRI_CRON_LOTE') ?: 20)));
$inicio    = microtime(true);
$procesados = 0;
$errores    = 0;

$log = function (string $nivel, string $msg) use ($debug): void {
    $ts = date('Y-m-d H:i:s');
    if ($nivel !== 'DEBUG' || $debug) {
        echo "[$ts][$nivel] $msg\n";
    }
};

$log('INFO', "=== SRI CRON iniciado (lote=$loteTam) ===");

// ── Obtener comprobantes pendientes ────────────────────────────────────────────
try {
    $db = Database::getConnection();
} catch (\Throwable $e) {
    $log('ERROR', "No se pudo conectar a la BD: " . $e->getMessage());
    exit(1);
}

// Verificar que las columnas existan
$colExists = $db->query(
    "SELECT 1 FROM information_schema.columns
     WHERE table_name = 'ventas_cabecera' AND column_name = 'estado_sri' AND table_schema = 'public'"
)->fetchColumn();

if (!$colExists) {
    $log('WARN', "Columna estado_sri no existe en ventas_cabecera. Ejecute la migración sri_firma_electronica.sql");
    exit(0);
}

// Facturas pendientes de envío o en procesamiento (el SRI aún no respondió)
$pendientes = $db->query(
    "SELECT id, id_empresa, clave_acceso, estado_sri, tipo_ambiente
     FROM ventas_cabecera
     WHERE eliminado = FALSE
       AND estado IN ('borrador', 'autorizado')
       AND estado_sri IN ('pendiente', 'en_procesamiento', 'error')
     ORDER BY created_at ASC
     LIMIT $loteTam
     FOR UPDATE SKIP LOCKED"
)->fetchAll(\PDO::FETCH_ASSOC);

if (empty($pendientes)) {
    $log('INFO', "Sin comprobantes pendientes.");
    exit(0);
}

$log('INFO', "Procesando " . count($pendientes) . " comprobante(s)...");

$envioService = new SriEnvioService(
    esperaInicial:       3,
    maxIntentos:         4,
    intervaloReintentos: 3
);

// Usuario del sistema para auditoría (0 = proceso automático)
$idUsuarioSistema = 0;

foreach ($pendientes as $venta) {
    $idVenta   = (int)$venta['id'];
    $idEmpresa = (int)$venta['id_empresa'];
    $clave     = $venta['clave_acceso'] ?? '(sin clave)';

    $log('INFO', "Procesando factura #$idVenta (empresa=$idEmpresa, clave=...{$clave})");

    try {
        // Marcar como "enviando" para evitar procesamiento doble en ejecuciones paralelas
        $db->prepare(
            "UPDATE ventas_cabecera SET estado_sri = 'enviando', fecha_envio_sri = NOW() WHERE id = ?"
        )->execute([$idVenta]);

        $resultado = $envioService->enviarFacturaVenta($idVenta, $idEmpresa, $idUsuarioSistema);

        $estado = $resultado['estado'] ?? 'error';
        $log('INFO', "  → Estado: $estado | " . ($resultado['mensaje'] ?? ''));

        if (!empty($resultado['errores'])) {
            foreach ($resultado['errores'] as $err) {
                $log('WARN', "    [" . ($err['tipo'] ?? 'WARN') . "] " . ($err['mensaje'] ?? '') . " " . ($err['info'] ?? ''));
            }
        }

        $procesados++;

    } catch (\Throwable $e) {
        $log('ERROR', "  → Error en factura #$idVenta: " . $e->getMessage());
        // Marcar como error para no reintentar indefinidamente en este ciclo
        try {
            $db->prepare(
                "UPDATE ventas_cabecera SET estado_sri = 'error', mensajes_sri = ?, updated_at = NOW() WHERE id = ?"
            )->execute([json_encode([['tipo' => 'ERROR', 'mensaje' => $e->getMessage()]]), $idVenta]);
        } catch (\Throwable) {}
        $errores++;
    }
}

$duracion = round(microtime(true) - $inicio, 2);
$log('INFO', "=== Finalizado: $procesados procesados, $errores errores | {$duracion}s ===");
exit(0);
