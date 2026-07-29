<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\repositories\modulos\ProformaRepository;
use App\Rules\modulos\ProformaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\ProformaService;

/**
 * Aprobación pública (por token del correo) de una proforma por el cliente.
 * Ruta pública SIN login: /aprobar-proforma/{token}[/aprobar].
 * La autorización es el token secreto enviado en el correo al cliente.
 */
class ProformaAprobacionController extends Controller
{
    private ProformaService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ProformaService(
            new ProformaRepository(),
            new ProformaRules(),
            new LogSistemaService()
        );
    }

    public function index(): void
    {
        $token = trim($_GET['token'] ?? '');
        $prof  = $token !== '' ? $this->service->getAprobacionPorToken($token) : null;

        if (!$prof) {
            $this->resultado('error', 'El enlace no es válido o la proforma ya no está disponible.');
            return;
        }
        if ($prof['estado'] !== 'borrador') {
            $mapa = [
                'aprobada'   => 'Esta proforma ya fue aprobada. ¡Gracias!',
                'rechazada'  => 'Esta proforma fue rechazada.',
                'anulada'    => 'Esta proforma está anulada.',
                'convertida' => 'Esta proforma ya fue facturada.',
            ];
            $this->resultado('info', $mapa[$prof['estado']] ?? 'Esta proforma ya no requiere acción.');
            return;
        }

        $this->view('proforma_aprobacion.pagina', [
            'vista' => 'detalle',
            'prof'  => $prof,
            'token' => $token,
        ]);
    }

    public function aprobar(): void
    {
        $token      = trim($_POST['token'] ?? $_GET['token'] ?? '');
        $comentario = trim($_POST['comentario'] ?? '');
        $ip         = $this->ipCliente();

        try {
            $res = $this->service->aprobarPorTokenCliente($token, $comentario, $ip);
            $this->resultado('ok', 'La proforma ' . ($res['numero'] ?? '') . ' fue APROBADA correctamente. ¡Gracias!');
        } catch (\Throwable $e) {
            $this->resultado('error', $e->getMessage());
        }
    }

    private function ipCliente(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = trim(explode(',', $_SERVER[$k])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }

    private function resultado(string $tipo, string $mensaje): void
    {
        $this->view('proforma_aprobacion.pagina', [
            'vista'   => 'resultado',
            'tipo'    => $tipo,
            'mensaje' => $mensaje,
        ]);
    }
}
