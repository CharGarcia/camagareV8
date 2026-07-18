<?php
/**
 * Servicio: refresh tokens de la API móvil.
 * Token opaco (no JWT) de vida larga, guardado solo como hash (SHA-256; el token
 * ya tiene 256 bits de entropía aleatoria, no requiere un hash lento tipo bcrypt).
 * Rotación en cada uso: se revoca el usado y se emite uno nuevo, para poder
 * detectar reuso de un token robado.
 */

declare(strict_types=1);

namespace App\Services\Api;

use App\repositories\ApiRefreshTokenRepository;

class RefreshTokenService
{
    private const TTL_DIAS = 45;

    private ApiRefreshTokenRepository $repo;

    public function __construct()
    {
        $this->repo = new ApiRefreshTokenRepository();
    }

    /**
     * Emite un nuevo refresh token para el usuario/dispositivo, atado a la sesión
     * activa ($sessionToken de sesiones_activas) vigente en este momento. Devuelve
     * el token en claro (solo se persiste su hash).
     */
    public function emitir(int $idUsuario, string $sessionToken, string $dispositivoId, string $canal = 'movil'): string
    {
        $raw = bin2hex(random_bytes(32));
        $hash = self::hash($raw);
        $expiresAt = new \DateTimeImmutable('+' . self::TTL_DIAS . ' days');

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'desconocido';

        $this->repo->crear($idUsuario, $hash, $sessionToken, $dispositivoId, $canal, $expiresAt, $ip, $userAgent);

        return $raw;
    }

    /**
     * Valida un refresh token y, si es válido, lo revoca (rotación) dejándolo listo
     * para que el llamador emita uno nuevo con emitir(). NO valida por sí solo que la
     * sesión siga activa (eso lo hace el llamador contra SesionActivaService con el
     * session_token devuelto) — así un dispositivo desplazado por force_login no
     * puede seguir renovando su acceso aunque su refresh token siga sin usar.
     *
     * @return array{id_usuario:int, session_token:string, dispositivo_id:string, canal:string}
     * @throws \RuntimeException si el token no existe, ya fue usado/revocado o expiró.
     */
    public function validarYRotar(string $rawToken): array
    {
        $hash = self::hash($rawToken);
        $row = $this->repo->obtenerVigentePorHash($hash);
        if (!$row) {
            throw new \RuntimeException('Refresh token inválido o expirado.');
        }

        $this->repo->revocarPorHash($hash);
        $this->repo->marcarUsado($hash);

        return [
            'id_usuario'     => (int) $row['id_usuario'],
            'session_token'  => (string) $row['session_token'],
            'dispositivo_id' => (string) $row['dispositivo_id'],
            'canal'          => (string) $row['canal'],
        ];
    }

    public function revocar(string $rawToken): void
    {
        $this->repo->revocarPorHash(self::hash($rawToken));
    }

    public function revocarTodosDelUsuario(int $idUsuario, string $canal = 'movil'): void
    {
        $this->repo->revocarTodosDelUsuario($idUsuario, $canal);
    }

    private static function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
