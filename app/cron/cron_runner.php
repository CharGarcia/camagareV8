<?php
/**
 * Cron Runner — punto de entrada del cron del servidor.
 *
 * Configurar en el servidor (cPanel, crontab):
 *   * * * * * php /ruta/sistema/app/cron/cron_runner.php >> /ruta/logs/cron.log 2>&1
 *
 * Solo debe correr este archivo. Toda la lógica de qué ejecutar y cuándo
 * está en la tabla `automatizaciones` de cada empresa.
 */

define('ROOT_PATH', __DIR__ . '/../..');
require ROOT_PATH . '/bootstrap.php';

// Sin límite de tiempo — los scripts CLI deben correr hasta completarse
set_time_limit(0);
ini_set('memory_limit', '256M');

use App\repositories\modulos\AutomatizacionesRepository;
use App\Services\modulos\AutomatizacionesService;
use App\Rules\modulos\AutomatizacionesRules;
use App\Services\LogSistemaService;

// ── Control de concurrencia (evita ejecuciones simultáneas) ──────────────────
$lockFile = sys_get_temp_dir() . '/sistema_cron.lock';

if (file_exists($lockFile)) {
    $pid = (int)file_get_contents($lockFile);
    // En Windows no hay función posix_kill; se asume que si el lock existe es válido
    if (PHP_OS_FAMILY !== 'Windows' && $pid > 0 && posix_kill($pid, 0)) {
        echo "[" . date('Y-m-d H:i:s') . "] Cron ya en ejecución (PID {$pid}). Saliendo.\n";
        exit(0);
    }
    // Lock huérfano, continuar
}

file_put_contents($lockFile, getmypid());

// ── Tareas (FIJO, no configurable) ────────────────────────────────────────────
//    1) En CADA tick: marcar vencidas por fecha (barato; mantiene el estado al día).
//    2) Recordatorio por correo a responsables: se autolimita a UN envío/día (06:00).
try {
    $svcTareas = new \App\Services\TareaRecordatorioService();

    $nVenc = $svcTareas->marcarVencidas();
    if ($nVenc > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Tareas marcadas vencidas: {$nVenc}.\n";
    }

    $rec = $svcTareas->ejecutarSiCorresponde();
    if (!empty($rec['ejecutado'])) {
        echo "[" . date('Y-m-d H:i:s') . "] Recordatorio tareas: {$rec['correos']} correo(s), {$rec['tareas']} tarea(s).\n";
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error en tareas (vencidas/recordatorio): " . $e->getMessage() . "\n";
}

// ── Ejecutar ──────────────────────────────────────────────────────────────────
try {
    $repository = new AutomatizacionesRepository();
    $rules      = new AutomatizacionesRules();
    $logService = new LogSistemaService();
    $service    = new AutomatizacionesService($repository, $rules, $logService);

    $pendientes = $repository->getPendientes();

    if (empty($pendientes)) {
        unlink($lockFile);
        exit(0);
    }

    $count = count($pendientes);
    echo "[" . date('Y-m-d H:i:s') . "] Iniciando cron — {$count} tareas pendientes.\n";

    foreach ($pendientes as $tarea) {
        $inicio = microtime(true);
        echo "[" . date('Y-m-d H:i:s') . "] Ejecutando: [{$tarea['modulo']}:{$tarea['accion']}] \"{$tarea['nombre']}\" (ID {$tarea['id']}, empresa {$tarea['id_empresa']})... ";

        try {
            $resultado = $service->ejecutarTarea($tarea, 'cron');
            $ms = round((microtime(true) - $inicio) * 1000);
            echo "OK ({$resultado['resultado']}, {$resultado['registros']} reg., {$ms}ms)\n";
        } catch (\Throwable $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Cron finalizado.\n";
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error fatal en cron: " . $e->getMessage() . "\n";
} finally {
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}
