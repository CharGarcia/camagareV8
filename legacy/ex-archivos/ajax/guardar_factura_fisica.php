<?php
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		include("../validadores/fecha.php");
		$con = conenta_login();
	if (empty($_POST['fecha_factura_e'])) {
           $errors[] = "Ingrese fecha para la factura electrónica.";
		}else if (!date($_POST['fecha_factura_e'])) {
           $errors[] = "Ingrese fecha correcta";
		}else if (empty($_POST['serie_factura_e'])) {
           $errors[] = "Seleccione serie para la factura electrónica.";
		}else if (empty($_POST['secuencial_factura_e'])) {
           $errors[] = "Ingrese un número de factura electrónica.";
		}else if (!is_numeric($_POST['secuencial_factura_e'])) {
           $errors[] = "Ingrese un número de factura electrónica.";
		}else if (empty($_POST['id_cliente_e'])) {
           $errors[] = "Seleccione un cliente para la factura electrónica.";
		}else if (empty($_POST['forma_pago_e'])) {
           $errors[] = "Seleccione una forma de pago.";							
        } else if (!empty($_POST['fecha_factura_e']) && !empty($_POST['serie_factura_e'])  && !empty($_POST['secuencial_factura_e']) 
		&& !empty($_POST['id_cliente_e'])&& !empty($_POST['forma_pago_e']))
		{

			$fecha_factura=date('Y-m-d H:i:s', strtotime($_POST['fecha_factura_e']));
			$serie_factura=mysqli_real_escape_string($con,(strip_tags($_POST["serie_factura_e"],ENT_QUOTES)));
			$secuencial_factura=mysqli_real_escape_string($con,(strip_tags($_POST["secuencial_factura_e"],ENT_QUOTES)));
			$id_cliente=mysqli_real_escape_string($con,(strip_tags($_POST["id_cliente_e"],ENT_QUOTES)));
			$guia_factura=mysqli_real_escape_string($con,(strip_tags($_POST["guia_factura_e"],ENT_QUOTES)));
			$forma_pago_factura=mysqli_real_escape_string($con,(strip_tags($_POST["forma_pago_e"],ENT_QUOTES)));	
			$total_factura=mysqli_real_escape_string($con,(strip_tags($_POST["total_factura_e"],ENT_QUOTES)));
			$adicional_concepto="";//mysqli_real_escape_string($con,(strip_tags($_POST["adicional_concepto"],ENT_QUOTES)));
			$adicional_descripcion="";//mysqli_real_escape_string($con,(strip_tags($_POST["adicional_descripcion"],ENT_QUOTES)));
			$fecha_registro=date("Y-m-d H:i:s");
			$observaciones_factura = "";
			session_start();
			$id_usuario = $_SESSION['id_usuario'];
			$ruc_empresa = $_SESSION['ruc_empresa'];
			//traer correo de esta empresa
			$busca_empresa = mysqli_query($con,"SELECT * FROM empresas WHERE ruc = '$ruc_empresa'");
			$datos_mail_empresa = mysqli_fetch_array($busca_empresa);
			$email_empresa='Favor enviar retención a: '.$datos_mail_empresa['mail'];
			
			$estado_pago = "POR COBRAR";
			$tipo_factura = "FISICA";
			$estado_sri = "PENDIENTE";
		
		if (!empty($guia_factura) && ((strlen( $guia_factura)!=17) or (!is_numeric(substr($guia_factura,0,3))) or (!is_numeric(substr($guia_factura,4,3))) or (!is_numeric(substr($guia_factura,8,9))) or (substr($guia_factura,3,1) != "-") or (substr($guia_factura,7,1) !="-") )) {
			$errors []='Ingrese un número de guía de remisión correcto. ej: 001-001-000000001 '.mysqli_error($con);			
		}else{
									//paraa ver si la factura que queremos registrar ya esta registrada	
									 $busca_empresa = "SELECT * FROM encabezado_factura WHERE ruc_empresa = '$ruc_empresa' and serie_factura = '$serie_factura' and secuencial_factura ='$secuencial_factura' and tipo_factura = 'FÍSICA'";
									 $result = $con->query($busca_empresa);
									 $count = mysqli_num_rows($result);
									 if ($count == 1){
									$errors []= "El número de factura que intenta guardar ya se encuentra registrado en el sistema.".mysqli_error($con);
									}else{
											$sql_factura_temporal=mysqli_query($con,"select * from factura_tmp where id_usuario = '$id_usuario'");
											$count=mysqli_num_rows($sql_factura_temporal);
											if ($count==0){
											$errors []= "No hay detalle de productos agregados a la factura.".mysqli_error($con);
											}else{
										//para guardar el encabezado de la factura
										$guarda_encabezado_factura="INSERT INTO encabezado_factura VALUES (null, '$ruc_empresa','$fecha_factura','$serie_factura','$secuencial_factura','$id_cliente','$observaciones_factura','$guia_factura','$fecha_registro','$estado_pago','$tipo_factura','$estado_sri', '$total_factura', $id_usuario,'0','0','','PENDIENTE')";
										$query_encabezado_factura = mysqli_query($con,$guarda_encabezado_factura);
										
										//para guardar la forma de pago de la factura
										$guarda_forma_pago="INSERT INTO formas_pago_ventas VALUES (null, '$ruc_empresa','$serie_factura','$secuencial_factura','$forma_pago_factura', '$total_factura')";
										$query_forma_pago_factura = mysqli_query($con,$guarda_forma_pago);
										
										//para guardar detalle adicional de la factura
										$busca_adicional_tmp = "SELECT * FROM adicional_tmp WHERE id_usuario = $id_usuario and serie_factura = '$serie_factura' and secuencial_factura = secuencial_factura";
										$query = mysqli_query($con, $busca_adicional_tmp);
										//para ver si ya estan agregados adicionales a la factura que se va hacer o sino se guarda el mail y la direccion como adicional
										$adicionales_encontradas = mysqli_num_rows($query);
											if ($adicionales_encontradas ==0){
										$busca_empresa_detalle = "SELECT * FROM clientes WHERE id = $id_cliente ";
										$result_detalle = $con->query($busca_empresa_detalle);
										$datos_detalle = mysqli_fetch_array($result_detalle);
										$email=$datos_detalle['email'];
										$direccion=$datos_detalle['direccion'];

										$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '$ruc_empresa','$serie_factura',$secuencial_factura,'Nota','$email_empresa')");
										$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '$ruc_empresa','$serie_factura',$secuencial_factura,'Email','$email')");
										$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '$ruc_empresa','$serie_factura',$secuencial_factura,'Dirección','$direccion')");
											}else{
												
												while ($row_detalle_adicional=mysqli_fetch_array($query)){
												$concepto=$row_detalle_adicional['concepto'];
												$detalle=$row_detalle_adicional['detalle'];
												$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '$ruc_empresa','$serie_factura',$secuencial_factura,'$concepto','$detalle')");
												}
											}
										
										
										//para guardar el detalle de la factura
										while ($row_detalle=mysqli_fetch_array($sql_factura_temporal)){
												$cantidad_factura=str_replace(",",".",$row_detalle["cantidad_tmp"]);
												$precio_venta=str_replace(",",".",$row_detalle['precio_tmp']);
												$subtotal_factura=str_replace(",",".",$precio_venta*$cantidad_factura);//Precio total formateado
												$tipo_produccion=$row_detalle['tipo_produccion'];
												$tarifa_iva=$row_detalle['tarifa_iva'];
												$tarifa_ice=$row_detalle['tarifa_ice'];
												$tarifa_bp=$row_detalle['tarifa_botellas'];
												$descuento=$row_detalle['descuento'];
												$id_producto=$row_detalle['id_producto'];
												
												$guarda_detalle_factura=mysqli_query($con, "INSERT INTO cuerpo_factura VALUES (null, '$ruc_empresa','$serie_factura',$secuencial_factura,$id_producto,'$cantidad_factura',$precio_venta,'$subtotal_factura','$tipo_produccion','$tarifa_iva','$tarifa_ice','$tarifa_bp','$descuento')");
											}
								
												if ($query_encabezado_factura && $guarda_detalle_factura && $query_forma_pago_factura && $query_guarda_detalle_adicional_factura ){
													echo "<script>
													$.notify('Factura guardada con éxito','success');
													setTimeout(function () {location.reload()}, 60 * 20); 
													</script>";													
													} else
														{
													$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
														}
									}
							
						}		
		
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