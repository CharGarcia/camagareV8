<?php
/**
 * Service: SesionActiva
 * Lógica de negocio para control de sesiones concurrentes.
 */

declare(strict_types=1);

namespace App\Services;

use App\repositories\SesionActivaRepository;

class SesionActivaService
{
    private SesionActivaRepository $repo;

    public function __construct()
    {
        $this->repo = new SesionActivaRepository();
    }

    /**
     * Verifica si el usuario ya tiene una sesión activa en el canal indicado.
     * Retorna la sesión activa o null.
     */
    public function obtenerSesionActiva(int $idUsuario, string $canal = 'web'): ?array
    {
        return $this->repo->obtenerSesionActiva($idUsuario, $canal);
    }

    /**
     * Inicia una nueva sesión para el usuario en un canal ('web' o 'movil').
     * Desactiva las sesiones previas del MISMO canal (no las de otros canales,
     * así web y app pueden estar activas a la vez) y genera un nuevo token.
     *
     * @return string El token generado.
     */
    public function iniciarSesion(int $idUsuario, string $canal = 'web'): string
    {
        // Desactivar sesiones anteriores del mismo canal
        $this->repo->desactivarTodasDelUsuario($idUsuario, $canal);

        // Generar token único
        $token = bin2hex(random_bytes(32));

        $ip        = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'desconocido';

        $this->repo->crear($idUsuario, $token, $ip, $userAgent, $canal);

        return $token;
    }

    /**
     * Valida que el token de sesión actual siga activo en la BD.
     * Actualiza la última actividad si es válido.
     */
    public function validarToken(string $token): bool
    {
        $valido = $this->repo->tokenEsValido($token);
        if ($valido) {
            // Actualizar actividad cada 5 minutos para no hacer UPDATE en cada request
            $ultimaUpdate = $_SESSION['_sesion_last_touch'] ?? 0;
            if (time() - $ultimaUpdate > 300) {
                $this->repo->actualizarActividad($token);
                $_SESSION['_sesion_last_touch'] = time();
            }
        }
        return $valido;
    }

    /**
     * Cierra la sesión del usuario, desactivando el token en la BD.
     */
    public function cerrarSesion(string $token): void
    {
        $this->repo->desactivarPorToken($token);
    }
}
