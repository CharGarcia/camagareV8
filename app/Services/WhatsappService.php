<?php
/**
 * Servicio WhatsappService
 * Lógica de negocio para interactuar con la API Cloud de WhatsApp Business (Meta)
 */

declare(strict_types=1);

namespace App\services;

use App\models\WhatsappConfig;

class WhatsappService
{
    private WhatsappConfig $configModel;
    private const API_VERSION = 'v19.0';
    private const BASE_URL = 'https://graph.facebook.com/' . self::API_VERSION . '/';

    public function __construct()
    {
        $this->configModel = new WhatsappConfig();
    }

    /**
     * Prueba la conexión con la API de Meta verificando las credenciales.
     * @return array ['success' => bool, 'message' => string, 'data' => array|null]
     */
    public function testConnection(int $idEmpresa): array
    {
        $config = $this->configModel->obtenerConfiguracion($idEmpresa);

        if (!$config || empty($config['access_token']) || empty($config['waba_id'])) {
            return [
                'success' => false,
                'message' => 'Configuración incompleta. Faltan credenciales.'
            ];
        }

        // Endpoint para obtener información de la cuenta de WhatsApp Business
        $url = self::BASE_URL . $config['waba_id'] . '?fields=id,name,message_templates';
        
        $response = $this->makeRequest('GET', $url, $config['access_token']);

        if (isset($response['error'])) {
            return [
                'success' => false,
                'message' => 'Error de conexión: ' . ($response['error']['message'] ?? 'Desconocido'),
                'data' => $response
            ];
        }

        return [
            'success' => true,
            'message' => 'Conexión exitosa. La cuenta Meta es válida.',
            'data' => $response
        ];
    }

    /**
     * Sincroniza (obtiene) las plantillas desde Meta
     */
    public function syncTemplates(int $idEmpresa): array
    {
        $config = $this->configModel->obtenerConfiguracion($idEmpresa);

        if (!$config || empty($config['access_token']) || empty($config['waba_id'])) {
            return ['success' => false, 'message' => 'Configuración incompleta. Faltan credenciales.'];
        }

        $url = self::BASE_URL . $config['waba_id'] . '/message_templates?limit=100';
        $response = $this->makeRequest('GET', $url, $config['access_token']);

        if (isset($response['error'])) {
            return ['success' => false, 'message' => 'Error al obtener plantillas: ' . ($response['error']['message'] ?? 'Desconocido')];
        }

        return ['success' => true, 'data' => $response['data'] ?? []];
    }

    /**
     * Sube un archivo a Meta para obtener un File Handle (Resumable Upload API)
     * Requiere el App ID configurado en Meta.
     */
    public function uploadMedia(int $idEmpresa, string $appId, string $filePath, string $mimeType, int $fileSize): array
    {
        $config = $this->configModel->obtenerConfiguracion($idEmpresa);

        if (!$config || empty($config['access_token'])) {
            return ['success' => false, 'message' => 'Falta token de acceso.'];
        }

        $token = $config['access_token'];
        $fileName = basename($filePath);

        // 1. Crear sesión de subida
        $urlSession = self::BASE_URL . $appId . '/uploads?file_length=' . $fileSize . '&file_type=' . urlencode($mimeType) . '&file_name=' . urlencode($fileName);
        
        $sessionResp = $this->makeRequest('POST', $urlSession, $token);
        
        if (isset($sessionResp['error']) || empty($sessionResp['id'])) {
            return ['success' => false, 'message' => 'Error al crear sesión de subida: ' . json_encode($sessionResp)];
        }

        $sessionId = $sessionResp['id'];

        // 2. Subir archivo usando el session ID
        $urlUpload = self::BASE_URL . $sessionId;
        $fileData = file_get_contents($filePath);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlUpload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: OAuth ' . $token,
            'file_offset: 0'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $uploadResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $uploadData = json_decode($uploadResponse, true);

        if ($httpCode !== 200 || isset($uploadData['error']) || empty($uploadData['h'])) {
            return ['success' => false, 'message' => 'Error al subir bytes del archivo: ' . json_encode($uploadData)];
        }

        return ['success' => true, 'handle' => $uploadData['h']];
    }

    /**
     * Crea una nueva plantilla en Meta
     */
    public function createTemplate(int $idEmpresa, array $data): array
    {
        $config = $this->configModel->obtenerConfiguracion($idEmpresa);

        if (!$config || empty($config['access_token']) || empty($config['waba_id'])) {
            return ['success' => false, 'message' => 'Configuración incompleta.'];
        }

        $url = self::BASE_URL . $config['waba_id'] . '/message_templates';
        
        $response = $this->makeRequest('POST', $url, $config['access_token'], $data);

        if (isset($response['error'])) {
            return ['success' => false, 'message' => 'Error al crear plantilla en Meta: ' . ($response['error']['message'] ?? 'Desconocido'), 'data' => $response];
        }

        return ['success' => true, 'data' => $response];
    }

