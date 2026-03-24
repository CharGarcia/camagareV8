<?php
if (!include_once("../clases/saldo_producto_y_conversion.php")){
include_once("../clases/saldo_producto_y_conversion.php");
}
//para buscar el saldo de un producto

if (isset($_POST['id_bodega']) && isset($_POST['id_producto']) && !empty($_POST['id_bodega']) && !empty($_POST['id_producto'])){
		$id_bodega=$_POST["id_bodega"];
		$id_producto=$_POST["id_producto"];
		$saldo_producto_factura = new saldo_producto_y_conversion();
		$con=null;
		echo $saldo_producto_factura->existencias_productos($id_bodega, $id_producto, $con);	
}
		
//para buscar el saldo de un producto y su conversion cuando se cambia el select
if(isset($_POST['id_medida_seleccionada']) && isset($_POST['id_producto']) && isset($_POST['precio_venta'])){
	$id_medida_salida=$_POST['id_medida_seleccionada'];
	$id_producto=$_POST['id_producto'];
	$precio_venta=floatval($_POST['precio_venta']);
	$stock_actual=floatval($_POST['stock_tmp']);
	$con=null;
	
$conversion_medidas= new saldo_producto_y_conversion();
header('Content-Type: application/json');
echo json_encode ($conversion_medidas->conversion('0', $id_medida_salida, $id_producto, $precio_venta, $stock_actual, $con));
}	


//para traer el saldo solo de salidas de inventario y restarlo cuando estoy editando una factura porque no se debe tomar en cuenta la salida de 
//una factura mientras no esta ya autorizada la factura.

if (isset($_POST['id_bode']) && isset($_POST['id_prod']) && isset($_POST['editar_factura']) && !empty($_POST['id_bode']) && !empty($_POST['id_prod']) && !empty($_POST['editar_factura'])){
		$id_bodega=$_POST["id_bode"];
		$id_producto=$_POST["id_prod"];
		$referencia_editar = $_POST["editar_factura"];
		$saldo_solo_salidas = new saldo_producto_y_conversion();
		$con=null;
		echo $saldo_solo_salidas->salidas_editar_factura($id_bodega, $id_producto, $con, $referencia_editar);		
} 
?>


