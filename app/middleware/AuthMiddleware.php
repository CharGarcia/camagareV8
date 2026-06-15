<?php
/**
 * Middleware de autenticación
 * Verifica sesión PHP y token de sesión activa en BD (control de acceso concurrente).
 */

declare(strict_types=1);

namespace App\middleware;

use App\Services\SesionActivaService;

class AuthMiddleware
{
    public static function handle(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['id_usuario'])) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if ($uri !== '' && strpos($uri, 'login') === false && $uri !== '/' && $uri !== '/sistema/') {
                $_SESSION['intended_url'] = $uri;
            }
            header('Location: /sistema/');
            exit;
        }

        // Validar token de sesión contra la BD (detecta acceso concurrente desde otro dispositivo)
        if (isset($_SESSION['session_token'])) {
            try {
                $sesionSvc = new SesionActivaService();
                if (!$sesionSvc->validarToken($_SESSION['session_token'])) {
                    // El token fue invalidado (otro dispositivo inició sesión); forzar logout
                    self::forzarLogout('Su sesión fue cerrada porque se inició sesión desde otro dispositivo.');
                }
            } catch (\Throwable $e) {
                // Si la tabla aún no existe o hay error de BD, no bloquear el acceso
                // (compatibilidad durante la migración)
            }
        }

        return true;
    }

    /**
     * Destruye la sesión y redirige al login con un mensaje.
     */
    private static function forzarLogout(string $mensaje = ''): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();

        $msg = urlencode($mensaje);
        header('Location: /sistema/?sesion_cerrada=1&msg=' . $msg);
        exit;
    }
}
