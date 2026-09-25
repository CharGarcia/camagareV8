<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

/**
 * Reglas de "Vendedores que puede ver" (Reporte de Ventas por Vendedor), que se
 * configuran en /config/permisos-modulos.
 */
class VendedorVisibleRules
{
    /**
     * Solo el administrador (nivel 2) o el superadministrador (nivel 3) configuran
     * esta lista. Se valida aquí además de en el controlador, por si el Service se
     * invoca desde otro flujo.
     */
    public function validarActor(int $nivelActor): void
    {
        if ($nivelActor < 2) {
            throw new Exception('Solo un administrador puede configurar los vendedores que ve un usuario.');
        }
    }

    /**
     * La lista solo tiene efecto sobre usuarios de nivel 1: los niveles 2 y 3 ya
     * ven las ventas de todos los vendedores.
     */
    public function validarUsuarioDestino(?array $usuario): void
    {
        if (!$usuario) {
            throw new Exception('El usuario no existe.');
        }
        if ((int) ($usuario['nivel'] ?? 0) >= 2) {
            throw new Exception('Los administradores ya ven las ventas de todos los vendedores; no necesitan esta configuración.');
        }
    }

    /** El vendedor debe existir en la empresa elegida. */
    public function validarVendedor(?array $vendedor): void
    {
        if (!$vendedor) {
            throw new Exception('El vendedor no existe en esta empresa.');
        }
    }
}
