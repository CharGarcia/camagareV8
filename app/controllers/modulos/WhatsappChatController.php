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
        $idChat    = (int)($_GET['id_chat'] ?? 0);

        if ($idChat <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Chat no válido']);
            return;
        }

        // Marcar como leídos
        $this->repository->resetUnread($idChat);

        // Obtener mensajes
        $mensajes = $this->repository->getChatMessages($idEmpresa, $idChat);

        // Decodificar contenido JSON
        foreach ($mensajes as &$msg) {
            if (is_string($msg['contenido'])) {
                $msg['contenido'] = json_decode($msg['contenido'], true);
            }
        }
        unset($msg);

        // Enriquecer mensajes de tipo "template" sin template_text
        $mensajes = $this->enriquecerMensajesPlantilla($mensajes, $idEmpresa);

        echo json_encode(['ok' => true, 'mensajes' => $mensajes]);
    }

    /**
     * Para cada mensaje de tipo "template" que no tenga template_text,
     * reconstruye el contenido completo (header, body con variables,
     * footer, botones) consultando la tabla whatsapp_plantillas.
     */
    private function enriquecerMensajesPlantilla(array $mensajes, int $idEmpresa): array
    {
        // Caché local: evita múltiples consultas por la misma plantilla
        $plantillasCache = [];

        foreach ($mensajes as &$msg) {
            if ($msg['tipo_mensaje'] !== 'template') {
                continue;
            }

            $c = $msg['contenido'] ?? [];

            // Si ya tiene template_text con valor, no hay nada que hacer
            if (!empty($c['template_text'])) {
                continue;
            }

            // ── Detectar nombre de plantilla según el formato del contenido ──
            // Formato 1 (campaña): { "template": "nombre", "variables": [...] }
            // Formato 2 (API payload): { "template": { "name": "nombre", "components": [...] } }
            $templateName = '';
            $apiComponents = [];

            if (is_string($c['template'] ?? null) && !empty($c['template'])) {
                $templateName = $c['template'];
            } elseif (is_array($c['template'] ?? null)) {
                $templateName  = $c['template']['name'] ?? '';
                $apiComponents = $c['template']['components'] ?? [];
            }

            if (empty($templateName)) {
                continue;
            }

            // ── Cargar componentes de la plantilla desde la BD (con caché) ──
            if (!array_key_exists($templateName, $plantillasCache)) {
                $stmt = $this->db->prepare(
                    "SELECT componentes FROM whatsapp_plantillas
                     WHERE id_empresa = ? AND nombre = ? AND eliminado = false
                     ORDER BY id DESC
                     LIMIT 1"
                );
                $stmt->execute([$idEmpresa, $templateName]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && !empty($row['componentes'])) {
                    $plantillasCache[$templateName] = json_decode($row['componentes'], true) ?? [];
                } else {
                    $plantillasCache[$templateName] = null; // Plantilla no encontrada
                }
            }

            $componentes = $plantillasCache[$templateName];
            if (!is_array($componentes) || empty($componentes)) {
                continue;
            }

            // ── Extraer parámetros para el BODY ──
            // Formato campaña: "variables" = ['val1', 'val2', ...]
            // Formato API:     "template.components" = [{type:'body', parameters:[{type:'text',text:'val'}]}]
            $bodyParams = [];

            if (!empty($c['variables']) && is_array($c['variables'])) {
                $bodyParams = array_values($c['variables']);
            } elseif (!empty($apiComponents)) {
                foreach ($apiComponents as $comp) {
                    if (strtolower($comp['type'] ?? '') === 'body') {
                        foreach ($comp['parameters'] ?? [] as $p) {
                            $bodyParams[] = $p['text'] ?? '';
                        }
                    }
                }
            }

            // ── Reconstruir cada componente de la plantilla ──
            $headerType = 'none';
            $headerText = '';
            $bodyText   = '';
            $footerText = '';
            $buttons    = [];

            foreach ($componentes as $comp) {
                $type = strtoupper($comp['type'] ?? '');

                if ($type === 'HEADER') {
                    $fmt        = strtolower($comp['format'] ?? 'text');
                    $headerType = $fmt;
                    if ($fmt === 'text') {
                        $headerText = $comp['text'] ?? '';
                    }

                } elseif ($type === 'BODY') {
                    $bodyText = $comp['text'] ?? '';
                    // Sustituir {{1}}, {{2}}, ...
                    foreach ($bodyParams as $idx => $val) {
                        $n        = $idx + 1;
                        $bodyText = str_replace('{{' . $n . '}}', $val, $bodyText);
                    }

                } elseif ($type === 'FOOTER') {
                    $footerText = $comp['text'] ?? '';

                } elseif ($type === 'BUTTONS') {
                    foreach ($comp['buttons'] ?? [] as $btn) {
                        if (!empty($btn['text'])) {
                            $buttons[] = ['text' => $btn['text']];
                        }
                    }
                }
            }

            // ── Inyectar los campos reconstruidos en el contenido ──
            $msg['contenido']['template_text'] = $bodyText;
            $msg['contenido']['header_type']   = $headerType;
            $msg['contenido']['header_text']   = $headerText;
            $msg['contenido']['footer_text']   = $footerText;
            $msg['contenido']['buttons']       = $buttons;

            // Normalizar "template" a string (puede ser array en formato API)
            if (!is_string($msg['contenido']['template'] ?? null)) {
                $msg['contenido']['template'] = $templateName;
            }
        }
        unset($msg);

        return $mensajes;
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

        // Crear directorio local (usando MVC_ROOT para ser independiente del DocumentRoot del servidor)
        $uploadDir = MVC_ROOT . '/public/storage/whatsapp_media/' . $idEmpresa;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin');
        $fileName  = uniqid('wa_', true) . '.' . $extension;
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
