<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Texto "por concepto de: …" de los mensajes de cobro por WhatsApp (Cuentas por Cobrar,
 * Factura de Venta y la automatización de CxC).
 *
 * Meta solo acepta el texto de la plantilla aprobada: lo que el cliente lee sobre qué se le
 * cobra viaja como UNA variable del cuerpo. Este helper la arma con las descripciones de los
 * ítems de los documentos, sin repetir conceptos, y la deja dentro de las reglas de Meta para
 * un parámetro de texto (sin saltos de línea ni tabulaciones, sin más de 4 espacios seguidos,
 * nunca vacía).
 */
final class ConceptosCobro
{
    /** Largo máximo del concepto: el cuerpo completo del mensaje no puede pasar de 1024 caracteres. */
    public const MAX = 250;

    /**
     * Une las descripciones sin repetir (sin distinguir mayúsculas, tildes ni espacios de más),
     * en el orden en que llegan. Si no caben en $max, corta y agrega "y N concepto(s) más".
     * Sin descripciones devuelve $respaldo.
     */
    public static function armar(array $descripciones, int $max = self::MAX, string $respaldo = 'documentos pendientes de pago'): string
    {
        $unicos = [];
        foreach ($descripciones as $d) {
            $texto = self::limpiar((string) $d);
            if ($texto === '' || $texto === '(sin descripción)') {
                continue;
            }
            $clave = self::clave($texto);
            if (!isset($unicos[$clave])) {
                $unicos[$clave] = $texto;
            }
        }
        if (!$unicos) {
            return self::limpiar($respaldo, $max);
        }

        $lista = array_values($unicos);
        $total = count($lista);
        $out   = '';
        foreach ($lista as $i => $texto) {
            $candidato = $out === '' ? $texto : $out . ', ' . $texto;
            $restan    = $total - $i - 1;
            $cola      = $restan > 0 ? " y {$restan} concepto(s) más" : '';
            if (mb_strlen($candidato . $cola) > $max) {
                if ($out === '') {
                    // Ni el primero cabe entero: se recorta.
                    return mb_substr($texto, 0, max(1, $max - 1)) . '…';
                }
                $restan = $total - $i;
                return $out . " y {$restan} concepto(s) más";
            }
            $out = $candidato;
        }
        return $out;
    }

    /**
     * Deja un texto (escrito por el usuario o armado) apto como parámetro de Meta: sin saltos
     * de línea ni tabulaciones, espacios colapsados y recortado a $max.
     */
    public static function limpiar(string $texto, int $max = self::MAX): string
    {
        $texto = TextoUnicodeHelper::nfc($texto);
        $texto = trim((string) preg_replace('/\s+/u', ' ', $texto));
        if (mb_strlen($texto) > $max) {
            $texto = rtrim(mb_substr($texto, 0, $max - 1)) . '…';
        }
        return $texto;
    }

    /** Clave de comparación: minúsculas, sin tildes y sin signos al final. */
    private static function clave(string $texto): string
    {
        $t = mb_strtolower($texto);
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u']);
        return rtrim($t, " .,;:-");
    }
}
