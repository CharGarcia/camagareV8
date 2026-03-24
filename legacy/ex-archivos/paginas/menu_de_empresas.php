<?php
/**
 * Wrapper - Redirige a app/Controllers (MVC)
 * Acceso directo: redirect a /sistema/empresa
 * Incluido por módulos: delega al controlador (mismo comportamiento que antes)
 */
if (!defined('ROOT_PATH')) {
    require_once dirname(dirname(__DIR__)) . '/app/bootstrap.php';
}
if (basename($_SERVER['SCRIPT_FILENAME']) === 'menu_de_empresas.php') {
    header('Location: /sistema/empresa');
    exit;
}
require_once dirname(dirname(__DIR__)) . '/app/Controllers/EmpresaController.php';
(new EmpresaController())->menu();
