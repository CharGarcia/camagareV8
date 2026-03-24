<?php
include("../conexiones/conectalogin.php");
		$con = conenta_login();
if (isset($_POST['id_producto_pasa'])){
		$id_producto=mysqli_real_escape_string($con,(strip_tags($_POST["id_producto_pasa"],ENT_QUOTES)));
	
		$detalle_productos = "SELECT * FROM productos_servicios WHERE id = $id_producto ";
		$detalle_encontrados = $con->query($detalle_productos);
		$datos_productos = mysqli_fetch_array($detalle_encontrados);
		$precio_producto = $datos_productos['precio_producto'];
		echo ($precio_producto);
		}	
?>