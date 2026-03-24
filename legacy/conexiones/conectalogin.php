<?php
/**
 * Conexión a BD - usa config/parametros.xml
 */
function conenta_login()
{
    static $cfg = null;
    if ($cfg === null) {
        $ruta_param = dirname(__DIR__, 2) . '/config/parametros.xml';
        if (!file_exists($ruta_param)) {
            die('No existe config/parametros.xml');
        }
        $xml = simplexml_load_file($ruta_param);
        $cfg = [
            'host' => (string) $xml->host_db,
            'user' => (string) $xml->user_db,
            'pass' => (string) $xml->pass_db,
            'db'   => (string) $xml->db_name,
        ];
    }
    $con = @new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['db']);
    if ($con->connect_error) {
        die('Error de conexión: ' . $con->connect_error);
    }
    $con->set_charset('utf8');
    return $con;
}
