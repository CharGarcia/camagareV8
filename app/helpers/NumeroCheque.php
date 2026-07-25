<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Cálculo del siguiente número de cheque a partir del último emitido.
 *
 * Mismo criterio que ya usaban Egresos, Declaración de IVA y Declaración de
 * Retenciones: solo autoincrementa cuando el último número es puramente
 * numérico, conservando el relleno con ceros a la izquierda. Si no lo es
 * (o no hay cheques previos), devuelve '' para que el número se ingrese manual.
 */
class NumeroCheque
{
    public static function siguiente(?string $ultimo): string
    {
        $ultimo = trim((string) $ultimo);
        if ($ultimo === '' || !preg_match('/^(\d+)$/', $ultimo, $m)) {
            return '';
        }

        return str_pad((string) ((int) $m[1] + 1), strlen($ultimo), '0', STR_PAD_LEFT);
    }
}
