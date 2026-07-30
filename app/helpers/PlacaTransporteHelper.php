<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Normaliza y valida la placa vehicular ecuatoriana para el requisito de
 * operadoras de transporte comercial (Ficha Técnica SRI v2.34, Anexo 25, Tabla 33).
 *
 * Formato del vehículo: 3 letras + 4 dígitos (p. ej. ABC1234). Las motocicletas
 * llevan solo 3 dígitos (ABC123); en ese caso la Tabla 33 (caso 2) exige rellenar
 * con un cero a la izquierda del bloque numérico antes de enviarlo al SRI, de modo
 * que el XML siempre lleve 3 letras + 4 dígitos.
 */
class PlacaTransporteHelper
{
    /**
     * Deja solo A-Z0-9 en mayúsculas y, si el bloque numérico tiene 3 dígitos
     * (moto), lo rellena con un cero a la izquierda. No valida el formato final;
     * usar validar() para eso.
     */
    public static function normalizar(string $valor): string
    {
        $v = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $valor));
        if (preg_match('/^([A-Z]{3})([0-9]{3})$/', $v, $m)) {
            $v = $m[1] . '0' . $m[2];
        }
        return $v;
    }

    /** True si, ya normalizada, cumple 3 letras + 4 dígitos exigido por el SRI. */
    public static function esValida(string $valor): bool
    {
        return (bool) preg_match('/^[A-Z]{3}[0-9]{4}$/', self::normalizar($valor));
    }
}
