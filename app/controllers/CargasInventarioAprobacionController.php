<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\Services\modulos\CargaInventarioService;

/**
 * Aprobación pública (por token del correo) de cargas de inventario.
 * Ruta pública SIN login: /aprobar-carga-inventario/{token}[/aprobar|/rechazar].
 * La autorización es el token secreto enviado por correo a los aprobadores.
 */
class CargasInventarioAprobacionController extends Controller
{
    private CargaInventarioService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new CargaInventarioService();
    }

    public function index(): void
    {
        $token = trim($_GET['token'] ?? '');
        $carga = $token !== '' ? $this->service->getCargaPorToken($token) : null;

        if (!$carga) {
            $this->resultado('error', 'El enlace no es válido o la carga ya fue procesada.');
            return;
        }
        if ($carga['estado'] !== 'pendiente') {
            $this->resultado('info', 'Esta carga ya está ' . $carga['estado'] . '. No requiere más acción.');
            return;
        }

        $this->view('cargas_inventario_aprobacion.pagina', [
            'vista' => 'detalle',
            'carga' => $carga,
            'token' => $token,
        ]);
    }

    public function aprobar(): void
    {
        $token = trim($_POST['token'] ?? $_GET['token'] ?? '');
        try {
            $res = $this->service->aprobarPorToken($token);
            $this->resultado('ok', 'La carga #' . ($res['numero'] ?? '') . ' fue APROBADA y aplicada al inventario.');
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
            $this->resultado('ok', 'La carga #' . ($res['numero'] ?? '') . ' fue RECHAZADA.');
        } catch (\Throwable $e) {
            $this->resultado('error', $e->getMessage());
        }
    }

    private function resultado(string $tipo, string $mensaje): void
    {
        $this->view('cargas_inventario_aprobacion.pagina', [
            'vista'   => 'resultado',
            'tipo'    => $tipo,
            'mensaje' => $mensaje,
        ]);
    }
}
