<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class UnidadesMedidaRules
{
    public function validarTipo(array $data): void
    {
        $errores = [];

        if (empty($data['id_empresa'])) {
            $errores[] = 'El identificador de la empresa es obligatorio.';
        }

        $nombre = trim($data['nombre'] ?? '');
        if ($nombre === '') {
            $errores[] = 'El nombre del tipo de medida es obligatorio.';
        } elseif (mb_strlen($nombre) > 100) {
            $errores[] = 'El nombre no puede exceder 100 caracteres.';
        }

        if (mb_strlen(trim($data['codigo'] ?? '')) > 50) {
            $errores[] = 'El código no puede exceder 50 caracteres.';
        }

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }
    }

    public function validarUnidad(array $data): void
    {
        $errores = [];

        if (empty($data['id_empresa'])) {
            $errores[] = 'El identificador de la empresa es obligatorio.';
        }

        if (empty($data['id_tipo']) || (int)$data['id_tipo'] <= 0) {
            $errores[] = 'Debe seleccionar un tipo de medida.';
        }

        $nombre = trim($data['nombre'] ?? '');
        if ($nombre === '') {
            $errores[] = 'El nombre de la unidad es obligatorio.';
        } elseif (mb_strlen($nombre) > 100) {
            $errores[] = 'El nombre no puede exceder 100 caracteres.';
        }

        $abreviatura = trim($data['abreviatura'] ?? '');
        if ($abreviatura === '') {
            $errores[] = 'La abreviatura es obligatoria.';
        } elseif (mb_strlen($abreviatura) > 20) {
            $errores[] = 'La abreviatura no puede exceder 20 caracteres.';
        }

        if (mb_strlen(trim($data['codigo'] ?? '')) > 50) {
            $errores[] = 'El código no puede exceder 50 caracteres.';
        }

        $factorBase = $data['factor_base'] ?? 1;
        if (!is_numeric($factorBase) || (float)$factorBase < 0) {
            $errores[] = 'El factor base debe ser un número mayor o igual a cero.';
        }

        // Si es unidad base, el factor debe ser exactamente 1
        if (!empty($data['es_base']) && (float)$factorBase != 1.0) {
            $errores[] = 'La unidad base debe tener factor base igual a 1.';
        }

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }
    }
}
