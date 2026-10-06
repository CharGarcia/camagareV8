<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\Helpers\PestanasModulo;
use Exception;

/**
 * Reglas de "Pestañas que puede ver" (pestañas de un módulo por usuario), que se
 * configuran en /config/permisos-modulos.
 */
class PestanasModuloRules
{
    /**
     * Solo el administrador (nivel 2) o el superadministrador (nivel 3) configuran
     * las pestañas. Se valida aquí además de en el controlador, por si el Service
     * se invoca desde otro flujo.
     */
    public function validarActor(int $nivelActor): void
    {
        if ($nivelActor < 2) {
            throw new Exception('Solo un administrador puede configurar las pestañas que ve un usuario.');
        }
    }

    /**
     * La configuración solo tiene efecto sobre usuarios de nivel 1: los niveles
     * 2 y 3 ven todas las pestañas.
     */
    public function validarUsuarioDestino(?array $usuario): void
    {
        if (!$usuario) {
            throw new Exception('El usuario no existe.');
        }
        if ((int) ($usuario['nivel'] ?? 0) >= 2) {
            throw new Exception('Los administradores ven todas las pestañas; no necesitan esta configuración.');
        }
    }

    /** El módulo debe ser configurable y la pestaña, una de las suyas (catálogo). */
    public function validarPestana(string $modulo, string $pestana): void
    {
        if (PestanasModulo::definicion($modulo) === null) {
            throw new Exception('Este módulo no tiene pestañas configurables.');
        }
        if (!PestanasModulo::existe($modulo, $pestana)) {
            throw new Exception('La pestaña no existe en este módulo.');
        }
    }
}
