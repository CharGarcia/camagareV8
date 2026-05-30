<?php
/**
 * Conexión PostgreSQL (PDO) singleton
 */

/* declare(strict_types=1);

namespace App\core;

class Database
{
    private static ?\PDO $connection = null;

    public static function getConnection(): \PDO
    {
        if (self::$connection === null) {
            $config = require \MVC_CONFIG . '/database.php';
            $driver = $config['driver'] ?? 'pgsql';
            $host = $config['host'] ?? '127.0.0.1';
            $port = (int) ($config['port'] ?? 5432);
            $dbname = $config['name'] ?? '';
            $user = $config['user'] ?? '';
            $pass = $config['pass'] ?? '';

            if ($driver !== 'pgsql') {
                throw new \RuntimeException('Solo está soportado PostgreSQL (driver pgsql).');
            }

            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $dbname);

            $opts = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ];

            self::$connection = new \PDO($dsn, $user, $pass, $opts);

            self::$connection->exec("SET client_encoding TO 'UTF8'");
            self::$connection->exec("SET TIME ZONE 'America/Guayaquil'");
        }

        return self::$connection;
    }

    public static function close(): void
    {
        self::$connection = null;
    }
}
 */

 
/**
 * Conexión PostgreSQL (PDO) singleton
 */

declare(strict_types=1);

namespace App\core;

class Database
{
    private static ?\PDO $connection = null;

    public static function getConnection(): \PDO
    {
        if (self::$connection === null) {
            $config = require \MVC_CONFIG . '/database.php';
            $driver = $config['driver'] ?? 'pgsql';
            $host = $config['host'] ?? '127.0.0.1';
            $port = (int) ($config['port'] ?? 5432);
            $dbname = $config['name'] ?? '';
            $user = $config['user'] ?? '';
            $pass = $config['pass'] ?? '';

            if ($driver !== 'pgsql') {
                throw new \RuntimeException('Solo está soportado PostgreSQL (driver pgsql).');
            }

            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $dbname);

            $opts = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ];

            self::$connection = new \PDO($dsn, $user, $pass, $opts);

            self::$connection->exec("SET client_encoding TO 'UTF8'");
            self::$connection->exec("SET TIME ZONE 'America/Guayaquil'");
        }

        return self::$connection;
    }

    public static function close(): void
    {
        self::$connection = null;
    }
}
