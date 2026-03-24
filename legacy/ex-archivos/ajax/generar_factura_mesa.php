<?php
		/* Connect To Database*/
		include("../clases/contabilizacion.php");
		$contabilizacion = new contabilizacion();
		include("../conexiones/conectalogin.php");
		include("../clases/secuencial_electronico.php");
		include("../clases/guardar_factura_electronica.php");
		$secuencial_electronico = new secuencial_electronico();
		$con = conenta_login();
	if (empty($_POST['id_mesa'])) {
           $errors[] = "Seleccione una mesa.";
		}else if (empty($_POST['id_cliente_mesa'])) {
           $errors[] = "Seleccione un cliente.";
		}else if (!date($_POST['fecha_mesa'])) {
           $errors[] = "Ingrese fecha correcta.";
		}else if (empty($_POST['por_facturar'])) {
           $errors[] = "Seleccione items que desea facturar";
		}else if (empty($_POST['total_factura'])) {
           $errors[] = "Seleccione items que desea facturar";		   
        } else if (!empty($_POST['id_mesa']) && !empty($_POST['id_cliente_mesa']) && !empty($_POST['fecha_mesa']) && !empty($_POST['por_facturar']))
		{
			ini_set('date.timezone','America/Guayaquil');
			//if (!include_once("../clases/control_salidas_inventario.php")){
			include_once("../clases/control_salidas_inventario.php");
			//}
			$id_mesa=mysqli_real_escape_string($con,(strip_tags($_POST["id_mesa"],ENT_QUOTES)));
			$id_cliente=mysqli_real_escape_string($con,(strip_tags($_POST["id_cliente_mesa"],ENT_QUOTES)));
			$fecha_mesa=date('Y-m-d H:i:s', strtotime($_POST['fecha_mesa']));
			$serie_facturar=mysqli_real_escape_string($con,(strip_tags($_POST["serie_factura_e"],ENT_QUOTES)));
			$total_factura=mysqli_real_escape_string($con,(strip_tags($_POST["total_factura"],ENT_QUOTES)));
			$forma_pago="20";//mysqli_real_escape_string($con,(strip_tags($_POST["forma_pago_e"],ENT_QUOTES)));
			$propina=mysqli_real_escape_string($con,(strip_tags($_POST["propina"],ENT_QUOTES)));
			$guarda_salida_inventario = new control_salida_inventario();
			
			$por_facturar=$_POST["por_facturar"];
			$fecha_registro=date("Y-m-d H:i:s");
			$tipo_salida="A";

			$id_usuario = $_SESSION['id_usuario'];
			$ruc_empresa = $_SESSION['ruc_empresa'];
			
			//para saber si quiere que se imprima lote, bodega, vencimiento, 
			$sql_impresion = mysqli_query($con,"SELECT * FROM configuracion_facturacion where ruc_empresa ='".$ruc_empresa."' and serie_sucursal ='".$serie_facturar."'");
			$row_impresion = mysqli_fetch_array($sql_impresion);
			$resultado_lote = $row_impresion['lote_impreso'];
			$resultado_medida = $row_impresion['medida_impreso'];
			$resultado_bodega = $row_impresion['bodega_impreso'];
			$resultado_vencimiento = $row_impresion['vencimiento_impreso'];
			$inventario = "SI";//$row_impresion['inventario'];
			
			//traer correo de esta empresa
			$busca_empresa = mysqli_query($con,"SELECT * FROM empresas WHERE ruc = '".$ruc_empresa."'");
			$datos_mail_empresa = mysqli_fetch_array($busca_empresa);
			$email_receptor=$datos_mail_empresa['mail'];
			$nombre_comercial=$datos_mail_empresa['nombre_comercial'];
			
			//traer datos de cliente
			$busca_cliente = mysqli_query($con,"SELECT * FROM clientes WHERE id = '".$id_cliente."'");
			$datos_cliente = mysqli_fetch_array($busca_cliente);
			$email_cliente=$datos_cliente['email'];
			$direccion_cliente=$datos_cliente['direccion'];
			
			//traer datos de la mesa
			$busca_mesa = mysqli_query($con,"SELECT * FROM mesas WHERE id_mesa = '".$id_mesa."'");
			$datos_mesa = mysqli_fetch_array($busca_mesa);
			$nombre_mesa=$datos_mesa['nombre_mesa'];
			$factura_final = $secuencial_electronico->consecutivo_siguiente($con, $ruc_empresa, 'factura', $serie_facturar);
			
			$referencia_salida_inventario=$serie_facturar."-".str_pad($factura_final,9,"000000000",STR_PAD_LEFT);
			$codigo_unico=$serie_facturar."-".str_pad($factura_final,9,"000000000",STR_PAD_LEFT);

			//guardar encabezado de factura
			$guarda_encabezado_factura="INSERT INTO encabezado_factura VALUES (null, '".$ruc_empresa."','".$fecha_mesa."','".$serie_facturar."','".$factura_final."','".$id_cliente."','','','".$fecha_registro."','POR COBRAR','ELECTRÓNICA','PENDIENTE', '".$total_factura."', '".$id_usuario."','0','0','','PENDIENTE','".$propina."',0)";
			$query_encabezado_factura = mysqli_query($con,$guarda_encabezado_factura);	
				
		//guarda formas de pago
		$query_forma_pago_factura=mysqli_query($con,"INSERT INTO formas_pago_ventas VALUES (null, '".$ruc_empresa."','".$serie_facturar."','".$factura_final."','".$forma_pago."', '".$total_factura."')");
		
		//guarda detalles adicionales
		$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '".$ruc_empresa."','".$serie_facturar."','".$factura_final."','Email','".$email_cliente."')");
		$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '".$ruc_empresa."','".$serie_facturar."','".$factura_final."','Dirección','".$direccion_cliente."')");
		$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '".$ruc_empresa."','".$serie_facturar."','".$factura_final."','Mesa','".$nombre_mesa."')");

			//para guardar el detalle de la factura
		foreach ($por_facturar as $id_detalle ){
			//detalle de la mesa
			$detalle_mesa=mysqli_query($con, "SELECT * FROM detalle_mesas WHERE id_detalle_mesa= '".$id_detalle."' ");
			$row_detalle=mysqli_fetch_array($detalle_mesa);
			$id_producto=$row_detalle['id_producto'];
			$cantidad_producto=$row_detalle['cantidad'];
			$precio_venta=$row_detalle['precio'];
			$subtotal_factura=$row_detalle['subtotal'];
			$descuento=$row_detalle['descuento'];
			$vencimiento=$row_detalle['vencimiento'];
			$id_bodega=$row_detalle['id_bodega'];
			$id_medida_salida=$row_detalle['id_medida'];
			$lote=$row_detalle['lote'];
			//detalle del producto
			$detalle_producto=mysqli_query($con, "SELECT * FROM productos_servicios WHERE id= '".$id_producto."' ");
			$row_producto=mysqli_fetch_array($detalle_producto);
			$tipo_produccion=$row_producto['tipo_produccion'];
			$tarifa_iva=$row_producto['tarifa_iva'];
			$codigo_producto=$row_producto['codigo_producto'];
			$nombre_producto=$row_producto['nombre_producto'];
			
			//PARA el nombre de la medida
			$nombre_medida=mysqli_query($con, "select * from unidad_medida where id_medida = '". $id_medida_salida ."'");
			$row_medida=mysqli_fetch_array($nombre_medida);
			$medida=isset($row_medida['abre_medida'])?$row_medida['abre_medida']:"";
			//PARA el nombre de la bodega
			$nombre_bodega=mysqli_query($con, "select * from bodega where id_bodega = '". $id_bodega ."'");
			$row_bodega=mysqli_fetch_array($nombre_bodega);
			$bodega=isset($row_bodega['nombre_bodega'])?$row_bodega['nombre_bodega']:"";
		
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

			//guarda en detalle de factura
			$guarda_detalle_factura=mysqli_query($con, "INSERT INTO cuerpo_factura VALUES (null, '".$ruc_empresa."','".$serie_facturar."','".$factura_final."','".$id_producto."','".$cantidad_producto."','".$precio_venta."','".$subtotal_factura."','".$tipo_produccion."','".$tarifa_iva."','0','0','".$descuento."','".$codigo_producto."','".$nombre_producto."','".$id_medida_salida."','".$lote."','".$vencimiento."','".$id_bodega."')");	
				
			//actualiza estado del detalle de la mesa, lo pone como facturado
			$actualiza_detalle_mesa = mysqli_query($con, "UPDATE detalle_mesas SET estado = 'FACTURADO' WHERE id_detalle_mesa = '".$id_detalle."'");
			//elimina la propina temporal
			$elimina_propina = mysqli_query($con, "DELETE FROM propina_restaurante_tmp WHERE id_mesa='".$id_mesa."' ");
		}

		//para registrar si fue generada una factura desde la orden mesa
		$guarda_registro_facturado=mysqli_query($con, "INSERT INTO registros_facturados VALUES (null, '".$ruc_empresa."', 'orden_mesa','Factura','".$nombre_mesa."', '".$serie_facturar."','".$factura_final."', '".$total_factura."', '".$id_usuario."', '".$id_cliente."', '".date('Y-m-d H:i:s')."')");
		
		if ($query_encabezado_factura && $guarda_detalle_factura && $query_forma_pago_factura && $query_guarda_detalle_adicional_factura ){
		//guardar el asiento contable
		$contabilizacion->documentosVentasFacturas($con, $ruc_empresa, $_POST['fecha_mesa'], $_POST['fecha_mesa']);
		$guardar_asientos_contables_generados = $contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'ventas');
				
		echo "<script>
		$.notify('Factura guardada.','success');
		</script>";	
		echo "<script>setTimeout(function () {location.reload()}, 40 * 20)</script>";
		} else
			{
		echo "<script>
		$.notify('Lo siento algo ha salido mal intenta nuevamente.','error');
		</script>";
		}
		}else{
			$errors []= "Error desconocido.";
			}
			
	if (isset($errors))
		{
		?>
		<div class="alert alert-danger" role="alert">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong>Atención! </strong> 
				<?php
					foreach ($errors as $error) 
					{
						echo $error;
					}
				?>
		</div>
		<?php
		}
		if (isset($messages))
		{
			
		?>
		<div class="alert alert-success" role="alert">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong>¡Bien hecho! </strong>
				<?php
					foreach ($messages as $message) 
					{
						echo $message;
					}
				?>
		</div>
		<?php
		}			
		?>
