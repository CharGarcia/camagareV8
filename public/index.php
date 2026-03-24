<?php
/**
 * Punto de entrada único - Front Controller
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

// Depuración login: activar ANTES de BD y Application (si no, errores tempranos no se ven).
$__appCfg = require MVC_CONFIG . '/app.php';
if (!empty($__appCfg['show_login_errors'])) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
    ini_set('html_errors', '1');
    @ini_set('log_errors', '1');
    $logDir = MVC_ROOT . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @ini_set('error_log', $logDir . '/php_errors.log');
}

use App\core\Application;

// Debe coincidir con BASE_URL en bootstrap (local: /sistema/public, producción en raíz: '')
$app = new Application(rtrim(BASE_URL, '/'));
$app->run();
