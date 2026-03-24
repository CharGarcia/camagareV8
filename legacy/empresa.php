<?php
/**
 * Punto de entrada Empresa - MVC
 * URL: /sistema/empresa.php
 */
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Controllers/EmpresaController.php';

$controller = new EmpresaController();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_empresa'])) {
    $controller->setEmpresa();
} else {
    $controller->menu();
}
