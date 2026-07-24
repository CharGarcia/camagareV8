<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\repositories\NuveiRepository;
use App\Services\NuveiService;

/**
 * NuveiController
 *
 * Controlador PÚBLICO que maneja el webhook y las páginas públicas de Nuvei.
 * No requiere sesión de usuario (Nuvei notifica servidor-a-servidor y los
 * clientes externos pagan desde el portal).
 *
 * Fase 1: solo el webhook (registro crudo del payload, ver NuveiService::procesarWebhook).
 * Las rutas /nuvei/pago/{token} y /nuvei/linktopay-retorno se agregan en fases posteriores.
 *
 * Rutas:
 *   POST /nuvei/webhook → notificación server-a-server de Nuvei
 */
class NuveiController extends Controller
{
    private NuveiService $nuvei;

    public function __construct()
    {
        parent::__construct();
        $this->nuvei = new NuveiService(new NuveiRepository());
    }

    public function webhook(): void
    {
        header('Content-Type: application/json');

        $raw     = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = ['_raw' => $raw];
        }

        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];

        try {
            $this->nuvei->procesarWebhook($payload, $headers);
        } catch (\Throwable $e) {
            error_log('[Nuvei] Error procesando webhook: ' . $e->getMessage());
        }

        // Responder 200 siempre: mientras no se confirme la política de reintentos
        // de Nuvei, se prioriza no perder el registro crudo (ya quedó en nuvei_webhook_log).
        echo json_encode(['ok' => true]);
        exit;
    }
}
