<?php
/**
 * Repository: Sesiones Activas
 * Maneja el almacenamiento de tokens de sesión para control de acceso concurrente.
 */

declare(strict_types=1);

namespace App\repositories;

use PDO;

class SesionActivaRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \App\core\Database::getConnection();
    }

    /**
     * Crea una nueva sesión activa para el usuario.
     */
    public function crear(int $idUsuario, string $token, string $ip, string $userAgent): bool
    {
        $sql = "INSERT INTO sesiones_activas (id_usuario, session_token, ip, user_agent, created_at, ultima_actividad, activa)
                VALUES (:id_usuario, :token, :ip, :user_agent, NOW(), NOW(), TRUE)";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id_usuario' => $idUsuario,
            ':token'      => $token,
            ':ip'         => $ip,
            ':user_agent' => $userAgent,
        ]);
    }

    /**
     * Obtiene la sesión activa de un usuario (si existe).
     */
    public function obtenerSesionActiva(int $idUsuario): ?array
    {
        $sql = "SELECT * FROM sesiones_activas
                WHERE id_usuario = :id_usuario AND activa = TRUE
                ORDER BY ultima_actividad DESC
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id_usuario' => $idUsuario]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Verifica si un token de sesión es válido y está activo.
     */
    public function tokenEsValido(string $token): bool
    {
        $sql = "SELECT id FROM sesiones_activas WHERE session_token = :token AND activa = TRUE LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':token' => $token]);
        return $st->fetch() !== false;
    }

    /**
     * Desactiva todas las sesiones activas de un usuario.
     */
    public function desactivarTodasDelUsuario(int $idUsuario): bool
    {
        $sql = "UPDATE sesiones_activas SET activa = FALSE WHERE id_usuario = :id_usuario AND activa = TRUE";
        $st = $this->db->prepare($sql);
        return $st->execute([':id_usuario' => $idUsuario]);
    }

    /**
     * Desactiva una sesión específica por token.
     */
    public function desactivarPorToken(string $token): bool
    {
        $sql = "UPDATE sesiones_activas SET activa = FALSE WHERE session_token = :token";
        $st = $this->db->prepare($sql);
        return $st->execute([':token' => $token]);
    }

    /**
     * Actualiza la última actividad de una sesión.
     */
    public function actualizarActividad(string $token): void
    {
        $sql = "UPDATE sesiones_activas SET ultima_actividad = NOW() WHERE session_token = :token AND activa = TRUE";
        $st = $this->db->prepare($sql);
        $st->execute([':token' => $token]);
    }

    /**
     * Limpia sesiones antiguas (más de X horas sin actividad).
     */
    public function limpiarSesionesAntiguas(int $horas = 24): void
    {
        $sql = "UPDATE sesiones_activas SET activa = FALSE
                WHERE activa = TRUE AND ultima_actividad < NOW() - INTERVAL ':horas hours'";
        // Usar concatenación directa para el intervalo (valor interno del sistema, no del usuario)
        $sql = "UPDATE sesiones_activas SET activa = FALSE
                WHERE activa = TRUE AND ultima_actividad < NOW() - INTERVAL '{$horas} hours'";
        $st = $this->db->prepare($sql);
        $st->execute();
    }
}
