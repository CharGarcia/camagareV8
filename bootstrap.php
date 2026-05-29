<?php
/**
 * Bootstrap - Inicialización del sistema
 */

declare(strict_types=1);

define('MVC_ROOT', __DIR__);
define('MVC_APP', MVC_ROOT . '/app');
define('MVC_CONFIG', MVC_ROOT . '/config');

// Composer Autoloader
if (file_exists(MVC_ROOT . '/vendor/autoload.php')) {
    require_once MVC_ROOT . '/vendor/autoload.php';
}

// Producción (raíz del dominio): crear config/local.php con ['base_url' => ''] (ver local.php.example)
$localCfgFile = MVC_CONFIG . '/local.php';
if (is_file($localCfgFile)) {
    $localCfg = require $localCfgFile;
    define('BASE_URL', is_array($localCfg) && array_key_exists('base_url', $localCfg)
        ? (string) $localCfg['base_url']
        : '/sistema/public');
    // APP_URL: URL pública completa (ej: https://www.camagare.com.ec). Sin barra final.
    define('APP_URL', is_array($localCfg) && !empty($localCfg['app_url'])
        ? rtrim((string) $localCfg['app_url'], '/')
        : '');
} else {
    define('BASE_URL', '/sistema/public');
    define('APP_URL', '');
}

// Cadenas UTF-8 (acentos, eñe) coherentes en todo el request
ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
if (function_exists('mb_http_output')) {
    mb_http_output('UTF-8');
}
if (function_exists('mb_language')) {
    mb_language('uni');
}

// Flags estándar para json_encode en todo el sistema
if (!defined('JSON_FLAGS')) {
    define('JSON_FLAGS', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// Configuración de zona horaria (Cargar desde config/app.php)
$appCfg = is_file(MVC_CONFIG . '/app.php') ? require MVC_CONFIG . '/app.php' : [];
date_default_timezone_set($appCfg['timezone'] ?? 'America/Guayaquil');

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
    $parts = explode('\\', $relativeClass);
    $fileName = array_pop($parts);

    // Resolver cada segmento de directorio probando: original, minúsculas, primera mayúscula
    $resolveDir = function (string $base, array $segments) use (&$resolveDir, $fileName): ?string {
        if (empty($segments)) {
            $file = $base . $fileName . '.php';
            return file_exists($file) ? $file : null;
        }
        $segment = array_shift($segments);
        $candidates = array_unique([$segment, strtolower($segment), ucfirst(strtolower($segment))]);
        foreach ($candidates as $candidate) {
            $path = $base . $candidate . '/';
            if (is_dir($path)) {
                $result = $resolveDir($path, $segments);
                if ($result !== null) {
                    return $result;
                }
            }
        }
        return null;
    };

    $file = $resolveDir($baseDir, $parts);
    if ($file !== null) {
        require $file;
    }
});
