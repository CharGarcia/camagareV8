<?php
/**
 * Handler para establecer empresa - MVC
 * Recibe POST del formulario de selección de empresa
 */
if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/app/bootstrap.php';
}
require_once __DIR__ . '/app/Controllers/EmpresaController.php';
(new EmpresaController())->setEmpresa();
