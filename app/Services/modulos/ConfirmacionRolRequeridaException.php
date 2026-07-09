<?php

declare(strict_types=1);

namespace App\Services\modulos;

/**
 * Señala que la actualización del empleado afecta al rol y existen roles abiertos
 * (borrador/generado, sin pagar) que deben regenerarse. El controlador la traduce
 * a una respuesta que pide confirmación al usuario antes de guardar.
 */
class ConfirmacionRolRequeridaException extends \Exception
{
    /** @var array<int,array{tipo_rol:string,periodo_anio:int,periodo_mes:int,estado:string}> */
    public array $roles;

    public function __construct(array $roles, string $message = '')
    {
        parent::__construct($message);
        $this->roles = $roles;
    }
}
