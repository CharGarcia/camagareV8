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
        $cfg = [
            'host' => (string) $xml->host_db,
            'user' => (string) $xml->user_db,
            'pass' => (string) $xml->pass_db,
            'name' => (string) $xml->db_name,
        ];
    }
    return $cfg;
}

/**
 * Devuelve conexión mysqli (singleton)
 * @return mysqli
 */
function getConnection()
{
    static $con = null;
    if ($con === null) {
        $config = _getDbConfig();
        $con = new mysqli($config['host'], $config['user'], $config['pass'], $config['name']);
        if (mysqli_connect_errno()) {
            throw new RuntimeException('Error de conexión: ' . mysqli_connect_error());
        }
        mysqli_set_charset($con, 'utf8');
    }
    return $con;
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
