<?php
function guardar_encabezado_recibo($con, $ruc_empresa, $fecha_recibo, $serie_recibo, $secuencial_recibo, $id_cliente, $total_recibo, $id_usuario, $propina, $tasa_turistica){
$fecha_registro=date("Y-m-d H:i:s");
$query_encabezado_recibo=mysqli_query($con,"INSERT INTO encabezado_recibo VALUES (null, '".$ruc_empresa."','".$fecha_recibo."','".$serie_recibo."','".$secuencial_recibo."','".$id_cliente."','".$fecha_registro."', '".$total_recibo."', '".$id_usuario."','0','".$propina."','".$tasa_turistica."','1')");
$lastid = mysqli_insert_id($con);
return $lastid;
}

/*
function actualizar_encabezado_recibo($con, $id_recibo, $fecha_recibo, $id_cliente, $id_usuario){
	$fecha_registro=date("Y-m-d H:i:s");
	$query_encabezado_recibo=mysqli_query($con,"UPDATE encabezado_recibo SET fecha_recibo='".$fecha_recibo."', id_cliente='".$id_cliente."', fecha_registro='".$fecha_registro."',id_usuario= '".$id_usuario."' where id_encabezado_recibo='".$id_recibo."' ");
	return $query_encabezado_recibo;
}
*/

function guarda_detalle_recibo($con, $sql_recibo_temporal, $ruc_empresa, $serie_recibo, $referencia_salida_inventario, $fecha_recibo, $id_encabezado_recibo){
/*
if (!include_once("../clases/control_salidas_inventario.php")){
include_once("../clases/control_salidas_inventario.php");
}
$guarda_salida_inventario = new control_salida_inventario();
$id_usuario = $_SESSION['id_usuario'];

//para saber si quiere que se imprima lote, bodega, vencimiento, 
	$sql_impresion = mysqli_query($con,"SELECT * FROM configuracion_facturacion where ruc_empresa ='".$ruc_empresa."' and serie_sucursal ='".$serie_recibo."'");
	$row_impresion = mysqli_fetch_array($sql_impresion);
	$inventario = $row_impresion['inventario'];
	*/


while ($row_detalle=mysqli_fetch_array($sql_recibo_temporal)){
	$cantidad_recibo=str_replace(",",".",$row_detalle["cantidad_tmp"]);
	$precio_venta=str_replace(",",".",$row_detalle['precio_tmp']);
	$subtotal_recibo=number_format(str_replace(",",".",$precio_venta*$cantidad_recibo),2,'.','');//Precio total formateado
	$tipo_produccion=$row_detalle['tipo_produccion'];
	$tarifa_iva=$row_detalle['tarifa_iva'];
	$tarifa_ice=$row_detalle['tarifa_ice'];
	$tarifa_bp=$row_detalle['tarifa_botellas'];
	$descuento=$row_detalle['descuento'];
	$id_producto=$row_detalle['id_producto'];
	$id_bodega=$row_detalle['id_bodega'];
	$id_medida_salida=$row_detalle['id_medida'];

	if($id_medida_salida==0){
		$sql_medida = mysqli_query($con,"SELECT * FROM productos_servicios where id ='".$id_producto."' ");
		$row_medida=mysqli_fetch_array($sql_medida);
		$id_medida_salida=$row_medida['id_unidad_medida'];
		}
		
	$lote=$row_detalle['lote'];
	$vencimiento=$row_detalle['vencimiento'];
	$codigo_producto= $row_detalle['codigo_producto'];
	$nombre_producto= $row_detalle['nombre_producto'];
	$medida=$row_detalle['abre_medida'];
	$bodega=$row_detalle['nombre_bodega'];
	
	
	$guarda_detalle_recibo=mysqli_query($con, "INSERT INTO cuerpo_recibo VALUES (null, '".$id_encabezado_recibo."','".$id_producto."','".$cantidad_recibo."','".$precio_venta."','".$subtotal_recibo."','".$tipo_produccion."','".$tarifa_iva."','".$tarifa_ice."','".$tarifa_bp."','".$descuento."','".$codigo_producto."','".$nombre_producto."','".$id_medida_salida."','".$lote."','".$vencimiento."','".$id_bodega."')");
//para guardar en el inventario los productos del recibo siempre y cuando manejen inventario
	/*
	if ($tipo_produccion == "01" && $inventario == "SI"){														
			$query_new_insert = $guarda_salida_inventario->salidas_desde_recibo($serie_recibo, $id_bodega, $id_producto, $cantidad_recibo, 'A', $fecha_recibo, $referencia_salida_inventario, $id_medida_salida, $precio_venta, $lote, $vencimiento);												

		//para registrar en el inventario los agregados de cada producto en el inventario
		$sql_agregados = mysqli_query($con,"SELECT det_agr.id_producto as id_producto, 
		det_agr.id_medida as id_medida, det_agr.cantidad as cantidad, det_agr.codigo_producto as codigo, det_agr.nombre_producto as nombre 
		FROM detalle_agregados_producto as det_agr INNER JOIN encabezado_agregados_producto as enc_agr 
		ON enc_agr.id=det_agr.id_agregado WHERE enc_agr.id_producto ='".$id_producto."' and enc_agr.status = '1' ");
		foreach ($sql_agregados as $detalle){
			$id_producto=$detalle['id_producto'];
			$id_medida_salida=$detalle['id_medida'];
			$cantidad_producto=$detalle['cantidad']*$cantidad_recibo;
			$codigo_producto=$detalle['codigo'];
			$nombre_producto=$detalle['nombre'];
			$lote="0";
			$vencimiento=date("Y-m-d H:i:s");
			$referencia=$referencia_salida_inventario." producto agregado";
			
			$sql_productos = mysqli_query($con,"SELECT * FROM productos_servicios WHERE id ='".$id_producto."'");
			$row_productos=mysqli_fetch_array($sql_productos);
			$precio_venta=$row_productos['precio_producto'];											
					$query_new_insert_agregados= mysqli_query($con,"INSERT INTO inventarios VALUES (NULL, '".$ruc_empresa."', '".$id_producto."','".$precio_venta."',0,'".$cantidad_producto."','".date("Y-m-d H:i:s")."','".date("Y-m-d H:i:s")."','".$referencia."', '".$id_usuario."', '".$id_medida_salida."','".date("Y-m-d H:i:s")."','A','".$id_bodega."','SALIDA','".$codigo_producto."','".$nombre_producto."','0','OK','".$lote."','"."RV".$ruc_empresa.$referencia_salida_inventario."')");			
				}
	}
	*/

}
	return $guarda_detalle_recibo;
}

?>