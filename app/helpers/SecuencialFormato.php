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

    /**
     * Expresión SQL con el NÚMERO COMPLETO del documento en su formato canónico
     * `000-000-000000000`, para los buscadores de los listados.
     *
     * Por qué (17-09-2026): el usuario busca por el número tal como lo lee en el documento
     * —`001-001-000000001`—, pero no todas las filas lo tienen guardado así. Hay egresos con
     * `numero_egreso = '000000001'` (sin la serie) y pedidos con el secuencial sin ceros
     * (`'16'` en vez de `'000000016'`), heredados de flujos antiguos y de migraciones. Con
     * esta expresión el listado arma el número canónico a partir de la serie y el secuencial,
     * así que ese formato encuentra el documento aunque lo guardado esté corto.
     *
     * `GREATEST(LONGITUD, LENGTH(...))` en vez de un LPAD fijo: LPAD TRUNCA cuando el valor
     * es más largo que el ancho pedido, y un secuencial de 10+ dígitos (dato heredado) se
     * perdería. Todas las funciones usadas son IMMUTABLE, así que la expresión sirve también
     * dentro de un índice (los módulos con índice trigram la usan: ver MotorBusqueda).
     *
     * @param string $estab Columna del establecimiento (p. ej. 'v.establecimiento')
     * @param string $punto Columna del punto de emisión
     * @param string $sec   Columna del secuencial
     */
    public static function sqlNumeroCompleto(string $estab, string $punto, string $sec): string
    {
        $n = self::LONGITUD;
        return "COALESCE({$estab}, '') || '-' || COALESCE({$punto}, '') || '-' || "
             . "LPAD(COALESCE({$sec}, ''), GREATEST({$n}, LENGTH(COALESCE({$sec}, ''))), '0')";
    }
}
