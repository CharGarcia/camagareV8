<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\Services\modulos\ImportacionesService;

/**
 * Aprobación pública (por token del correo) de importaciones pendientes.
 * Ruta pública SIN login: /aprobar-importacion/{token}[/aprobar|/rechazar].
 * La autorización es el token secreto enviado por correo a los aprobadores.
 */
class ImportacionesAprobacionController extends Controller
{
    private ImportacionesService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ImportacionesService();
    }

    public function index(): void
    {
        $token = trim($_GET['token'] ?? '');
        $importacion = $token !== '' ? $this->service->getImportacionPorToken($token) : null;

        if (!$importacion) {
            $this->resultado('error', 'El enlace no es válido o la importación ya fue procesada.');
            return;
        }
        if ($importacion['estado'] !== 'pendiente_aprobacion') {
            $this->resultado('info', 'Esta importación ya está en estado "' . $importacion['estado'] . '". No requiere más acción.');
            return;
        }

        $this->view('importaciones_aprobacion.pagina', [
            'vista'       => 'detalle',
            'importacion' => $importacion,
            'token'       => $token,
        ]);
    }

    public function aprobar(): void
    {
        $token = trim($_POST['token'] ?? $_GET['token'] ?? '');
        try {
            $res = $this->service->aprobarPorToken($token);
            $mensaje = 'La importación ' . ($res['numero_importacion'] ?? '') . ' fue APROBADA y nacionalizada.';
            if (!empty($res['asiento_warning'])) {
                $mensaje .= ' Atención: el asiento contable no se pudo generar (' . $res['asiento_warning'] . ').';
            }
            $this->resultado('ok', $mensaje);
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
            $this->resultado('ok', 'La importación ' . ($res['numero_importacion'] ?? '') . ' fue RECHAZADA.');
        } catch (\Throwable $e) {
            $this->resultado('error', $e->getMessage());
        }
    }

    private function resultado(string $tipo, string $mensaje): void
    {
        $this->view('importaciones_aprobacion.pagina', [
            'vista'   => 'resultado',
            'tipo'    => $tipo,
            'mensaje' => $mensaje,
        ]);
    }
}
