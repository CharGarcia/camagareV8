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

// ── Chat de soporte: archivar conversaciones cerradas (FIJO, 1 vez al día) ────
//    Archivar NO borra: solo saca de la bandeja las conversaciones cerradas que
//    ya cumplieron soporte_config.dias_archivar_cerradas. Se autolimita con una
//    marca diaria porque el cron corre cada minuto y esto no tiene por qué.
try {
    $marcaArchivado = sys_get_temp_dir() . '/sistema_soporte_archivado.txt';
    $hoy = date('Y-m-d');

    if ((int) date('H') >= 3 && @file_get_contents($marcaArchivado) !== $hoy) {
        $svcSoporte = new \App\Services\modulos\SoporteChatService(
            new \App\repositories\modulos\SoporteChatRepository(),
            new \App\Rules\modulos\SoporteChatRules(),
            new LogSistemaService(),
        );
        $nArchivadas = $svcSoporte->archivarCerradas();
        file_put_contents($marcaArchivado, $hoy);

        if ($nArchivadas > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] Soporte: {$nArchivadas} conversación(es) archivada(s).\n";
        }
    }

    // Aviso de consultas sin atender: sí corre en CADA tick (el retraso en
    // responder importa por minutos, no por días). El propio servicio evita
    // reenviar mientras no cambie la lista, así que no genera spam.
    $svcAlerta = new \App\Services\modulos\SoporteChatService(
        new \App\repositories\modulos\SoporteChatRepository(),
        new \App\Rules\modulos\SoporteChatRules(),
        new LogSistemaService(),
    );
    $alerta = $svcAlerta->alertarSinAtender();
    if (!empty($alerta['enviado'])) {
        $canales = [];
        if (!empty($alerta['correo'])) {
            $canales[] = 'correo';
        }
        if (!empty($alerta['whatsapp'])) {
            $canales[] = "WhatsApp ({$alerta['whatsapp']} número/s)";
        }
        echo "[" . date('Y-m-d H:i:s') . "] Soporte: aviso enviado por {$alerta['conversaciones']} consulta(s) sin atender"
            . ($canales !== [] ? ' — ' . implode(' + ', $canales) : '') . ".\n";
    }
} catch (\Throwable $e) {
    // Si el módulo de soporte aún no está desplegado, el resto del cron sigue.
    echo "[" . date('Y-m-d H:i:s') . "] Error archivando conversaciones de soporte: " . $e->getMessage() . "\n";
}

// ── Videollamadas: recordatorio de las reuniones que están por empezar ───────
//    Corre en CADA tick a propósito: el aviso debe llegar minutos antes, no una
//    vez al día. No necesita marca de "ya corrí": la consulta descarta las
//    reuniones que ya tienen su recordatorio en la bitácora, así que cada una
//    recibe exactamente uno.
try {
    $svcVc = new \App\Services\modulos\VideollamadaService(
        new \App\repositories\modulos\VideollamadaRepository(),
        new \App\Rules\modulos\VideollamadaRules(),
        new LogSistemaService(),
    );
    $rec = $svcVc->enviarRecordatoriosPendientes(15);
    if ($rec['salas'] > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Videollamadas: {$rec['salas']} reunión(es) recordada(s), {$rec['correos']} correo(s).\n";
    }

    // Cierre de reuniones abandonadas. También en CADA tick: una sala colgada
    // en 'en_curso' bloquea su eliminación y aparece como reunión activa, así
    // que conviene limpiarla pronto. No necesita marca de "ya corrí": la
    // condición es el latido de cada sala, y una vez finalizada deja de
    // aparecer en la cola.
    $inact = $svcVc->finalizarInactivas(10);
    if ($inact['salas'] > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Videollamadas: {$inact['salas']} reunión(es) finalizada(s) por inactividad, "
            . "{$inact['participantes']} participante(s) cerrado(s).\n";
    }
} catch (\Throwable $e) {
    // Si el módulo aún no está desplegado (faltan las tablas), el resto sigue.
    echo "[" . date('Y-m-d H:i:s') . "] Error en recordatorios de videollamadas: " . $e->getMessage() . "\n";
}

// ── SRI: reintentar comprobantes pendientes de resolución ────────────────────
//    Corre en CADA tick a propósito: la elegibilidad la da la antigüedad de cada
//    comprobante (mínimo 5 min desde el último intento), no una marca de "ya
//    corrí". Es seguro e idempotente: SriEnvioService nunca reenvía una clave
//    de acceso que el SRI ya tiene en cola, solo vuelve a consultar su estado.
try {
    $svcSriPend = new \App\Services\Sri\SriReintentosPendientesService();
    $resSriPend = $svcSriPend->procesar();
    if ($resSriPend['reintentados'] > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] SRI pendientes: {$resSriPend['reintentados']} reintentado(s), "
            . "{$resSriPend['resueltos']} resuelto(s), {$resSriPend['avisos']} aviso(s) enviado(s).\n";
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error en reintentos de comprobantes SRI: " . $e->getMessage() . "\n";
}

// ── Firma electrónica: aviso por correo de caducidad (FIJO, 1 vez al día) ─────
//    Complementa al ícono del navbar: avisa al correo registrado en la ficha de
//    la empresa (empresas.mail) cuando la firma activa caduca mañana (o acaba de
//    caducar y aún no se avisó). Se autolimita a UNA revisión diaria desde las
//    06:00 y a UN correo por firma (la marca vive en log_sistema).
try {
    $svcFirma = new \App\Services\FirmaCaducidadAvisoService();
    $resFirma = $svcFirma->ejecutarSiCorresponde();
    if (!empty($resFirma['ejecutado']) && $resFirma['firmas'] > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Firma electrónica: {$resFirma['firmas']} firma(s) por caducar, "
            . "{$resFirma['correos']} correo(s) enviado(s), {$resFirma['sin_correo']} sin correo, "
            . "{$resFirma['fallidos']} fallido(s).\n";
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error en aviso de caducidad de firma electrónica: " . $e->getMessage() . "\n";
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
