<?php

declare(strict_types=1);

namespace App\Services\MigracionMysql;

use PDO;
use RuntimeException;

/**
 * Conexión de SOLO LECTURA a la BD MySQL/MariaDB del sistema anterior (otro servidor).
 * Credenciales en config/parametros.xml (no versionado): mysql_legacy_host/port/user/pass/db,
 * con fallback a los valores por defecto conocidos.
 */
class LegacyMysqlConnection
{
    private static ?PDO $pdo = null;

    /** Devuelve (y cachea) la conexión PDO al MySQL viejo. */
    public static function get(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $c = self::config();
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['db']};charset=utf8";
        try {
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 10,
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('No se pudo conectar a la BD anterior (MySQL): ' . $e->getMessage());
        }
        return self::$pdo;
    }

    /** Prueba de conexión: [ok, mensaje, server]. No lanza. */
    public static function probar(): array
    {
        try {
            $pdo = self::get();
            $ver = $pdo->query('SELECT VERSION() AS v')->fetchColumn();
            return ['ok' => true, 'mensaje' => 'Conexión exitosa', 'server' => (string) $ver];
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage(), 'server' => null];
        }
    }

    private static function config(): array
    {
        $cfg = ['host' => 'camagare.com.ec', 'port' => 3306, 'user' => 'Char', 'pass' => 'CmGr1980', 'db' => 'sistema'];
        $file = (defined('MVC_CONFIG') ? MVC_CONFIG : dirname(__DIR__, 3) . '/config') . '/parametros.xml';
        if (is_file($file)) {
            $xml = @simplexml_load_file($file);
            if ($xml !== false) {
                if (!empty($xml->mysql_legacy_host)) $cfg['host'] = (string) $xml->mysql_legacy_host;
                if (!empty($xml->mysql_legacy_port)) $cfg['port'] = (int) $xml->mysql_legacy_port;
                if (!empty($xml->mysql_legacy_user)) $cfg['user'] = (string) $xml->mysql_legacy_user;
                if (!empty($xml->mysql_legacy_pass)) $cfg['pass'] = (string) $xml->mysql_legacy_pass;
                if (!empty($xml->mysql_legacy_db))   $cfg['db']   = (string) $xml->mysql_legacy_db;
            }
        }
        return $cfg;
    }
}
