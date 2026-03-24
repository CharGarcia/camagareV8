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
} else if (empty($_POST['id_cliente_mecanica'])) {
	$errors[] = "Agregue un cliente a la factura.";
} else if (empty($_POST['fecha_mecanica'])) {
	$errors[] = "Ingrese fecha de la factura";
} else if (!date($_POST['fecha_mecanica'])) {
	$errors[] = "Ingrese fecha correcta de la factura";
} else if (!empty($_POST['codigo_unico_factura']) && !empty($_POST['id_cliente_mecanica']) && !empty($_POST['fecha_mecanica'])) {
	ini_set('date.timezone', 'America/Guayaquil');
	$codigo_unico_factura = mysqli_real_escape_string($con, (strip_tags($_POST["codigo_unico_factura"], ENT_QUOTES)));
	$id_cliente = mysqli_real_escape_string($con, (strip_tags($_POST["id_cliente_mecanica"], ENT_QUOTES)));
	$fecha_mecanica = date('Y-m-d', strtotime($_POST['fecha_mecanica']));
	$serie_facturar = mysqli_real_escape_string($con, (strip_tags($_POST["serie_mecanica"], ENT_QUOTES)));
	$forma_pago = "20"; //mysqli_real_escape_string($con,(strip_tags($_POST["forma_pago_e"],ENT_QUOTES)));
	$total_factura = mysqli_real_escape_string($con, (strip_tags($_POST["total_factura"], ENT_QUOTES)));
	$fecha_registro = date("Y-m-d");

	session_start();
	$id_usuario = $_SESSION['id_usuario'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	//traer correo de esta empresa
	$busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "'");
	$datos_mail_empresa = mysqli_fetch_array($busca_empresa);
	$email_receptor = $datos_mail_empresa['mail'];
	$nombre_comercial = $datos_mail_empresa['nombre_comercial'];

	//traer el numero de factura que continua
	$factura_final = $secuencial_electronico->consecutivo_siguiente($con, $ruc_empresa, 'factura', $serie_facturar);

	$sql_detalle_por_facturar = mysqli_query($con, "SELECT * from detalle_factura_mecanica WHERE ruc_empresa='" . $ruc_empresa . "' and codigo_unico = '" . $codigo_unico_factura . "'");
	$contar_registros = mysqli_num_rows($sql_detalle_por_facturar);

	if ($id_cliente != 0) {
		if ($contar_registros > 0 && $id_cliente != 0) {

			//para guardar encabezado de factura
			$guarda_encabezado_factura = mysqli_query($con, "INSERT INTO encabezado_factura VALUES (null, '" . $ruc_empresa . "',
				'" . $fecha_registro . "','" . $serie_facturar . "','" . $factura_final . "',
				'" . $id_cliente . "','','','" . $fecha_registro . "','POR COBRAR','ELECTRÓNICA','PENDIENTE', '" . $total_factura . "',
				 '" . $id_usuario . "','0','0','','PENDIENTE',0,0)");

			while ($row_detalle = mysqli_fetch_array($sql_detalle_por_facturar)) {
				$id_producto = $row_detalle["id_producto"];
				$cantidad_producto = $row_detalle["cantidad"];
				$precio_venta = $row_detalle["precio"];
				$descuento = $row_detalle['descuento'];
				$subtotal_factura = $row_detalle['subtotal'] + $row_detalle['descuento'];
				$id_bodega = $row_detalle['id_bodega'];
				$id_medida = $row_detalle['id_medida'];
				$lote = $row_detalle['lote'];
				$vencimiento = $row_detalle['vencimiento'];
				$tarifa_iva = $row_detalle['tarifa_iva'];

				//para traer tipo de tarifas y tipos de produccion
				$sql_tarifas = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id= '" . $id_producto . "' ");
				$row_tarifas = mysqli_fetch_array($sql_tarifas);
				$tipo_produccion = $row_tarifas['tipo_produccion'];

				$tarifa_ice = $row_tarifas['tarifa_ice'];
				$tarifa_bp = $row_tarifas['tarifa_botellas'];
				$codigo_producto = $row_tarifas['codigo_producto'];
				$nombre_producto = $row_tarifas['nombre_producto'];
				$guarda_detalle_factura = mysqli_query($con, "INSERT INTO cuerpo_factura VALUES (null, '" . $ruc_empresa . "','" . $serie_facturar . "','" . $factura_final . "','" . $id_producto . "','" . $cantidad_producto . "','" . $precio_venta . "','" . $subtotal_factura . "','" . $tipo_produccion . "','" . $tarifa_iva . "','" . $tarifa_ice . "','" . $tarifa_bp . "','" . $descuento . "','" . $codigo_producto . "','" . $nombre_producto . "','" . $id_medida . "','" . $lote . "','" . $vencimiento . "','" . $id_bodega . "')");
			}

			//traer datos del cliente
			$sql_clientes = mysqli_query($con, "SELECT * FROM clientes WHERE id='" . $id_cliente . "'");
			$row_clientes = mysqli_fetch_array($sql_clientes);
			$email = $row_clientes['email'];
			$direccion = $row_clientes['direccion'];
			$telefono = $row_clientes['telefono'];
			//traer datos de la orden
			$sql_orden = mysqli_query($con, "SELECT * FROM encabezado_mecanica enc_fac, vehiculos as veh WHERE enc_fac.codigo_unico = veh.codigo_unico and enc_fac.codigo_unico='" . $codigo_unico_factura . "'");
			$row_orden = mysqli_fetch_array($sql_orden);
			$numero_orden = $row_orden['numero_orden'];
			$placa = $row_orden['placa'];
			$proxima_cita = !empty($row_orden['proximo_chequeo']) ? date('d-m-Y', strtotime($row_orden['proximo_chequeo'])) : "";
			$observaciones_proxima_cita = $row_orden['obs_prox_chequeo'];


			//para guardar la forma de pago de la factura
			$guarda_forma_pago = "INSERT INTO formas_pago_ventas VALUES (null, '" . $ruc_empresa . "','" . $serie_facturar . "','" . $factura_final . "','" . $forma_pago . "', '" . $total_factura . "')";
			$query_forma_pago_factura = mysqli_query($con, $guarda_forma_pago);
			// para guardar detalle adicional factura
			$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '" . $ruc_empresa . "','" . $serie_facturar . "','" . $factura_final . "','Email','" . $email . "')");
			$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '" . $ruc_empresa . "','" . $serie_facturar . "','" . $factura_final . "','Dirección','" . $direccion . "')");
			if (!empty($telefono)) {
				$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '" . $ruc_empresa . "','" . $serie_facturar . "','" . $factura_final . "','Teléfono','" . $telefono . "')");
			}
			$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '" . $ruc_empresa . "','" . $serie_facturar . "','" . $factura_final . "','Placa','" . $placa . "')");
			$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '" . $ruc_empresa . "','" . $serie_facturar . "','" . $factura_final . "','Orden N.','" . $numero_orden . "')");
			if (!empty($proxima_cita)) {
				$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '" . $ruc_empresa . "','" . $serie_facturar . "','" . $factura_final . "','Próxima cita','" . $proxima_cita . "')");
			}
			if (!empty($observaciones_proxima_cita)) {
				$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '" . $ruc_empresa . "','" . $serie_facturar . "','" . $factura_final . "','Observaciones','" . $observaciones_proxima_cita . "')");
			}

			//para registrar si fue generada una factura desde la orden mecanica
			$guarda_registro_facturado = mysqli_query($con, "INSERT INTO registros_facturados VALUES (null, '" . $ruc_empresa . "', 'orden_mecanica','Factura','" . $numero_orden . "', '" . $serie_facturar . "','" . $factura_final . "', '" . $total_factura . "', '" . $id_usuario . "', '" . $id_cliente . "', '" . date('Y-m-d H:i:s') . "')");


			//actualizar encabezado de factura a cerrado
			$actualizar_encabezado_factura = mysqli_query($con, "UPDATE encabezado_mecanica SET estado='CERRADA' WHERE codigo_unico='" . $codigo_unico_factura . "'");
			$actualizar_detalle_factura_mecanica = mysqli_query($con, "UPDATE detalle_factura_mecanica SET secuencial='" . $factura_final . "' WHERE codigo_unico='" . $codigo_unico_factura . "'");

			if ($guarda_encabezado_factura && $guarda_detalle_factura && $query_forma_pago_factura && $query_guarda_detalle_adicional_factura && $actualizar_encabezado_factura && $actualizar_detalle_factura_mecanica) {
				//guardar el asiento contable
				$contabilizacion->documentosVentasFacturas($con, $ruc_empresa, $_POST['fecha_mecanica'], $_POST['fecha_mecanica']);
				$guardar_asientos_contables_generados = $contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'ventas');

				echo "<script>
		$.notify('Factura guardada.','success');
		</script>";
				echo "<script>setTimeout(function () {location.reload()}, 40 * 20)</script>";
			} else {
				echo "<script>
		$.notify('Lo siento algo ha salido mal intenta nuevamente.','error');
		</script>";
			}
		} else {
			$errors[] = "No hay servicios y/o productos agregados para generar la factura.";
		}
	} else {
		$errors[] = "No hay cliente o detalle de productos asignados a la factura.";
	}
} else {
	$errors[] = "Error desconocido.";
}

if (isset($errors)) {
?>
	<div class="alert alert-danger" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>Atención! </strong>
		<?php
		foreach ($errors as $error) {
			echo $error;
		}
		?>
	</div>
<?php
}
if (isset($messages)) {

?>
	<div class="alert alert-success" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>¡Bien hecho! </strong>
		<?php
		foreach ($messages as $message) {
			echo $message;
		}
		?>
	</div>
<?php
}
?>