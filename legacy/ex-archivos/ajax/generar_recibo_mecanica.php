<?php
		/* Connect To Database*/
		include("../clases/contabilizacion.php");
		$contabilizacion = new contabilizacion();
		include("../conexiones/conectalogin.php");
		include("../clases/secuencial_electronico.php");
		$secuencial_electronico = new secuencial_electronico();
		$con = conenta_login();
	if (empty($_POST['codigo_unico_factura'])) {
           $errors[] = "Seleccione una orden de servicio.";
		}else if (empty($_POST['id_cliente_mecanica'])) {
           $errors[] = "Agregue un cliente al recibo.";
		}else if (empty($_POST['fecha_mecanica'])) {
           $errors[] = "Ingrese fecha del recibo";
		}else if (!date($_POST['fecha_mecanica'])) {
           $errors[] = "Ingrese fecha correcta del recibo";		   
        } else if (!empty($_POST['codigo_unico_factura']) && !empty($_POST['id_cliente_mecanica']) && !empty($_POST['fecha_mecanica']))
		{
			ini_set('date.timezone','America/Guayaquil');
			$codigo_unico_factura=mysqli_real_escape_string($con,(strip_tags($_POST["codigo_unico_factura"],ENT_QUOTES)));
			$id_cliente=mysqli_real_escape_string($con,(strip_tags($_POST["id_cliente_mecanica"],ENT_QUOTES)));
			$fecha_mecanica=date('Y-m-d', strtotime($_POST['fecha_mecanica']));
			$serie_recibo=mysqli_real_escape_string($con,(strip_tags($_POST["serie_mecanica"],ENT_QUOTES)));
			$total_recibo=mysqli_real_escape_string($con,(strip_tags($_POST["total_factura"],ENT_QUOTES)));
			$fecha_registro=date("Y-m-d H:i:s");
			
			session_start();
			$id_usuario = $_SESSION['id_usuario'];
			$ruc_empresa = $_SESSION['ruc_empresa'];
			//traer correo de esta empresa
			$busca_empresa = mysqli_query($con,"SELECT * FROM empresas WHERE ruc = '".$ruc_empresa."'");
			$datos_mail_empresa = mysqli_fetch_array($busca_empresa);
			$email_receptor=$datos_mail_empresa['mail'];
			$nombre_comercial=$datos_mail_empresa['nombre_comercial'];
			
			//traer el numero de factura que continua
			$siguiente_numero_recibo = $secuencial_electronico->consecutivo_siguiente($con, $ruc_empresa, 'recibo_venta', $serie_recibo);
			
			$sql_detalle_por_facturar=mysqli_query($con,"SELECT * from detalle_factura_mecanica WHERE ruc_empresa='".$ruc_empresa."' and codigo_unico = '".$codigo_unico_factura."'");
			$contar_registros = mysqli_num_rows($sql_detalle_por_facturar);
			
			$sql_subtotal=mysqli_query($con,"SELECT sum(subtotal) as subtotal from detalle_factura_mecanica WHERE ruc_empresa='".$ruc_empresa."' and codigo_unico = '".$codigo_unico_factura."' group by codigo_unico");
			$subtotal = mysqli_fetch_array($sql_subtotal);

			$sql_impuestos_recibo = mysqli_query($con,"SELECT * FROM sucursales WHERE ruc_empresa = '".$ruc_empresa."' and serie='".$serie_recibo."' ");
			$row_impuestos_recibo=mysqli_fetch_array($sql_impuestos_recibo);
			$impuestos_recibo=$row_impuestos_recibo['impuestos_recibo'];

			$total_recibo=$impuestos_recibo==2?$total_recibo:$subtotal;
		
		if ($id_cliente !=0){
			if ($contar_registros>0 && $id_cliente !=0){
			$query_guarda_encabezado_recibo = mysqli_query($con, "INSERT INTO encabezado_recibo VALUES(null, '".$ruc_empresa."',
			'".$fecha_mecanica."','".$serie_recibo."','".$siguiente_numero_recibo."','".$id_cliente."',
			'".$fecha_registro."', '".$total_recibo."', '".$id_usuario."','0','0','0','1')");
			$lastid = mysqli_insert_id($con);
			
			$query_guarda_detalle_recibo = mysqli_query($con, "INSERT INTO cuerpo_recibo (id_encabezado_recibo, id_producto, cantidad, 
				valor_unitario, subtotal, tipo_produccion, tarifa_iva, tarifa_ice, adicional, descuento,
				codigo_producto, nombre_producto, id_medida, lote, vencimiento, id_bodega) 
			SELECT '".$lastid."', det.id_producto, det.cantidad, det.precio, det.subtotal, 
			det.tipo_produccion, det.tarifa_iva as tarifa_iva, '0', '', det.descuento, pro.codigo_producto as codigo_producto,
			pro.nombre_producto as nombre_producto, det.id_medida, det.lote, det.vencimiento, det.id_bodega
			  FROM detalle_factura_mecanica as det INNER JOIN productos_servicios as pro ON pro.id=det.id_producto 
			  WHERE det.codigo_unico = '".$codigo_unico_factura."' ");
		
			  $sql_clientes=mysqli_query($con, "SELECT * FROM clientes WHERE id='".$id_cliente."'");
			$row_clientes=mysqli_fetch_array($sql_clientes);
			$email=$row_clientes['email'];
			$direccion=$row_clientes['direccion'];
			$telefono=$row_clientes['telefono'];

			  $sql_orden=mysqli_query($con, "SELECT * FROM encabezado_mecanica enc_fac, vehiculos as veh WHERE enc_fac.codigo_unico = veh.codigo_unico and enc_fac.codigo_unico='".$codigo_unico_factura."'");
			  $row_orden=mysqli_fetch_array($sql_orden);
			  $numero_orden=$row_orden['numero_orden'];
			  $placa=$row_orden['placa'];
			  $proxima_cita=$row_orden['proximo_chequeo']>0?date('d-m-Y', strtotime($row_orden['proximo_chequeo'])):"";
			  $observaciones_proxima_cita=$row_orden['obs_prox_chequeo'];

			  $query_guarda_detalle_adicional_recibo = mysqli_query($con, "INSERT INTO detalle_adicional_recibo VALUES (null, '".$lastid."','Email','".$email."')");
			  $query_guarda_detalle_adicional_recibo = mysqli_query($con, "INSERT INTO detalle_adicional_recibo VALUES (null, '".$lastid."','Dirección','".$direccion."')");
			 if(!empty($telefono)){
			  $query_guarda_detalle_adicional_recibo = mysqli_query($con, "INSERT INTO detalle_adicional_recibo VALUES (null, '".$lastid."','Teléfono','".$telefono."')");
			 } 
			  $query_guarda_detalle_adicional_recibo = mysqli_query($con, "INSERT INTO detalle_adicional_recibo VALUES (null, '".$lastid."','Placa','".$placa."')");
			  $query_guarda_detalle_adicional_recibo = mysqli_query($con, "INSERT INTO detalle_adicional_recibo VALUES (null, '".$lastid."','Orden N.','".$numero_orden."')");
			  if(!empty($proxima_cita)){ 
			  $query_guarda_detalle_adicional_recibo = mysqli_query($con, "INSERT INTO detalle_adicional_recibo VALUES (null, '".$lastid."','Próxima cita','".$proxima_cita."')");
			  }
			  if(!empty($observaciones_proxima_cita)){
			  $query_guarda_detalle_adicional_recibo = mysqli_query($con, "INSERT INTO detalle_adicional_recibo VALUES (null, '".$lastid."','Observaciones','".$observaciones_proxima_cita."')");
			  }

			//para registrar si fue generada una factura desde la orden mecanica
			$guarda_registro_facturado=mysqli_query($con, "INSERT INTO registros_facturados VALUES (null, '".$ruc_empresa."', 'orden_mecanica','Recibo','".$numero_orden."', '".$serie_recibo."','".$siguiente_numero_recibo."', '".$total_recibo."', '".$id_usuario."', '".$id_cliente."', '".date('Y-m-d H:i:s')."')");

			//root@localhost
		//actualizar encabezado de factura a cerrado
		$actualizar_encabezado_factura = mysqli_query($con,"UPDATE encabezado_mecanica SET estado='CERRADA' WHERE codigo_unico='".$codigo_unico_factura."'");
		$actualizar_detalle_factura_mecanica = mysqli_query($con,"UPDATE detalle_factura_mecanica SET secuencial=concat('RV','".$siguiente_numero_recibo."') WHERE codigo_unico='".$codigo_unico_factura."'");
		
		if ($actualizar_encabezado_factura && $actualizar_detalle_factura_mecanica){
		//para guardar asientos contables de recibos
		$contabilizacion->documentosVentasRecibos($con, $ruc_empresa, $fecha_mecanica, $fecha_mecanica);
		$guardar_asientos_contables_generados = $contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'recibos');
							
		echo "<script>
		$.notify('Recibo guardado.','success');
		</script>";	
		echo "<script>setTimeout(function () {location.reload()}, 40 * 20)</script>";
		} else
			{
		echo "<script>
		$.notify('Lo siento algo ha salido mal intenta nuevamente.','error');
		</script>";
		}
		}else{
		$errors []= "No hay servicios y/o productos agregados para generar el recibo de venta.";	
		}
		}else{
		$errors []= "No hay cliente o detalle de productos asignados el recibo de venta.";	
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
