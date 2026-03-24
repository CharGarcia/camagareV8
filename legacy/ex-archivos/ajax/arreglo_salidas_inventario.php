<?php
	/*Inicia validacion del lado del servidor*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		session_start();
		$id_usuario = $_SESSION['id_usuario'];
		$ruc_empresa = $_SESSION['ruc_empresa'];
		ini_set('date.timezone','America/Guayaquil');
		$fecha_agregado=date("Y-m-d H:i:s");

$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';


if($action == 'actualizar'){	
	$id_producto = $_GET['id_producto'];
//para ver la medida
	$query_medida=mysqli_query($con, "SELECT * FROM productos_servicios WHERE id='".$id_producto."' ");
	$row_medida=mysqli_fetch_array($query_medida);
	$id_medida=$row_medida['id_unidad_medida'];
	//consultar la ultima fecha de vencimiento del producto
	$query_fecha=mysqli_query($con, "SELECT * FROM inventarios WHERE id_producto='".$id_producto."' and ruc_empresa='".$ruc_empresa."' and operacion='ENTRADA' order by fecha_vencimiento desc ");
	$row_vencimiento=mysqli_fetch_array($query_fecha);
	$ultima_fecha=date('Y-m-d H:i:s', strtotime($row_vencimiento['fecha_vencimiento']));
	
	
	$query_actualiza_vencimiento = mysqli_query($con, "UPDATE inventarios SET fecha_vencimiento='".$ultima_fecha."' WHERE id_producto='".$id_producto."' and ruc_empresa='".$ruc_empresa."'");
	//para actualizar
	$query_actualiza = mysqli_query($con, "UPDATE cuerpo_factura SET id_medida_salida= '".$id_medida."' WHERE id_producto='".$id_producto."' and ruc_empresa='".$ruc_empresa."'");

	
				if ($query_actualiza && $query_actualiza_vencimiento){
					echo "<script>
					$.notify('Registros actualizados.','success');
					</script>";					
				} else{
					$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
				}			
}
//function borra_registros($ruc_empresa, $con){	
if($action == 'eliminar'){
	$id_producto_eliminar = $_GET['id_producto'];
		$delete_query_inventario=mysqli_query($con,"DELETE FROM inventarios WHERE id_producto = '".$id_producto_eliminar."' and operacion='SALIDA' and tipo_registro !='M' and cantidad_salida>0");
			if ($delete_query_inventario){
					echo "<script>
					$.notify('Registros eliminados','success');
					</script>";				
			} else{
				$errors[]= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
			}
}


if($action == 'arreglar'){	
	$id_producto = $_GET['id_producto'];
	//limpiar la nueva tabla
	$delete_query_arreglo=mysqli_query($con,"DELETE FROM inventarios_arreglo");
	$referencia="VENTA SEGúN FACTURA: ";	
	//para copiar de una base a otra
	$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO inventarios_arreglo (id_inventario, ruc_empresa, id_producto, precio, cantidad_entrada, cantidad_salida, fecha_registro, fecha_vencimiento, referencia, id_usuario, id_medida, fecha_agregado, tipo_registro, id_bodega, operacion, codigo_producto, nombre_producto, costo_unitario, estado, lote, id_documento_venta) 
	SELECT null, ruc_empresa, id_producto, valor_unitario_factura, '0', cantidad_factura, '".$fecha_agregado."', 0, CONCAT('VENTA SEGúN FACTURA: ' , serie_factura, '-', LPAD(secuencial_factura,'9','0')), '".$id_usuario."', id_medida_salida, '".$fecha_agregado."', 'A', '2', 'SALIDA', codigo_producto, nombre_producto, valor_unitario_factura, 'OK', lote, '0' FROM cuerpo_factura WHERE ruc_empresa ='". $ruc_empresa ."' and id_producto='".$id_producto."' ");

				if ($delete_query_arreglo && $query_guarda_inventario_tmp){
					echo "<script>
					$.notify('Registros Copiados.','success');
					</script>";					
				} else{
					$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
				}			
}		

if($action == 'corregir'){
//para corregir las nuevas fechas
		$id_producto_arreglar = $_GET['id_producto'];
		include_once("../clases/saldo_producto_y_conversion.php");
		$saldo_producto_y_conversion = new saldo_producto_y_conversion();
		include_once("../clases/control_salidas_inventario.php");
		$guarda_salida_inventario = new control_salida_inventario();
		//sucursal
		$query_sucursal=mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa='".$ruc_empresa."' ");
		$row_sucursal=mysqli_fetch_array($query_sucursal);
		$serie=$row_sucursal['serie'];
		
		$salidas_de_inventario= array();		
		$query_registro=mysqli_query($con, "SELECT * FROM inventarios_arreglo WHERE ruc_empresa='".$ruc_empresa."' ");
		while ($row_salidas=mysqli_fetch_array($query_registro)){
		$id_producto=$row_salidas["id_producto"];
		$id_bodega=$row_salidas["id_bodega"];
		$cantidad_factura=$row_salidas["cantidad_salida"];
		$precio_producto=$row_salidas["precio"];
		$fecha_salida=$row_salidas["fecha_registro"];
		$referencia=$row_salidas["referencia"];
		$id_usuario_registrar=$row_salidas["id_usuario"];
		$unidad_medida_salida=$row_salidas["id_medida"];
		$fecha_agregado=$row_salidas["fecha_agregado"];
		$tipo_salida=$row_salidas["tipo_registro"];
		$codigo_producto=$row_salidas["codigo_producto"];
		$nombre_producto=$row_salidas["nombre_producto"];
		$estado=$row_salidas["estado"];
		$lote=$row_salidas["lote"];
		$costo_unitario=$row_salidas["costo_unitario"];
		$id_documento_venta=$row_salidas["id_documento_venta"];
		$vencimiento="";//$row_salidas["fecha_vencimiento"];
		
	$salidas_de_inventario[] = $guarda_salida_inventario->salidas_desde_factura($serie, $id_bodega, $id_producto, $cantidad_factura, $tipo_salida, $fecha_salida, $referencia, $unidad_medida_salida, $precio_producto, $lote, $vencimiento);												
	
	}
	
	if ($salidas_de_inventario){
				echo "<script>
				$.notify('Registros corregidos.','success');
				</script>";			
		} else{
			return "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
		}
}	

//ex

if($action == 'corregir_ex'){
//para corregir las nuevas fechas
		$id_producto_arreglar = $_GET['id_producto'];
		include_once("../clases/saldo_producto_y_conversion.php");
		$saldo_producto_y_conversion = new saldo_producto_y_conversion();
			
	//traer todas las fechas de caducidad de ese producto de entradas de inventarios
		$query_fechas_entradas=mysqli_query($con, "SELECT * FROM inventarios WHERE ruc_empresa= '".$ruc_empresa."' and id_producto='".$id_producto_arreglar."' and operacion = 'ENTRADA' order by fecha_vencimiento asc");
		$total_registro_entradas = mysqli_num_rows($query_fechas_entradas);
		
			$fechas_de_vencimiento=array();
			$lote=array();
			while ($row_detalle_entradas=mysqli_fetch_array($query_fechas_entradas)){
			$fechas_de_vencimiento[]=$row_detalle_entradas["fecha_vencimiento"];
			$lote[]=$row_detalle_entradas["lote"];
			}
		
		$query_registro=mysqli_query($con, "SELECT * FROM inventarios_arreglo WHERE ruc_empresa='".$ruc_empresa."' ");
		while ($row_salidas=mysqli_fetch_array($query_registro)){
		$id_producto=$row_salidas["id_producto"];
		$bodega=$row_salidas["id_bodega"];
		$cantidad_salida=$row_salidas["cantidad_salida"];
		$precio_producto=$row_salidas["precio"];
		$fecha_salida=$row_salidas["fecha_registro"];
		$referencia=$row_salidas["referencia"];
		$id_usuario_registrar=$row_salidas["id_usuario"];
		$unidad_medida_salida=$row_salidas["id_medida"];
		$fecha_agregado=$row_salidas["fecha_agregado"];
		$tipo_salida=$row_salidas["tipo_registro"];
		$codigo_producto=$row_salidas["codigo_producto"];
		$nombre_producto=$row_salidas["nombre_producto"];
		$estado=$row_salidas["estado"];
		$lote=$row_salidas["lote"];
		$costo_unitario=$row_salidas["costo_unitario"];
		$id_documento_venta=$row_salidas["id_documento_venta"];
		
		//borrar las existencias temporales
		$delete_inventario_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."' and id_usuario='".$id_usuario."';");


			$query_new_insert=array();
			for ($i=0; $i<$total_registro_entradas; $i++){
			$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad,ruc_empresa, saldo_producto,lote,id_usuario) 
			SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada),sum(cantidad_salida), id_bodega, id_medida, fecha_vencimiento,ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida),lote,'".$id_usuario."' FROM inventarios WHERE ruc_empresa ='". $ruc_empresa ."' and id_producto='".$id_producto."' group by id_bodega, id_producto, id_medida");
			//borrar las filas que tengan saldo cero
			$delete_fila_saldo_cero_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."' and saldo_producto =0 and id_usuario='".$id_usuario."';");
			
			// while para traer todas las filas
			$total_saldo_producto = array();
			$sql_filas = mysqli_query($con,"SELECT * FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."' and id_producto = '".$id_producto."' and id_usuario = '".$id_usuario."' ");
				
				while ($row_temporales = mysqli_fetch_array($sql_filas)){
				$id_medida_tmp_entrada=$row_temporales["id_medida"];
				$cantidad_a_transformar = $row_temporales['saldo_producto'];
				//transformar la medida temporal a la medida que se esta vendiendo en la factura
				$total_saldo_producto[]= $saldo_producto_y_conversion->conversion($id_medida_tmp_entrada, $unidad_medida_salida, $id_producto, '0', $cantidad_a_transformar, $con, 'saldo');	
				}
					$suma_total_producto = array_sum($total_saldo_producto);

					$saldo_producto=$suma_total_producto;

				//para sacar costo unitario de producto
				$sql_costo = mysqli_query($con,"SELECT * FROM inventarios WHERE ruc_empresa = '".$ruc_empresa."' and id_producto = '".$id_producto."' and operacion = 'ENTRADA' order by id_inventario desc ");
				$row_costo = mysqli_fetch_array($sql_costo);
				$total_costo_unitario=$row_costo['costo_unitario'];

				
				if($cantidad_salida > 0 && $saldo_producto > 0){
					if($cantidad_salida <= $saldo_producto ){
						if ($cantidad_salida>0){
						$query_new_insert[] = mysqli_query($con, "INSERT INTO inventarios VALUES (NULL, '".$ruc_empresa."', '".$id_producto."','".$precio_producto."','0','".$cantidad_salida."','".$fecha_salida."','".$fechas_de_vencimiento[$i]."','".$referencia."', '".$id_usuario."', '".$unidad_medida_salida."','".$fecha_agregado."','".$tipo_salida."','".$bodega."','SALIDA','".$codigo_producto."','".$nombre_producto."','".$total_costo_unitario."','OK','".$lote[$i]."',0)");
						}
						$cantidad_salida=$cantidad_salida-$saldo_producto;
					}

						if ($cantidad_salida > $saldo_producto ){
							if ($saldo_producto>0){
						$query_new_insert[]= mysqli_query($con,"INSERT INTO inventarios VALUES (NULL, '".$ruc_empresa."', '".$id_producto."','".$precio_producto."',0,'".$saldo_producto."','".$fecha_salida."','".$fechas_de_vencimiento[$i]."','".$referencia."', '".$id_usuario."', '".$unidad_medida_salida."','".$fecha_agregado."','".$tipo_salida."','".$bodega."','SALIDA','".$codigo_producto."','".$nombre_producto."','".$total_costo_unitario."','OK','".$lote[$i]."',0)");			
							}
						$cantidad_salida=$cantidad_salida-$saldo_producto;
					}
				}
			}
			if ($query_new_insert){
					echo "<script>
					$.notify('Registros corregidos.','success');
					</script>";			
			} else{
				return "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
			}
	
	}
		
}

	
		if (isset($errors)){			
			?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Error!</strong> 
					<?php
						foreach ($errors as $error) {
								echo $error;
							}
						?>
			</div>
			<?php
			}
			if (isset($messages)){
				
				?>
				<div class="alert alert-success" role="alert">
						<button type="button" class="close" data-dismiss="alert">&times;</button>
						<strong>¡Bien hecho!</strong>
						<?php
							foreach ($messages as $message) {
									echo $message;
								}
							?>
				</div>
				<?php
			}

?>