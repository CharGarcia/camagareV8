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
                        $fromPhone     = $message['from'] ?? '';
                        $type          = $message['type'] ?? 'text';

                        // Extracto legible para la lista de chats
                        $extractosLabels = [
                            'image'    => '📷 Imagen',
                            'document' => '📄 Documento',
                            'audio'    => '🎵 Audio',
                            'video'    => '🎬 Video',
                            'sticker'  => '🖼️ Sticker',
                            'location' => '📍 Ubicación',
                            'contacts' => '👤 Contacto',
                        ];
                        $extracto = $extractosLabels[$type] ?? $type;

                        if ($type === 'text') {
                            $extracto = mb_substr($message['text']['body'] ?? '', 0, 80);
                        }

                        // Tipos de mensajes que incluyen media descargable
                        $tiposMedia = ['image', 'document', 'audio', 'video', 'sticker'];

                        if (in_array($type, $tiposMedia)) {
                            $mediaId  = $message[$type]['id'] ?? '';
                            $mimeType = $message[$type]['mime_type'] ?? '';

                            if ($mediaId) {
                                $whatsappService = new \App\services\WhatsappService();
                                $ext      = $this->getExtensionFromMime($mimeType, $type);
                                $fileName = $mediaId . '.' . $ext;
                                $saveDir  = MVC_ROOT . '/public/storage/whatsapp_media/' . $idEmpresa;
                                $savePath = $saveDir . '/' . $fileName;

                                if (!is_dir($saveDir)) {
                                    mkdir($saveDir, 0755, true);
                                }

                                $result = $whatsappService->downloadMedia($idEmpresa, $mediaId, $savePath);
                                if ($result['success']) {
                                    $message['local_path'] = 'storage/whatsapp_media/' . $idEmpresa . '/' . $fileName;
                                    $message['mime_type']  = $result['mime_type'] ?? $mimeType;
                                }
                            }
                        }

                        // Crear o actualizar chat
                        $idChat = $this->repository->getOrCreateChat(
                            $idEmpresa, $fromPhone, $contactName, $extracto, true
                        );

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

    /**
     * Determina la extensión de archivo a partir del MIME type.
     * Utilizado para guardar los archivos multimedia recibidos con la extensión correcta.
     */
    private function getExtensionFromMime(string $mimeType, string $fallbackType): string
    {
        // Quitar parámetros del mime (ej: "audio/ogg; codecs=opus" → "audio/ogg")
        $mimeBase = strtolower(trim(explode(';', $mimeType)[0]));

        $map = [
            // Imágenes
            'image/jpeg'          => 'jpg',
            'image/jpg'           => 'jpg',
            'image/png'           => 'png',
            'image/gif'           => 'gif',
            'image/webp'          => 'webp',
            'image/bmp'           => 'bmp',
            // Audio
            'audio/ogg'           => 'ogg',
            'audio/mpeg'          => 'mp3',
            'audio/mp4'           => 'm4a',
            'audio/aac'           => 'aac',
            'audio/amr'           => 'amr',
            'audio/wav'           => 'wav',
            'audio/x-wav'         => 'wav',
            // Video
            'video/mp4'           => 'mp4',
            'video/3gpp'          => '3gp',
            'video/quicktime'     => 'mov',
            'video/webm'          => 'webm',
            // Documentos
            'application/pdf'                                                              => 'pdf',
            'application/msword'                                                           => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'     => 'docx',
            'application/vnd.ms-excel'                                                     => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'           => 'xlsx',
            'application/vnd.ms-powerpoint'                                                => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation'   => 'pptx',
            'text/plain'                                                                   => 'txt',
            'application/zip'                                                              => 'zip',
        ];

        if (isset($map[$mimeBase])) {
            return $map[$mimeBase];
        }

        // Fallback por tipo genérico
        $defaults = [
            'image'    => 'jpg',
            'audio'    => 'ogg',
            'video'    => 'mp4',
            'document' => 'pdf',
            'sticker'  => 'webp',
        ];

        return $defaults[$fallbackType] ?? 'bin';
    }
}
