<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\Services\modulos\TransferenciaLoteService;

/**
 * Aprobación pública (por token del correo) de lotes de transferencias.
 * Ruta pública SIN login: /aprobar-transferencia/{token}[/aprobar|/rechazar].
 * La autorización es el token secreto enviado por correo a los aprobadores.
 */
class TransferenciasAprobacionController extends Controller
{
    private TransferenciaLoteService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new TransferenciaLoteService();
    }

    public function index(): void
    {
        $token = trim($_GET['token'] ?? '');
        $lote = $token !== '' ? $this->service->getLotePorToken($token) : null;

        if (!$lote) {
            $this->resultado('error', 'El enlace no es válido o el lote ya fue procesado.');
            return;
        }
        if ($lote['estado'] !== 'PENDIENTE_APROBACION') {
            $this->resultado('info', 'Este lote ya está ' . strtolower((string) $lote['estado']) . '. No requiere más acción.');
            return;
        }

        $this->view('transferencias_aprobacion.pagina', [
            'vista' => 'detalle',
            'lote'  => $lote,
            'token' => $token,
        ]);
    }

    public function aprobar(): void
    {
        $token = trim($_POST['token'] ?? $_GET['token'] ?? '');
        try {
            $res = $this->service->aprobarPorToken($token);
            $this->resultado('ok', 'El lote #' . ($res['numero'] ?? '') . ' fue APROBADO. Ya puede generarse el archivo bancario desde el sistema.');
        } catch (\Throwable $e) {
            $this->resultado('error', $e->getMessage());
        }
    }

    public function rechazar(): void
    {
        $token  = trim($_POST['token'] ?? $_GET['token'] ?? '');
        $motivo = trim($_POST['motivo'] ?? '');
        if ($motivo === '') {
            $this->resultado('error', 'Debe indicar el motivo del rechazo.');
            return;
        }
        try {
            $res = $this->service->rechazarPorToken($token, $motivo);
            $this->resultado('ok', 'El lote #' . ($res['numero'] ?? '') . ' fue RECHAZADO.');
        } catch (\Throwable $e) {
            $this->resultado('error', $e->getMessage());
        }
    }

    private function resultado(string $tipo, string $mensaje): void
    {
        $this->view('transferencias_aprobacion.pagina', [
            'vista'   => 'resultado',
            'tipo'    => $tipo,
            'mensaje' => $mensaje,
        ]);
    }
}
