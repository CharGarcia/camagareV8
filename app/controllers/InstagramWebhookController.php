<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\Services\modulos\CrmWebhookService;

/**
 * Webhook PÚBLICO de Instagram para el CRM (URL: /instagram-webhook, registrado
 * en $publicControllers de Application). Diseño: docs/diseno/crm-redes-fase1.md §6.
 *
 *  GET  → verificación de Meta: hub.verify_token contra el token global de la app
 *         (config meta_instagram.webhook_verify_token) y se devuelve hub.challenge.
 *  POST → firma X-Hub-Signature-256 obligatoria (HMAC con el App Secret). Firma
 *         inválida = 403 y no se guarda nada. Válida = se encola, se responde 200
 *         enseguida (Meta reintenta si tarda) y se procesa en la misma petición;
 *         lo que falle lo retoma el cron cada minuto.
 *
 * La empresa activa se valida al procesar (CrmCanalRepository::getActivoPorExterno).
 */
class InstagramWebhookController extends Controller
{
    public function index(): void
    {
        $metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($metodo === 'GET') {
            $this->verificar();
        }
        if ($metodo === 'POST') {
            $this->recibir();
        }
        http_response_code(405);
        header('Allow: GET, POST');
        exit;
    }

    private function verificar(): never
    {
        header('Content-Type: text/plain; charset=utf-8');
        // PHP convierte los puntos de hub.mode / hub.verify_token / hub.challenge en guiones bajos.
        $modo = (string) ($_GET['hub_mode'] ?? '');
        $token = (string) ($_GET['hub_verify_token'] ?? '');
        $challenge = (string) ($_GET['hub_challenge'] ?? '');
        if ($modo !== 'subscribe' || $challenge === '' || !CrmWebhookService::crear()->verifyTokenValido($token)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        // Meta manda un número; se filtra para no reflejar nada más.
        echo preg_replace('/[^A-Za-z0-9_\-]/', '', $challenge);
        exit;
    }

    private function recibir(): never
    {
        $cuerpo = (string) file_get_contents('php://input');
        $firma = (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
        $svc = CrmWebhookService::crear();

        if (!$svc->disponible()) {
            // Sin el SQL del CRM no hay dónde guardar: 200 para que Meta no desactive el webhook.
            $this->responderYSeguir(200, 'EVENT_RECEIVED');
            exit;
        }
        try {
            $idEvento = $svc->recibir($cuerpo, $firma);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'recibir']);
            http_response_code(500);   // Meta reintentará
            exit;
        }
        if ($idEvento === null) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Invalid signature';
            exit;
        }

        $this->responderYSeguir(200, 'EVENT_RECEIVED');
        try {
            $svc->procesar($idEvento);   // si falla queda en la cola para el cron
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'procesar', 'id_evento' => $idEvento]);
        }
        // Asistente virtual en vivo: si el mensaje dejó una conversación en su cola, un
        // proceso aparte responde apenas vence la espera para agrupar (el cron de cada
        // minuto queda de respaldo). Uno solo a la vez (candado en el script).
        try {
            if (\App\Services\modulos\CrmBotService::crear()->hayEnCola(60.0)) {
                \App\Helpers\ProcesoSegundoPlano::lanzar('crm_asistente_tiempo_real.php');
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'asistente_en_vivo', 'id_evento' => $idEvento]);
        }
        exit;
    }

    /**
     * Entrega la respuesta a Meta y deja seguir el script: fastcgi_finish_request()
     * en PHP-FPM; en Apache mod_php, cerrar la conexión con Content-Length y vaciar
     * los búferes.
     */
    private function responderYSeguir(int $codigo, string $texto): void
    {
        ignore_user_abort(true);
        http_response_code($codigo);
        if (function_exists('fastcgi_finish_request')) {
            header('Content-Type: text/plain; charset=utf-8');
            echo $texto;
            fastcgi_finish_request();
            return;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Connection: close');
        header('Content-Length: ' . strlen($texto));
        echo $texto;
        flush();
    }
}
