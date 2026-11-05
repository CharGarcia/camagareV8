<?php
/**
 * Middleware de autenticación
 * Verifica sesión PHP y token de sesión activa en BD (control de acceso concurrente).
 */

declare(strict_types=1);

namespace App\middleware;

use App\Services\SesionActivaService;
use App\models\Empresa;

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
            header('Location: ' . BASE_URL . '/');
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

        // Validar que la empresa activa en sesión siga habilitada. Nivel 3 (superadmin)
        // no se restringe: debe poder entrar a una empresa inactiva para reactivarla o
        // administrarla. Se throttlea a cada 5 min (igual que el token) para no golpear
        // la BD en cada request.
        if (!empty($_SESSION['id_empresa']) && (int) ($_SESSION['nivel'] ?? 0) !== 3) {
            $ultimaVerificacion = $_SESSION['_empresa_last_check'] ?? 0;
            if (time() - $ultimaVerificacion > 300) {
                try {
                    $empresaModel = new Empresa();
                    if (!$empresaModel->estaActiva((int) $_SESSION['id_empresa'])) {
                        self::forzarLogout('La empresa a la que pertenece su sesión fue desactivada. Contacte al administrador.');
                    }
                    $_SESSION['_empresa_last_check'] = time();
                } catch (\Throwable $e) {
                    // Error de BD: no bloquear el acceso
                }
            }
        }

        // Métricas del sistema: en qué empresa trabaja el usuario hoy. Frenado a 5 min en la
        // sesión (o al cambiar de empresa/día); nunca bloquea la navegación.
        \App\Services\ActividadUsuarioService::registrarWeb();

        // Cambio temporal de IVA (prestadores de servicios turísticos): respaldo del cron. Si llegó la
        // fecha desde o pasó la fecha hasta y el cron no corrió, se aplica o restaura
        // aquí, antes de que el usuario facture con la tarifa equivocada. Cada 5 min y,
        // además, en la primera petición después de medianoche (cambia el día).
        if (!empty($_SESSION['id_empresa'])) {
            $ultimaIva = (int) ($_SESSION['_iva_temporal_last_check'] ?? 0);
            if (time() - $ultimaIva > 300 || date('Y-m-d', $ultimaIva) !== date('Y-m-d')) {
                $_SESSION['_iva_temporal_last_check'] = time();
                try {
                    \App\Services\modulos\IvaCambioTemporalService::crear()->procesarEmpresa((int) $_SESSION['id_empresa']);
                } catch (\Throwable $e) {
                    // Nunca bloquear la navegación por esto.
                }
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
        header('Location: ' . BASE_URL . '/?sesion_cerrada=1&msg=' . $msg);
        exit;
    }
}
