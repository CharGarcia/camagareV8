<?php
declare(strict_types=1);

namespace App\Services\modulos\Handlers;

use App\Services\WhatsappService;

class WhatsappHandler extends BaseHandler
{
    public function ejecutar(int $idEmpresa, ?int $idEstablecimiento, int $idUsuario, array $parametros): array
    {
        return match ($this->accion) {
            'aviso_mensajes_no_leidos' => $this->avisarMensajesNoLeidos($idEmpresa),
            default => throw new \RuntimeException("Acción '{$this->accion}' no implementada en WhatsappHandler."),
        };
    }

    // ── Aviso de mensajes no leídos ───────────────────────────────────────────

    private function avisarMensajesNoLeidos(int $idEmpresa): array
    {
        // 1. Leer configuración de avisos para esta empresa
        $stmt = $this->db->prepare("
            SELECT activo, umbral_minutos, cooldown_minutos, plantilla_nombre, plantilla_idioma
            FROM   whatsapp_aviso_config
            WHERE  id_empresa = ? AND eliminado = FALSE
            LIMIT  1
        ");
        $stmt->execute([$idEmpresa]);
        $cfg = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$cfg || !($cfg['activo'] === true || $cfg['activo'] === 't')) {
            return ['registros' => 0, 'mensaje' => 'Avisos desactivados en la configuración de WhatsApp.'];
        }

        $umbral   = max(1, (int) $cfg['umbral_minutos']);
        $cooldown = max(1, (int) $cfg['cooldown_minutos']);

        // 2. Verificar cooldown
        $stmtLog = $this->db->prepare("
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
                return [
                    'registros' => 0,
                    'mensaje'   => 'Cooldown activo — último aviso hace ' . round($minDesde) . ' min (mínimo ' . $cooldown . ' min).',
                ];
            }
        }

        // 3. Buscar chats con mensajes sin leer más antiguos que el umbral
        $stmtChats = $this->db->prepare("
            SELECT id, telefono_cliente, nombre_cliente, mensajes_sin_leer, updated_at
            FROM   whatsapp_chats
            WHERE  id_empresa        = ?
               AND mensajes_sin_leer > 0
               AND updated_at        <= NOW() - (? * INTERVAL '1 minute')
               AND eliminado         = FALSE
            ORDER  BY updated_at ASC
        ");
        $stmtChats->execute([$idEmpresa, $umbral]);
        $chatsPendientes = $stmtChats->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($chatsPendientes)) {
            return ['registros' => 0, 'mensaje' => 'Sin chats pendientes de respuesta.'];
        }

        $totalChats = count($chatsPendientes);

        // 4. Obtener números a notificar
        $stmtNums = $this->db->prepare("
            SELECT telefono, nombre
            FROM   whatsapp_aviso_numeros
            WHERE  id_empresa = ? AND activo = TRUE AND eliminado = FALSE
            ORDER  BY id ASC
        ");
        $stmtNums->execute([$idEmpresa]);
        $numeros = $stmtNums->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($numeros)) {
            return ['registros' => 0, 'mensaje' => 'No hay números configurados para recibir los avisos.'];
        }

        // 5. Construir resumen de chats (máx. 10)
        $lineas = [];
        foreach (array_slice($chatsPendientes, 0, 10) as $ch) {
            $quien    = $ch['nombre_cliente'] ?: $ch['telefono_cliente'];
            $msgs     = (int) $ch['mensajes_sin_leer'];
            $lineas[] = "• {$quien} ({$msgs} msg)";
        }
        if ($totalChats > 10) {
            $lineas[] = '• ... y ' . ($totalChats - 10) . ' más';
        }

        $minAntiguo = (int) round((time() - strtotime($chatsPendientes[0]['updated_at'])) / 60);

        // 6. Enviar a cada número
        $waService  = new WhatsappService();
        $enviados   = 0;
        $detalleLog = [];

        foreach ($numeros as $num) {
            $telefono = preg_replace('/[^0-9]/', '', $num['telefono']);

            if (!empty($cfg['plantilla_nombre'])) {
                // Modo plantilla: {{1}} = total chats, {{2}} = umbral
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
                // Modo texto libre
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
        }

        // 7. Registrar en log de avisos
        $this->db->prepare("
            INSERT INTO whatsapp_aviso_log
                   (id_empresa, chats_pendientes, numeros_notificados, detalle)
            VALUES (?, ?, ?, ?)
        ")->execute([
            $idEmpresa,
            $totalChats,
            $enviados,
            json_encode($detalleLog, JSON_UNESCAPED_UNICODE),
        ]);

        return [
            'registros' => $enviados,
            'mensaje'   => "{$totalChats} chat(s) pendiente(s). Aviso enviado a {$enviados}/" . count($numeros) . " número(s).",
        ];
    }
}
