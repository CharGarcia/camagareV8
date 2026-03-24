<?php
/**
 * Servicio para obtener configuraciones de correo por propósito.
 * Uso: EmailConfigService::getDataForSendEmail('recuperar_password')
 * Retorna array compatible con la función legacy sendEmail() o con PHPMailer.
 */

declare(strict_types=1);

namespace App\services;

use App\models\CorreoConfig;

class EmailConfigService
{
    /** @var array<string, array> Cache en memoria por codigo */
    private static array $cache = [];

    /**
     * Obtiene la configuración completa por código (incluye password_smtp).
     * Solo incluye configuraciones activas (status=1).
     */
    public static function getByCodigo(string $codigo): ?array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }
        if (isset(self::$cache[$codigo])) {
            return self::$cache[$codigo];
        }
        $model = new CorreoConfig();
        $config = $model->getPorCodigo($codigo);
        if ($config !== null) {
            self::$cache[$codigo] = $config;
        }
        return $config;
    }

    /**
     * Retorna un array en formato compatible con la función legacy sendEmail().
     * Campos: host, pass, port, emisor, empresa (nombre_remitente).
     *
     * Uso con sendEmail:
     *   $base = EmailConfigService::getDataForSendEmail('recuperar_password');
     *   if ($base) {
     *     $data = array_merge($base, [
     *       'asunto' => '...',
     *       'receptor' => '...',
     *       'template' => '...',
     *     ]);
     *     sendEmail($data);
     *   }
     */
    public static function getDataForSendEmail(string $codigo): ?array
    {
        $config = self::getByCodigo($codigo);
        if ($config === null) {
            return null;
        }
        $enc = $config['encryption'] ?? 'tls';
        if ($enc === '') {
            $enc = 'tls'; // fallback para compatibilidad
        }
        return [
            'host' => $config['host_smtp'] ?? '',
            'pass' => $config['password_smtp'] ?? '',
            'port' => (int) ($config['puerto_smtp'] ?? 587),
            'emisor' => $config['usuario_smtp'] ?: ($config['email'] ?? ''),
            'empresa' => $config['nombre_remitente'] ?: ($config['nombre'] ?? ''),
            'smtp_secure' => $enc,
        ];
    }

    /**
     * Retorna array listo para PHPMailer (host, port, user, pass, from, fromName, smtpSecure).
     */
    public static function getPhpMailerConfig(string $codigo): ?array
    {
        $config = self::getByCodigo($codigo);
        if ($config === null) {
            return null;
        }
        $enc = $config['encryption'] ?? 'tls';
        return [
            'host' => $config['host_smtp'] ?? '',
            'port' => (int) ($config['puerto_smtp'] ?? 587),
            'username' => $config['usuario_smtp'] ?: ($config['email'] ?? ''),
            'password' => $config['password_smtp'] ?? '',
            'from' => $config['email'] ?? '',
            'fromName' => $config['nombre_remitente'] ?: ($config['nombre'] ?? ''),
            'smtpSecure' => $enc === 'ssl' ? 'ssl' : ($enc === 'tls' ? 'tls' : ''),
        ];
    }

    /**
     * Limpia el cache (útil tras actualizar config en BD).
     */
    public static function clearCache(?string $codigo = null): void
    {
        if ($codigo === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[$codigo]);
        }
    }
}
