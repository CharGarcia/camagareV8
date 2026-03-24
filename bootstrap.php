<?php
/**
 * Bootstrap - Inicialización del sistema
 */

declare(strict_types=1);

define('MVC_ROOT', __DIR__);
define('MVC_APP', MVC_ROOT . '/app');
define('MVC_CONFIG', MVC_ROOT . '/config');

// Producción (raíz del dominio): crear config/local.php con ['base_url' => ''] (ver local.php.example)
$localCfgFile = MVC_CONFIG . '/local.php';
if (is_file($localCfgFile)) {
    $localCfg = require $localCfgFile;
    define('BASE_URL', is_array($localCfg) && array_key_exists('base_url', $localCfg)
        ? (string) $localCfg['base_url']
        : '/sistema/public');
} else {
    define('BASE_URL', '/sistema/public');
}

require_once MVC_APP . '/helpers/helpers.php';
require_once MVC_APP . '/helpers/funciones.php';
require_once MVC_APP . '/helpers/theme.php';
require_once MVC_APP . '/helpers/mail.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = MVC_APP . '/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
