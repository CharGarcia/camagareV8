<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
include_once("../clases/saldo_producto_y_conversion.php");
$saldo_producto = new saldo_producto_y_conversion();

//borrar las existencias temporales
$delete_inventario_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."'");

if($action == 'consignacion_venta'){
$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
SELECT null, det_con.id_producto, det_con.codigo_producto, det_con.nombre_producto, sum(det_con.cant_consignacion), 0, enc_con.id_cli_pro, enc_con.numero_consignacion, det_con.vencimiento, det_con.ruc_empresa, 0, det_con.lote, '".$id_usuario."' FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con ON det_con.codigo_unico=enc_con.codigo_unico WHERE det_con.ruc_empresa ='". $ruc_empresa ."' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='ENTRADA'  group by det_con.codigo_producto, det_con.lote");//group by det_con.codigo_producto, det_con.lote
$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
SELECT null, det_con.id_producto, det_con.codigo_producto, det_con.nombre_producto, 0, sum(det_con.cant_consignacion), enc_con.id_cli_pro, enc_con.numero_consignacion, det_con.vencimiento, det_con.ruc_empresa, 0, det_con.lote, '".$id_usuario."'  FROM detalle_consignacion det_con INNER JOIN encabezado_consignacion enc_con ON det_con.codigo_unico=enc_con.codigo_unico WHERE det_con.ruc_empresa ='". $ruc_empresa ."' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='FACTURA' ");//group by det_con.nombre_producto
$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
SELECT null, det_con.id_producto, det_con.codigo_producto, det_con.nombre_producto, 0, sum(det_con.cant_consignacion), enc_con.id_cli_pro, enc_con.numero_consignacion, det_con.vencimiento, det_con.ruc_empresa, 0, det_con.lote, '".$id_usuario."'  FROM detalle_consignacion det_con INNER JOIN encabezado_consignacion enc_con ON det_con.codigo_unico=enc_con.codigo_unico WHERE det_con.ruc_empresa ='". $ruc_empresa ."' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='DEVOLUCIÓN' ");//group by det_con.nombre_producto
	//todos los id temporales traidos para luego borrarlos
	$ides= array();
	$sql_filas_borrar = mysqli_query($con,"SELECT * FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."' and id_usuario = '".$id_usuario."'");
	while ($row_ides_temporales = mysqli_fetch_array($sql_filas_borrar)){
	$id_temp_iniciales=$row_ides_temporales["id_existencia_tmp"];
	$ides[]= array('id_tmp_iniciales'=>$id_temp_iniciales);
	}
$query_actualiza_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada), sum(cantidad_salida), id_bodega, id_medida, fecha_caducidad, ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida),lote, '".$id_usuario."'  FROM existencias_inventario_tmp WHERE ruc_empresa ='". $ruc_empresa ."' group by lote, id_producto");
//eliminar los ides tmp iniciales
	foreach ($ides as $id_tm){
	$delete_ides_tmp_iniciales = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE id_existencia_tmp='".$id_tm['id_tmp_iniciales']."';");
	}

}


if($action == 'general'){
$delete_inventario_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."'");
$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
SELECT null, id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada), sum(cantidad_salida), id_bodega, id_medida, fecha_vencimiento,ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida), lote, '".$id_usuario."' FROM inventarios WHERE ruc_empresa ='".$ruc_empresa."' group by id_producto, id_medida, id_bodega");

