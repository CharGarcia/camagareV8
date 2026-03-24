<?php
/**
 * Redirección al modal de clientes (MVC)
 * Mantiene compatibilidad con módulos legacy que incluyen este archivo.
 * El modal real está en: app/Views/cliente/modal.php
 */
require_once dirname(__DIR__) . '/conexiones/conectalogin.php';
$base = isset($base) ? $base : (defined('BASE_URL') ? BASE_URL : '/sistema');
include dirname(__DIR__) . '/app/Views/cliente/modal.php';
