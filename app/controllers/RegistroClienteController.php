<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\Rules\modulos\ClienteQrRules;
use App\Services\modulos\ClienteQrService;

/**
 * QR de registro de clientes — página PÚBLICA, sin login.
 * Rutas (app/core/Router.php):
 *   GET  /registro-cliente/{token}         formulario
 *   POST /registro-cliente/{token}/enviar  crea o actualiza el cliente
 *   GET  /registro-cliente/{token}/sri     AJAX: nombre según el SRI
 * La autorización es el token del QR de la empresa; con el QR desactivado o la
 * empresa inactiva el enlace se comporta como inexistente (CLAUDE.md §6).
 */
class RegistroClienteController extends Controller
{
    private ClienteQrService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = ClienteQrService::crear();
    }

    public function index(): void
    {
        $cfg = $this->configOError();
        if ($cfg === null) {
            return;
        }
        $this->render('formulario', ['cfg' => $cfg, 'form' => [], 'error' => null]);
    }

    public function enviar(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ' . rtrim(BASE_URL, '/') . '/registro-cliente/' . rawurlencode((string) ($_GET['token'] ?? '')));
            exit;
        }
        $token = trim((string) ($_GET['token'] ?? ''));
        $form  = [
            'tipo_id'        => (string) ($_POST['tipo_id'] ?? ''),
            'identificacion' => (string) ($_POST['identificacion'] ?? ''),
            'nombre'         => (string) ($_POST['nombre'] ?? ''),
            'email'          => (string) ($_POST['email'] ?? ''),
            'telefono'       => (string) ($_POST['telefono'] ?? ''),
            'direccion'      => (string) ($_POST['direccion'] ?? ''),
        ];

        try {
            $res = $this->service->registrarPublico($token, $form, $this->ipCliente(), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
            $cfg = $this->service->getConfigPublica($token);
            $this->render('confirmacion', ['cfg' => $cfg, 'res' => $res, 'token' => $token]);
        } catch (\InvalidArgumentException $e) {
            $cfg = $this->service->getConfigPublica($token);
            if ($cfg === null) {
                $this->render('error', ['mensaje' => 'Este enlace no está disponible o ha sido desactivado.']);
                return;
            }
            $this->render('formulario', ['cfg' => $cfg, 'form' => $form, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->render('error', ['mensaje' => 'Ocurrió un error al registrar tus datos. Por favor intenta nuevamente.']);
        }
    }

    /**
     * Nombre según el SRI para la cédula/RUC escrita. Se consulta SIN empresa a
     * propósito: con empresa, el servicio busca primero en los clientes locales y
     * devolvería nombre y correo de un cliente existente a quien escriba su cédula.
     * Solo se devuelve el nombre; el correo lo escribe el propio cliente.
     */
    public function consultarSri(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $token = trim((string) ($_GET['token'] ?? ''));
        $ident = preg_replace('/\D/', '', (string) ($_GET['identificacion'] ?? ''));
        if ($this->service->getConfigPublica($token) === null || !in_array(strlen($ident), [10, 13], true)) {
            echo json_encode(['ok' => false]);
            exit;
        }
        try {
            $r = (new \App\Services\SriIdentificationService())->consultar($ident);
            $nombre = ($r['ok'] ?? false) ? trim((string) ($r['data']['nombre'] ?? '')) : '';
            echo json_encode($nombre !== '' ? ['ok' => true, 'nombre' => $nombre] : ['ok' => false]);
        } catch (\Throwable) {
            echo json_encode(['ok' => false]);
        }
        exit;
    }

    private function configOError(): ?array
    {
        $cfg = $this->service->getConfigPublica(trim((string) ($_GET['token'] ?? '')));
        if ($cfg === null) {
            $this->render('error', ['mensaje' => 'Este enlace no está disponible o ha sido desactivado.']);
        }
        return $cfg;
    }

    private function render(string $vista, array $datos): void
    {
        $datos['tipos'] = ClienteQrRules::TIPOS;
        $datos['token'] = $datos['token'] ?? trim((string) ($_GET['token'] ?? ''));
        if ($vista === 'error') {
            http_response_code(404);
        }
        extract($datos);
        require MVC_APP . '/views/publica/registro-cliente/' . $vista . '.php';
    }

    private function ipCliente(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = trim(explode(',', (string) $_SERVER[$k])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
