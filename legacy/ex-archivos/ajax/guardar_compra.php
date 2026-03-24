<?php
/* Connect To Database*/
include("../clases/contabilizacion.php");
include("../conexiones/conectalogin.php");
include("../validadores/generador_codigo_unico.php");
$con = conenta_login();
if (empty($_POST['fecha_compra'])) {
	$errors[] = "Ingrese fecha.";
} else if (!date($_POST['fecha_compra'])) {
	$errors[] = "Ingrese fecha correcta";
} else if (empty($_POST['id_proveedor_compra'])) {
	$errors[] = "Seleccione un proveedor.";
} else if (empty($_POST['tipo_comprobante_compra'])) {
	$errors[] = "Seleccione un tipo de documento de compra.";
} else if (empty($_POST['numero_comprobante_compra'])) {
	$errors[] = "Ingrese un número de documento de compra.";
} else if (
	!empty($_POST['fecha_compra']) && !empty($_POST['id_proveedor_compra']) && !empty($_POST['tipo_comprobante_compra'])
	&& !empty($_POST['numero_comprobante_compra'])
) {
	ini_set('date.timezone', 'America/Guayaquil');
	session_start();
	$id_usuario = $_SESSION['id_usuario'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$fecha_compra = date('Y-m-d H:i:s', strtotime($_POST['fecha_compra']));
	$id_proveedor_compra = mysqli_real_escape_string($con, (strip_tags($_POST["id_proveedor_compra"], ENT_QUOTES)));
	$total_compra = mysqli_real_escape_string($con, (strip_tags($_POST["total_compra"], ENT_QUOTES)));
	$tipo_comprobante_compra = mysqli_real_escape_string($con, (strip_tags($_POST["tipo_comprobante_compra"], ENT_QUOTES)));

	$numero_comprobante_compra = mysqli_real_escape_string($con, (strip_tags($_POST["numero_comprobante_compra"], ENT_QUOTES)));
	$tipo_empresa = mysqli_real_escape_string($con, (strip_tags($_POST["tipo_empresa"], ENT_QUOTES)));
	$fecha_registro = date("Y-m-d H:i:s");

	$sustento_tributario = "1";
	$aut_sri_compra = "1234567890";
	$fecha_caducidad_compra = $fecha_compra;
	$numero_desde = "";
	$numero_hasta = "";
	$factura_aplica_nc = "";

	// para controlar el tipo de documento y que controle la serie y secuencial
	$control_comprobantes = control_comprobantes($tipo_comprobante_compra);

	if ($control_comprobantes == "SI" && ((strlen($numero_comprobante_compra) != 17) or (!is_numeric(substr($numero_comprobante_compra, 0, 3))) or (!is_numeric(substr($numero_comprobante_compra, 4, 3))) or (!is_numeric(substr($numero_comprobante_compra, 8, 9))) or (substr($numero_comprobante_compra, 3, 1) != "-") or (substr($numero_comprobante_compra, 7, 1) != "-"))) {
		$errors[] = " Ingrese un número de documento correcto. ejemplo: 001-001-123456789." . mysqli_error($con);
	} else {
		//para ver si la compra que queremos registrar ya esta registrada	
		$busca_compra = "SELECT * FROM encabezado_compra WHERE ruc_empresa = '" . $ruc_empresa . "' and id_proveedor='" . $id_proveedor_compra . "' and numero_documento='" . $numero_comprobante_compra . "' and id_comprobante='" . $tipo_comprobante_compra . "'";
		$result = $con->query($busca_compra);
		$count_compras = mysqli_num_rows($result);
		if ($count_compras > 0) {
			$errors[] = " El número de documento que intenta guardar ya se encuentra registrado." . mysqli_error($con);
		} else {
			$sql_compra_temporal = mysqli_query($con, "select * from compra_tmp where id_usuario = '" . $id_usuario . "'");
			$count = mysqli_num_rows($sql_compra_temporal);
			if ($count == 0) {
				$errors[] = " No hay detalle de productos agregados a la compra." . mysqli_error($con);
			} else {
				//para cuando la empresa esta obligada a llevar contabilidad, 1= persona natural

				if ($tipo_empresa > 1 && $control_comprobantes == "SI") {
					if (empty($_POST['sustento_tributario'])) {
						$errors[] = " Seleccione sustento tributario.";
					} else if (empty($_POST['aut_sri_compra'])) {
						$errors[] = " Ingrese un número de autorización.";
					} else if (!date($_POST['fecha_caducidad_compra'])) {
						$errors[] = " Ingrese fecha de caducidad del documento correcta";
					} else if (empty($_POST['numero_desde'])) {
						$errors[] = " Ingrese un número inicial de autorización.";
					} else if (empty($_POST['numero_hasta'])) {
						$errors[] = " Ingrese un número final de autorización.";
					} else if (
						!empty($_POST['sustento_tributario']) && !empty($_POST['aut_sri_compra']) && !empty($_POST['fecha_caducidad_compra'])
						&& !empty($_POST['numero_desde']) && !empty($_POST['numero_hasta'])
					) {
						$sustento_tributario = mysqli_real_escape_string($con, (strip_tags($_POST["sustento_tributario"], ENT_QUOTES)));
						$aut_sri_compra = mysqli_real_escape_string($con, (strip_tags($_POST["aut_sri_compra"], ENT_QUOTES)));
						$fecha_caducidad_compra = date('Y-m-d H:i:s', strtotime($_POST['fecha_caducidad_compra']));
						$numero_desde = mysqli_real_escape_string($con, (strip_tags($_POST["numero_desde"], ENT_QUOTES)));
						$numero_hasta = mysqli_real_escape_string($con, (strip_tags($_POST["numero_hasta"], ENT_QUOTES)));
						$factura_aplica_nc = mysqli_real_escape_string($con, (strip_tags($_POST["factura_aplica_nc"], ENT_QUOTES)));

						if (intval(strlen($aut_sri_compra) < 10)) {
							$errors[] = " Revisar el número de autorización, debe tener al menos 10 dígitos.";
						} else {
							$aut_sri_compra = mysqli_real_escape_string($con, (strip_tags($_POST["aut_sri_compra"], ENT_QUOTES)));
							//comprobar si la fecha de caducidad esta bien con respecto de la fecha actual y dentro del rango permitido
							if ($fecha_compra > $fecha_caducidad_compra) {
								$errors[] = " La fecha de compra no esta dentro del período de validez con respecto a la fecha de vigencia del documento.";
							} else {
								//extraer los 9 digitos y compararlos con el num desde y hasta
								$secuencial_compra = substr($numero_comprobante_compra, -9, 9);
								if ($secuencial_compra < $numero_desde or $secuencial_compra > $numero_hasta) {
									$errors[] = " El número de documento no esta dentro del rango de la autorización SRI.";
								} else {
									$control_nota_credito = control_nota_credito($tipo_comprobante_compra, $factura_aplica_nc);
									if ($control_nota_credito == "SI" && ((strlen($factura_aplica_nc) != 17) or (!is_numeric(substr($factura_aplica_nc, 0, 3))) or (!is_numeric(substr($factura_aplica_nc, 4, 3))) or (!is_numeric(substr($factura_aplica_nc, 8, 9))) or (substr($factura_aplica_nc, 3, 1) != "-") or (substr($factura_aplica_nc, 7, 1) != "-"))) {
										$errors[] = ' Ingrese un número de documento, al que aplica la nota de crédito/débito, correcto. ejemplo: 001-001-123456789' . mysqli_error($con);
									} else {

										$array_encabezado_compra = array(
											'fecha_compra' => $fecha_compra,
											'ruc_empresa' => $ruc_empresa,
											'numero_comprobante_compra' => $numero_comprobante_compra,
											'id_proveedor_compra' => $id_proveedor_compra,
											'tipo_comprobante_compra' => $tipo_comprobante_compra,
											'sustento_tributario' => $sustento_tributario,
											'aut_sri_compra' => $aut_sri_compra,
											'fecha_caducidad_compra' => $fecha_caducidad_compra,
											'numero_desde' => $numero_desde,
											'numero_hasta' => $numero_hasta,
											'fecha_registro' => $fecha_registro,
											'id_usuario' => $id_usuario,
											'total_compra' => $total_compra,
											'factura_aplica_nc' => $factura_aplica_nc
										);
										echo guardar_info_bd($array_encabezado_compra, $sql_compra_temporal, $con);
									}
								}
							}
						}
					} else {
						$errors[] = "Revisar datos cuando la empresa lleva contabilidad.";
					}
					//hasta aqui si lleva contabilidad
				} else {
					$array_encabezado_compra = array('fecha_compra' => $fecha_compra, 'ruc_empresa' => $ruc_empresa, 'numero_comprobante_compra' => $numero_comprobante_compra, 'id_proveedor_compra' => $id_proveedor_compra, 'tipo_comprobante_compra' => $tipo_comprobante_compra, 'sustento_tributario' => $sustento_tributario, 'aut_sri_compra' => $aut_sri_compra, 'fecha_caducidad_compra' => $fecha_caducidad_compra, 'numero_desde' => $numero_desde, 'numero_hasta' => $numero_hasta, 'fecha_registro' => $fecha_registro, 'id_usuario' => $id_usuario, 'total_compra' => $total_compra, 'factura_aplica_nc' => $factura_aplica_nc);
					echo guardar_info_bd($array_encabezado_compra, $sql_compra_temporal, $con);
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


function guardar_info_bd($array_encabezado_compra, $sql_compra_temporal, $con)
{
	//para generar un codigo del documento
	$contabilizacion = new contabilizacion();
	$codigo_unico = codigo_unico(20);
	$guarda_encabezado_compra = "INSERT INTO encabezado_compra VALUES (null, '" . $array_encabezado_compra['fecha_compra'] . "', '" . $array_encabezado_compra['ruc_empresa'] . "', '" . $array_encabezado_compra['numero_comprobante_compra'] . "', '" . $codigo_unico . "', '" . $array_encabezado_compra['id_proveedor_compra'] . "', '" . $array_encabezado_compra['tipo_comprobante_compra'] . "', '" . $array_encabezado_compra['sustento_tributario'] . "', '" . $array_encabezado_compra['aut_sri_compra'] . "','" . $array_encabezado_compra['fecha_caducidad_compra'] . "', '" . $array_encabezado_compra['numero_desde'] . "','" . $array_encabezado_compra['numero_hasta'] . "', '" . $array_encabezado_compra['fecha_registro'] . "', '" . $array_encabezado_compra['id_usuario'] . "', '" . $array_encabezado_compra['total_compra'] . "', '" . $array_encabezado_compra['factura_aplica_nc'] . "', '0','FÍSICA','04',0,0,'01')";
	$query_encabezado_compra = mysqli_query($con, $guarda_encabezado_compra);
	$id_compras = mysqli_insert_id($con);

	while ($row_detalle = mysqli_fetch_array($sql_compra_temporal)) {
		$codigo = $row_detalle['codigo'];
		$detalle = $row_detalle['detalle'];
		$cantidad = $row_detalle['cantidad'];
		$precio = $row_detalle['unitario'];
		$impuesto = $row_detalle['impuesto'];
		$codigo_impuesto = $row_detalle['codigo_impuesto'];

		$descuento = $row_detalle['descuento'];
		$guarda_detalle_compra = mysqli_query($con, "INSERT INTO cuerpo_compra VALUES (null, '" . $array_encabezado_compra['ruc_empresa'] . "','" . $codigo_unico . "','" . $codigo . "','" . $detalle . "','" . $cantidad . "','" . $precio . "','" . $descuento . "','" . $impuesto . "','" . $codigo_impuesto . "','" . ($cantidad * $precio - $descuento) . "',0)");
	}

	//guardar forma de pago y dias de credito
	$busca_forma_pago_compra = "SELECT * FROM proveedores WHERE id_proveedor = '" . $array_encabezado_compra['id_proveedor_compra'] . "' ";
	$result_forma_pago_compra = $con->query($busca_forma_pago_compra);
	$row_forma_pago_compra = mysqli_fetch_array($result_forma_pago_compra);

	$guarda_formas_pago_compra = "INSERT INTO formas_pago_compras VALUES (null,'" . $array_encabezado_compra['ruc_empresa'] . "', '" . $codigo_unico . "', '20','" . $array_encabezado_compra['total_compra'] . "', '" . $row_forma_pago_compra['plazo'] . "','" . $row_forma_pago_compra['unidad_tiempo'] . "' )";
	$query_formas_pago_compra = mysqli_query($con, $guarda_formas_pago_compra);

	//para actualizar el ide de la compra en las retenciones

	//para guarar el id de la compra en la retencion
	$actualizar_id_compra_en_retencion = mysqli_query($con, "UPDATE encabezado_retencion as enc 
	INNER JOIN encabezado_compra as com ON com.numero_documento=enc.numero_comprobante 
		and com.id_proveedor=enc.id_proveedor and com.ruc_empresa=enc.ruc_empresa 
		and com.fecha_compra=enc.fecha_documento SET enc.id_compras=com.id_encabezado_compra 
		WHERE com.ruc_empresa='" . $array_encabezado_compra['ruc_empresa'] . "' 
		and com.id_proveedor= '" . $array_encabezado_compra['id_proveedor_compra'] . "'
		and com.numero_documento= '" . $array_encabezado_compra['numero_comprobante_compra'] . "' and enc.id_compras = '0'");


	if ($query_encabezado_compra && $guarda_detalle_compra && $query_formas_pago_compra) {
		$contabilizacion->documentosAdquisiciones($con, $array_encabezado_compra['ruc_empresa'], $array_encabezado_compra['fecha_compra'], $array_encabezado_compra['fecha_compra']);
		$guardar_asientos_contables_generados = $contabilizacion->guardar_asientos_contables_generados($con, $array_encabezado_compra['ruc_empresa'], $array_encabezado_compra['id_usuario'], 'compras_servicios');

		echo "<script>
		$.notify('Compra guardada con éxito','success');
		//if (confirm('Desea continuar con el mismo proveedor?')) {
		//setTimeout(function (){location.href ='../modulos/nuevo_registro_compras.php'}, 1000);
		//}else{
		setTimeout(function () {location.reload()}, 30 * 20);
		//}
		</script>";
	} else {
		echo "<script>
		$.notify('Lo siento algo ha salido mal intenta nuevamente','error');
		</script>";
	}
}

function control_comprobantes($tipo_comprobante_compra)
{
	//tipos de comprobantes que tienes que ser controlados
	switch (intval($tipo_comprobante_compra)) {
		case "1":
		case "2":
		case "3":
		case "4":
		case "5":
		case "7":
		case "18":
		case "23":
		case "24":
		case "47":
		case "48":
		case "294":
		case "344":
			return $documento_controlado = "SI";
			break;
		default:
			return $documento_controlado = "NO";
			break;
	}
}


function control_nota_credito($tipo_comprobante_compra, $factura_aplica_nc)
{
	//verificar si el documento es nota de credito, nd... controlar que este lleno el campo 
	switch (intval($tipo_comprobante_compra)) {
		case "4":
		case "5":
		case "23":
		case "24":
		case "47":
		case "48":
			return $aplica_nc = "SI";
			break;
		default:
			return $aplica_nc = "NO";
			break;
	}
}

?>