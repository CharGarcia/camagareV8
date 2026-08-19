<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\Services\modulos\ComprasService;

/**
 * Aprobación pública (por token del correo) de compras pendientes.
 * Ruta pública SIN login: /aprobar-compra/{token}[/aprobar|/rechazar].
 * La autorización es el token secreto enviado por correo a los aprobadores.
 */
class ComprasAprobacionController extends Controller
{
    private ComprasService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ComprasService();
    }

    public function index(): void
    {
        $token  = trim($_GET['token'] ?? '');
        $compra = $token !== '' ? $this->service->getPorTokenAprobacion($token) : null;

        if (!$compra) {
            $this->resultado('error', 'El enlace no es válido o la compra ya fue procesada.');
            return;
        }

        $this->view('compras_aprobacion.pagina', [
            'vista'  => 'detalle',
            'compra' => $compra,
            'token'  => $token,
        ]);
    }

    public function aprobar(): void
    {
        $token  = trim($_POST['token'] ?? $_GET['token'] ?? '');
        $compra = $token !== '' ? $this->service->getPorTokenAprobacion($token) : null;

        if (!$compra) {
            $this->resultado('error', 'El enlace no es válido o la compra ya fue procesada.');
            return;
        }

        try {
            // El token ES la autorización: quien llega por el enlace del correo ya
            // fue elegido como aprobador, así que no se revalida contra la sesión
            // (no hay sesión). Se registra a nombre del aprobador configurado.
            $this->service->aprobarCompra(
                (int) $compra['id'],
                (int) $compra['id_empresa'],
                $this->idAprobador($compra),
                true
            );
            $this->resultado('ok', 'La compra ' . $this->numero($compra) . ' fue APROBADA.');
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

        $compra = $token !== '' ? $this->service->getPorTokenAprobacion($token) : null;
        if (!$compra) {
            $this->resultado('error', 'El enlace no es válido o la compra ya fue procesada.');
            return;
        }

        try {
            $this->service->rechazarCompra(
                (int) $compra['id'],
                (int) $compra['id_empresa'],
                $this->idAprobador($compra),
                $motivo,
                true
            );
            $this->resultado('ok', 'La compra ' . $this->numero($compra) . ' fue RECHAZADA.');
        } catch (\Throwable $e) {
            $this->resultado('error', $e->getMessage());
        }
    }

    /**
     * A quién se le atribuye la decisión. El enlace es el mismo para todos los
     * aprobadores, así que no se sabe cuál de ellos hizo clic: se registra el
     * primero configurado. Quién decidió exactamente queda en el log del sistema
     * junto con la IP de la petición.
     */
    private function idAprobador(array $compra): int
    {
        $cfg = $this->service->getConfigAprobacion((int) $compra['id_empresa']);
        return (int) ($cfg['aprobadores'][0] ?? 0);
    }

    private function numero(array $compra): string
    {
        return ($compra['establecimiento_prov'] ?? '') . '-'
             . ($compra['punto_emision_prov'] ?? '') . '-'
             . ($compra['secuencial_prov'] ?? '');
    }

    private function resultado(string $tipo, string $mensaje): void
    {
        $this->view('compras_aprobacion.pagina', [
            'vista'   => 'resultado',
            'tipo'    => $tipo,
            'mensaje' => $mensaje,
        ]);
    }
}
