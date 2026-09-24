<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Dígito verificador de cédula y RUC ecuatorianos.
 *
 * Es solo un AVISO, nunca un bloqueo: hay cédulas y RUC reales, emitidos por el
 * Registro Civil / SRI, que no superan el algoritmo (ver el comentario de
 * FacturaExpressQrRules::validarIdentificacion). Por eso ninguna Rules rechaza
 * con esto; se usa para advertir de un posible error de digitación. Si el SRI
 * encuentra la identificación, esa respuesta manda sobre el algoritmo.
 *
 * El espejo en JavaScript es `window.CMG_Identificacion` en `public/js/app.js`;
 * si se cambia el algoritmo aquí, cambiarlo también allí.
 */
class DigitoVerificador
{
    /**
     * Mensaje del problema o null si el número pasa (o si el tipo no tiene
     * algoritmo: pasaporte, exterior, consumidor final).
     *
     * @param string $tipo 'C' / 'CEDULA' para cédula, 'R' / 'RUC' para RUC.
     */
    public static function aviso(string $tipo, string $numero): ?string
    {
        $tipo   = strtoupper(trim($tipo));
        $numero = trim($numero);

        if ($tipo === 'C' || $tipo === 'CEDULA') {
            return self::cedulaValida($numero)
                ? null
                : 'La cédula ' . $numero . ' no supera el dígito verificador. Revise que esté bien digitada.';
        }
        if ($tipo === 'R' || $tipo === 'RUC') {
            return self::rucValido($numero)
                ? null
                : 'El RUC ' . $numero . ' no supera el dígito verificador. Revise que esté bien digitado.';
        }

        return null;
    }

    /** Módulo 10 sobre los 9 primeros dígitos (cédula de identidad). */
    public static function cedulaValida(string $cedula): bool
    {
        if (!preg_match('/^\d{10}$/', $cedula)) {
            return false;
        }
        if (!self::provinciaValida($cedula)) {
            return false;
        }
        if ((int) $cedula[2] > 5) {
            return false; // el tercer dígito de una cédula siempre es menor a 6
        }

        $suma = 0;
        for ($i = 0; $i < 9; $i++) {
            $valor = (int) $cedula[$i] * ($i % 2 === 0 ? 2 : 1);
            $suma += $valor > 9 ? $valor - 9 : $valor;
        }
        $verificador = (10 - ($suma % 10)) % 10;

        return $verificador === (int) $cedula[9];
    }

    /**
     * RUC ecuatoriano. Según el tercer dígito:
     *   0-5 persona natural  → cédula válida + establecimiento
     *   6   sector público   → módulo 11 sobre 8 dígitos, verificador en la 9.ª posición
     *   9   sociedad privada → módulo 11 sobre 9 dígitos, verificador en la 10.ª posición
     */
    public static function rucValido(string $ruc): bool
    {
        if (!preg_match('/^\d{13}$/', $ruc)) {
            return false;
        }
        if (!self::provinciaValida($ruc)) {
            return false;
        }

        $tercer = (int) $ruc[2];

        if ($tercer < 6) {
            return self::cedulaValida(substr($ruc, 0, 10));
        }
        if ($tercer === 6) {
            return self::modulo11($ruc, [3, 2, 7, 6, 5, 4, 3, 2], 8);
        }
        if ($tercer === 9) {
            return self::modulo11($ruc, [4, 3, 2, 7, 6, 5, 4, 3, 2], 9);
        }

        return false;
    }

    /** Los dos primeros dígitos: provincia 01-24, o 30 (ecuatorianos en el exterior). */
    private static function provinciaValida(string $numero): bool
    {
        $provincia = (int) substr($numero, 0, 2);
        return $provincia >= 1 && ($provincia <= 24 || $provincia === 30);
    }

    /** @param int[] $coeficientes */
    private static function modulo11(string $numero, array $coeficientes, int $posicionVerificador): bool
    {
        $suma = 0;
        foreach ($coeficientes as $i => $coef) {
            $suma += (int) $numero[$i] * $coef;
        }
        $residuo     = $suma % 11;
        $verificador = $residuo === 0 ? 0 : 11 - $residuo;

        return $verificador === (int) $numero[$posicionVerificador];
    }
}
