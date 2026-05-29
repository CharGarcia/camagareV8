<?php

namespace App\controllers;

use App\core\Controller;
use App\repositories\modulos\WhatsappMensajeRepository;
use App\models\WhatsappConfig;

class WhatsappWebhookController extends Controller
{
    private WhatsappMensajeRepository $repository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new WhatsappMensajeRepository();
    }

    /**
     * Endpoint principal para Webhooks de Meta.
     * Soporta tanto GET (verificacin) como POST (eventos).
     */
    public function index(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->verifyWebhook();
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleWebhookEvent();
        } else {
            http_response_code(405);
            echo "Method Not Allowed";
            exit;
        }
    }

    /**
     * Verificación del Webhook.
     * Meta envía hub.mode, hub.challenge y hub.verify_token.
     * Validamos el token contra cualquier empresa configurada (multitenant:
     * una sola App de Meta, múltiples WABA/empresas).
     */
    private function verifyWebhook(): void
    {
        $mode      = $_GET['hub_mode']         ?? '';
        $token     = $_GET['hub_verify_token'] ?? '';
        $challenge = $_GET['hub_challenge']    ?? '';

        if ($mode !== 'subscribe' || empty($token) || empty($challenge)) {
            http_response_code(403);
            echo "Forbidden";
            exit;
        }

        // Validar el token contra todos los webhook_verify_token registrados
        $configModel = new WhatsappConfig();
        if (!$configModel->verificarWebhookToken($token)) {
            http_response_code(403);
            echo "Forbidden: token inválido";
            exit;
        }

        echo $challenge;
        exit;
    }

    /**
     * Manejo de eventos POST
     */
    private function handleWebhookEvent(): void
    {
        $input = file_get_contents('php://input');
        $payload = json_decode($input, true);

        if (!$payload || !isset($payload['object']) || $payload['object'] !== 'whatsapp_business_account') {
            http_response_code(400);
            echo "Invalid payload";
            exit;
        }

        foreach ($payload['entry'] as $entry) {
            if (!isset($entry['changes'])) continue;

            foreach ($entry['changes'] as $change) {
                if ($change['field'] !== 'messages') continue;

                $value = $change['value'];
                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

                if (!$phoneNumberId) continue;

                // Identificar a qu empresa pertenece este webhook
                $idEmpresa = $this->repository->getEmpresaIdByPhoneNumberId($phoneNumberId);
                if (!$idEmpresa) {
                    continue; // No tenemos configurado este nmero
                }

                // Extraer el perfil del cliente (opcional, para Incoming Messages)
                $contactName = '';
                if (isset($value['contacts']) && is_array($value['contacts'])) {
                    $contactName = $value['contacts'][0]['profile']['name'] ?? '';
                }

                // 1. Manejar Updates de Estado (Outbound messages status: sent, delivered, read, failed)
                if (isset($value['statuses']) && is_array($value['statuses'])) {
                    foreach ($value['statuses'] as $status) {
                        $metaMessageId = $status['id'] ?? '';
                        $statusName = $status['status'] ?? '';
                        $errorMsg = null;
                        
                        if (isset($status['errors'])) {
                            $errorMsg = json_encode($status['errors'], JSON_UNESCAPED_UNICODE);
                        }

                        if ($metaMessageId && $statusName) {
                            $this->repository->updateMessageStatus($metaMessageId, $statusName, $errorMsg);
                        }
                    }
                }

                // 2. Manejar Mensajes Entrantes (Incoming messages)
                if (isset($value['messages']) && is_array($value['messages'])) {
                    foreach ($value['messages'] as $message) {
                        $metaMessageId = $message['id'] ?? '';
                        $fromPhone = $message['from'] ?? '';
                        $type = $message['type'] ?? 'text';
                        
                        // Preparar el extracto rapido
                        $extracto = $type;
                        if ($type === 'text') {
                            $extracto = mb_substr($message['text']['body'] ?? '', 0, 50);
                        } else if (in_array($type, ['image', 'document'])) {
                            // Descargar media
                            $mediaId = $message[$type]['id'] ?? '';
                            if ($mediaId) {
                                $whatsappService = new \App\services\WhatsappService();
                                $fileName = $mediaId . ($type === 'image' ? '.jpg' : '.pdf'); // simplificado, idealmente inferir del mime
                                // Guardar en carpeta local
                                $savePath = $_SERVER['DOCUMENT_ROOT'] . '/sistema/public/storage/whatsapp_media/' . $idEmpresa . '/' . $fileName;
                                
                                $result = $whatsappService->downloadMedia($idEmpresa, $mediaId, $savePath);
                                if ($result['success']) {
                                    $message['local_path'] = 'storage/whatsapp_media/' . $idEmpresa . '/' . $fileName;
                                    $extracto = 'Archivo adjunto (' . $type . ')';
                                }
                            }
                        }

                        // Crear o actualizar chat
                        $idChat = $this->repository->getOrCreateChat($idEmpresa, $fromPhone, $contactName, $extracto, true);

                        // Guardar el mensaje entrante
                        $this->repository->saveMessage(
                            $idEmpresa,
                            $idChat,
                            'IN',
                            $fromPhone,
                            $type,
                            $message,
                            $metaMessageId,
                            'received'
                        );
                    }
                }
            }
        }

        // Siempre devolver 200 OK a Meta para que no reintente
        http_response_code(200);
        echo "EVENT_RECEIVED";
        exit;
    }
}
