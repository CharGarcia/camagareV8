<?php
/**
 * Servicio: JWT (HS256) para el access token de la API móvil.
 * Implementación propia y mínima (sin dependencia de composer) para no acoplar
 * el despliegue a una librería externa: solo firma/verifica HMAC-SHA256 sobre
 * header.payload en base64url, que es todo lo que este caso de uso necesita
 * (un único emisor y verificador: este mismo backend).
 */

declare(strict_types=1);

namespace App\Services\Api;

class JwtService
{
    private const ALG = 'HS256';

    private string $secret;

    public function __construct()
    {
        $this->secret = self::obtenerSecreto();
    }

    /**
     * Firma un payload y devuelve el JWT. $ttlSeconds es el tiempo de vida en segundos.
     */
    public function emitir(array $payload, int $ttlSeconds): string
    {
        $now = time();
        $header = ['alg' => self::ALG, 'typ' => 'JWT'];
        $claims = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
        ]);

        $segments = [
            self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES)),
            self::base64UrlEncode(json_encode($claims, JSON_UNESCAPED_SLASHES)),
        ];
        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $this->secret, true);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * Verifica firma y expiración. Devuelve los claims si es válido.
     * @throws \RuntimeException si el token es inválido, está mal formado o expiró.
     */
    public function verificar(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Token con formato inválido.');
        }
        [$headerB64, $payloadB64, $sigB64] = $parts;

        $header = json_decode((string) self::base64UrlDecode($headerB64), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== self::ALG) {
            throw new \RuntimeException('Algoritmo de token no soportado.');
        }

        $signingInput = $headerB64 . '.' . $payloadB64;
        $expected = hash_hmac('sha256', $signingInput, $this->secret, true);
        $actual = self::base64UrlDecode($sigB64);
        if ($actual === false || !hash_equals($expected, $actual)) {
            throw new \RuntimeException('Firma de token inválida.');
        }

        $claims = json_decode((string) self::base64UrlDecode($payloadB64), true);
        if (!is_array($claims)) {
            throw new \RuntimeException('Payload de token inválido.');
        }

        if (!isset($claims['exp']) || time() >= (int) $claims['exp']) {
            throw new \RuntimeException('Token expirado.');
        }

        return $claims;
    }

    /**
     * Lee el secreto de firma desde config/local.php (NO versionado). Falla explícito
     * si no está configurado: nunca se usa un secreto por defecto/predecible.
     */
    private static function obtenerSecreto(): string
    {
        $localFile = MVC_CONFIG . '/local.php';
        $local = is_file($localFile) ? (require $localFile) : [];
        $secret = is_array($local) ? ($local['api_jwt_secret'] ?? '') : '';

        if (!is_string($secret) || strlen($secret) < 32) {
            throw new \RuntimeException(
                "Falta configurar 'api_jwt_secret' (mínimo 32 caracteres aleatorios) en config/local.php. " .
                "Generar uno con: php -r \"echo bin2hex(random_bytes(32));\""
            );
        }

        return $secret;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string|false
    {
        $padded = str_pad($data, strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=');
        return base64_decode(strtr($padded, '-_', '+/'), true);
    }
}
