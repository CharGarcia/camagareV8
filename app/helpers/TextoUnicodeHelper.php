<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Normaliza texto Unicode a forma compuesta (NFC) antes de imprimirlo en PDF.
 *
 * Texto pegado desde macOS (Notas, Pages, Safari, etc.) suele llegar en forma
 * descompuesta (NFD): una letra base + una marca diacrítica suelta como
 * carácter aparte (p. ej. "n" + U+0303 en vez del carácter único "ñ").
 * En HTML se ve bien porque el navegador compone las marcas visualmente al
 * dibujar, pero las fuentes core de TCPDF (helvetica, WinAnsiEncoding) no
 * tienen glifo para una marca diacrítica suelta: la letra base se imprime
 * bien y la marca sale como "?" pegado a la letra (p. ej. "MONSEN?OR").
 */
final class TextoUnicodeHelper
{
    /**
     * Pares base+marca combinante (NFD) => carácter precompuesto (NFC).
     * Cubre los diacríticos que aparecen en textos en español y préstamos
     * comunes, para cuando la extensión intl (Normalizer) no está disponible.
     */
    private const MAPA_NFD_A_NFC = [
        "a\u{0301}" => 'á', "e\u{0301}" => 'é', "i\u{0301}" => 'í', "o\u{0301}" => 'ó', "u\u{0301}" => 'ú',
        "A\u{0301}" => 'Á', "E\u{0301}" => 'É', "I\u{0301}" => 'Í', "O\u{0301}" => 'Ó', "U\u{0301}" => 'Ú',
        "n\u{0303}" => 'ñ', "N\u{0303}" => 'Ñ',
        "u\u{0308}" => 'ü', "U\u{0308}" => 'Ü',
        "a\u{0300}" => 'à', "e\u{0300}" => 'è', "i\u{0300}" => 'ì', "o\u{0300}" => 'ò', "u\u{0300}" => 'ù',
        "A\u{0300}" => 'À', "E\u{0300}" => 'È', "I\u{0300}" => 'Ì', "O\u{0300}" => 'Ò', "U\u{0300}" => 'Ù',
        "a\u{0302}" => 'â', "e\u{0302}" => 'ê', "i\u{0302}" => 'î', "o\u{0302}" => 'ô', "u\u{0302}" => 'û',
        "A\u{0302}" => 'Â', "E\u{0302}" => 'Ê', "I\u{0302}" => 'Î', "O\u{0302}" => 'Ô', "U\u{0302}" => 'Û',
        "c\u{0327}" => 'ç', "C\u{0327}" => 'Ç',
    ];

    public static function nfc(?string $texto): string
    {
        $texto = (string) $texto;
        if ($texto === '') {
            return $texto;
        }
        if (class_exists(\Normalizer::class)) {
            $normalizado = \Normalizer::normalize($texto, \Normalizer::FORM_C);
            if ($normalizado !== false) {
                return $normalizado;
            }
        }
        return strtr($texto, self::MAPA_NFD_A_NFC);
    }

    /** Aplica nfc() a todos los strings de un array, recursivamente (incluye subarrays anidados). */
    public static function nfcArray(array $datos): array
    {
        foreach ($datos as $k => $v) {
            if (is_string($v)) {
                $datos[$k] = self::nfc($v);
            } elseif (is_array($v)) {
                $datos[$k] = self::nfcArray($v);
            }
        }
        return $datos;
    }
}
