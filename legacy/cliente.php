<?php
/**
 * Punto de entrada Clientes - MVC
 * URL: /sistema/cliente.php
 */
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Controllers/ClienteController.php';

(new ClienteController())->index();
