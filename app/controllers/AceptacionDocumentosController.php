<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\Services\DocumentosLegalesService;

/**
 * Aceptación pública (por token del correo) de los documentos legales.
 * Ruta pública SIN login: /aceptar-documentos/{token}[/aceptar].
 * La autorización es el token secreto enviado al correo de la empresa.
 */
class AceptacionDocumentosController extends Controller
{
    private DocumentosLegalesService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new DocumentosLegalesService();
    }

    public function index(): void
    {
        $token = trim($_GET['token'] ?? '');
        $envio = $this->service->getEnvioPorToken($token);

        if (!$envio) {
            $this->resultado('error', 'El enlace no es válido o ya no está disponible.');
            return;
        }

        if (($envio['estado'] ?? '') === 'aceptado') {
            $this->resultado(
                'ok',
                'Estos documentos ya fueron aceptados el '
                . date('d-m-Y H:i:s', strtotime((string) $envio['aceptado_at']))
                . ' por ' . htmlspecialchars((string) $envio['aceptado_nombre']) . '.'
            );
            return;
        }

        $this->view('aceptacion_documentos.pagina', [
            'vista'      => 'detalle',
            'envio'      => $envio,
            'documentos' => $this->service->getDocumentosDeEnvio($envio),
            'token'      => $token,
        ]);
    }

    public function aceptar(): void
    {
        $token          = trim($_POST['token'] ?? $_GET['token'] ?? '');
        $nombre         = trim($_POST['nombre'] ?? '');
        $identificacion = trim($_POST['identificacion'] ?? '');

        try {
            $res = $this->service->aceptarPorToken($token, $nombre, $identificacion);
            $this->resultado(
                'ok',
                '¡Gracias! Los documentos de <b>' . htmlspecialchars((string) $res['empresa'])
                . '</b> fueron aceptados correctamente. Se registró la fecha, hora y dirección IP como constancia.'
            );
        } catch (\Throwable $e) {
            $this->resultado('error', $e->getMessage());
        }
    }

    private function resultado(string $tipo, string $mensaje): void
    {
        $this->view('aceptacion_documentos.pagina', [
            'vista'   => 'resultado',
            'tipo'    => $tipo,
            'mensaje' => $mensaje,
        ]);
    }
}
