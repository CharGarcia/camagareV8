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
        return self::conectar();
    }

    /** (Re)crea la conexión al MySQL viejo. Úsese tras un "server has gone away". */
    public static function reconnect(): PDO
    {
        self::$pdo = null;
        return self::conectar();
    }

    /** Crea la conexión y fija timeouts de sesión largos para que el servidor viejo no la corte por inactividad. */
    private static function conectar(): PDO
    {
        $c = self::config();
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['db']};charset=utf8";
        try {
            $pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 15,
            ]);
            // La migración intercala lecturas al MySQL viejo con escrituras largas a PostgreSQL; entre
            // lectura y lectura la conexión al viejo queda ociosa y el servidor la mata por wait_timeout
            // → "MySQL server has gone away". Se suben los timeouts de ESTA sesión para que no la corte por
            // inactividad (8h) ni por lecturas/escrituras lentas (10 min). No afecta a otras conexiones.
            foreach ([
                'SET SESSION wait_timeout = 28800',
                'SET SESSION interactive_timeout = 28800',
                'SET SESSION net_read_timeout = 600',
                'SET SESSION net_write_timeout = 600',
            ] as $sql) {
                try { $pdo->exec($sql); } catch (\Throwable $e) { /* algunos hostings lo restringen; no es crítico */ }
            }
        } catch (\Throwable $e) {
            throw new RuntimeException('No se pudo conectar a la BD anterior (MySQL): ' . $e->getMessage());
        }
        return self::$pdo = $pdo;
    }

    /**
     * ¿La excepción es una caída de conexión al MySQL viejo ("server has gone away" / "Lost connection")?
     * Se usa para reconectar y reintentar (la migración es idempotente).
     */
    public static function isGoneAway(\Throwable $e): bool
    {
        $msg = $e->getMessage();
        if (stripos($msg, 'gone away') !== false || stripos($msg, 'Lost connection') !== false) {
            return true;
        }
        // SQLSTATE HY000 + códigos MySQL 2006 (gone away) / 2013 (lost connection during query).
        return (bool) preg_match('/\b(2006|2013)\b/', $msg) && stripos($msg, 'HY000') !== false;
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
