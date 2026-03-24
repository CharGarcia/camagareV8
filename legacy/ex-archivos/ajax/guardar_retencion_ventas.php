<?php
		include("../clases/contabilizacion.php");
		$contabilizacion = new contabilizacion();
		include("../conexiones/conectalogin.php");
		include("../validadores/generador_codigo_unico.php");
		$con = conenta_login();
	if (empty($_POST['fecha_retencion_venta'])) {
           $errors[] = "Ingrese fecha para la retención.";
		}else if (!date($_POST['fecha_retencion_venta'])) {
           $errors[] = "Ingrese fecha de emisión de retención correcta";
		}else if (empty($_POST['numero_retencion_venta'])) {
           $errors[] = "Ingrese número de retención.";
		}else if (empty($_POST['id_cliente_ret'])) {
           $errors[] = "Seleecione un cliente.";
		}else if (empty($_POST['numero_comprobante'])) {
           $errors[] = "Ingrese un comprobante de venta.";		
        } else if (!empty($_POST['fecha_retencion_venta']) && !empty($_POST['numero_retencion_venta'])  && !empty($_POST['id_cliente_ret']) && !empty($_POST['numero_comprobante'])){

			$codigo_unico=codigo_unico(20);
			$fecha_retencion=date('Y-m-d H:i:s', strtotime($_POST['fecha_retencion_venta']));
			$numero_retencion_venta=mysqli_real_escape_string($con,(strip_tags($_POST["numero_retencion_venta"],ENT_QUOTES)));
			$id_cliente=mysqli_real_escape_string($con,(strip_tags($_POST["id_cliente_ret"],ENT_QUOTES)));
			$id_comprobante=mysqli_real_escape_string($con,(strip_tags($_POST["tipo_comprobante"],ENT_QUOTES)));
			$numero_comprobante=str_replace("-","",mysqli_real_escape_string($con,(strip_tags($_POST["numero_comprobante"],ENT_QUOTES))));
			$total_retencion=mysqli_real_escape_string($con,(strip_tags($_POST["total_retencion_e"],ENT_QUOTES)));
			session_start();
			$id_usuario = $_SESSION['id_usuario'];
			$ruc_empresa = $_SESSION['ruc_empresa'];
			$mes_fiscal = date("m", strtotime($fecha_retencion));
			$anio_fiscal = date("Y", strtotime($fecha_retencion));
			$ejercicio_fiscal = $mes_fiscal."/".$anio_fiscal;
			$serie_retencion=substr($numero_retencion_venta,0,3)."-".substr($numero_retencion_venta,4,3);
			$secuencial_retencion=substr($numero_retencion_venta,8,9);
		
					//para ver si la retencion que queremos registrar ya esta registrada	
					 $busca_retencion = "SELECT * FROM encabezado_retencion_venta WHERE ruc_empresa = '".$ruc_empresa."' and serie_retencion = '".$serie_retencion."' and secuencial_retencion = '".$secuencial_retencion."' and id_cliente='".$id_cliente."' ";
					 $result = $con->query($busca_retencion);
					 $count = mysqli_num_rows($result);
					 if ($count == 1){
					$errors []= "El número de retención que intenta guardar ya se encuentra registrado en el sistema.".mysqli_error($con);
					}else{							
						$sql_retencion_temporal=mysqli_query($con,"select * from retencion_tmp where id_usuario = '".$id_usuario."'");
						$count=mysqli_num_rows($sql_retencion_temporal);
						if ($count==0){
						$errors []= "No hay conceptos de retenciones agregados.".mysqli_error($con);
						}else{
						//para guardar el encabezado de la retencion
						$query_encabezado_retencion=mysqli_query($con,"INSERT INTO encabezado_retencion_venta VALUES (null, '".$ruc_empresa."','".$id_cliente."','".$serie_retencion."','".$secuencial_retencion."','0','".$fecha_retencion."','".$id_usuario."','0','".$codigo_unico."','".$numero_comprobante."')");
												
						//para guardar el detalle de la retencion
						while ($row_detalle=mysqli_fetch_array($sql_retencion_temporal)){
								$id_ret=$row_detalle["id_ret"];
								$cod_ret=$row_detalle["cod_ret"];
								$concepto_ret=$row_detalle["concepto_ret"];
								$nombre_impuesto=$row_detalle["impuesto_ret"];
								
								switch ($nombre_impuesto) {
								case "RENTA":
									$nombre_impuesto='1';
									break;
								case "IVA":
									$nombre_impuesto='2';
									break;
								case "ISD":
									$nombre_impuesto='6';
									break;
									}
								
								$impuesto_ret=$nombre_impuesto;
								$porcentaje_ret=$row_detalle["porcentaje_ret"];
								$base_ret=str_replace(",",".",$row_detalle['base_ret']);
								$val_ret=str_replace(",",".",$row_detalle['val_ret']);
								
								$guarda_detalle_retencion=mysqli_query($con, "INSERT INTO cuerpo_retencion_venta VALUES (null,'".$serie_retencion."','".$secuencial_retencion."','".$ruc_empresa."','".$ejercicio_fiscal."','".$base_ret."','".$cod_ret."','".$impuesto_ret."','".$porcentaje_ret."','".$val_ret."','".$codigo_unico."','".$id_comprobante."','".$numero_comprobante."')");
							}
							
								if ($query_encabezado_retencion && $guarda_detalle_retencion ){
									//para guardar asientos contables
									$contabilizacion->documentosRetencionesVentas($con, $ruc_empresa, $fecha_retencion, $fecha_retencion);
									$guardar_asientos_contables_generados = $contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'retenciones_ventas');
									$messages []= "Retención guardada con éxito.".mysqli_error($con);
									echo "<script>
									setTimeout(function () {location.reload()}, 1000); 
									</script>";	
										} else{
										$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
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