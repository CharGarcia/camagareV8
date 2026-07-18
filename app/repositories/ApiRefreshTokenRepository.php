<?php
/**
 * Repository: refresh tokens de la API móvil (tabla api_refresh_tokens).
 */

declare(strict_types=1);

namespace App\repositories;

use PDO;

class ApiRefreshTokenRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \App\core\Database::getConnection();
    }

    public function crear(int $idUsuario, string $tokenHash, string $sessionToken, string $dispositivoId, string $canal, \DateTimeImmutable $expiresAt, string $ip, string $userAgent): bool
    {
        $sql = "INSERT INTO api_refresh_tokens (id_usuario, token_hash, session_token, dispositivo_id, canal, ip, user_agent, expires_at)
                VALUES (:id_usuario, :token_hash, :session_token, :dispositivo_id, :canal, :ip, :user_agent, :expires_at)";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id_usuario'     => $idUsuario,
            ':token_hash'     => $tokenHash,
            ':session_token'  => $sessionToken,
            ':dispositivo_id' => $dispositivoId,
            ':canal'          => $canal,
            ':ip'             => $ip,
            ':user_agent'     => $userAgent,
            ':expires_at'     => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function obtenerVigentePorHash(string $tokenHash): ?array
    {
        $sql = "SELECT * FROM api_refresh_tokens
                WHERE token_hash = :hash AND revoked = FALSE AND expires_at > NOW()
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':hash' => $tokenHash]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function revocarPorHash(string $tokenHash): bool
    {
        $sql = "UPDATE api_refresh_tokens SET revoked = TRUE, revoked_at = NOW() WHERE token_hash = :hash";
        $st = $this->db->prepare($sql);
        return $st->execute([':hash' => $tokenHash]);
    }

    public function revocarTodosDelUsuario(int $idUsuario, string $canal = 'movil'): bool
    {
        $sql = "UPDATE api_refresh_tokens SET revoked = TRUE, revoked_at = NOW()
                WHERE id_usuario = :id_usuario AND canal = :canal AND revoked = FALSE";
        $st = $this->db->prepare($sql);
        return $st->execute([':id_usuario' => $idUsuario, ':canal' => $canal]);
    }

    public function marcarUsado(string $tokenHash): void
    {
        $sql = "UPDATE api_refresh_tokens SET last_used_at = NOW() WHERE token_hash = :hash";
        $st = $this->db->prepare($sql);
        $st->execute([':hash' => $tokenHash]);
    }
}