//selet para buscar linea por linea y ver si la medida es igual al producto o sino modificar esa linea
	$resultado= array();
	$sql_filas = mysqli_query($con,"SELECT * FROM existencias_inventario_tmp as exi_tmp LEFT JOIN productos_servicios as pro_ser ON pro_ser.id=exi_tmp.id_producto WHERE exi_tmp.ruc_empresa = '".$ruc_empresa."' and exi_tmp.id_usuario = '".$id_usuario."' and exi_tmp.cantidad_salida>0 ");
	while ($row_temporales = mysqli_fetch_array($sql_filas)){
	$id_producto=$row_temporales["id_producto"];
	$codigo_producto=$row_temporales["codigo_producto"];
	$nombre_producto=$row_temporales["nombre_producto"];
		//obtener medida del producto
		/*
		$sql_medida_producto = mysqli_query($con,"SELECT * FROM productos_servicios WHERE id = '".$id_producto."'");
		$row_producto = mysqli_fetch_array($sql_medida_producto);
		$id_medida_salida= $row_producto['id_unidad_medida'];
		*/
		$id_medida_salida= $row_temporales['id_unidad_medida'];
		
		
		$id_medida_entrada=$row_temporales["id_medida"];
		$cantidad_entrada_tmp = $row_temporales['cantidad_entrada'];
		$id_bodega = $row_temporales['id_bodega'];
		$caducidad = $row_temporales['fecha_caducidad'];
		$lote = $row_temporales['lote'];
	
		if ($id_medida_entrada != $id_medida_salida){
		$id_tmp=$row_temporales["id_existencia_tmp"];
		$cantidad_a_transformar = $row_temporales['cantidad_salida'];
		$total_saldo_producto= $saldo_producto->conversion($id_medida_entrada, $id_medida_salida, $id_producto, '0', $cantidad_a_transformar, $con, 'saldo');	
		$resultado[]= array('id_tmp'=>$id_tmp, 'id_producto'=>$id_producto, 'codigo_producto'=>$codigo_producto, 'nombre_producto'=>$nombre_producto, 'entrada'=>$cantidad_entrada_tmp, 'salida'=>$cantidad_a_transformar, 'id_bodega'=>$id_bodega, 'id_medida'=>$id_medida_salida, 'caducidad'=>$caducidad, 'saldo_convertido'=> $total_saldo_producto, 'lote'=> $lote );
		}
	}
	foreach ($resultado as $valor){
		$delete_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE id_existencia_tmp='".$valor['id_tmp']."';");
		$sql_actualizar=mysqli_query($con,"INSERT INTO existencias_inventario_tmp VALUES (null,'".$valor['id_producto']."','".$valor['codigo_producto']."','".$valor['nombre_producto']."','".$valor['entrada']."','".$valor['saldo_convertido']."','".$valor['id_bodega']."','".$valor['id_medida']."','".$valor['caducidad']."', '".$ruc_empresa."', '".$valor['saldo_convertido']."','".$valor['lote']."','".$id_usuario."' )");
	}
	//todos los id temporales traidos para luego borrarlos
	$ides= array();
	$sql_filas_borrar = mysqli_query($con,"SELECT * FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."' and id_usuario = '".$id_usuario."'");
	while ($row_ides_temporales = mysqli_fetch_array($sql_filas_borrar)){
	$id_temp_iniciales=$row_ides_temporales["id_existencia_tmp"];
	$ides[]= array('id_tmp_iniciales'=>$id_temp_iniciales);
	}
$query_actualiza_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada), sum(cantidad_salida), id_bodega, id_medida, fecha_caducidad, ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida),lote, '".$id_usuario."'  FROM existencias_inventario_tmp WHERE ruc_empresa ='". $ruc_empresa ."' group by id_bodega, id_producto, id_medida");
//eliminar los ides tmp iniciales
	foreach ($ides as $id_tm){
	$delete_ides_tmp_iniciales = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE id_existencia_tmp='".$id_tm['id_tmp_iniciales']."';");
	}
}


	
if($action == 'fecha_caducidad'){
$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada), sum(cantidad_salida), id_bodega, id_medida, fecha_vencimiento,ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida),lote, '".$id_usuario."'  FROM inventarios WHERE ruc_empresa ='". $ruc_empresa ."' group by id_bodega, id_producto, id_medida, fecha_vencimiento");

