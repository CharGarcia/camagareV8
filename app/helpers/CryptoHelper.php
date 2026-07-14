<?php
/**
 * CryptoHelper - Cifrado/descifrado reutilizable de secretos en reposo
 * (ej. API keys de proveedores de IA en ia_config.api_key_cifrada).
 *
 * Replica el patrón AES-256-CBC ya usado en
 * SriDescargaAutomaticaService::encriptarClave()/desencriptarClave(),
 * como helper independiente para no acoplar módulos entre sí.
 */

declare(strict_types=1);

namespace App\Helpers;

class CryptoHelper
{
    public static function encriptar(string $valor): string
    {
        $key = self::derivarLlave();
        $iv  = random_bytes(16);
        $enc = openssl_encrypt($valor, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $enc);
    }

    public static function desencriptar(string $valorCifrado): string
    {
        if ($valorCifrado === '') {
            return '';
        }
        $key  = self::derivarLlave();
        $data = base64_decode($valorCifrado);
        if ($data === false || strlen($data) <= 16) {
            return '';
        }
        $iv  = substr($data, 0, 16);
        $enc = substr($data, 16);
        $dec = openssl_decrypt($enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $dec !== false ? $dec : '';
    }

    /**
     * Llave AES independiente de la contraseña de BD.
     * Lee IA_ENCRYPTION_KEY del entorno; si no existe usa APP_KEY de config/app.php.
     */
    private static function derivarLlave(): string
    {
        $envKey = getenv('IA_ENCRYPTION_KEY');
        if (!empty($envKey)) {
            return substr(hash('sha256', $envKey, true), 0, 32);
        }

        $appCfg = is_file(MVC_CONFIG . '/app.php') ? require MVC_CONFIG . '/app.php' : [];
        $appKey = $appCfg['key'] ?? $appCfg['app_key'] ?? '';
        if (!empty($appKey)) {
            return substr(hash('sha256', 'ia_cred_' . $appKey, true), 0, 32);
        }

        // Fallback: salt fijo en código (peor opción, solo si las anteriores no están).
        return substr(hash('sha256', 'CaMaGaRe_IA_Soporte_v1', true), 0, 32);
    }
}
