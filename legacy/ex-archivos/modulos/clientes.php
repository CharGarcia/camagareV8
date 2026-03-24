<?php
/**
 * Redirige a /sistema/cliente (MVC)
 * Ya no se usa modulos/clientes.php como entrada
 */
$base = defined('BASE_URL') ? BASE_URL : '/sistema';
header('Location: ' . $base . '/cliente', true, 301);
exit;
