<?php
//para buscar los clientes
$ruc_empresa = $_SESSION['ruc_empresa'];
$con = conenta_login();
$sql = "SELECT * FROM proveedores LIMIT 0 ,5000";
$res = mysqli_query($con,$sql);
$arreglo_proveedores = array();
if (mysqli_num_rows($res) ==0){
	array_push($arreglo_proveedores,"No hay datos");
}else{
while($palabras = mysqli_fetch_array($res)){
	$id_proveedor=$palabras['id_proveedor'];
		$row_array['value'] = $palabras['razon_social'];
		$row_array['id_proveedor']=$id_proveedor;
		$row_array['razon_social']=$palabras['razon_social'];
	array_push($arreglo_proveedores,$row_array);
}
}

/* Free connection resources. */
mysqli_close($con);




?>