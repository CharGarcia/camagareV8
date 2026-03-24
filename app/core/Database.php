<?php
/**
 * Conexión MySQL singleton
 */

declare(strict_types=1);

namespace App\core;

class Database
{
    private static ?\mysqli $connection = null;

    public static function getConnection(): \mysqli
    {
        if (self::$connection === null) {
            $config = require MVC_CONFIG . '/database.php';
            self::$connection = new \mysqli(
                $config['host'],
                $config['user'],
                $config['pass'],
                $config['name']
            );
            if (self::$connection->connect_error) {
                throw new \RuntimeException(
                    'Error de conexión MySQL: ' . self::$connection->connect_error
                );
            }
            self::$connection->set_charset($config['charset'] ?? 'utf8mb4');
        }
        return self::$connection;
    }

    public static function close(): void
    {
        if (self::$connection !== null) {
            self::$connection->close();
            self::$connection = null;
        }
    }
}
