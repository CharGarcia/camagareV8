<?php
/**
 * Middleware de autenticación de la WEB (sesión PHP).
 *
 * Lo invoca Application::run() en cada petición web con sesión, salvo los controladores y
 * acciones públicos (y nunca /api/v1, que usa ApiAuthMiddleware). Revalida, con frenos para
 * no consultar la BD en cada petición:
 *   - el token de sesión activa (sesión abierta en otro dispositivo), cada 60 s;
 *   - que la empresa activa siga habilitada (CLAUDE.md §6), cada 5 min, salvo nivel 3;
 *   - el respaldo del cambio temporal de IVA, cada 5 min y al cambiar el día.
 * Un error de BD en estas comprobaciones nunca bloquea el acceso.
 *
 * Hasta el 05-11-2026 esta clase existía pero el flujo web no la llamaba: las tres
 * comprobaciones no se ejecutaban (la de otro dispositivo solo la hacía el sondeo del navbar).
 */

declare(strict_types=1);

namespace App\middleware;

use App\Services\SesionActivaService;
use App\models\Empresa;

class AuthMiddleware
{
    /** Segundos entre comprobaciones del token de sesión (el UPDATE de actividad sigue a 5 min). */
    private const INTERVALO_TOKEN = 60;

    /** Segundos entre comprobaciones de empresa activa y del IVA temporal. */
    private const INTERVALO_EMPRESA = 300;

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

        // Token de sesión contra la BD: detecta que se inició sesión desde otro dispositivo.
        if (isset($_SESSION['session_token'])
            && time() - (int) ($_SESSION['_token_last_check'] ?? 0) >= self::INTERVALO_TOKEN) {
            try {
                $sesionSvc = new SesionActivaService();
                if (!$sesionSvc->validarToken((string) $_SESSION['session_token'])) {
                    self::forzarLogout('Su sesión fue cerrada porque se inició sesión desde otro dispositivo.');
                }
                $_SESSION['_token_last_check'] = time();
            } catch (\Throwable $e) {
                // Si la tabla aún no existe o hay error de BD, no bloquear el acceso.
            }
        }

        // Empresa activa en sesión todavía habilitada. Nivel 3 (superadmin) no se restringe:
        // debe poder entrar a una empresa inactiva para reactivarla o administrarla.
        if (!empty($_SESSION['id_empresa']) && (int) ($_SESSION['nivel'] ?? 0) !== 3) {
            if (time() - (int) ($_SESSION['_empresa_last_check'] ?? 0) > self::INTERVALO_EMPRESA) {
                try {
                    $empresaModel = new Empresa();
                    if (!$empresaModel->estaActiva((int) $_SESSION['id_empresa'])) {
                        self::forzarLogout('La empresa a la que pertenece su sesión fue desactivada. Contacte al administrador.');
                    }
                    $_SESSION['_empresa_last_check'] = time();
                } catch (\Throwable $e) {
                    // Error de BD: no bloquear el acceso.
                }
            }
        }

        // Cambio temporal de IVA (prestadores de servicios turísticos): respaldo del cron. Si llegó la
        // fecha desde o pasó la fecha hasta y el cron no corrió, se aplica o restaura
        // aquí, antes de que el usuario facture con la tarifa equivocada. Cada 5 min y,
        // además, en la primera petición después de medianoche (cambia el día).
        if (!empty($_SESSION['id_empresa'])) {
            $ultimaIva = (int) ($_SESSION['_iva_temporal_last_check'] ?? 0);
            if (time() - $ultimaIva > self::INTERVALO_EMPRESA || date('Y-m-d', $ultimaIva) !== date('Y-m-d')) {
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
     * ¿La petición la hizo JavaScript (fetch / XHR) y no el navegador al navegar? Un fetch
     * sin cabeceras propias igual manda `Sec-Fetch-Mode: cors|same-origin`; navegar, `navigate`.
     */
    private static function esPeticionAjax(): bool
    {
        if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
            return true;
        }
        if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            return true;
        }
        $modo = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_MODE'] ?? ''));
        return $modo !== '' && $modo !== 'navigate';
    }

    /**
     * Destruye la sesión y manda al login con el motivo. En una petición AJAX no redirige
     * (el fetch recibiría el HTML del login): responde 401 con JSON y la cabecera
     * `X-Sesion-Cerrada`, que public/js/csrf.js reconoce para avisar y llevar al login.
     */
    private static function forzarLogout(string $mensaje = ''): never
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

        if (self::esPeticionAjax()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Sesion-Cerrada: 1');
            header('X-Sesion-Mensaje: ' . rawurlencode($mensaje));
            echo json_encode(['ok' => false, 'sesion_cerrada' => true, 'error' => $mensaje, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: ' . BASE_URL . '/?sesion_cerrada=1&msg=' . urlencode($mensaje));
        exit;
    }
}
