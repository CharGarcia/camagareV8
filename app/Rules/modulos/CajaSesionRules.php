<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class CajaSesionRules
{
    public function validarApertura(array $data): void
    {
        $errores = [];

        if (empty($data['id_empresa'])) {
            $errores[] = 'El identificador de la empresa es obligatorio.';
        }

        if (empty($data['id_punto_emision'])) {
            $errores[] = 'Debe seleccionar un punto de emisión.';
        }

        if (!isset($data['fondo_inicial']) || !is_numeric($data['fondo_inicial'])) {
            $errores[] = 'El fondo inicial debe ser un valor numérico.';
        } elseif ((float) $data['fondo_inicial'] < 0) {
            $errores[] = 'El fondo inicial no puede ser negativo.';
        }

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }
    }

    public function validarCierre(array $data): void
    {
        $errores = [];

        if (!isset($data['monto_contado']) || !is_numeric($data['monto_contado'])) {
            $errores[] = 'El monto contado es obligatorio y debe ser numérico.';
        } elseif ((float) $data['monto_contado'] < 0) {
            $errores[] = 'El monto contado no puede ser negativo.';
        }

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }
    }
}
