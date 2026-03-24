<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
if (isset($_POST['id_bodega']) && isset($_POST['id_producto']) && isset($_POST['serie']) && isset($_POST['secuencial']) && !empty($_POST['id_bodega']) && !empty($_POST['id_producto']) && !empty($_POST['serie']) && !empty($_POST['secuencial'])) {
	$id_bodega = mysqli_real_escape_string($con, (strip_tags($_POST["id_bodega"], ENT_QUOTES)));
	$id_producto = mysqli_real_escape_string($con, (strip_tags($_POST["id_producto"], ENT_QUOTES)));
	$serie = mysqli_real_escape_string($con, (strip_tags($_POST["serie"], ENT_QUOTES)));
	$secuencial = mysqli_real_escape_string($con, (strip_tags($_POST["secuencial"], ENT_QUOTES)));

	$referencia_salida_inventario = "Venta según factura: " . $serie . "-" . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT);

	$busca_saldo_tmp = "SELECT sum(cantidad_tmp) as saldo_tmp FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "' and id_producto = '" . $id_producto . "' and id_bodega='" . $id_bodega . "'";
	$result_tmp = $con->query($busca_saldo_tmp);
	$saldo_producto_tmp = mysqli_fetch_array($result_tmp);
	$saldo_temp = $saldo_producto_tmp['saldo_tmp'];

	$busca_saldo_producto = "SELECT sum(cantidad_entrada-cantidad_salida) as saldo_final FROM inventarios WHERE ruc_empresa = '" . $ruc_empresa . "' and id_producto = '" . $id_producto . "' and id_bodega='" . $id_bodega . "' and referencia !='" . $referencia_salida_inventario . "' ";
	$result = $con->query($busca_saldo_producto);
	$saldo_producto = mysqli_fetch_array($result);
	$saldo_final = $saldo_producto['saldo_final'] - $saldo_temp;
	echo ($saldo_final);
} else {
	echo ("0");
}
