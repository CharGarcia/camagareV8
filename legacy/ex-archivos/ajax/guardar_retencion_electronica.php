<?php
/* Connect To Database*/
include("../clases/contabilizacion.php");
$contabilizacion = new contabilizacion();
include("../conexiones/conectalogin.php");
$con = conenta_login();
if (empty($_POST['fecha_retencion_e'])) {
	$errors[] = "Ingrese fecha para la retención electrónica.";
} else if (!date($_POST['fecha_retencion_e'])) {
	$errors[] = "Ingrese fecha de emisión de retención correcta";
} else if (empty($_POST['fecha_comprobante_e'])) {
	$errors[] = "Ingrese fecha de comprobante.";
} else if (!date($_POST['fecha_comprobante_e'])) {
	$errors[] = "Ingrese fecha de comprobante correcta";
} else if (empty($_POST['serie_retencion_e'])) {
	$errors[] = "Seleccione serie para la retención electrónica.";
} else if (empty($_POST['secuencial_retencion_e'])) {
	$errors[] = "Ingrese un número de retención electrónica.";
} else if (!is_numeric($_POST['secuencial_retencion_e'])) {
	$errors[] = "Ingrese un número de retención electrónica.";
} else if (empty($_POST['tipo_comprobante'])) {
	$errors[] = "Seleccione un comprobante.";
} else if (empty($_POST['numero_comprobante'])) {
	$errors[] = "Seleccione o ingrese un comprobante de venta recibido.";
} else if (empty($_POST['id_proveedor_e'])) {
	$errors[] = "Seleccione un proveedor para la retención electrónica.";
} else if (
	!empty($_POST['fecha_retencion_e']) && !empty($_POST['serie_retencion_e'])  && !empty($_POST['secuencial_retencion_e'])
	&& !empty($_POST['id_proveedor_e']) && !empty($_POST['tipo_comprobante']) && !empty($_POST['numero_comprobante']) && !empty($_POST['fecha_comprobante_e'])
) {

	$fecha_retencion = date('Y-m-d H:i:s', strtotime($_POST['fecha_retencion_e']));
	$fecha_documento = date('Y-m-d H:i:s', strtotime($_POST['fecha_comprobante_e']));
	$serie_retencion = mysqli_real_escape_string($con, (strip_tags($_POST["serie_retencion_e"], ENT_QUOTES)));
	$secuencial_retencion = mysqli_real_escape_string($con, (strip_tags($_POST["secuencial_retencion_e"], ENT_QUOTES)));
	$id_comprobante = mysqli_real_escape_string($con, (strip_tags($_POST["tipo_comprobante"], ENT_QUOTES)));
	$numero_comprobante = mysqli_real_escape_string($con, (strip_tags($_POST["numero_comprobante"], ENT_QUOTES)));
	$id_registro_compras = $_POST["id_compra"]; //para saber con que factura esta atada esta retencion
	$id_proveedor = mysqli_real_escape_string($con, (strip_tags($_POST["id_proveedor_e"], ENT_QUOTES)));
	$total_retencion = mysqli_real_escape_string($con, (strip_tags($_POST["total_retencion_e"], ENT_QUOTES)));
	$mail_proveedor = mysqli_real_escape_string($con, (strip_tags(strtolower($_POST["mail_proveedor"]), ENT_QUOTES)));
	session_start();
	$id_usuario = $_SESSION['id_usuario'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$estado_sri = "PENDIENTE";
	$mes_periodo = date("m", strtotime($fecha_retencion));
	$anio_periodo = date("Y", strtotime($fecha_retencion));
	$mes_fiscal = date("m", strtotime($fecha_documento));
	$anio_fiscal = date("Y", strtotime($fecha_documento));
	$ejercicio_fiscal = $mes_fiscal . "/" . $anio_fiscal;

	if (substr($numero_comprobante, 0, 3) == "000" or substr($numero_comprobante, 4, 3) == "000" or substr($numero_comprobante, 8, 9) == "000000000") {
		$errors[] = 'Ingrese un número de comprobante correcto. ej: 001-001-000000001 ' . mysqli_error($con);
	} else {
		//para ver si la retencion que queremos registrar ya esta registrada	
		$busca_retencion = "SELECT * FROM encabezado_retencion WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_retencion = '" . $serie_retencion . "' and secuencial_retencion = '" . $secuencial_retencion . "' and id_compras = '" . $id_registro_compras . "' ";
		$result = $con->query($busca_retencion);
		$count = mysqli_num_rows($result);
		if ($count > 0) {
			$errors[] = "El número de retención ya se encuentra registrado, vuelva a escoger la serie para que el sistema tome otro numero de retención." . mysqli_error($con);
		} else {
			//para ver si la factura que le vamos a retener de ese proveedor ya tiene retencion
			$busca_factura = "SELECT * FROM encabezado_retencion WHERE ruc_empresa = '" . $ruc_empresa . "' and id_proveedor = '" . $id_proveedor . "' and tipo_comprobante = '" . $id_comprobante . "' and numero_comprobante = '" . $numero_comprobante . "' AND estado_sri !='ANULADA' "; //and id_compras = '" . $id_registro_compras . "'
			$result_retencion = $con->query($busca_factura);
			$documento_encontrado = mysqli_fetch_array($result_retencion);
			$serie_asignada = isset($documento_encontrado['serie_retencion']) ? $documento_encontrado['serie_retencion'] : "";
			$secuencial_asignada = isset($documento_encontrado['secuencial_retencion']) ? $documento_encontrado['secuencial_retencion'] : "";
			$numero_retencion = $serie_asignada . "-" . str_pad($secuencial_asignada, 9, "000000000", STR_PAD_LEFT);

			$count_registros = mysqli_num_rows($result_retencion);
			if ($count_registros > 0) {
				$errors[] = "El documento al que desea retener ya esta registrado con el número de retención " . $numero_retencion . mysqli_error($con);
			} else {
				$sql_retencion_temporal = mysqli_query($con, "select * from retencion_tmp where id_usuario = '" . $id_usuario . "	'");
				$count = mysqli_num_rows($sql_retencion_temporal);
				if ($count == 0) {
					$errors[] = "No hay conceptos de retenciones agregados." . mysqli_error($con);
				} else {
					//PARA ACTUALIZAR EL MAIL EN EL PROVEEDOR
					$query_update_proveedor = mysqli_query($con, "UPDATE proveedores SET mail_proveedor='" . $mail_proveedor . "' WHERE id_proveedor = '" . $id_proveedor . "'");
					//para guardar el encabezado de la retencion
					$guarda_encabezado_retencion = "INSERT INTO encabezado_retencion VALUES (null, '" . $ruc_empresa . "','" . $id_proveedor . "','" . $serie_retencion . "','" . $secuencial_retencion . "','" . $total_retencion . "','0','" . $estado_sri . "','" . $fecha_retencion . "','" . $fecha_documento . "','" . $id_usuario . "','" . $id_comprobante . "','" . $numero_comprobante . "','" . $id_registro_compras . "','0','0','PENDIENTE')";
					$query_encabezado_retencion = mysqli_query($con, $guarda_encabezado_retencion);

					//para guardar detalle adicional de la retencion
					$busca_adicional_tmp = "SELECT * FROM adicional_tmp WHERE id_usuario = '" . $id_usuario . "' and serie_factura = '" . $serie_retencion . "' and secuencial_factura = '" . $secuencial_retencion . "'";
					$query = mysqli_query($con, $busca_adicional_tmp);
					//para ver si ya estan agregados adicionales a la retencion que se va hacer o sino se guarda el mail y la direccion como adicional
					$adicionales_encontradas = mysqli_num_rows($query);
					if ($adicionales_encontradas == 0) {
						$query_guarda_detalle_adicional_retencion = mysqli_query($con, "INSERT INTO detalle_adicional_retencion VALUES (null, '" . $ruc_empresa . "','" . $serie_retencion . "','" . $secuencial_retencion . "','Total retención','" . $total_retencion . "')");
						$query_guarda_detalle_adicional_retencion = mysqli_query($con, "INSERT INTO detalle_adicional_retencion VALUES (null, '" . $ruc_empresa . "','" . $serie_retencion . "','" . $secuencial_retencion . "','Email','" . $mail_proveedor . "')");
					} else {
						while ($row_detalle_adicional = mysqli_fetch_array($query)) {
							$concepto = $row_detalle_adicional['concepto'];
							$detalle = $row_detalle_adicional['detalle'];
							$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_retencion VALUES (null, '" . $ruc_empresa . "','" . $serie_retencion . "','" . $secuencial_retencion . "','" . $concepto . "','" . $detalle . "','" . $codigo_producto . "','" . $nombre_producto . "')");
						}
					}

					//para guardar el detalle de la retencion
					while ($row_detalle = mysqli_fetch_array($sql_retencion_temporal)) {
						$id_ret = $row_detalle["id_ret"];
						$cod_ret = $row_detalle["cod_ret"];
						$concepto_ret = $row_detalle["concepto_ret"];
						$impuesto_ret = $row_detalle["impuesto_ret"];
						$porcentaje_ret = $row_detalle["porcentaje_ret"];
						$base_ret = str_replace(",", ".", $row_detalle['base_ret']);
						$val_ret = str_replace(",", ".", $row_detalle['val_ret']);

						$guarda_detalle_retencion = mysqli_query($con, "INSERT INTO cuerpo_retencion VALUES (null,'" . $serie_retencion . "','" . $secuencial_retencion . "','" . $ruc_empresa . "','" . $id_ret . "','" . $ejercicio_fiscal . "','" . $base_ret . "','" . $cod_ret . "','" . $impuesto_ret . "','" . $porcentaje_ret . "','" . $val_ret . "','" . $concepto_ret . "')");
					}


					//para guarar el id de la compra en la retencion
					$actualizar_id_compra_en_retencion = mysqli_query($con, "UPDATE encabezado_retencion as enc 
					INNER JOIN encabezado_compra as com ON com.numero_documento=enc.numero_comprobante 
					and com.id_proveedor=enc.id_proveedor and com.ruc_empresa=enc.ruc_empresa 
					SET enc.id_compras=com.id_encabezado_compra 
					WHERE com.ruc_empresa='" . $ruc_empresa . "' 
					and com.id_proveedor= '" . $id_proveedor . "'
					and com.numero_documento= '" . $numero_comprobante . "' and enc.id_compras = '0'");

					//and com.fecha_compra=enc.fecha_documento



					if ($query_encabezado_retencion && $guarda_detalle_retencion && $query_guarda_detalle_adicional_retencion) {
						//para guardar asientos contables
						$contabilizacion->documentosRetencionesCompras($con, $ruc_empresa, $fecha_retencion, $fecha_retencion);
						$guardar_asientos_contables_generados = $contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'retenciones_compras');

						$messages[] = "Retención guardada con éxito." . mysqli_error($con);
						echo "<script>
									setTimeout(function () {location.reload()}, 1000); 
									</script>";
					} else {
						$errors[] = "Lo siento algo ha salido mal intenta nuevamente." . mysqli_error($con);
					}
				}
			}
		}
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