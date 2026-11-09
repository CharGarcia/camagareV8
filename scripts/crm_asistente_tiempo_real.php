<?php
/**
 * Worker CLI — Asistente virtual de Instagram "en vivo".
 *
 * Uso:
 *   php scripts/crm_asistente_tiempo_real.php
 *
 * Lo lanza el webhook de Instagram (InstagramWebhookController) en segundo plano
 * cuando un mensaje deja una conversación en la cola del asistente: responde apenas
 * vence la espera para agrupar (CrmBotRules::SEGUNDOS_AGRUPAR), sin esperar al cron
 * de cada minuto, que sigue de respaldo. Un solo worker a la vez (candado de
 * archivo): si ya hay uno trabajando, sale sin hacer nada. La cola se toma con
 * SKIP LOCKED, así que nunca responde dos veces lo mismo aunque corra junto al cron.
 * Diseño: docs/diseno/crm-redes-fase2.md (asistente) — respuesta en vivo, 09-11-2026.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo se ejecuta por línea de comandos (CLI).\n");
    exit(1);
}

@set_time_limit(0);   // el tope lo pone atenderEnVivo() entre turnos: cortar a mitad de una respuesta la perdería
ignore_user_abort(true);

require_once dirname(__DIR__) . '/bootstrap.php';

$candado = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sistema_crm_asistente_vivo.lock';
$svc = \App\Services\modulos\CrmBotService::crear();

try {
    // Hasta 3 rondas: si al soltar el candado llegó otro mensaje (y su lanzador salió
    // porque el candado estaba tomado), se vuelve a tomar en vez de dejarlo al cron.
    for ($ronda = 0; $ronda < 3; $ronda++) {
        $fh = @fopen($candado, 'c');
        if ($fh === false || !flock($fh, LOCK_EX | LOCK_NB)) {
            exit(0);   // otro worker está trabajando
        }
        $r = $svc->atenderEnVivo(90, 20.0);
        flock($fh, LOCK_UN);
        fclose($fh);
        if ($r['respondidas'] || $r['traspasos'] || $r['errores']) {
            fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] Asistente en vivo: {$r['respondidas']} respuesta(s), {$r['traspasos']} pasada(s) a una persona, {$r['errores']} con error.\n");
        }
        if (!$svc->hayEnCola(20.0)) {
            break;
        }
    }
    exit(0);
} catch (\Throwable $e) {
    error_log('[crm_asistente_tiempo_real] ' . $e->getMessage());
    \App\Services\ErrorLogService::registrar($e, ['ruta' => 'scripts/crm_asistente_tiempo_real.php', 'accion' => 'atenderEnVivo']);
    exit(1);
}
