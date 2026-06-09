<?php
/**
 * whatsapp_avisos.php — Envía notificaciones de mensajes WhatsApp sin leer.
 *
 * Lógica:
 *  1. Lee las configuraciones activas de la tabla whatsapp_aviso_config.
 *  2. Por cada empresa, busca chats con mensajes sin leer desde hace más de
 *     `umbral_minutos` minutos.
 *  3. Verifica que no se haya enviado un aviso dentro de `cooldown_minutos`.
 *  4. Si hay chats pendientes, envía el aviso a todos los números configurados.
 *  5. Registra el resultado en whatsapp_aviso_log.
 *
 * Configurar en crontab (cada 5 minutos):
 *   *\/5 * * * * php /ruta/sistema/app/cron/whatsapp_avisos.php >> /ruta/logs/wa_avisos.log 2>&1
 *
 * En Windows (Task Scheduler):
 *   C:\xampp\php\php.exe C:\xampp\htdocs\sistema\app\cron\whatsapp_avisos.php
 */

define('ROOT_PATH', __DIR__ . '/../..');
require ROOT_PATH . '/bootstrap.php';

set_time_limit(0);
ini_set('memory_limit', '128M');

use App\core\Database;
use App\services\WhatsappService;

// ── Control de concurrencia ───────────────────────────────────────────────────
$lockFile = sys_get_temp_dir() . '/wa_avisos.lock';

if (file_exists($lockFile)) {
    $ts = (int) file_get_contents($lockFile);
    // Si el lock tiene más de 10 min es huérfano; lo ignoramos
    if ((time() - $ts) < 600) {
        echo '[' . date('Y-m-d H:i:s') . "] Aviso cron ya en ejecución. Saliendo.\n";
        exit(0);
    }
}
file_put_contents($lockFile, time());

// ── Bootstrap ─────────────────────────────────────────────────────────────────
$db        = Database::getConnection();
$waService = new WhatsappService();

echo '[' . date('Y-m-d H:i:s') . "] === Iniciando revisión de avisos WhatsApp ===\n";

