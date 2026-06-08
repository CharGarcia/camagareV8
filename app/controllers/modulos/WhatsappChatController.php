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

        if (!move_uploaded_file($file['tmp_name'], $localPath)) {
            echo json_encode(['ok' => false, 'error' => 'No se pudo guardar el archivo en el servidor']);
            return;
        }

        $mimeType = @mime_content_type($localPath) ?: $file['type'] ?: 'application/octet-stream';

        // Determinar tipo de mensaje WhatsApp según MIME
        if (str_starts_with($mimeType, 'image/')) {
            $type = 'image';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            $type = 'audio';
        } elseif (str_starts_with($mimeType, 'video/')) {
            $type = 'video';
        } else {
            $type = 'document';
        }

        // Subir archivo a Meta
        $uploadResult = $this->whatsappService->uploadMessageMedia($idEmpresa, $localPath, $mimeType);

        if (!$uploadResult['success']) {
            // Archivo ya guardado localmente — solo falla el envío a Meta
            echo json_encode(['ok' => false, 'error' => $uploadResult['message']]);
            return;
        }

        $mediaId = $uploadResult['media_id'];

        // Enviar el mensaje con el media_id a Meta
        $sendResult = $this->whatsappService->sendMediaMessage($idEmpresa, $telefono, $mediaId, $type);

        if (!$sendResult['success']) {
            echo json_encode(['ok' => false, 'error' => $sendResult['message']]);
            return;
        }

        $metaMessageId = $sendResult['data']['messages'][0]['id'] ?? null;

        $contenido = [
            'local_path' => 'storage/whatsapp_media/' . $idEmpresa . '/' . $fileName,
            'mime_type'  => $mimeType,
            'media_id'   => $mediaId,
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
            'ok'              => true,
            'id_mensaje'      => $msgId,
            'local_path'      => $contenido['local_path'],
            'type'            => $type,
            'fecha_hora'      => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Sirve un archivo multimedia de WhatsApp de forma segura.
     * Valida que el archivo pertenezca a la empresa activa del usuario.
     * URL: /modulos/whatsapp-chat/serveMedia?file=storage/whatsapp_media/{idEmpresa}/{archivo}
     */
    public function serveMedia(): void
    {
        if (empty($_SESSION['id_empresa'])) {
            http_response_code(403);
            exit;
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $file      = trim($_GET['file'] ?? '');

        // ── Seguridad: solo permitir rutas dentro de storage/whatsapp_media/{empresa}/ ──
        if (empty($file)) {
            http_response_code(400);
            exit;
        }

        // Normalizar separadores y bloquear path traversal
        $file = str_replace('\\', '/', $file);
        if (str_contains($file, '..') || str_contains($file, "\0")) {
            http_response_code(403);
            exit;
        }

        // El archivo DEBE estar bajo storage/whatsapp_media/{idEmpresa}/
        $allowedPrefix = 'storage/whatsapp_media/' . $idEmpresa . '/';
        if (!str_starts_with($file, $allowedPrefix)) {
            http_response_code(403);
            exit;
        }

        $fullPath = MVC_ROOT . '/public/' . $file;

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            // ── Intentar re-descargar desde Meta si tenemos el media_id en la BD ──
            $redownloaded = $this->tryRedownloadMedia($idEmpresa, $file, $fullPath);
            if (!$redownloaded) {
                http_response_code(404);
                echo 'Archivo no encontrado';
                exit;
            }
        }

        $mimeType   = @mime_content_type($fullPath) ?: 'application/octet-stream';
        $fileName   = basename($fullPath);
        $fileSize   = filesize($fullPath);
        $forceDownload = !empty($_GET['dl']); // ?dl=1 fuerza descarga (Content-Disposition: attachment)

        // Sin ?dl=1: imágenes/audio/video se muestran inline; documentos se descargan
        // Con ?dl=1: siempre descarga (attachment)
        $isInline = !$forceDownload && (
            str_starts_with($mimeType, 'image/')
         || str_starts_with($mimeType, 'audio/')
         || str_starts_with($mimeType, 'video/')
        );

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);
        header('Content-Disposition: ' . ($isInline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($fileName) . '"');
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');

        // Limpiar cualquier buffer previo
        while (ob_get_level()) {
            ob_end_clean();
        }

        readfile($fullPath);
        exit;
    }

    /**
     * Intenta re-descargar un archivo de Media desde Meta usando el media_id
     * almacenado en whatsapp_mensajes para esa ruta.
     */
    private function tryRedownloadMedia(int $idEmpresa, string $relPath, string $fullPath): bool
    {
        try {
            // Buscar el media_id en whatsapp_mensajes cuyo contenido tiene ese local_path
            $stmt = $this->db->prepare(
                "SELECT contenido FROM whatsapp_mensajes
                 WHERE id_empresa = ?
                   AND eliminado = false
                   AND (
                         contenido::text LIKE ?
                       )
                 LIMIT 1"
            );
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $relPath) . '%';
            $stmt->execute([$idEmpresa, $like]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                return false;
            }

            $contenido = is_string($row['contenido'])
                ? (json_decode($row['contenido'], true) ?? [])
                : ($row['contenido'] ?? []);

            // Buscar media_id en el contenido (puede estar directamente o anidado)
            $mediaId = $contenido['media_id']
                ?? $contenido['image']['id']
                ?? $contenido['audio']['id']
                ?? $contenido['video']['id']
                ?? $contenido['document']['id']
                ?? $contenido['sticker']['id']
                ?? null;

            if (empty($mediaId)) {
                return false;
            }

            // Crear directorio si no existe
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $result = $this->whatsappService->downloadMedia($idEmpresa, (string)$mediaId, $fullPath);
            return $result['success'] ?? false;

        } catch (\Throwable $e) {
            error_log('[serveMedia] Error re-descargando: ' . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // RESPUESTAS RÁPIDAS
    // =========================================================================

    /**
     * Devuelve todas las respuestas rápidas de la empresa
     * y las personales del usuario actual.
     * GET /modulos/whatsapp-chat/getRespuestasRapidas
     */
    public function getRespuestasRapidas(): void
    {
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $stmt = $this->db->prepare(
            "SELECT id, id_usuario, titulo, contenido, orden
             FROM whatsapp_respuestas_rapidas
             WHERE id_empresa = ?
               AND eliminado  = FALSE
               AND (id_usuario IS NULL OR id_usuario = ?)
             ORDER BY id_usuario NULLS FIRST, orden ASC, titulo ASC"
        );
        $stmt->execute([$idEmpresa, $idUsuario]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Separar en empresa y personales
        $empresa    = [];
        $personales = [];
        foreach ($rows as $r) {
            $item = [
                'id'        => (int) $r['id'],
                'titulo'    => $r['titulo'],
                'contenido' => $r['contenido'],
                'orden'     => (int) $r['orden'],
            ];
            if ($r['id_usuario'] === null) {
                $empresa[] = $item;
            } else {
                $personales[] = $item;
            }
        }

        echo json_encode(['ok' => true, 'empresa' => $empresa, 'personales' => $personales]);
    }

    /**
     * Crea o actualiza una respuesta rápida.
     * POST /modulos/whatsapp-chat/saveRespuestaRapida
     * Body JSON: { id?, titulo, contenido, tipo: 'empresa'|'personal' }
     */
    public function saveRespuestaRapida(): void
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
            return;
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $input     = json_decode(file_get_contents('php://input'), true) ?? [];

        $id        = (int) ($input['id'] ?? 0);
        $titulo    = trim($input['titulo']    ?? '');
        $contenido = trim($input['contenido'] ?? '');
        $tipo      = $input['tipo'] ?? 'personal'; // 'empresa' | 'personal'

        if (empty($titulo) || empty($contenido)) {
            echo json_encode(['ok' => false, 'error' => 'El título y el contenido son obligatorios.']);
            return;
        }

        // id_usuario: NULL si es de empresa, id del usuario si es personal
        $idUsuarioDb = ($tipo === 'empresa') ? null : $idUsuario;

        if ($id > 0) {
            // ── Actualizar ────────────────────────────────────────────────────
            // Solo puede editar la suya: si es de empresa necesita permiso,
            // si es personal debe ser del mismo usuario.
            $check = $this->db->prepare(
                "SELECT id, id_usuario FROM whatsapp_respuestas_rapidas
                 WHERE id = ? AND id_empresa = ? AND eliminado = FALSE"
            );
            $check->execute([$id, $idEmpresa]);
            $existing = $check->fetch(\PDO::FETCH_ASSOC);

            if (!$existing) {
                echo json_encode(['ok' => false, 'error' => 'Respuesta no encontrada.']);
                return;
            }

            // Verificar propiedad: personal solo la edita su dueño
            if ($existing['id_usuario'] !== null && (int)$existing['id_usuario'] !== $idUsuario) {
                echo json_encode(['ok' => false, 'error' => 'No tienes permiso para editar esta respuesta.']);
                return;
            }

            $this->db->prepare(
                "UPDATE whatsapp_respuestas_rapidas
                    SET titulo = ?, contenido = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ?
                  WHERE id = ? AND id_empresa = ?"
            )->execute([$titulo, $contenido, $idUsuario, $id, $idEmpresa]);

            echo json_encode(['ok' => true, 'id' => $id, 'mensaje' => 'Respuesta actualizada.']);

        } else {
            // ── Crear ─────────────────────────────────────────────────────────
            $stmt = $this->db->prepare(
                "INSERT INTO whatsapp_respuestas_rapidas
                    (id_empresa, id_usuario, titulo, contenido, orden, created_by, updated_by)
                 VALUES (?, ?, ?, ?, 0, ?, ?)
                 RETURNING id"
            );
            $stmt->execute([$idEmpresa, $idUsuarioDb, $titulo, $contenido, $idUsuario, $idUsuario]);
            $newId = $stmt->fetchColumn();

            echo json_encode(['ok' => true, 'id' => (int)$newId, 'mensaje' => 'Respuesta creada.']);
        }
    }

    /**
     * Elimina (lógicamente) una respuesta rápida.
     * POST /modulos/whatsapp-chat/deleteRespuestaRapida
     * Body JSON: { id }
     */
    public function deleteRespuestaRapida(): void
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
            return;
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $input     = json_decode(file_get_contents('php://input'), true) ?? [];
        $id        = (int) ($input['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID inválido.']);
            return;
        }

        // Verificar que existe y pertenece a esta empresa
        $check = $this->db->prepare(
            "SELECT id, id_usuario FROM whatsapp_respuestas_rapidas
             WHERE id = ? AND id_empresa = ? AND eliminado = FALSE"
        );
        $check->execute([$id, $idEmpresa]);
        $existing = $check->fetch(\PDO::FETCH_ASSOC);

        if (!$existing) {
            echo json_encode(['ok' => false, 'error' => 'Respuesta no encontrada.']);
            return;
        }

        // Personal: solo la borra su dueño
        if ($existing['id_usuario'] !== null && (int)$existing['id_usuario'] !== $idUsuario) {
            echo json_encode(['ok' => false, 'error' => 'No tienes permiso para eliminar esta respuesta.']);
            return;
        }

        $this->db->prepare(
            "UPDATE whatsapp_respuestas_rapidas
                SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = ?
              WHERE id = ? AND id_empresa = ?"
        )->execute([$idUsuario, $id, $idEmpresa]);

        echo json_encode(['ok' => true, 'mensaje' => 'Respuesta eliminada.']);
    }
}
