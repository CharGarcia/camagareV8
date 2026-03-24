<?php
function guardar_encabezado_factura($con, $ruc_empresa, $fecha_factura, $serie_factura, $secuencial_factura, $id_cliente, $guia_factura, $total_factura, $id_usuario, $propina, $tasa_turistica){
$fecha_registro=date("Y-m-d H:i:s");
$query_encabezado_factura=mysqli_query($con,"INSERT INTO encabezado_factura VALUES (null, '".$ruc_empresa."','".$fecha_factura."','".$serie_factura."','".$secuencial_factura."','".$id_cliente."','','".$guia_factura."','".$fecha_registro."','NINGUNO','ELECTRÓNICA','PENDIENTE', '".$total_factura."', '".$id_usuario."','0','0','','PENDIENTE','".$propina."','".$tasa_turistica."')");
return $query_encabezado_factura;

}

function actualizar_encabezado_factura($con, $ruc_empresa, $fecha_factura, $serie_factura, $secuencial_factura, $id_cliente, $guia_factura, $id_usuario){
	$fecha_registro=date("Y-m-d H:i:s");
	$query_encabezado_factura=mysqli_query($con,"UPDATE encabezado_factura SET  fecha_factura='".$fecha_factura."', id_cliente='".$id_cliente."', guia_remision='".$guia_factura."', fecha_registro='".$fecha_registro."',id_usuario= '".$id_usuario."' where ruc_empresa='".$ruc_empresa."' and serie_factura='".$serie_factura."' and secuencial_factura='".$secuencial_factura."'");
	return $query_encabezado_factura;
	}

function guarda_forma_de_pago($con, $ruc_empresa, $serie_factura, $secuencial_factura, $forma_pago_factura, $total_factura){
$query_forma_pago_factura=mysqli_query($con,"INSERT INTO formas_pago_ventas VALUES (null, '".$ruc_empresa."','".$serie_factura."','".$secuencial_factura."','".$forma_pago_factura."', '".$total_factura."')");
return $query_forma_pago_factura;
}


function adicionales_factura($con, $ruc_empresa, $serie_factura, $secuencial_factura, $id_usuario){
$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura (id_detalle, ruc_empresa, serie_factura, secuencial_factura, adicional_concepto, adicional_descripcion) 
SELECT null, '".$ruc_empresa."', '".$serie_factura."', '".$secuencial_factura."', concepto, detalle FROM adicional_tmp WHERE id_usuario = '".$id_usuario."' and serie_factura = '".$serie_factura."' and secuencial_factura = '".$secuencial_factura."'");
return $query_guarda_detalle_adicional_factura;
}