//selet para buscar linea por linea y ver si la medida es igual a la medida del producto o sino modificar esa linea
	$resultado= array();
	$sql_filas = mysqli_query($con,"SELECT * FROM existencias_inventario_tmp as exi_tmp LEFT JOIN productos_servicios as pro_ser ON pro_ser.id=exi_tmp.id_producto WHERE exi_tmp.ruc_empresa = '".$ruc_empresa."' and exi_tmp.id_usuario = '".$id_usuario."' and exi_tmp.cantidad_salida>0 ");
	while ($row_temporales = mysqli_fetch_array($sql_filas)){
	$id_producto=$row_temporales["id_producto"];
	$codigo_producto=$row_temporales["codigo_producto"];
	$nombre_producto=$row_temporales["nombre_producto"];
	$id_medida_salida= $row_temporales['id_unidad_medida'];
		//obtener medida del producto
		/*
		$sql_medida_producto = mysqli_query($con,"SELECT * FROM productos_servicios WHERE id = '".$id_producto."'");
		$row_producto = mysqli_fetch_array($sql_medida_producto);
		$id_medida_salida= $row_producto['id_unidad_medida'];
		*/
		$id_medida_entrada=$row_temporales["id_medida"];
		$cantidad_entrada_tmp = $row_temporales['cantidad_entrada'];
		$id_bodega = $row_temporales['id_bodega'];
		$lote = $row_temporales['lote'];
		$caducidad = $row_temporales['fecha_caducidad'];
			if ($id_medida_entrada != $id_medida_salida){
			$id_tmp=$row_temporales["id_existencia_tmp"];
			$cantidad_a_transformar = $row_temporales['cantidad_salida'];
			$total_saldo_producto= $saldo_producto->conversion($id_medida_entrada, $id_medida_salida, $id_producto, '0', $cantidad_a_transformar, $con, 'saldo');	
			$resultado[]= array('id_tmp'=>$id_tmp, 'id_producto'=>$id_producto, 'codigo_producto'=>$codigo_producto, 'nombre_producto'=>$nombre_producto, 'entrada'=>$cantidad_entrada_tmp, 'salida'=>$cantidad_a_transformar, 'id_bodega'=>$id_bodega, 'id_medida'=>$id_medida_salida, 'caducidad'=>$caducidad, 'saldo_convertido'=> $total_saldo_producto, 'lote'=> $lote );
			}
	}
	
	foreach ($resultado as $valor){
		$delete_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE id_existencia_tmp='".$valor['id_tmp']."';");
		$sql_actualizar=mysqli_query($con,"INSERT INTO existencias_inventario_tmp VALUES (null,'".$valor['id_producto']."','".$valor['codigo_producto']."','".$valor['nombre_producto']."','".$valor['entrada']."','".$valor['saldo_convertido']."','".$valor['id_bodega']."','".$valor['id_medida']."','".$valor['caducidad']."', '".$ruc_empresa."', '".$valor['saldo_convertido']."','".$valor['lote']."','".$id_usuario."' )");
	}
	//todos los id temporales traidos para luego borrarlos
	
	$ides= array();
	$sql_filas_borrar = mysqli_query($con,"SELECT * FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."' and id_usuario = '".$id_usuario."'");
	while ($row_ides_temporales = mysqli_fetch_array($sql_filas_borrar)){
	$id_temp_iniciales=$row_ides_temporales["id_existencia_tmp"];
	$ides[]= array('id_tmp_iniciales'=>$id_temp_iniciales);
	}
	
$query_actualiza_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada), sum(cantidad_salida), id_bodega, id_medida, fecha_caducidad, ruc_empresa, sum(cantidad_entrada-cantidad_salida),lote, '".$id_usuario."'  FROM existencias_inventario_tmp WHERE ruc_empresa ='". $ruc_empresa ."' group by id_bodega, id_producto, id_medida, fecha_caducidad");
//eliminar los ides tmp iniciales
	foreach ($ides as $id_tm){
	$delete_ides_tmp_iniciales = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE id_existencia_tmp='".$id_tm['id_tmp_iniciales']."';");
	}
}

if($action == 'lote'){
$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada), sum(cantidad_salida), id_bodega, id_medida, fecha_vencimiento,ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida),lote, '".$id_usuario."'  FROM inventarios WHERE ruc_empresa ='". $ruc_empresa ."' group by id_bodega, id_producto, id_medida, lote");

