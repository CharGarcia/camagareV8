<?php
/**
 * Wrapper - Redirige a app/Controllers (MVC)
 */
if (!defined('ROOT_PATH')) {
    require_once dirname(dirname(__DIR__)) . '/app/bootstrap.php';
}
require_once dirname(dirname(__DIR__)) . '/app/Controllers/EmpresaController.php';
(new EmpresaController())->setEmpresa();
