<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\Helpers\VendedoresModulo;
use Exception;

/**
 * Reglas de "Vendedores que puede ver — {módulo}" (vendedores ocultos a un
 * usuario en un módulo), que se configuran en /config/permisos-modulos.
 */
class VendedorOcultoRules
{
    /**
     * Solo el administrador (nivel 2) o el superadministrador (nivel 3) configuran
     * esta lista. Se valida aquí además de en el controlador, por si el Service
     * se invoca desde otro flujo.
     */
    public function validarActor(int $nivelActor): void
    {
        if ($nivelActor < 2) {
            throw new Exception('Solo un administrador puede configurar los vendedores que ve un usuario.');
        }
    }

    /** La lista solo tiene efecto sobre usuarios de nivel 1: los niveles 2 y 3 ven a todos. */
    public function validarUsuarioDestino(?array $usuario): void
    {
        if (!$usuario) {
            throw new Exception('El usuario no existe.');
        }
        if ((int) ($usuario['nivel'] ?? 0) >= 2) {
            throw new Exception('Los administradores ven las ventas de todos los vendedores; no necesitan esta configuración.');
        }
    }

    /** El módulo debe estar en el catálogo de módulos que admiten esta configuración. */
    public function validarModulo(string $modulo): void
    {
        if (VendedoresModulo::definicion($modulo) === null) {
            throw new Exception('Este módulo no admite limitar los vendedores por usuario.');
        }
    }

    /** El vendedor debe existir en la empresa elegida. */
    public function validarVendedor(?array $vendedor): void
    {
        if (!$vendedor) {
            throw new Exception('El vendedor no existe en esta empresa.');
        }
    }

    /**
     * Siempre debe quedar al menos un vendedor visible: con todos ocultos el
     * reporte no mostraría nada y el usuario no sabría por qué. Para que no vea
     * el reporte, se le quita el permiso de Ver.
     */
    public function validarQuedaAlgunoVisible(int $totalVendedores, int $ocultosTrasCambio): void
    {
        if ($totalVendedores > 0 && $ocultosTrasCambio >= $totalVendedores) {
            throw new Exception('Debe quedar al menos un vendedor visible. Si el usuario no debe ver el reporte, quítele el permiso de Ver.');
        }
    }
}
