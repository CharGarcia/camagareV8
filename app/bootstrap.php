<?php
/**
 * Bootstrap del sistema - usa config/parametros.xml
 */
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

function _getDbConfig()
{
    static $cfg = null;
    if ($cfg === null) {
        $xml = simplexml_load_file(ROOT_PATH . '/config/parametros.xml');
        $port = isset($xml->port_db) ? (int) (string) $xml->port_db : 5432;
        $cfg = [
            'host' => (string) $xml->host_db,
            'port' => $port > 0 ? $port : 5432,
            'user' => (string) $xml->user_db,
            'pass' => (string) $xml->pass_db,
            'name' => (string) $xml->db_name,
        ];
    }
    return $cfg;
}

/**
 * Devuelve conexión PDO PostgreSQL (singleton vía App\core\Database).
 */
function getConnection(): \PDO
{
    require_once ROOT_PATH . '/bootstrap.php';

    return \App\core\Database::getConnection();
}

/**
 * Devuelve instancia de db para consultas (compatibilidad con scripts)
 * @return db
 */
function getDB()
{
    static $db = null;
    if ($db === null) {
        require_once ROOT_PATH . '/core/db.php';
        $db = new db();
    }
    return $db;
}
