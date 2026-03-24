<?php
		/* Connect To Database*/
		include("../clases/contabilizacion.php");
		$contabilizacion = new contabilizacion();
		include("../conexiones/conectalogin.php");
		include("../clases/secuencial_electronico.php");
		$secuencial_electronico = new secuencial_electronico();
		$con = conenta_login();
	if (empty($_POST['id_reg_alumno'])) {
           $errors[] = "Seleccione un alumno.";
		}else if (empty($_POST['detalle'])) {
           $errors[] = "Ingrese periodo que se factura, ejemplo: 05-2018.";
		}else if (empty($_POST['periodo_facturar'])) {
           $errors[] = "Seleccione que items desea facturar";			   
        } else if (!empty($_POST['id_reg_alumno']) && !empty($_POST['detalle']) && !empty($_POST['periodo_facturar']))
		{
			ini_set('date.timezone','America/Guayaquil');
			$id_reg_alumno=mysqli_real_escape_string($con,(strip_tags($_POST["id_reg_alumno"],ENT_QUOTES)));
			$periodo_facturar=mysqli_real_escape_string($con,(strip_tags($_POST["periodo_facturar"],ENT_QUOTES)));
			$periodo=mysqli_real_escape_string($con,(strip_tags($_POST["detalle"],ENT_QUOTES)));

			//traer serie de sucursal
			$busca_serie = mysqli_query($con,"SELECT * FROM alumnos WHERE id_alumno = '".$id_reg_alumno."'");
			$datos_serie = mysqli_fetch_array($busca_serie);
			$serie_facturar=$datos_serie['serie_facturar'];
			$id_referencia=$datos_serie['id_alumno'];
			$id_cliente=$datos_serie['id_cliente'];
			$fecha_registro=date("Y-m-d H:i:s");

			session_start();
			$id_usuario = $_SESSION['id_usuario'];
			$ruc_empresa = $_SESSION['ruc_empresa'];
			//traer correo de esta empresa
			$busca_empresa = mysqli_query($con,"SELECT * FROM empresas WHERE ruc = '".$ruc_empresa."'");
			$datos_mail_empresa = mysqli_fetch_array($busca_empresa);
			$email_receptor=$datos_mail_empresa['mail'];
			$nombre_comercial=$datos_mail_empresa['nombre_comercial'];
			
			$factura_final = $secuencial_electronico->consecutivo_siguiente($con, $ruc_empresa, 'factura', $serie_facturar);
			
			if ($periodo_facturar=="00"){ // 00 = todos los registros
			$sql_detalle_por_facturar=mysqli_query($con,"select * from detalle_por_facturar where ruc_empresa='".$ruc_empresa."' and id_referencia = '".$id_referencia."'");
			}else{
			$sql_detalle_por_facturar=mysqli_query($con,"select * from detalle_por_facturar where ruc_empresa='".$ruc_empresa."' and id_referencia = '".$id_referencia."' and cuando_facturar = '".$periodo_facturar."' ");
			}
			$contar_registros = mysqli_num_rows($sql_detalle_por_facturar);
		
		if ($id_cliente !=0){
			if ($contar_registros>0 && $id_cliente !=0){
				
				//para guardar encabezado de factura
				$guarda_encabezado_factura=mysqli_query($con, "INSERT INTO encabezado_factura VALUES (null, '".$ruc_empresa."',
				'".$fecha_registro."','".$serie_facturar."','".$factura_final."',
				'".$id_cliente."','','','".$fecha_registro."','POR COBRAR','ELECTRÓNICA','PENDIENTE', '0', '".$id_usuario."','0','0','','PENDIENTE',0,0)");
	
			while ($row_detalle=mysqli_fetch_array($sql_detalle_por_facturar)){
						$id_producto=$row_detalle["id_producto"];
						$cantidad_producto=$row_detalle["cant_producto"];
						$precio_venta=$row_detalle["precio_producto"];
						$descuento=$row_detalle['descuento'];
						$subtotal_factura=str_replace(",",".",$precio_venta*$cantidad_producto);
						//para traer tipo de tarifas y tipos de produccion
						$sql_tarifas=mysqli_query($con, "SELECT * FROM productos_servicios WHERE id= '".$id_producto."' ");
						$row_tarifas=mysqli_fetch_array($sql_tarifas);
						$tipo_produccion=$row_tarifas['tipo_produccion'];
						$tarifa_iva=$row_tarifas['tarifa_iva'];
						$tarifa_ice=$row_tarifas['tarifa_ice'];
						$tarifa_bp=$row_tarifas['tarifa_botellas'];
						$codigo_producto=$row_tarifas['codigo_producto'];
						$nombre_producto=$row_tarifas['nombre_producto'];
						$guarda_detalle_factura=mysqli_query($con, "INSERT INTO cuerpo_factura VALUES (null, '".$ruc_empresa."','".$serie_facturar."','".$factura_final."','".$id_producto."','".$cantidad_producto."','".$precio_venta."','".$subtotal_factura."','".$tipo_produccion."','".$tarifa_iva."','".$tarifa_ice."','".$periodo."','".$descuento."','".$codigo_producto."','".$nombre_producto."','0','0','0','0')");
					}

					//consultar el valor total de cada factura
				$sql_total_factura=mysqli_query($con, "SELECT sum(round(subtotal_factura-descuento,2)) as total FROM cuerpo_factura WHERE ruc_empresa='".$ruc_empresa."' and serie_factura = '".$serie_facturar."' and secuencial_factura = '".$factura_final."' ");
				$row_total_factura=mysqli_fetch_array($sql_total_factura);
				$total_factura=$row_total_factura['total'];

				$update_total_factura=mysqli_query($con, "UPDATE encabezado_factura SET total_factura='".$total_factura."' WHERE ruc_empresa='".$ruc_empresa."' and serie_factura = '".$serie_facturar."' and secuencial_factura = '".$factura_final."' ");
			
				//traer datos del cliente
				$sql_clientes=mysqli_query($con, "SELECT * FROM alumnos as al, clientes as cl WHERE al.id_alumno= '".$id_referencia."' and al.ruc_empresa='".$ruc_empresa."' and al.id_cliente=cl.id");
				$row_clientes=mysqli_fetch_array($sql_clientes);
				$id_cliente=$row_clientes['id_cliente'];
				$email=$row_clientes['email'];
				$direccion=$row_clientes['direccion'];
				$alumno= strtoupper($row_clientes['nombres_apellidos']);
				$sexo_alumno=$row_clientes['sexo_alumno'];
					if($sexo_alumno=='F'){
						$sexo_alumno = "NIÑA";
					}else{
						$sexo_alumno = "NIÑO";
					}
				
				//para guardar la forma de pago de la factura
				$guarda_forma_pago="INSERT INTO formas_pago_ventas VALUES (null, '".$ruc_empresa."','".$serie_facturar."','".$factura_final."','20', '".$total_factura."')";
				$query_forma_pago_factura = mysqli_query($con,$guarda_forma_pago);
				
				// para guardar detalle adicional factura
				$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '".$ruc_empresa."','".$serie_facturar."','".$factura_final."','Email','".$email."')");
				$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '".$ruc_empresa."','".$serie_facturar."','".$factura_final."','Dirección','".$direccion."')");
				$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '".$ruc_empresa."','".$serie_facturar."','".$factura_final."','".$sexo_alumno."','".$alumno."')");
					
		if ($guarda_encabezado_factura && $guarda_detalle_factura && $query_forma_pago_factura && $query_guarda_detalle_adicional_factura ){
			//guardar el asiento contable
			$contabilizacion->documentosVentasFacturas($con, $ruc_empresa, $fecha_registro, $fecha_registro);
			$guardar_asientos_contables_generados = $contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'ventas');
			echo "<script>
		$.notify('Factura guardada.','success');
		</script>";	
		//borrar los items que solo son una vez y los descuentos	
		echo eliminar_por_facturar($id_referencia,$ruc_empresa, $con);
		echo "<script>setTimeout(function () {location.reload()}, 40 * 20)</script>";
		} else
			{
		echo "<script>
		$.notify('Lo siento algo ha salido mal intenta nuevamente.','error');
		</script>";
		}
		}else{
		$errors []= "No hay servicios agregados con el tipo seleccionado de, cuando facturar.";	
		}
		}else{
		$errors []= "No hay cliente asignado al alumno.";	
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
			
function borrar_descuentos($id_referencia,$ruc_empresa, $con){

$sql="UPDATE detalle_por_facturar SET descuento = '0' WHERE id_referencia='".$id_referencia."' and ruc_empresa='".$ruc_empresa."'";
$query_update = mysqli_query($con,$sql);
}	
	
function eliminar_por_facturar($id_referencia,$ruc_empresa, $con){
$sql="DELETE FROM detalle_por_facturar WHERE id_referencia='".$id_referencia."' and ruc_empresa='".$ruc_empresa."' and cuando_facturar='03' ";
$query_delete = mysqli_query($con,$sql);
}
?>
