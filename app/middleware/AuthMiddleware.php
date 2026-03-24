<?php
/**
 * Middleware de autenticación
 */

declare(strict_types=1);

namespace App\middleware;

class AuthMiddleware
{
    public static function handle(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['id_usuario'])) {
            header('Location: /sistema/');
            exit;
        }
        return true;
    }
}
