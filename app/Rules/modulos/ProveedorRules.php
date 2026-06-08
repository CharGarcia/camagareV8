<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class ProveedorRules
{
    /**
     * Valida los datos requeridos para crear/actualizar un proveedor.
     */
    public function validar(array $data): void
    {
        $errores = [];

        if (empty($data['id_empresa'])) {
            $errores[] = 'El identificador de la empresa es obligatorio.';
        }

        if (empty($data['id_usuario'])) {
            $errores[] = 'El usuario responsable es obligatorio.';
        }

        if (empty(trim($data['razon_social'] ?? ''))) {
            $errores[] = 'La razón social / nombre es obligatorio.';
        } elseif (mb_strlen(trim($data['razon_social'])) > 200) {
            $errores[] = 'La razón social no puede exceder 200 caracteres.';
        }

        if (mb_strlen(trim($data['direccion'] ?? '')) > 255) {
            $errores[] = 'La dirección no puede exceder 255 caracteres.';
        }

        if (mb_strlen(trim($data['provincia'] ?? '')) > 100) {
            $errores[] = 'La provincia no puede exceder 100 caracteres.';
        }

        if (mb_strlen(trim($data['ciudad'] ?? '')) > 100) {
            $errores[] = 'La ciudad no puede exceder 100 caracteres.';
        }

        $tipo_id = trim($data['tipo_id_proveedor'] ?? '');
        $identificacion = trim($data['identificacion'] ?? '');

        if (empty($identificacion)) {
            $errores[] = 'El número de identificación es obligatorio.';
        } else {
            if ($tipo_id === '02') { // Cédula
                if (!preg_match('/^[0-9]{10}$/', $identificacion)) {
                    $errores[] = 'La Cédula debe tener exactamente 10 dígitos numéricos.';
                }
            } elseif ($tipo_id === '01') { // RUC
                if (!preg_match('/^[0-9]{13}$/', $identificacion)) {
                    $errores[] = 'El RUC debe tener exactamente 13 dígitos numéricos.';
                } else {
                    $sufijo = substr($identificacion, -3);
                    if ($sufijo !== '001' && $sufijo !== '002') {
                        $errores[] = 'El RUC debe terminar en 001 o 002.';
                    }
                }
            } elseif ($tipo_id === '03') { // Pasaporte
                if (mb_strlen($identificacion) > 20) {
                    $errores[] = 'El Pasaporte permite letras y números, pero no puede exceder los 20 caracteres.';
                }
            } else {
                if (mb_strlen($identificacion) > 30) {
                    $errores[] = 'El número de identificación no puede exceder 30 caracteres.';
                }
            }
        }

        if (empty($tipo_id)) {
            $errores[] = 'El tipo de identificación es obligatorio.';
        }

        if (!empty(trim($data['email'] ?? ''))) {
            $emails = array_map('trim', explode(',', trim($data['email'])));
            foreach ($emails as $email) {
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errores[] = "El formato del correo electrónico '{$email}' es inválido.";
                    break;
                }
            }
        }

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }
    }
}