//selet para buscar linea por linea y ver si la medida es igual a la medida del producto o sino modificar esa linea
	$resultado= array();
	$sql_filas = mysqli_query($con,"SELECT * FROM existencias_inventario_tmp as exi_tmp LEFT JOIN productos_servicios as pro_ser ON pro_ser.id=exi_tmp.id_producto WHERE exi_tmp.ruc_empresa = '".$ruc_empresa."' and exi_tmp.id_usuario = '".$id_usuario."' and exi_tmp.cantidad_salida>0 ");
	while ($row_temporales = mysqli_fetch_array($sql_filas)){
	$id_producto=$row_temporales["id_producto"];
	$codigo_producto=$row_temporales["codigo_producto"];
	$nombre_producto=$row_temporales["nombre_producto"];
	$id_medida_salida= $row_temporales['id_unidad_medida'];
		//obtener medida del producto
		/*
		$sql_medida_producto = mysqli_query($con,"SELECT * FROM productos_servicios WHERE id = '".$id_producto."'");
		$row_producto = mysqli_fetch_array($sql_medida_producto);
		$id_medida_salida= $row_producto['id_unidad_medida'];
		*/
		
		$id_medida_entrada=$row_temporales["id_medida"];
		$cantidad_entrada_tmp = $row_temporales['cantidad_entrada'];
		$id_bodega = $row_temporales['id_bodega'];
		$lote = $row_temporales['lote'];
		$caducidad = $row_temporales['fecha_caducidad'];
			if ($id_medida_entrada != $id_medida_salida){
			$id_tmp=$row_temporales["id_existencia_tmp"];
			$cantidad_a_transformar = $row_temporales['cantidad_salida'];
			$total_saldo_producto= $saldo_producto->conversion($id_medida_entrada, $id_medida_salida, $id_producto, '0', $cantidad_a_transformar, $con, 'saldo');	
			$resultado[]= array('id_tmp'=>$id_tmp, 'id_producto'=>$id_producto, 'codigo_producto'=>$codigo_producto, 'nombre_producto'=>$nombre_producto, 'entrada'=>$cantidad_entrada_tmp, 'salida'=>$cantidad_a_transformar, 'id_bodega'=>$id_bodega, 'id_medida'=>$id_medida_salida, 'caducidad'=>$caducidad, 'saldo_convertido'=> $total_saldo_producto, 'lote'=> $lote );
			}
	}
	
	foreach ($resultado as $valor){
		$delete_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE id_existencia_tmp='".$valor['id_tmp']."';");
		$sql_actualizar=mysqli_query($con,"INSERT INTO existencias_inventario_tmp VALUES (null,'".$valor['id_producto']."','".$valor['codigo_producto']."','".$valor['nombre_producto']."','".$valor['entrada']."','".$valor['saldo_convertido']."','".$valor['id_bodega']."','".$valor['id_medida']."','".$valor['caducidad']."', '".$ruc_empresa."', '".$valor['saldo_convertido']."','".$valor['lote']."','".$id_usuario."' )");
	}
	//todos los id temporales traidos para luego borrarlos
	
	$ides= array();
	$sql_filas_borrar = mysqli_query($con,"SELECT * FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."' and id_usuario = '".$id_usuario."'");
	while ($row_ides_temporales = mysqli_fetch_array($sql_filas_borrar)){
	$id_temp_iniciales=$row_ides_temporales["id_existencia_tmp"];
	$ides[]= array('id_tmp_iniciales'=>$id_temp_iniciales);
	}
	
$query_actualiza_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada), sum(cantidad_salida), id_bodega, id_medida, fecha_caducidad, ruc_empresa, sum(cantidad_entrada-cantidad_salida),lote, '".$id_usuario."'  FROM existencias_inventario_tmp WHERE ruc_empresa ='". $ruc_empresa ."' group by id_bodega, id_producto, id_medida, lote");
//eliminar los ides tmp iniciales
	foreach ($ides as $id_tm){
	$delete_ides_tmp_iniciales = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE id_existencia_tmp='".$id_tm['id_tmp_iniciales']."';");
	}
}
?>