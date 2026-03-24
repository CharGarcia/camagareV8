<?php
/**
 * Verifica permiso de lectura del módulo actual. Si no tiene permiso, redirige a sistema/empresa.
 * Incluir al inicio del módulo, después de tener sesión y $con.
 * Uso: require_once dirname(__DIR__) . '/paginas/verificar_permiso_modulo.php';
 */
if (!isset($con) || $con === null) {
    $con = conenta_login();
}
require_once dirname(__DIR__) . '/helpers/helpers.php';
$modulo_actual = basename($_SERVER['SCRIPT_FILENAME'], '.php');
verificarPermisoModulo($con, $modulo_actual);
