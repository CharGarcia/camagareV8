<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\Services\modulos\AlumnoPortalService;

/**
 * Portal público de representantes (QR general del colegio). SIN login:
 *   /portal-alumnos/{token}            → identificarse con cédula/RUC
 *   /portal-alumnos/{token}/codigo     → (POST) envía el código al correo
 *   /portal-alumnos/{token}/verificar  → (POST) valida el código → formulario
 *   /portal-alumnos/{token}/guardar    → (POST) guarda UNA vez
 *
 * Registrado en $publicControllers (Application): no pasa por AuthMiddleware, así
 * que el Service resuelve el token con la empresa activa (§6). Vista standalone.
 */
class AlumnosPortalController extends Controller
{
    private AlumnoPortalService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new AlumnoPortalService();
    }

    public function index(): void
    {
        $token = $this->token();
        $portal = $this->service->getContexto($token);
        if (!$portal) { $this->resultado('info', AlumnoPortalService::ERROR_ENLACE); return; }
        $this->pagina('identificar', $portal, $token);
    }

    public function codigo(): void
    {
        $token = $this->token();
        $portal = $this->service->getContexto($token);
        if (!$portal) { $this->resultado('info', AlumnoPortalService::ERROR_ENLACE); return; }
        $ident  = (string) ($_POST['identificacion'] ?? '');
        $correo = (string) ($_POST['correo'] ?? '');
        try {
            $r = $this->service->solicitarCodigo($token, $ident, $correo, $this->ipCliente());
            match ($r['estado']) {
                'pedir_correo' => $this->pagina('pedir_correo', $portal, $token, ['identificacion' => $r['identificacion']]),
                'sin_correo'   => $this->resultado('info', 'No tenemos un correo registrado para esa identificación. Comuníquese con la institución para registrarlo.', $portal),
                default        => $this->pagina('codigo', $portal, $token, $r),
            };
        } catch (\Throwable $e) {
            $this->pagina($correo !== '' ? 'pedir_correo' : 'identificar', $portal, $token,
                ['identificacion' => $ident, 'correo_escrito' => $correo, 'error' => $e->getMessage()]);
        }
    }

    public function verificar(): void
    {
        $token = $this->token();
        $portal = $this->service->getContexto($token);
        if (!$portal) { $this->resultado('info', AlumnoPortalService::ERROR_ENLACE); return; }
        $idCodigo = (int) ($_POST['id_codigo'] ?? 0);
        try {
            $sesion = $this->service->verificarCodigo($token, $idCodigo, (string) ($_POST['codigo'] ?? ''));
            $form = $this->service->getFormulario($token, $sesion);
            $this->pagina('formulario', $portal, $token, $form + ['sesion' => $sesion]);
        } catch (\Throwable $e) {
            $this->pagina('codigo', $portal, $token, [
                'id_codigo' => $idCodigo,
                'correo'    => (string) ($_POST['correo_mascara'] ?? ''),
                'error'     => $e->getMessage(),
            ]);
        }
    }

    public function guardar(): void
    {
        $token = $this->token();
        $portal = $this->service->getContexto($token);
        if (!$portal) { $this->resultado('info', AlumnoPortalService::ERROR_ENLACE); return; }
        $sesion = (string) ($_POST['sesion'] ?? '');
        try {
            $r = $this->service->guardar($token, $sesion, $_POST, $this->ipCliente());
            $partes = [];
            if ($r['actualizados'] > 0) { $partes[] = $r['actualizados'] . ' alumno(s) actualizado(s)'; }
            if ($r['creados'] > 0) { $partes[] = $r['creados'] . ' alumno(s) registrado(s)'; }
            $this->resultado('ok', '¡Listo! Sus datos de facturación quedaron guardados' . ($partes ? ' y ' . implode(' y ', $partes) : '')
                . '. Le enviamos una constancia a su correo. Para hacer otro cambio, vuelva a ingresar y pida un código nuevo.', $portal, $token);
        } catch (\Throwable $e) {
            // Si la verificación sigue vigente, se vuelve al formulario con lo escrito.
            try {
                $form = $this->service->getFormulario($token, $sesion);
                $this->pagina('formulario', $portal, $token, $form + ['sesion' => $sesion, 'error' => $e->getMessage(), 'post' => $_POST]);
            } catch (\Throwable $e2) {
                $this->resultado('info', $e->getMessage(), $portal, $token);
            }
        }
    }

    private function token(): string
    {
        return trim((string) ($_GET['token'] ?? ''));
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

    private function pagina(string $vista, array $portal, string $token, array $extra = []): void
    {
        $this->view('alumnos_portal.pagina', ['vista' => $vista, 'portal' => $portal, 'token' => $token] + $extra);
    }

    private function resultado(string $tipo, string $mensaje, ?array $portal = null, string $token = ''): void
    {
        $this->view('alumnos_portal.pagina', ['vista' => 'resultado', 'tipo' => $tipo, 'mensaje' => $mensaje, 'portal' => $portal, 'token' => $token]);
    }
}