    /**
     * Actualiza una plantilla existente en Meta
     */
    public function updateTemplate(int $idEmpresa, string $metaTemplateId, array $data): array
    {
        $config = $this->configModel->obtenerConfiguracion($idEmpresa);

        if (!$config || empty($config['access_token']) || empty($config['waba_id'])) {
            return ['success' => false, 'message' => 'Configuración incompleta.'];
        }

        $url = self::BASE_URL . $metaTemplateId;
        
        $response = $this->makeRequest('POST', $url, $config['access_token'], $data);

        if (isset($response['error'])) {
            $err  = $response['error'];
            $msg  = $err['message'] ?? 'Desconocido';
            $sub  = isset($err['error_subcode']) ? ' (subcode: ' . $err['error_subcode'] . ')' : '';
            $data = isset($err['error_data']) ? ' — ' . $err['error_data'] : '';
            return ['success' => false, 'message' => 'Error al actualizar en Meta: ' . $msg . $sub . $data, 'raw' => $response];
        }

        return ['success' => true, 'data' => $response];
    }

    /**
     * Sube un archivo a Meta para enviar en un mensaje (POST /{phone_number_id}/media)
     */
    public function uploadMessageMedia(int $idEmpresa, string $filePath, string $mimeType): array
    {
        $config = $this->configModel->obtenerConfiguracion($idEmpresa);

        if (!$config || empty($config['access_token']) || empty($config['phone_number_id'])) {
            return ['success' => false, 'message' => 'Falta token de acceso o phone_number_id.'];
        }

        $url = self::BASE_URL . $config['phone_number_id'] . '/media';
        
        $cFile = curl_file_create($filePath, $mimeType, basename($filePath));
        $postData = [
            'messaging_product' => 'whatsapp',
            'file' => $cFile
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $config['access_token']
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true) ?? [];

        if ($httpCode !== 200 || isset($data['error']) || empty($data['id'])) {
            return ['success' => false, 'message' => 'Error al subir media: ' . ($data['error']['message'] ?? 'Desconocido')];
        }

        return ['success' => true, 'media_id' => $data['id']];
    }

    /**
     * Envía un mensaje de plantilla usando la API de Meta
     */
    public function sendTemplateMessage(int $idEmpresa, string $to, string $templateName, string $languageCode, array $components): array
    {
        $config = $this->configModel->obtenerConfiguracion($idEmpresa);

        if (!$config || empty($config['access_token']) || empty($config['phone_number_id'])) {
            return ['success' => false, 'message' => 'Configuración incompleta. Faltan credenciales.'];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
                'components' => $components
            ]
        ];

        $url = self::BASE_URL . $config['phone_number_id'] . '/messages';
        $response = $this->makeRequest('POST', $url, $config['access_token'], $payload);

        if (isset($response['error'])) {
            return ['success' => false, 'message' => 'Error enviando mensaje: ' . ($response['error']['message'] ?? 'Desconocido'), 'data' => $response];
        }

        return ['success' => true, 'data' => $response];
    }

    /**
     * Envía un mensaje multimedia usando el media_id de Meta
     */
    public function sendMediaMessage(int $idEmpresa, string $to, string $mediaId, string $type = 'image', string $caption = ''): array
    {
        $config = $this->configModel->obtenerConfiguracion($idEmpresa);

        if (!$config || empty($config['access_token']) || empty($config['phone_number_id'])) {
            return ['success' => false, 'message' => 'Configuración incompleta.'];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => $type,
            $type => [
                'id' => $mediaId
            ]
        ];

        if (!empty($caption) && in_array($type, ['image', 'document', 'video'])) {
            $payload[$type]['caption'] = $caption;
        }

        $url = self::BASE_URL . $config['phone_number_id'] . '/messages';
        $response = $this->makeRequest('POST', $url, $config['access_token'], $payload);

        if (isset($response['error'])) {
            return ['success' => false, 'message' => 'Error enviando media: ' . ($response['error']['message'] ?? 'Desconocido'), 'data' => $response];
        }

        return ['success' => true, 'data' => $response];
    }

    /**
     * Descarga un archivo multimedia desde Meta
     */
    public function downloadMedia(int $idEmpresa, string $mediaId, string $savePath): array
    {
        $config = $this->configModel->obtenerConfiguracion($idEmpresa);

        if (!$config || empty($config['access_token'])) {
            return ['success' => false, 'message' => 'Configuración incompleta.'];
        }

        // Paso 1: Obtener la URL de descarga
        $url = self::BASE_URL . $mediaId;
        $response = $this->makeRequest('GET', $url, $config['access_token']);

        if (isset($response['error']) || !isset($response['url'])) {
            return ['success' => false, 'message' => 'No se pudo obtener URL del media'];
        }

        $downloadUrl = $response['url'];
        $mimeType = $response['mime_type'] ?? 'application/octet-stream';

        // Paso 2: Descargar los bytes
        $ch = curl_init();
        $headers = [
            'Authorization: Bearer ' . $config['access_token']
        ];

        curl_setopt($ch, CURLOPT_URL, $downloadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $fileData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch) || $httpCode !== 200) {
            curl_close($ch);
            return ['success' => false, 'message' => 'Error descargando archivo desde URL'];
        }

        curl_close($ch);

        // Guardar en disco
        $dirname = dirname($savePath);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0755, true);
        }

        if (file_put_contents($savePath, $fileData) !== false) {
            return ['success' => true, 'mime_type' => $mimeType];
        }

        return ['success' => false, 'message' => 'Error guardando archivo en servidor'];
    }

    /**
     * Método interno para realizar peticiones cURL (JSON)
     */
    private function makeRequest(string $method, string $url, string $token, array $data = []): array
    {
        $ch = curl_init();

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($method !== 'GET' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['error' => ['message' => 'CURL Error: ' . $error]];
        }

        curl_close($ch);

        return json_decode($response, true) ?? ['error' => ['message' => 'Invalid JSON response']];
    }
}