function detalle_factura_inventario($con, $sql_factura_temporal, $ruc_empresa, $serie_factura, $secuencial_factura, $referencia_salida_inventario, $fecha_factura){
//if (!include_once("../clases/control_salidas_inventario.php")){
//include_once("../clases/control_salidas_inventario.php");
//}
//$guarda_salida_inventario = new control_salida_inventario();
$id_usuario = $_SESSION['id_usuario'];

//para saber si quiere que se imprima lote, bodega, vencimiento, 
	$sql_impresion = mysqli_query($con,"SELECT * FROM configuracion_facturacion where ruc_empresa ='".$ruc_empresa."' and serie_sucursal ='".$serie_factura."'");
	$row_impresion = mysqli_fetch_array($sql_impresion);
	$inventario = isset($row_impresion['inventario'])?$row_impresion['inventario']:"";
	$resultado_lote = isset($row_impresion['lote_impreso'])?$row_impresion['lote_impreso']:"";
	$resultado_medida = isset($row_impresion['medida_impreso'])?$row_impresion['medida_impreso']:"";
	$resultado_bodega = isset($row_impresion['bodega_impreso'])?$row_impresion['bodega_impreso']:"";
	$resultado_vencimiento = isset($row_impresion['vencimiento_impreso'])?$row_impresion['vencimiento_impreso']:"";


while ($row_detalle=mysqli_fetch_array($sql_factura_temporal)){
	$cantidad_factura=str_replace(",",".",$row_detalle["cantidad_tmp"]);
	$precio_venta=str_replace(",",".",$row_detalle['precio_tmp']);
	$subtotal_factura=number_format(str_replace(",",".",$precio_venta*$cantidad_factura),2,'.','');//Precio total formateado
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
	
	//para saber si quiere que se imprima lote, bodega, vencimiento, 
		
	if ($tipo_produccion=="01"){
		if ($resultado_lote=="SI"){
			$nombre_producto=$nombre_producto." Lt ".$lote;
		}
		if ($resultado_medida=="SI"){
			$nombre_producto=$nombre_producto." Md ".$medida;
		}
		
		if ($resultado_bodega=="SI"){
			$nombre_producto=$nombre_producto." Bg ".$bodega;
		}
		
		if ($resultado_vencimiento=="SI"){
			$nombre_producto=$nombre_producto." Vto ".date('d-m-Y', strtotime($vencimiento)); ;
		}
	}
	
	$guarda_detalle_factura=mysqli_query($con, "INSERT INTO cuerpo_factura VALUES (null, '".$ruc_empresa."','".$serie_factura."','".$secuencial_factura."','".$id_producto."','".$cantidad_factura."','".$precio_venta."','".$subtotal_factura."','".$tipo_produccion."','".$tarifa_iva."','".$tarifa_ice."','".$tarifa_bp."','".$descuento."','".$codigo_producto."','".$nombre_producto."','".$id_medida_salida."','".$lote."','".$vencimiento."','".$id_bodega."')");
//para guardar en el inventario los productos de la factura siempre y cuando manejen inventario
	//if ($tipo_produccion == "01" && $inventario == "SI"){														
			//$query_new_insert = $guarda_salida_inventario->salidas_desde_factura($serie_factura, $id_bodega, $id_producto, $cantidad_factura, 'A', $fecha_factura, $referencia_salida_inventario, $id_medida_salida, $precio_venta, $lote, $vencimiento);												

			/*
		//para registrar en el inventario los agregados de cada producto en el inventario
		$sql_agregados = mysqli_query($con,"SELECT det_agr.id_producto as id_producto, 
		det_agr.id_medida as id_medida, det_agr.cantidad as cantidad, det_agr.codigo_producto as codigo, det_agr.nombre_producto as nombre 
		FROM detalle_agregados_producto as det_agr INNER JOIN encabezado_agregados_producto as enc_agr 
		ON enc_agr.id=det_agr.id_agregado WHERE enc_agr.id_producto ='".$id_producto."' and enc_agr.status = '1' ");
		foreach ($sql_agregados as $detalle){
			$id_producto=$detalle['id_producto'];
			$id_medida_salida=$detalle['id_medida'];
			$cantidad_producto=$detalle['cantidad']*$cantidad_factura;
			$codigo_producto=$detalle['codigo'];
			$nombre_producto=$detalle['nombre'];
			$lote="0";
			$vencimiento=date("Y-m-d H:i:s");
			$referencia=$referencia_salida_inventario." producto agregado";
			
			$sql_productos = mysqli_query($con,"SELECT * FROM productos_servicios WHERE id ='".$id_producto."'");
			$row_productos=mysqli_fetch_array($sql_productos);
			$precio_venta=$row_productos['precio_producto'];											
					$query_new_insert_agregados= mysqli_query($con,"INSERT INTO inventarios VALUES (NULL, '".$ruc_empresa."', '".$id_producto."','".$precio_venta."',0,'".$cantidad_producto."','".date("Y-m-d H:i:s")."','".date("Y-m-d H:i:s")."','".$referencia."', '".$id_usuario."', '".$id_medida_salida."','".date("Y-m-d H:i:s")."','A','".$id_bodega."','SALIDA','".$codigo_producto."','".$nombre_producto."','0','OK','".$lote."','".$referencia_salida_inventario."')");			
				}
				*/
	//}

}
	return $guarda_detalle_factura;
}

?>