try {
    // 1. Obtener todas las configs activas con datos de la cuenta WhatsApp
    $stmtCfg = $db->query("
        SELECT wac.*
        FROM   whatsapp_aviso_config wac
        INNER JOIN empresa_whatsapp_config ewc
               ON  ewc.id_empresa = wac.id_empresa
               AND ewc.eliminado  = FALSE
               AND ewc.status     = TRUE
        WHERE  wac.activo    = TRUE
           AND wac.eliminado = FALSE
    ");
    $configs = $stmtCfg->fetchAll(PDO::FETCH_ASSOC);

    if (empty($configs)) {
        echo '[' . date('Y-m-d H:i:s') . "] Sin configuraciones activas. Fin.\n";
        exit(0);
    }

    foreach ($configs as $cfg) {
        $idEmpresa = (int) $cfg['id_empresa'];
        $umbral    = max(1, (int) $cfg['umbral_minutos']);
        $cooldown  = max(1, (int) $cfg['cooldown_minutos']);

        echo '[' . date('Y-m-d H:i:s') . "] Empresa {$idEmpresa} (umbral {$umbral} min | cooldown {$cooldown} min)... ";

        // 2. Verificar cooldown
        $stmtLog = $db->prepare("
            SELECT fecha_envio
            FROM   whatsapp_aviso_log
            WHERE  id_empresa = ?
            ORDER  BY fecha_envio DESC
            LIMIT  1
        ");
        $stmtLog->execute([$idEmpresa]);
        $ultimoEnvio = $stmtLog->fetchColumn();

        if ($ultimoEnvio) {
            $minDesde = (time() - strtotime($ultimoEnvio)) / 60;
            if ($minDesde < $cooldown) {
                echo 'cooldown activo (último aviso hace ' . round($minDesde) . " min).\n";
                continue;
            }
        }

        // 3. Buscar chats con mensajes sin leer más antiguos que el umbral
        $stmtChats = $db->prepare("
            SELECT id, telefono_cliente, nombre_cliente, mensajes_sin_leer, updated_at
            FROM   whatsapp_chats
            WHERE  id_empresa        = ?
               AND mensajes_sin_leer > 0
               AND updated_at        <= NOW() - (? * INTERVAL '1 minute')
               AND eliminado         = FALSE
            ORDER  BY updated_at ASC
        ");
        $stmtChats->execute([$idEmpresa, $umbral]);
        $chatsPendientes = $stmtChats->fetchAll(PDO::FETCH_ASSOC);

        if (empty($chatsPendientes)) {
            echo "sin chats pendientes.\n";
            continue;
        }

        $totalChats = count($chatsPendientes);
        echo "{$totalChats} chat(s) pendiente(s).\n";

        // 4. Obtener números a notificar
        $stmtNums = $db->prepare("
            SELECT telefono, nombre
            FROM   whatsapp_aviso_numeros
            WHERE  id_empresa = ?
               AND activo     = TRUE
               AND eliminado  = FALSE
            ORDER  BY id ASC
        ");
        $stmtNums->execute([$idEmpresa]);
        $numeros = $stmtNums->fetchAll(PDO::FETCH_ASSOC);

        if (empty($numeros)) {
            echo "  → Sin números configurados para notificar. Omitiendo.\n";
            continue;
        }

        // 5. Construir el resumen de chats (máx. 10 en el mensaje)
        $lineas = [];
        foreach (array_slice($chatsPendientes, 0, 10) as $ch) {
            $quien  = $ch['nombre_cliente'] ?: $ch['telefono_cliente'];
            $msgs   = (int) $ch['mensajes_sin_leer'];
            $lineas[] = "• {$quien} ({$msgs} msg)";
        }
        if ($totalChats > 10) {
            $lineas[] = '• ... y ' . ($totalChats - 10) . ' más';
        }

        // Antigüedad del chat más viejo
        $minAntiguo = (int) round((time() - strtotime($chatsPendientes[0]['updated_at'])) / 60);

        // 6. Enviar a cada número
        $enviados   = 0;
        $detalleLog = [];

        foreach ($numeros as $num) {
            $telefono = preg_replace('/[^0-9]/', '', $num['telefono']);

            if (!empty($cfg['plantilla_nombre'])) {
                // ── Modo plantilla ───────────────────────────────────────────
                $components = [[
                    'type'       => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => (string) $totalChats],
                        ['type' => 'text', 'text' => (string) $umbral],
                    ],
                ]];
                $result = $waService->sendTemplateMessage(
                    $idEmpresa,
                    $telefono,
                    $cfg['plantilla_nombre'],
                    $cfg['plantilla_idioma'] ?? 'es',
                    $components
                );
            } else {
                // ── Modo texto libre ─────────────────────────────────────────
                $texto  = "⚠️ *Avisos de WhatsApp pendientes*\n\n";
                $texto .= "Tienes *{$totalChats}* chat(s) con mensajes sin leer ";
                $texto .= "hace más de {$minAntiguo} minutos:\n\n";
                $texto .= implode("\n", $lineas);
                $result = $waService->sendTextMessage($idEmpresa, $telefono, $texto);
            }

            $ok = $result['success'] ?? false;
            if ($ok) {
                $enviados++;
            }

            $detalleLog[] = [
                'telefono' => $telefono,
                'nombre'   => $num['nombre'] ?? '',
                'ok'       => $ok,
                'error'    => $ok ? null : ($result['message'] ?? 'Error desconocido'),
            ];

            $status = $ok ? 'OK' : 'ERROR: ' . ($result['message'] ?? '');
            echo "  → {$telefono}" . ($num['nombre'] ? " ({$num['nombre']})" : '') . ": {$status}\n";
        }

        // 7. Registrar en log
        $db->prepare("
            INSERT INTO whatsapp_aviso_log
                   (id_empresa, chats_pendientes, numeros_notificados, detalle)
            VALUES (?, ?, ?, ?)
        ")->execute([
            $idEmpresa,
            $totalChats,
            $enviados,
            json_encode($detalleLog, JSON_UNESCAPED_UNICODE),
        ]);

        echo "  → Aviso enviado a {$enviados}/" . count($numeros) . " número(s). Registrado en log.\n";
    }

} catch (\Throwable $e) {
    echo '[' . date('Y-m-d H:i:s') . "] Error fatal: " . $e->getMessage() . "\n";
} finally {
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}

echo '[' . date('Y-m-d H:i:s') . "] === Finalizado ===\n";
