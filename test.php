<?php
require 'vendor/autoload.php';
define('MVC_CONFIG', require 'config/config.php');
require 'app/core/Database.php';
$db = \App\core\Database::getConnection();

// Check if retencion_venta_cabecera has tipo_ambiente
$st = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'retencion_venta_cabecera'");
echo "Columns in retencion_venta_cabecera:\n";
print_r($st->fetchAll(PDO::FETCH_COLUMN));

// Check what is in casilleros
$st2 = $db->query("SELECT * FROM casilleros_declaracion_sri WHERE origen = 'retenciones_ventas'");
echo "Casilleros retenciones_ventas:\n";
print_r($st2->fetchAll(PDO::FETCH_ASSOC));

// Check retenciones en la BD
$st3 = $db->query("SELECT * FROM retencion_venta_cabecera LIMIT 5");
echo "Retenciones ventas cabecera:\n";
print_r($st3->fetchAll(PDO::FETCH_ASSOC));

$st4 = $db->query("SELECT * FROM retencion_venta_detalle LIMIT 5");
echo "Retenciones ventas detalle:\n";
print_r($st4->fetchAll(PDO::FETCH_ASSOC));
