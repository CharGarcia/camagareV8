<?php
include("../clases/contabilizacion.php");
$contabilizacion = new contabilizacion();
include("../ajax/buscar_ultimo_recibo.php");
$con = conenta_login();
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';	
if($action == 'guardar_recibo'){	

	if (empty($_POST['sucursal_facturar'])) {
           $errors[] = "Seleccione serie de sucursal de la cual desea facturar.";
		}else if (empty($_POST['mes_facturar'])) {
           $errors[] = "Seleccione mes del cual desea facturar.";
		}else if (empty($_POST['aplica_recibo'])) {
           $errors[] = "Seleccione a quien desea hacer el recibo.";
		}else if (empty($_POST['anio_facturar'])) {
           $errors[] = "Seleccione año del cual desea facturar";
		}else if (empty($_POST['periodo_facturar'])) {
           $errors[] = "Seleccione periodo a facturar.";
		}else if (empty($_POST['fecha_facturar_programados'])) {
           $errors[] = "Ingrese fecha de emisión de los recibos.";
		}else if (!date($_POST['fecha_facturar_programados'])) {
           $errors[] = "Ingrese fecha correcta dd/mm/aaaa.";
        } else if (!empty($_POST['sucursal_facturar']) && !empty($_POST['mes_facturar']) && !empty($_POST['anio_facturar'])  && !empty($_POST['periodo_facturar'])  && !empty($_POST['fecha_facturar_programados']) && !empty($_POST['aplica_recibo'])){

			$serie=mysqli_real_escape_string($con,(strip_tags($_POST["sucursal_facturar"],ENT_QUOTES)));
			$mes_facturar=mysqli_real_escape_string($con,(strip_tags($_POST["mes_facturar"],ENT_QUOTES)));
			$anio_facturar=mysqli_real_escape_string($con,(strip_tags($_POST["anio_facturar"],ENT_QUOTES)));
			$periodo_facturar=mysqli_real_escape_string($con,(strip_tags($_POST["periodo_facturar"],ENT_QUOTES)));
			$fecha_facturar_programados=date('Y-m-d H:i:s', strtotime($_POST['fecha_facturar_programados']));
			$periodo = $mes_facturar . "-" . $anio_facturar;
			$fecha_agregado=date("Y-m-d H:i:s");
			//session_start();
			$id_usuario= $_SESSION['id_usuario'];
			$ruc_empresa = $_SESSION['ruc_empresa'];
				
			foreach ($_POST['aplica_recibo'] as $id_cliente_programado ){
				//consultar el valor total de cada factura + impuestos
				$id_cliente_facturar="RECIBO".$id_cliente_programado;

				$sql_total_recibo=mysqli_query($con, "SELECT sum((dpf.cant_producto * dpf.precio_producto -dpf.descuento) * (1 + (tar.porcentaje_iva / 100))) as total FROM detalle_por_facturar as dpf INNER JOIN productos_servicios as pro ON pro.id=dpf.id_producto INNER JOIN tarifa_iva as tar ON tar.codigo=pro.tarifa_iva WHERE dpf.ruc_empresa='".$ruc_empresa."' and dpf.id_referencia='".$id_cliente_facturar."' group by dpf.id_referencia ");
				$row_total_recibo=mysqli_fetch_array($sql_total_recibo);
				$total_recibo=$row_total_recibo['total'];
				
				//traer datos del cliente
				$sql_clientes=mysqli_query($con, "SELECT * FROM clientes as cl INNER JOIN clientes_recibos_programados as crp 
				ON crp.id_cliente=cl.id WHERE crp.id_fp= '".$id_cliente_programado."' and crp.ruc_empresa='".$ruc_empresa."' ");
				$row_clientes=mysqli_fetch_array($sql_clientes);
				$id_cliente=$row_clientes['id_cliente'];
				$email=$row_clientes['email'];
				$direccion=$row_clientes['direccion'];
				//para guardar encabezado de recibo
				$ultimo_recibo = siguiente_documento($con, $ruc_empresa, $serie);
				$guarda_encabezado_recibo=mysqli_query($con, "INSERT INTO encabezado_recibo VALUES (null, '".$ruc_empresa."','".$fecha_facturar_programados."','".$serie."','".$ultimo_recibo."','".$id_cliente."','".$fecha_agregado."','".$total_recibo."','".$id_usuario."','0','0', '0', '1')");
				$lastid = mysqli_insert_id($con);
				//para guardar el detalle de la factura	 
				
				$sql_detalle_por_facturar=mysqli_query($con,"select * from detalle_por_facturar where ruc_empresa='".$ruc_empresa."' and id_referencia = '".$id_cliente_facturar."' and cuando_facturar = '".$periodo_facturar."' ");
					while ($row_detalle=mysqli_fetch_array($sql_detalle_por_facturar)){
						$id_producto=$row_detalle["id_producto"];
						$cantidad_producto=$row_detalle["cant_producto"];
						$precio_venta=$row_detalle["precio_producto"];
						$subtotal_recibo=str_replace(",",".",$precio_venta*$cantidad_producto);
						//para traer tipo de tarivas y tipos de produccion
						$sql_tarifas=mysqli_query($con, "SELECT * FROM productos_servicios WHERE id= '".$id_producto."' ");
						$row_tarifas=mysqli_fetch_array($sql_tarifas);
						$tipo_produccion=$row_tarifas['tipo_produccion'];
						$tarifa_iva=$row_tarifas['tarifa_iva'];
						$tarifa_ice=$row_tarifas['tarifa_ice'];
						$tarifa_bp=$row_tarifas['tarifa_botellas'];
						$codigo_producto=$row_tarifas['codigo_producto'];
						$nombre_producto=$row_tarifas['nombre_producto'];
						$id_medida=$row_tarifas['id_unidad_medida'];
						//traer descuentos programados
						$sql_descuento_producto=mysqli_query($con, "SELECT sum(valor_descuento) as valdes FROM descuentos_programados WHERE id_referencia= '".$id_cliente_facturar."' and mes_descuento = '".$mes_facturar."' and anio_descuento = '".$anio_facturar."' and ruc_empresa='".$ruc_empresa."' and id_producto = '".$id_producto."'");
						$row_descuentos_producto=mysqli_fetch_array($sql_descuento_producto);
						$descuento=$row_descuentos_producto['valdes'];
						$subtotal_final = $subtotal_recibo;
						$guarda_detalle_recibo_programada=mysqli_query($con, "INSERT INTO cuerpo_recibo VALUES (null, '".$lastid."','".$id_producto."','".$cantidad_producto."','".$precio_venta."','".$subtotal_final."','".$tipo_produccion."','".$tarifa_iva."','".$tarifa_ice."','','".$descuento."','".$codigo_producto."','".$nombre_producto."','".$id_medida."','0','0','0')");
					}
						
				// para guardar detalle adicional recibo
				$query_guarda_detalle_adicional_recibo = mysqli_query($con, "INSERT INTO detalle_adicional_recibo VALUES (null, '".$lastid."','Email','".$email."')");
				$query_guarda_detalle_adicional_recibo = mysqli_query($con, "INSERT INTO detalle_adicional_recibo VALUES (null, '".$lastid."','Dirección','".$direccion."')");
				$query_guarda_detalle_adicional_recibo = mysqli_query($con, "INSERT INTO detalle_adicional_recibo VALUES (null, '".$lastid."','MES','".$periodo."')");
								
				//eliminar los registros de solo una vez en la tabla de detalle por facturar
				$sql_eliminar_resgistros_solo_una_vez=mysqli_query($con,"DELETE FROM detalle_por_facturar WHERE id_referencia= '".$id_cliente_facturar."' and cuando_facturar = '03' and ruc_empresa='".$ruc_empresa."'");	
			}
			
				if ($guarda_encabezado_recibo && $guarda_detalle_recibo_programada && $query_guarda_detalle_adicional_recibo ){
					
				//para guardar asientos contables de recibos
				$contabilizacion->documentosVentasRecibos($con, $ruc_empresa, $fecha_facturar_programados, $fecha_facturar_programados);
				$guardar_asientos_contables_generados = $contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'recibos');

					$messages[] = "Recibos guardados satisfactoriamente.";
					} else{
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