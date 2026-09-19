<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\repositories\modulos\VacacionSolicitudRepository;
use App\Rules\modulos\VacacionSolicitudRules;
use App\Services\LogSistemaService;
use App\Services\modulos\VacacionSolicitudService;

/**
 * Formulario público con el que el empleado solicita sus vacaciones.
 * Ruta pública SIN login: /solicitud-vacaciones/{token}[/enviar].
 *
 * La autorización es el token del enlace que se le envió por correo: es de un
 * solo uso, caduca, y el repositorio solo lo resuelve si la empresa sigue activa
 * (§6). Nunca se revela por qué un enlace no sirve.
 */
class SolicitudVacacionesController extends Controller
{
    private VacacionSolicitudService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new VacacionSolicitudService(
            new VacacionSolicitudRepository(),
            new VacacionSolicitudRules(),
            new LogSistemaService()
        );
    }

    public function index(): void
    {
        $token = trim($_GET['token'] ?? '');
        $ctx   = $this->service->getContextoPublico($token);

        if ($ctx['error'] !== '') {
            $this->resultado('info', $ctx['error']);
            return;
        }

        $this->view('solicitud_vacaciones.pagina', [
            'vista'     => 'formulario',
            'solicitud' => $ctx['solicitud'],
            'info'      => $ctx['info'],
            'token'     => $token,
            'datos'     => [],
            'error'     => '',
        ]);
    }

    public function enviar(): void
    {
        $token = trim($_POST['token'] ?? $_GET['token'] ?? '');
        $datos = [
            'fecha_desde'      => trim($_POST['fecha_desde'] ?? ''),
            'fecha_hasta'      => trim($_POST['fecha_hasta'] ?? ''),
            'dias_solicitados' => (float) ($_POST['dias_solicitados'] ?? 0),
            'motivo'           => trim($_POST['motivo'] ?? ''),
            'contacto'         => trim($_POST['contacto'] ?? ''),
        ];

        try {
            $this->service->registrarSolicitud($token, $datos, $this->ipCliente());
            $this->resultado('ok', 'Su solicitud de vacaciones fue enviada. Queda en revisión y le avisaremos en cuanto se resuelva.');
        } catch (\Throwable $e) {
            // Si el enlace todavía sirve, se vuelve a mostrar el formulario con lo
            // que ya había escrito y el motivo del error; si no, solo el aviso.
            $ctx = $this->service->getContextoPublico($token);
            if ($ctx['error'] !== '') {
                $this->resultado('info', $ctx['error']);
                return;
            }
            $this->view('solicitud_vacaciones.pagina', [
                'vista'     => 'formulario',
                'solicitud' => $ctx['solicitud'],
                'info'      => $ctx['info'],
                'token'     => $token,
                'datos'     => $datos,
                'error'     => $e->getMessage(),
            ]);
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
        $this->view('solicitud_vacaciones.pagina', [
            'vista'   => 'resultado',
            'tipo'    => $tipo,
            'mensaje' => $mensaje,
        ]);
    }
}
