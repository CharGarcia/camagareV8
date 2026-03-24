<?php
if (!include_once("../clases/saldo_producto_y_conversion.php")){
include_once("../clases/saldo_producto_y_conversion.php");
}
//para buscar el saldo de un producto en base a una bodega
if (isset($_POST['id_bodega']) && isset($_POST['id_producto']) && !empty($_POST['id_bodega']) && !empty($_POST['id_producto'])){
		$id_bodega=$_POST["id_bodega"];
		$id_producto=$_POST["id_producto"];
		$saldo_producto_factura = new saldo_producto_y_conversion();
		$con=null;
		echo number_format($saldo_producto_factura->existencias_productos($id_bodega, $id_producto, $con),4,'.','');	
}

//para buscar el saldo de un producto en base a una bodega para las mecanicas
if (isset($_POST['id_bodega_mecanica']) && isset($_POST['id_producto_mecanica']) && !empty($_POST['id_bodega_mecanica']) && !empty($_POST['id_producto_mecanica'])){
		$id_bodega=$_POST["id_bodega_mecanica"];
		$id_producto=$_POST["id_producto_mecanica"];
		$saldo_producto_factura = new saldo_producto_y_conversion();
		$con=null;
		echo number_format($saldo_producto_factura->existencias_productos_mecanica($id_bodega, $id_producto, $con),4,'.','');	
}
		
//para buscar el saldo de un producto y su conversion cuando se cambia el select de medida
if(isset($_POST['id_medida_seleccionada']) && isset($_POST['id_producto']) && isset($_POST['precio_venta'])){
	$id_medida_salida=$_POST['id_medida_seleccionada'];
	$id_producto=$_POST['id_producto'];
	$precio_venta=floatval($_POST['precio_venta']);
	$stock_actual=floatval($_POST['stock_tmp']);
	$dato_obtener=$_POST['dato_obtener'];
	$con=null;
$conversion_medidas= new saldo_producto_y_conversion();
echo number_format($conversion_medidas->conversion('0', $id_medida_salida, $id_producto, $precio_venta, $stock_actual, $con, $dato_obtener),4,'.','');
}	

//para buscar el saldo de un producto y su conversion cuando se cambia el select de lote
if (isset($_POST['opcion_lote']) && isset($_POST['id_producto']) && !empty($_POST['opcion_lote']) && !empty($_POST['id_producto'])){
		$lote=$_POST["opcion_lote"];
		$id_producto=$_POST["id_producto"];
		$id_bodega=$_POST["bodega"];
		$saldo_producto_factura = new saldo_producto_y_conversion();
		$con=null;
		echo number_format($saldo_producto_factura->existencias_productos_lote($id_bodega, $lote, $id_producto, $con),4,'.','');	
}

//para buscar el saldo de un producto y su conversion cuando se cambia el select de caducidad
if (isset($_POST['opcion_caducidad']) && isset($_POST['id_producto']) && !empty($_POST['opcion_caducidad']) && !empty($_POST['id_producto'])){
		$caducidad=$_POST["opcion_caducidad"];
		$id_producto=$_POST["id_producto"];
		$saldo_producto_factura = new saldo_producto_y_conversion();
		$con=null;
		echo number_format($saldo_producto_factura->existencias_productos_caducidad($caducidad, $id_producto, $con),4,'.','');	
}


//para traer el saldo solo de salidas de inventario y restarlo cuando estoy editando una factura porque no se debe tomar en cuenta la salida de 
//una factura mientras no esta ya autorizada la factura.

if (isset($_POST['id_bode']) && isset($_POST['id_prod']) && isset($_POST['editar_factura']) && !empty($_POST['id_bode']) && !empty($_POST['id_prod']) && !empty($_POST['editar_factura'])){
		$id_bodega=$_POST["id_bode"];
		$id_producto=$_POST["id_prod"];
		$referencia_editar = $_POST["editar_factura"];
		$saldo_solo_salidas = new saldo_producto_y_conversion();
		$con=null;
		echo number_format($saldo_solo_salidas->salidas_editar_factura($id_bodega, $id_producto, $con, $referencia_editar),4,'.','');		
} 
?>


