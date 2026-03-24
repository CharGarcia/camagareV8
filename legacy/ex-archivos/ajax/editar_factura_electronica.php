<?php
include("../conexiones/conectalogin.php");
include("../clases/guardar_factura_electronica.php");
		$con = conenta_login();
	if (empty($_POST['fecha_factura_e'])) {
           $errors[] = "Ingrese fecha para la factura electrónica.";
		}else if (!date($_POST['fecha_factura_e'])) {
           $errors[] = "Ingrese fecha correcta";
		}else if (empty($_POST['editar_serie_factura'])) {
           $errors[] = "Seleccione una factura para editar.";
		}else if (empty($_POST['secuencial_factura_e'])) {
           $errors[] = "Seleccione una factura para editar.";
		}else if (!is_numeric($_POST['secuencial_factura_e'])) {
           $errors[] = "Ingrese un número de factura electrónica.";
		}else if (empty($_POST['id_cliente_e'])) {
           $errors[] = "Seleccione un cliente para la factura electrónica.";
		}else if (empty($_POST['forma_pago_e'])) {
           $errors[] = "Seleccione una forma de pago.";							
        } else if (!empty($_POST['fecha_factura_e']) && !empty($_POST['editar_serie_factura'])  && !empty($_POST['secuencial_factura_e']) 
		&& !empty($_POST['id_cliente_e'])&& !empty($_POST['forma_pago_e']))
		{
			ini_set('date.timezone','America/Guayaquil');
			$fecha_factura=date('Y-m-d H:i:s', strtotime($_POST['fecha_factura_e']));
			$serie_factura=mysqli_real_escape_string($con,(strip_tags($_POST["editar_serie_factura"],ENT_QUOTES)));
			$secuencial_factura=mysqli_real_escape_string($con,(strip_tags($_POST["secuencial_factura_e"],ENT_QUOTES)));
			$id_cliente=mysqli_real_escape_string($con,(strip_tags($_POST["id_cliente_e"],ENT_QUOTES)));
			$guia_factura=mysqli_real_escape_string($con,(strip_tags($_POST["guia_factura_e"],ENT_QUOTES)));
			$forma_pago_factura=mysqli_real_escape_string($con,(strip_tags($_POST["forma_pago_e"],ENT_QUOTES)));	
			$total_factura=mysqli_real_escape_string($con,(strip_tags($_POST["total_factura_e"],ENT_QUOTES)));
			$propina=mysqli_real_escape_string($con,(strip_tags($_POST["propina_final"],ENT_QUOTES)));
			$tasa_turistica=mysqli_real_escape_string($con,(strip_tags($_POST["tasa_turistica_final"],ENT_QUOTES)));
			$adicional_concepto="";
			$adicional_descripcion="";
			$referencia_salida_inventario=$serie_factura."-".str_pad($secuencial_factura,9,"000000000",STR_PAD_LEFT);

			$fecha_registro=date("Y-m-d H:i:s");
			session_start();
			$id_usuario = $_SESSION['id_usuario'];
			$ruc_empresa = $_SESSION['ruc_empresa'];
			
			$estado_pago = "POR COBRAR";
			$tipo_factura = "ELECTRÓNICA";
			$estado_sri = "PENDIENTE";
		
		if (!empty($guia_factura) && ((strlen( $guia_factura)!=17) or (!is_numeric(substr($guia_factura,0,3))) or (!is_numeric(substr($guia_factura,4,3))) or (!is_numeric(substr($guia_factura,8,9))) or (substr($guia_factura,3,1) != "-") or (substr($guia_factura,7,1) !="-") )) {
			$errors []='Ingrese un número de guía de remisión correcto. ej: 001-001-000000001 '.mysqli_error($con);			
		}else{
			//para comprobar si hay items agregados a la factura
				$sql_factura_temporal=mysqli_query($con,"SELECT * from factura_tmp as fac_tmp LEFT JOIN productos_servicios as pro ON fac_tmp.id_producto=pro.id LEFT JOIN unidad_medida as med ON med.id_medida=fac_tmp.id_medida LEFT JOIN bodega as bod ON bod.id_bodega=fac_tmp.id_bodega WHERE fac_tmp.id_usuario = '".$id_usuario."' and fac_tmp.ruc_empresa = '".$ruc_empresa."'");
				$count=mysqli_num_rows($sql_factura_temporal);
				if ($count==0){
				$errors []= "No hay detalle de productos agregados a la factura.".mysqli_error($con);
				}else{
				//consultar si existe en facturacion de consignacion existen registros y asi guardar los mismos item de la consignacion y no en el inventario como salidas
				$buscar_registros_consignacion=mysqli_query($con, "SELECT * from encabezado_consignacion where ruc_empresa='".$ruc_empresa."' and serie_sucursal='".$serie_factura."' and factura_venta='".$secuencial_factura."' and operacion ='FACTURA'");
				$contar_consignacion=mysqli_num_rows($buscar_registros_consignacion);
				
				//cuando no hay registros de consignaciones
				if ($contar_consignacion==0){
					//para eliminar el encabezado de factura, cuerpo factura, adicionales factura
				$delete_encabezado_factura = mysqli_query($con, "DELETE FROM encabezado_factura WHERE ruc_empresa= '".$ruc_empresa."' and serie_factura= '".$serie_factura."' and secuencial_factura= '".$secuencial_factura."' ");
				$delete_cuerpo_factura = mysqli_query($con, "DELETE FROM cuerpo_factura WHERE ruc_empresa= '".$ruc_empresa."' and serie_factura= '".$serie_factura."' and secuencial_factura= '".$secuencial_factura."'");
				$query_encabezado_factura = guardar_encabezado_factura($con, $ruc_empresa, $fecha_factura, $serie_factura, $secuencial_factura, $id_cliente, $guia_factura, $total_factura, $id_usuario, $propina, $tasa_turistica);	
				//para guardar el detalle de la factura y en el inventario
				$guarda_detalle_factura = detalle_factura_inventario($con, $sql_factura_temporal, $ruc_empresa, $serie_factura, $secuencial_factura, $referencia_salida_inventario, $fecha_factura);
				
				}else{
				//actualizar encabezado factura
				$query_encabezado_factura = actualizar_encabezado_factura($con, $ruc_empresa, $fecha_factura, $serie_factura, $secuencial_factura, $id_cliente, $guia_factura, $id_usuario);	
				//para guardar el detalle de la factura y en el inventario
				$guarda_detalle_factura = true;

				}

				$delete_adicionales_factura = mysqli_query($con, "DELETE FROM detalle_adicional_factura WHERE ruc_empresa= '".$ruc_empresa."' and serie_factura= '".$serie_factura."' and secuencial_factura= '".$secuencial_factura."'");
				$delete_formas_pago_factura = mysqli_query($con, "DELETE FROM formas_pago_ventas WHERE ruc_empresa= '".$ruc_empresa."' and serie_factura= '".$serie_factura."' and secuencial_factura= '".$secuencial_factura."'");
				$delete_registro_inventario = mysqli_query($con, "DELETE FROM inventarios WHERE ruc_empresa = '".$ruc_empresa."' and id_documento_venta='".$referencia_salida_inventario."'");
										
					//para guardar la forma de pago de la factura
					$query_forma_pago_factura = guarda_forma_de_pago($con, $ruc_empresa, $serie_factura, $secuencial_factura, $forma_pago_factura, $total_factura);

					//para guardar detalle adicional de la factura
					$query_guarda_detalle_adicional_factura = adicionales_factura($con, $ruc_empresa, $serie_factura, $secuencial_factura, $id_usuario);

					//para actualizar el cliente en la facturacion de consignacion
					$update_facturacion_consignacion=mysqli_query($con, "UPDATE encabezado_consignacion as enc_con SET enc_con.id_cli_pro = '".$id_cliente."' WHERE enc_con.operacion='FACTURA' and enc_con.serie_sucursal='".$serie_factura."' and enc_con.factura_venta='".$secuencial_factura."' ");
					
						if ($query_encabezado_factura && $query_forma_pago_factura && $query_guarda_detalle_adicional_factura && $guarda_detalle_factura){
							echo "<script>
							$.notify('Factura editada con éxito','success');
							 setTimeout(function (){location.href ='../modulos/facturas.php'}, 1000);
							</script>";	
							} else{
							echo "<script>
							$.notify('Lo siento algo ha salido mal intenta nuevamente','error');
							</script>";	
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
