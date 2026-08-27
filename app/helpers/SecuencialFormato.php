<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Formato canónico del secuencial de un documento: 9 dígitos con ceros a la izquierda.
 *
 * POR QUÉ EXISTE
 *   La columna `secuencial` es de texto y los índices únicos que impiden repetir un número
 *   (uq_ingresos_secuencial_activo, uq_egresos_secuencial_activo, uix_ventas_secuencial_activo)
 *   comparan TEXTO. Mientras un flujo guarde '16' y otro '000000016', Postgres los considera
 *   valores distintos y el índice deja pasar el duplicado; lo mismo le pasa a la validación en
 *   PHP (existeSecuencial), que también compara cadenas.
 *
 *   Eso fue exactamente lo que ocurrió: los controladores de Ingresos y Egresos guardaban el
 *   valor formateado, pero los flujos automáticos —cobro con tarjeta al facturar, cobro de
 *   suscripciones, pagos desde compras y declaraciones— guardaban el entero pelado que devuelve
 *   SecuencialService en la clave 'secuencial' (la formateada es 'formateado'). El generador de
 *   números no se equivocaba: los cuenta con CAST(... AS BIGINT), así que veía ambos formatos
 *   igual. Quien no los veía igual era la barrera que debía impedir el choque.
 *
 * REGLA: todo lo que escriba un secuencial en la base pasa por aquí, así el formato no depende
 * de cuál de los muchos caminos de creación se haya usado.
 */
class SecuencialFormato
{
    /** Longitud del secuencial en los comprobantes del SRI. */
    public const LONGITUD = 9;

    /**
     * Devuelve el secuencial en formato canónico (9 dígitos).
     *
     * Un valor no numérico se devuelve tal cual, sin inventar ceros: si algún día llega un
     * secuencial con letras (dato heredado o mal capturado), es preferible que se guarde como
     * vino —y se note— a enmascararlo con un formato que no le corresponde.
     * null / cadena vacía se devuelven como null para no escribir '000000000'.
     */
    public static function normalizar(string|int|float|null $secuencial): ?string
    {
        if ($secuencial === null) {
            return null;
        }

        $valor = trim((string) $secuencial);
        if ($valor === '') {
            return null;
        }

        if (!ctype_digit($valor)) {
            return $valor;
        }

        return str_pad($valor, self::LONGITUD, '0', STR_PAD_LEFT);
    }
}
