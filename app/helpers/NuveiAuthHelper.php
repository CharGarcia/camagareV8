<?php
/**
 * NuveiAuthHelper - Genera el header Auth-Token que exige la API de Nuvei (Paymentez).
 *
 * Fórmula documentada: base64("APP-CODE;UNIX-TIMESTAMP;UNIQ-TOKEN")
 * donde UNIQ-TOKEN = sha256(app_key + timestamp) en hexadecimal.
 * El token es válido solo ~15 segundos desde su generación, por eso se
 * construye en el momento de cada llamada (no se cachea).
 */

declare(strict_types=1);

namespace App\Helpers;

class NuveiAuthHelper
{
    public static function token(string $appCode, string $appKey): string
    {
        $timestamp = (string) time();
        $uniqToken = hash('sha256', $appKey . $timestamp);
        return base64_encode($appCode . ';' . $timestamp . ';' . $uniqToken);
    }
}
