<?php
/**
 * Punto de entrada Clientes - MVC
 * URL: /sistema/cliente o /sistema/cliente.php
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Controllers/ClienteController.php';

(new ClienteController())->index();
