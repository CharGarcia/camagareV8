<?php
/**
 * Middleware de autenticación para la API móvil (/api/v1/*).
 * Valida el Bearer JWT del request y revalida el session_token contra
 * SesionActivaService (mismo mecanismo de bloqueo de sesión concurrente que usa
 * AuthMiddleware para la web). Si es válido, rellena $_SESSION con los mismos
 * campos que la sesión web para que el código existente (PermisoModuloTrait,
 * Helpers\Permisos, Services) funcione sin modificarse.
 */

declare(strict_types=1);

namespace App\middleware;

use App\Services\Api\JwtService;
use App\Services\SesionActivaService;

class ApiAuthMiddleware
{
    /**
     * @return array Los claims del JWT ya validado.
     */
    public static function handle(): array
    {
        $header = self::obtenerHeaderAuthorization();
        if ($header === '' || !str_starts_with($header, 'Bearer ')) {
            self::rechazar('NO_TOKEN', 'Falta el token de autenticación.');
        }
        $jwt = trim(substr($header, 7));

        try {
            $claims = (new JwtService())->verificar($jwt);
        } catch (\Throwable $e) {
            self::rechazar('TOKEN_INVALIDO', 'Token inválido o expirado.');
        }

        $idUsuario = (int) ($claims['sub'] ?? 0);
        $sessionToken = (string) ($claims['session_token'] ?? '');
        if ($idUsuario <= 0 || $sessionToken === '') {
            self::rechazar('TOKEN_INVALIDO', 'Token con datos incompletos.');
        }

        try {
            $sesionSvc = new SesionActivaService();
            if (!$sesionSvc->validarToken($sessionToken)) {
                self::rechazar('SESION_CERRADA', 'La sesión fue cerrada (se inició sesión desde otro dispositivo).');
            }
        } catch (\Throwable $e) {
            // Igual que AuthMiddleware web: si hay error de BD en la validación, no bloquear.
        }

        $_SESSION['id_usuario']    = $idUsuario;
        $_SESSION['id_empresa']    = !empty($claims['id_empresa']) ? (int) $claims['id_empresa'] : null;
        $_SESSION['nivel']         = (int) ($claims['nivel'] ?? 1);
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['nombre']        = (string) ($claims['nombre'] ?? '');

        return $claims;
    }

    private static function obtenerHeaderAuthorization(): string
    {
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return trim((string) $_SERVER['HTTP_AUTHORIZATION']);
        }
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return trim((string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }
        if (function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $k => $v) {
                if (strtolower($k) === 'authorization') {
                    return trim((string) $v);
                }
            }
        }
        return '';
    }

    private static function rechazar(string $codigo, string $mensaje): never
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => ['code' => $codigo, 'message' => $mensaje]], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
