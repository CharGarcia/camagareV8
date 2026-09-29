<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

/**
 * Validaciones del portal de representantes (formulario público del QR).
 * Los datos del alumno se validan además con AlumnoRules (mismas reglas que el
 * modal del sistema), así no hay dos criterios distintos para el mismo campo.
 */
class AlumnoPortalRules
{
    public const CONSUMIDOR_FINAL = '9999999999999';

    /** Quita espacios, guiones y puntos; pasaporte en mayúsculas. */
    public static function normalizarIdentificacion(string $v): string
    {
        return strtoupper((string) preg_replace('/[\s.\-]+/', '', trim($v)));
    }

    public function validarIdentificacion(string $ident): void
    {
        if ($ident === '' || $ident === self::CONSUMIDOR_FINAL
            || !preg_match('/^(\d{10}|\d{13}|[A-Z0-9]{3,20})$/', $ident)) {
            throw new Exception('Escriba su número de cédula (10 dígitos), RUC (13 dígitos) o pasaporte.');
        }
    }

    /** Tipo de identificación del cliente: 05 cédula, 04 RUC, 06 pasaporte. */
    public static function tipoIdCliente(string $ident): string
    {
        return match (true) {
            (bool) preg_match('/^\d{10}$/', $ident) => '05',
            (bool) preg_match('/^\d{13}$/', $ident) => '04',
            default => '06',
        };
    }

    public function validarCorreo(string $correo): void
    {
        if ($correo === '' || mb_strlen($correo) > 150 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Escriba un correo electrónico válido.');
        }
    }

    public static function limpiarTexto(string $v, int $max): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $v)), 0, $max);
    }

    /** Datos de facturación del representante (el cliente al que se factura). */
    public function validarCliente(array $d): void
    {
        if ($d['nombre'] === '') {
            throw new Exception('Escriba sus nombres completos o la razón social para la factura.');
        }
        $this->validarCorreo($d['email']);
    }

    /** Código de verificación: exactamente 6 dígitos. */
    public function validarFormatoCodigo(string $codigo): void
    {
        if (!preg_match('/^\d{6}$/', $codigo)) {
            throw new Exception('El código tiene 6 dígitos.');
        }
    }
}
