<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
if (empty($_POST['id_transportista_guia'])) {
	$errors[] = "Seleccione un transportista.";
} else if (empty($_POST['id_cliente_guia'])) {
	$errors[] = "Seleccione un cliente";
} else if (empty($_POST['placa_guia'])) {
	$errors[] = "Ingrese placa del vehículo";
} else if (empty($_POST['partida_guia'])) {
	$errors[] = "Ingrese el punto de partida para el traslado.";
} else if (empty($_POST['destino_guia'])) {
	$errors[] = "Ingrese el destino del traslado.";
} else if (empty($_POST['motivo_guia'])) {
	$errors[] = "Ingrese el motivo de traslado.";
} else if (empty($_POST['ruta_guia'])) {
	$errors[] = "Ingrese ruta por donde se va a trasladar el producto a su destino.";
} else if (!date($_POST['fecha_salida_guia'])) {
	$errors[] = "Ingrese fecha de salida.";
} else if (!date($_POST['fecha_llegada_guia'])) {
	$errors[] = "Ingrese fecha de llegada.";
} else if (!date($_POST['fecha_guia'])) {
	$errors[] = "Ingrese fecha para la guía de remisión.";
} else if (empty($_POST['serie_guia'])) {
	$errors[] = "Seleccione serie.";
} else if (empty($_POST['secuencial_guia'])) {
	$errors[] = "Seleccione serie para obtener el número de guía de remisión.";
} else if (
	!empty($_POST['id_transportista_guia']) && !empty($_POST['placa_guia']) && !empty($_POST['partida_guia']) && !empty($_POST['destino_guia']) && !empty($_POST['motivo_guia']) && !empty($_POST['fecha_salida_guia'])
	&& !empty($_POST['fecha_llegada_guia']) && !empty($_POST['fecha_guia']) && !empty($_POST['serie_guia']) && !empty($_POST['secuencial_guia'])
	&& !empty($_POST['ruta_guia']) && !empty($_POST['id_cliente_guia'])
) {

	$id_transportista_guia = mysqli_real_escape_string($con, (strip_tags($_POST["id_transportista_guia"], ENT_QUOTES)));
	$id_cliente_guia = mysqli_real_escape_string($con, (strip_tags($_POST["id_cliente_guia"], ENT_QUOTES)));
	$placa_guia = mysqli_real_escape_string($con, (strip_tags($_POST["placa_guia"], ENT_QUOTES)));
	$factura_guia = mysqli_real_escape_string($con, (strip_tags($_POST["factura_guia"], ENT_QUOTES)));
	$origen_guia = mysqli_real_escape_string($con, (strip_tags($_POST["partida_guia"], ENT_QUOTES)));
	$destino_guia = mysqli_real_escape_string($con, (strip_tags($_POST["destino_guia"], ENT_QUOTES)));
	$motivo_guia = mysqli_real_escape_string($con, (strip_tags($_POST["motivo_guia"], ENT_QUOTES)));
	$ruta_guia = mysqli_real_escape_string($con, (strip_tags($_POST["ruta_guia"], ENT_QUOTES)));

	$fecha_salida_guia = date('Y-m-d H:i:s', strtotime($_POST['fecha_salida_guia']));
	$fecha_llegada_guia = date('Y-m-d H:i:s', strtotime($_POST['fecha_llegada_guia']));
	$fecha_guia = date('Y-m-d H:i:s', strtotime($_POST['fecha_guia']));
	$serie_guia = mysqli_real_escape_string($con, (strip_tags($_POST["serie_guia"], ENT_QUOTES)));
	$secuencial_guia = mysqli_real_escape_string($con, (strip_tags($_POST["secuencial_guia"], ENT_QUOTES)));

	$aduanero_guia = mysqli_real_escape_string($con, (strip_tags($_POST["aduanero_guia"], ENT_QUOTES)));
	$codigo_destino_guia = mysqli_real_escape_string($con, (strip_tags($_POST["codigo_destino_guia"], ENT_QUOTES)));
	$fecha_registro = date("Y-m-d H:i:s");

	session_start();
	$id_usuario = $_SESSION['id_usuario'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

	if (substr($factura_guia, 0, 3) == "000" or substr($factura_guia, 4, 3) == "000" or substr($factura_guia, 8, 9) == "000000000") {
		$errors[] = 'Ingrese un número de factura correcto. ej: 001-001-000000001 ' . mysqli_error($con);
	} else {
		//para ver si la guia que queremos registrar ya esta registrada	
		$busca_empresa = "SELECT * FROM encabezado_gr WHERE ruc_empresa = '$ruc_empresa' and serie_gr = '$serie_guia' and secuencial_gr ='$secuencial_guia' and tipo_gr = 'ELECTRÓNICA'";
		$result = $con->query($busca_empresa);
		$count = mysqli_num_rows($result);
		if ($count > 0) {
			$errors[] = "El número de guía de remisión que intenta guardar ya se encuentra registrado en el sistema." . mysqli_error($con);
		} else {
			$sql_guia_temporal = mysqli_query($con, "SELECT * FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "'");
			$count = mysqli_num_rows($sql_guia_temporal);
			if ($count == 0) {
				$errors[] = "No hay detalle agregados a la guía de remisión." . mysqli_error($con);
			} else {
				//para guardar el encabezado de la guia
				$guarda_encabezado_guia = "INSERT INTO encabezado_gr VALUES (null, '$ruc_empresa','$fecha_guia','$fecha_salida_guia','$fecha_llegada_guia','$serie_guia','$secuencial_guia','$factura_guia','$origen_guia','$destino_guia','$aduanero_guia','$codigo_destino_guia','$id_transportista_guia','$id_cliente_guia','$placa_guia','$fecha_registro','ELECTRÓNICA','PENDIENTE',$id_usuario,'0','','','$motivo_guia','$ruta_guia','PENDIENTE')";
				$query_encabezado_guia = mysqli_query($con, $guarda_encabezado_guia);

				//para guardar detalle adicional de la guia
				$busca_adicional_tmp = "SELECT * FROM adicional_tmp WHERE id_usuario='" . $id_usuario . "' and serie_factura = '" . $serie_guia . "' and secuencial_factura = '" . $secuencial_guia . "' ";
				$query_gr_tmp = mysqli_query($con, $busca_adicional_tmp);

				$contar_registros = mysqli_num_rows($query_gr_tmp);

				if ($contar_registros == 0) {
					$busca_cliente = "SELECT * FROM clientes WHERE id = '" . $id_cliente_guia . "' ";
					$query_cliente = mysqli_query($con, $busca_cliente);
					$row_cliente = mysqli_fetch_array($query_cliente);
					$correo_cliente = $row_cliente['email'];
					$direccion_cliente = $row_cliente['direccion'];
					$query_guarda_detalle_adicional_guia = mysqli_query($con, "INSERT INTO detalle_adicional_gr VALUES (null, '" . $ruc_empresa . "','" . $serie_guia . "','" . $secuencial_guia . "','Correo','" . $correo_cliente . "')");
					$query_guarda_detalle_adicional_guia = mysqli_query($con, "INSERT INTO detalle_adicional_gr VALUES (null, '" . $ruc_empresa . "','" . $serie_guia . "','" . $secuencial_guia . "','Dirección','" . $direccion_cliente . "')");
				} else {

					while ($row_detalle_adicional = mysqli_fetch_array($query_gr_tmp)) {
						$concepto = $row_detalle_adicional['concepto'];
						$detalle = $row_detalle_adicional['detalle'];
						$query_guarda_detalle_adicional_guia = mysqli_query($con, "INSERT INTO detalle_adicional_gr VALUES (null, '" . $ruc_empresa . "','" . $serie_guia . "','" . $secuencial_guia . "','" . $concepto . "','" . $detalle . "')");
					}
				}

				//para guardar el detalle de la guia
				while ($row_detalle = mysqli_fetch_array($sql_guia_temporal)) {
					$cantidad_guia = str_replace(",", ".", $row_detalle["cantidad_tmp"]);
					$id_producto = $row_detalle['id_producto'];
					//para ver el nombre del producto
					$busca_nombre_producto = "SELECT * FROM productos_servicios WHERE ruc_empresa='$ruc_empresa' and id=$id_producto ";
					$result_nombre_producto = $con->query($busca_nombre_producto);
					$datos_nombre_producto = mysqli_fetch_array($result_nombre_producto);
					$codigo_producto = strtoupper($datos_nombre_producto['codigo_producto']);
					$nombre_producto = strtoupper($datos_nombre_producto['nombre_producto']);

					$guarda_detalle_guia = mysqli_query($con, "INSERT INTO cuerpo_gr VALUES (null, '$ruc_empresa','$serie_guia',$secuencial_guia,$id_producto,'$cantidad_guia','$codigo_producto','$nombre_producto')");
				}

				if ($query_encabezado_guia && $guarda_detalle_guia && $query_guarda_detalle_adicional_guia) {
					echo "<script>
													$.notify('Guía de remisión guardada con éxito','success');
													setTimeout(function (){location.href ='../modulos/guias_remision.php'}, 1000); 
													</script>";
				} else {
					$errors[] = "Lo siento algo ha salido mal intenta nuevamente." . mysqli_error($con);
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