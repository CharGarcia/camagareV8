<?php

//para buscar los productos
$ruc_empresa = $_SESSION['ruc_empresa'];
$con = conenta_login();
//and nombre_producto LIKE '%" . mysqli_real_escape_string($con,($_GET['term'])) . "%'
$sql = "SELECT * FROM productos_servicios where ruc_empresa = '$ruc_empresa' LIMIT 0, 500 ";
$res = mysqli_query($con,$sql);
$arreglo_productos = array();
if (mysqli_num_rows($res) ==0){
	array_push($arreglo_productos,"No hay datos");
}else{
while($palabras = mysqli_fetch_array($res)){
	$id_producto=$palabras['id'];
		$row_array['value'] = $palabras['nombre_producto'];
		$row_array['id']=$id_producto;
		$row_array['nombre']=$palabras['nombre_producto'];
		$row_array['precio']=$palabras['precio_producto'];
		$row_array['tipo']=$palabras['tipo_produccion'];
	array_push($arreglo_productos,$row_array);
}
}
mysqli_close($con);
?>