<?php
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
	if (empty($_POST['fecha_factura'])) {
           $errors[] = "Ingrese fecha para la factura.";
		}else if (!date($_POST['fecha_factura'])) {
           $errors[] = "Ingrese fecha correcta";
		}else if (empty($_POST['serie_factura'])) {
           $errors[] = "Seleccione serie para la factura";
		}else if (empty($_POST['secuencial_factura'])) {
           $errors[] = "Ingrese un número de factura.";
		}else if (empty($_POST['ruc_cliente'])) {
           $errors[] = "Seleccione un cliente para la factura.";
        } else if (!empty($_POST['fecha_factura']) && !empty($_POST['serie_factura'])  && !empty($_POST['secuencial_factura']) 
		&& !empty($_POST['ruc_cliente']))
		{
			// escaping, additionally removing everything that could be (html/javascript-) code
			$fecha_factura=date('Y-m-d H:i:s', strtotime($_POST['fecha_factura']));
			$serie_factura=mysqli_real_escape_string($con,(strip_tags($_POST["serie_factura"],ENT_QUOTES)));
			$secuencial_factura=mysqli_real_escape_string($con,(strip_tags($_POST["secuencial_factura"],ENT_QUOTES)));
			$ruc_cliente=mysqli_real_escape_string($con,(strip_tags($_POST["ruc_cliente"],ENT_QUOTES)));
			$nombre_cliente=mysqli_real_escape_string($con,(strip_tags($_POST["nombre_cliente"],ENT_QUOTES)));
			$direccion_cliente=mysqli_real_escape_string($con,(strip_tags($_POST["direccion_cliente"],ENT_QUOTES)));
			$observaciones_factura=mysqli_real_escape_string($con,(strip_tags($_POST["observaciones_factura"],ENT_QUOTES)));
			$guia_factura=mysqli_real_escape_string($con,(strip_tags($_POST["guia_factura"],ENT_QUOTES)));
			$total_factura=mysqli_real_escape_string($con,(strip_tags($_POST["total_factura"],ENT_QUOTES)));
			$fecha_registro=date("Y-m-d H:i:s");
			session_start();
			$id_tmp = $_SESSION['id_usuario'];
			$ruc_empresa = $_SESSION['ruc_empresa'];
			$estado_factura = "POR COBRAR";
			$mes_periodo = date("m", strtotime($fecha_factura));
			$anio_periodo = date("Y", strtotime($fecha_factura));
					
			//para ver si la factura que queremos registrar esta dentro de los periodos permitidos para registrar
			 
			 $busca_periodos = mysqli_query($con,"SELECT * FROM periodo_contable WHERE mes_periodo = '$mes_periodo' and anio_periodo = '$anio_periodo' and ruc_empresa = '$ruc_empresa'");
			 $count = mysqli_num_rows($busca_periodos);
		     if ($count == 0) {
					$errors []= "La fecha de la factura no está dentro del período contable permitido para ingresar información".mysqli_error($con);
				 }
				 else
					{
						//para ver si la factura que queremos registrar esta dentro del rango de la autorizacion
						 $busca_autorizacion = mysqli_query($con,"SELECT * FROM autorizaciones_sri as autsri, sucursales as sucursal WHERE autsri.id_serie = sucursal.id_sucursal and sucursal.serie = '$serie_factura' and autsri.codigo_documento = '01' and $secuencial_factura between autsri.del_autorizacion and autsri.al_autorizacion and '$fecha_factura' between autsri.emision_autorizacion and autsri.vence_autorizacion and autsri.ruc_empresa = '$ruc_empresa' and sucursal.ruc_empresa = '$ruc_empresa'");
						 $count = mysqli_num_rows( $busca_autorizacion);
							 if ($count == 0) 
							 {
							$errors []= "La factura no está dentro de un rango de autorizaciones del SRI, actualice las autorizaciones o proceda a registrar una nueva autorización".mysqli_error($con);
							}else
								{
									//paraa ver si la factura que queremos registrar ya esta registrada	
									 $busca_empresa = "SELECT * FROM encabezado_factura WHERE ruc_empresa = '$ruc_empresa' and serie_factura = '$serie_factura' and secuencial_factura ='$secuencial_factura'";
									 $result = $con->query($busca_empresa);
									 $count = mysqli_num_rows($result);
									 if ($count == 1) 
									 {
									$errors []= "El número de factura que intenta guardar ya se encuentra en registrado en el sistema.".mysqli_error($con);
									}else
										{
											$sql_factura_temporal=mysqli_query($con,"select * from factura_tmp where id_usuario = '$id_tmp'");
											$count=mysqli_num_rows($sql_factura_temporal);
											if ($count==0)
											{
											$errors []= "No hay detalle de productos agregados a la factura.".mysqli_error($con);
											}else
												{
													//para guardar el encabezado de la factura
													$sql="INSERT INTO encabezado_factura (id_encabezado_factura, ruc_empresa, fecha_factura, serie_factura, secuencial_factura,
													ruc_cliente_factura, nombre_cliente_factura, direccion_cliente_factura, observaciones_factura,guia_remision,fecha_registro, estado_factura, total_factura, id_usuario)
													VALUES (null, '$ruc_empresa','$fecha_factura','$serie_factura','$secuencial_factura','$ruc_cliente','$nombre_cliente','$direccion_cliente','$observaciones_factura','$guia_factura','$fecha_registro','$estado_factura', '$total_factura', $id_tmp)";
													$query_encabezado_factura = mysqli_query($con,$sql);
												
													//para guardar el detalle de la factura
													while ($row=mysqli_fetch_array($sql_factura_temporal))
														{
															$cantidad_factura=$row["cantidad_tmp"];
															$detalle_factura=$row['nombre_producto'];
															$valor_unitario_factura=$row['precio_tmp'];
															
															$precio_total=$valor_unitario_factura*$cantidad_factura;
															$subtotal_factura=number_format($precio_total,2);//Precio total formateado
															
															$tipo_produccion=$row['tipo_produccion'];
															$tarifa_iva=$row['tarifa_iva'];
															$tarifa_ice=$row['tarifa_ice'];
															$tarifa_bp=$row['tarifa_botellas'];
															$descuento=$row['descuento'];
															$id_producto=$row['id_producto'];
															
															//para sacar el codigo del producto
															$sql_codigo_factura=mysqli_query($con,"select * from productos_servicios where id = '$id_producto'");
															$row_codigo=mysqli_fetch_array($sql_codigo_factura);
															$codigo_producto = $row_codigo['codigo_producto'] ;
															
															$guarda_detalle_factura=mysqli_query($con, "INSERT INTO cuerpo_factura (id_cuerpo_factura, ruc_empresa, serie_factura, secuencial_factura, codigo_producto, cantidad_factura, detalle_factura, valor_unitario_factura, subtotal_factura,tipo_produccion, tarifa_iva, tarifa_ice, tarifa_bp, descuento)
																VALUES (null, '$ruc_empresa','$serie_factura','$secuencial_factura','$codigo_producto','$cantidad_factura','$detalle_factura','$valor_unitario_factura','$subtotal_factura','$tipo_produccion','$tarifa_iva','$tarifa_ice','$tarifa_bp','$descuento')");
														}
											
															if ($query_encabezado_factura && $guarda_detalle_factura){
																$messages[] = "Factura guardada satisfactoriamente.";
																} else
																	{
																		$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
																	}
												}
										}
								}
					}
		} else 
			{
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