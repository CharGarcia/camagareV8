<?php

//para buscar los clientes
$ruc_empresa = $_SESSION['ruc_empresa'];
$con = conenta_login();
$sql = "SELECT * FROM clientes where ruc_empresa = '$ruc_empresa' LIMIT 0 ,5000";
$res = mysqli_query($con,$sql);
$arreglo_php = array();
if (mysqli_num_rows($res) ==0){
	array_push($arreglo_php,"No hay datos");
}else{
while($palabras = mysqli_fetch_array($res)){
	$id_cliente=$palabras['id'];
		$row_array['value'] = $palabras['nombre'];
		$row_array['id']=$id_cliente;
		$row_array['nombre']=$palabras['nombre'];
	array_push($arreglo_php,$row_array);
}
}

/* Free connection resources. */
mysqli_close($con);

/* Toss back results as json encoded array. */
//return ($arreglo_php);
//echo json_encode($arreglo_php);

?>