<?php
		/* Connect To Database*/
		//include("../conexiones/conectalogin.php");
		include("../ajax/buscar_ultima_factura.php");
		$con = conenta_login();
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';	
if($action == 'guardar_facturas'){	

	if (empty($_POST['sucursal_facturar'])) {
           $errors[] = "Seleccione serie de sucursal de la cual desea facturar.";
		}else if (empty($_POST['mes_facturar'])) {
           $errors[] = "Seleccione mes del cual desea facturar.";
		}else if (empty($_POST['aplica_factura'])) {
           $errors[] = "Seleccione a quien desea hacer la factura.";
		}else if (empty($_POST['anio_facturar'])) {
           $errors[] = "Seleccione año del cual desea facturar";
		}else if (empty($_POST['sucursal_alumno_facturar'])) {
           $errors[] = "Seleccione campus.";
		}else if (empty($_POST['paralelo_alumno_facturar'])) {
           $errors[] = "Seleccione nivel del alumno.";
		}else if (empty($_POST['periodo_facturar'])) {
           $errors[] = "Seleccione periodo a facturar.";
		}else if (empty($_POST['fecha_facturar_alumnos'])) {
           $errors[] = "Ingrese fecha de emisión de las facturas.";
		}else if (!date($_POST['fecha_facturar_alumnos'])) {
           $errors[] = "Ingrese fecha correcta dd/mm/aaaa.";
        } else if (!empty($_POST['sucursal_facturar']) && !empty($_POST['mes_facturar']) && !empty($_POST['anio_facturar'])  && !empty($_POST['sucursal_alumno_facturar']) && !empty($_POST['paralelo_alumno_facturar']) && !empty($_POST['periodo_facturar'])  && !empty($_POST['fecha_facturar_alumnos']) && !empty($_POST['aplica_factura'])){

			$serie=mysqli_real_escape_string($con,(strip_tags($_POST["sucursal_facturar"],ENT_QUOTES)));
			$mes_facturar=mysqli_real_escape_string($con,(strip_tags($_POST["mes_facturar"],ENT_QUOTES)));
			$anio_facturar=mysqli_real_escape_string($con,(strip_tags($_POST["anio_facturar"],ENT_QUOTES)));
			$sucursal_alumno_facturar=mysqli_real_escape_string($con,(strip_tags($_POST["sucursal_alumno_facturar"],ENT_QUOTES)));
			$paralelo_alumno_facturar=mysqli_real_escape_string($con,(strip_tags($_POST["paralelo_alumno_facturar"],ENT_QUOTES)));
			$periodo_facturar=mysqli_real_escape_string($con,(strip_tags($_POST["periodo_facturar"],ENT_QUOTES)));
			$fecha_facturar_alumnos=date('Y-m-d H:i:s', strtotime($_POST['fecha_facturar_alumnos']));
			$periodo = $mes_facturar . "-" . $anio_facturar;
			$fecha_agregado=date("Y-m-d H:i:s");
			//session_start();
			$id_usuario= $_SESSION['id_usuario'];
			$ruc_empresa = $_SESSION['ruc_empresa'];
			$estado_pago = "POR COBRAR";
			$tipo_factura = "ELECTRÓNICA";
			$estado_sri = "PENDIENTE";
			//traer el numero de factura que continua 
			/*
			$busca_factura = "SELECT MAX(secuencial_factura) as maximo FROM encabezado_factura WHERE ruc_empresa = '$ruc_empresa' and serie_factura = '$serie' and tipo_factura = 'ELECTRÓNICA'";
			$result = $con->query($busca_factura);
			$res_sql = mysqli_fetch_assoc($result);
			$factura_final = $res_sql['maximo']+1;
			*/

			
			
			foreach ($_POST['aplica_factura'] as $id_alumnos_facturar ){
				$factura_final = siguiente_documento($con, $ruc_empresa, $serie);	
				//para guardar el detalle de la factura	 
				$sql_detalle_por_facturar=mysqli_query($con,"select * from detalle_por_facturar where ruc_empresa='$ruc_empresa' and id_referencia = $id_alumnos_facturar and cuando_facturar = '$periodo_facturar' ");
					while ($row_detalle=mysqli_fetch_array($sql_detalle_por_facturar)){
						$id_producto=$row_detalle["id_producto"];
						$cantidad_producto=$row_detalle["cant_producto"];
						$precio_venta=$row_detalle["precio_producto"];
						$subtotal_factura=str_replace(",",".",$precio_venta*$cantidad_producto);
						//para traer tipo de tarivas y tipos de produccion
						$sql_tarifas=mysqli_query($con, "SELECT * FROM productos_servicios WHERE id= $id_producto ");
						$row_tarifas=mysqli_fetch_array($sql_tarifas);
						$tipo_produccion=$row_tarifas['tipo_produccion'];
						$tarifa_iva=$row_tarifas['tarifa_iva'];
						$tarifa_ice=$row_tarifas['tarifa_ice'];
						$tarifa_bp=$row_tarifas['tarifa_botellas'];
						$codigo_producto=$row_tarifas['codigo_producto'];
						$nombre_producto=$row_tarifas['nombre_producto'];
						//traer descuentos programados
						$sql_descuento_producto=mysqli_query($con, "SELECT sum(valor_descuento) as valdes FROM descuentos_programados WHERE id_referencia= $id_alumnos_facturar and mes_descuento = '$mes_facturar' and anio_descuento = '$anio_facturar' and ruc_empresa='$ruc_empresa' and id_producto = $id_producto");
						$row_descuentos_producto=mysqli_fetch_array($sql_descuento_producto);
						$descuento=$row_descuentos_producto['valdes'];
						$subtotal_final = $subtotal_factura;
						$guarda_detalle_factura=mysqli_query($con, "INSERT INTO cuerpo_factura VALUES (null, '$ruc_empresa','$serie',$factura_final,$id_producto,'$cantidad_producto',$precio_venta,'$subtotal_final','$tipo_produccion','$tarifa_iva','$tarifa_ice','$tarifa_bp','$descuento','$codigo_producto','$nombre_producto')");
					}
					

				//traer datos del cliente
				$sql_clientes=mysqli_query($con, "SELECT * FROM alumnos al, clientes cl WHERE al.id_alumno= $id_alumnos_facturar and al.ruc_empresa='$ruc_empresa' and al.id_cliente=cl.id");
				$row_clientes=mysqli_fetch_array($sql_clientes);
				$id_cliente=$row_clientes['id_cliente'];
				$email=$row_clientes['email'];
				$direccion=$row_clientes['direccion'];
				$nombres_alumno=$row_clientes['nombres_alumno'];
				$apellidos_alumno=$row_clientes['apellidos_alumno'];
				$alumno= strtoupper($nombres_alumno ." ".$apellidos_alumno);
				$sexo_alumno=$row_clientes['sexo_alumno'];
					if($sexo_alumno=='F'){
						$sexo_alumno = "NIÑA";
					}else{
						$sexo_alumno = "NIÑO";
					}
				
				//consultar el valor total de cada factura
				$sql_total_factura=mysqli_query($con, "SELECT sum(subtotal_factura-descuento) as total FROM cuerpo_factura WHERE ruc_empresa='$ruc_empresa' and serie_factura = '$serie' and secuencial_factura = $factura_final ");
				$row_total_factura=mysqli_fetch_array($sql_total_factura);
				$total_factura=$row_total_factura['total'];
				
				//para guardar encabezado de factura
				$guarda_encabezado_factura="INSERT INTO encabezado_factura VALUES (null, '$ruc_empresa','$fecha_facturar_alumnos','$serie','$factura_final','$id_cliente','','','$fecha_agregado','$estado_pago','$tipo_factura','$estado_sri', '$total_factura', $id_usuario,'0','0','','PENDIENTE',0,0)";
				$query_encabezado_factura = mysqli_query($con,$guarda_encabezado_factura);	

				//para guardar la forma de pago de la factura
				$guarda_forma_pago="INSERT INTO formas_pago_ventas VALUES (null, '$ruc_empresa','$serie','$factura_final','20', '$total_factura')";
				$query_forma_pago_factura = mysqli_query($con,$guarda_forma_pago);
				// para guardar detalle adicional factura
				$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '$ruc_empresa','$serie',$factura_final,'Email','$email')");
				$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '$ruc_empresa','$serie',$factura_final,'Dirección','$direccion')");
				$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '$ruc_empresa','$serie',$factura_final,'$sexo_alumno','$alumno')");
				$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '$ruc_empresa','$serie',$factura_final,'MES','$periodo')");
				
					
				//$factura_final = $factura_final+1;
			}
			
				if ($query_encabezado_factura && $guarda_detalle_factura && $query_forma_pago_factura && $query_guarda_detalle_adicional_factura ){
					$messages[] = "Facturas electrónicas guardadas satisfactoriamente.";
					} else
						{
					$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
						}
		}
		else 
		{
		$errors []= "Error desconocido.";
		}
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