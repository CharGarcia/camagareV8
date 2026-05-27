<?php

namespace App\controllers\modulos;

use App\core\Controller;
use App\repositories\modulos\WhatsappMensajeRepository;
use App\services\WhatsappService;
use App\models\WhatsappConfig;

class WhatsappChatController extends Controller
{
    private WhatsappMensajeRepository $repository;
    private WhatsappService $whatsappService;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new WhatsappMensajeRepository();
        $this->whatsappService = new WhatsappService();
    }

    public function index(): void
    {
        $idEmpresa = $_SESSION['id_empresa'];
        
        // Verificar si tiene configurado
        $configModel = new WhatsappConfig();
        $config = $configModel->obtenerConfiguracion($idEmpresa);
        $configurado = ($config && !empty($config['access_token']) && !empty($config['phone_number_id']));

        $this->viewWithLayout('layouts.main', 'modulos/whatsapp_chat/index', [
            'titulo' => 'Bandeja de Entrada WhatsApp',
            'configurado' => $configurado
        ]);
    }

    public function getChatsAjax(): void
    {
        error_log("LLAMANDO A GETCHATSAJAX");
        $idEmpresa = $_SESSION['id_empresa'];
        $chats = $this->repository->getChatsList($idEmpresa);
        
        echo json_encode(['ok' => true, 'chats' => $chats]);
    }

    public function countUnreadAjax(): void
    {
        $idEmpresa = $_SESSION['id_empresa'] ?? 0;
        if (!$idEmpresa) {
            echo json_encode(['ok' => false]);
            return;
        }

        $count = $this->repository->countUnreadChats($idEmpresa);
        echo json_encode(['ok' => true, 'count' => $count]);
    }

    public function getMensajesAjax(): void
    {
        $idEmpresa = $_SESSION['id_empresa'];
        $idChat = (int)($_GET['id_chat'] ?? 0);

        if ($idChat <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Chat no vlido']);
            return;
        }

        // Marcar como ledos
        $this->repository->resetUnread($idChat);

        // Obtener mensajes
        $mensajes = $this->repository->getChatMessages($idEmpresa, $idChat);

        // Procesar contenido JSON para enviar fcilmente al frontend
        foreach ($mensajes as &$msg) {
            if (is_string($msg['contenido'])) {
                $msg['contenido'] = json_decode($msg['contenido'], true);
            }
        }

        echo json_encode(['ok' => true, 'mensajes' => $mensajes]);
    }

    public function enviarMensajeAjax(): void
    {
        $idEmpresa = $_SESSION['id_empresa'];
        
        $input = json_decode(file_get_contents('php://input'), true);
        $telefono = $input['telefono'] ?? '';
        $texto = $input['texto'] ?? '';

        if (empty($telefono) || empty(trim($texto))) {
            echo json_encode(['ok' => false, 'error' => 'Telfono y texto son requeridos.']);
            return;
        }

        // Enviar va API de Meta
        $configModel = new WhatsappConfig();
        $config = $configModel->obtenerConfiguracion($idEmpresa);

        if (!$config || empty($config['access_token']) || empty($config['phone_number_id'])) {
            echo json_encode(['ok' => false, 'error' => 'WhatsApp no est configurado.']);
            return;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $telefono,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $texto
            ]
        ];

        $url = "https://graph.facebook.com/v19.0/" . $config['phone_number_id'] . '/messages';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $config['access_token'],
            'Content-Type: application/json'
        ]);

        $responseStr = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($responseStr, true);

        if ($httpCode >= 200 && $httpCode < 300 && isset($response['messages'][0]['id'])) {
            $metaMessageId = $response['messages'][0]['id'];
            
            // Guardar en Base de Datos
            $idChat = $this->repository->getOrCreateChat($idEmpresa, $telefono, '', mb_substr($texto, 0, 50), false);
            
            $msgId = $this->repository->saveMessage(
                $idEmpresa,
                $idChat,
                'OUT',
                $telefono,
                'text',
                $payload,
                $metaMessageId,
                'sent'
            );

            echo json_encode([
                'ok' => true,
                'id_mensaje' => $msgId,
                'meta_message_id' => $metaMessageId,
                'fecha_hora' => date('Y-m-d H:i:s')
            ]);
        } else {
            $errorMsg = $response['error']['message'] ?? 'Error desconocido en Meta';
            echo json_encode(['ok' => false, 'error' => $errorMsg]);
        }
    }

    public function uploadMediaAjax(): void
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idChat = (int) ($_POST['id_chat'] ?? 0);
        $telefono = $_POST['telefono'] ?? '';

        if (empty($idChat) || empty($telefono) || empty($_FILES['file'])) {
            echo json_encode(['ok' => false, 'error' => 'Faltan datos o archivo']);
            return;
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'error' => 'Error al subir el archivo']);
            return;
        }

        // Crear directorio local
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/sistema/public/storage/whatsapp_media/' . $idEmpresa;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '.' . $extension;
        $localPath = $uploadDir . '/' . $fileName;

        if (move_uploaded_file($file['tmp_name'], $localPath)) {
            $whatsappService = new \App\services\WhatsappService();
            $mimeType = mime_content_type($localPath);
            
            // Subir a Meta
            $uploadResult = $whatsappService->uploadMessageMedia($idEmpresa, $localPath, $mimeType);
            
            if ($uploadResult['success']) {
                $mediaId = $uploadResult['media_id'];
                
                // Determinar tipo (image o document)
                $type = (strpos($mimeType, 'image/') === 0) ? 'image' : 'document';
                
                // Enviar el mensaje con el media_id
                $sendResult = $whatsappService->sendMediaMessage($idEmpresa, $telefono, $mediaId, $type);
                
                if ($sendResult['success']) {
                    $metaMessageId = $sendResult['data']['messages'][0]['id'] ?? null;
                    
                    $contenido = [
                        'local_path' => 'storage/whatsapp_media/' . $idEmpresa . '/' . $fileName,
                        'mime_type' => $mimeType,
                        'media_id' => $mediaId
                    ];

                    $msgId = $this->repository->saveMessage(
                        $idEmpresa,
                        $idChat,
                        'OUT',
                        $telefono,
                        $type,
                        $contenido,
                        $metaMessageId,
                        'sent'
                    );

                    echo json_encode([
                        'ok' => true,
                        'id_mensaje' => $msgId,
                        'local_path' => $contenido['local_path'],
                        'type' => $type,
                        'fecha_hora' => date('Y-m-d H:i:s')
                    ]);
                    return;
                } else {
                    echo json_encode(['ok' => false, 'error' => $sendResult['message']]);
                    return;
                }
            } else {
                echo json_encode(['ok' => false, 'error' => $uploadResult['message']]);
                return;
            }
        }
        
        echo json_encode(['ok' => false, 'error' => 'No se pudo guardar el archivo en el servidor']);
    }
}
