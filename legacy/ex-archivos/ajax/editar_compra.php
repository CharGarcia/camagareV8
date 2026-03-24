<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
if (empty($_POST['fecha_compra_mod'])) {
	$errors[] = "Ingrese fecha.";
} else if (!date($_POST['fecha_compra_mod'])) {
	$errors[] = "Ingrese fecha correcta";
} else if (empty($_POST['tipo_comprobante_mod'])) {
	$errors[] = "Seleccione el tipo de documento.";
} else if (!is_numeric($_POST['propina_mod'])) {
	$errors[] = "Ingrese valores en propina.";
} else if (!is_numeric($_POST['otros_val_mod'])) {
	$errors[] = "Ingrese valores en otros valores.";
} else if (empty($_POST['tipo_comprobante_compra_mod'])) {
	$errors[] = "Seleccione un tipo de documento de compra.";
} else if (empty($_POST['numero_documento_mod'])) {
	$errors[] = "Ingrese un número de documento de compra.";
} else if (
	!empty($_POST['fecha_compra_mod']) && !empty($_POST['tipo_comprobante_compra_mod'])
	&& !empty($_POST['numero_documento_mod'])
) {
	ini_set('date.timezone', 'America/Guayaquil');
	session_start();
	$id_usuario = $_SESSION['id_usuario'];
	$ruc_empresa_inicial = $_SESSION['ruc_empresa'];
	$fecha_compra = date('Y-m-d H:i:s', strtotime($_POST['fecha_compra_mod']));
	$tipo_comprobante_compra = mysqli_real_escape_string($con, (strip_tags($_POST["tipo_comprobante_compra_mod"], ENT_QUOTES)));
	$numero_comprobante_compra = mysqli_real_escape_string($con, (strip_tags($_POST["numero_documento_mod"], ENT_QUOTES)));
	$tipo_comprobante_mod = mysqli_real_escape_string($con, (strip_tags($_POST["tipo_comprobante_mod"], ENT_QUOTES)));
	$otros_val = mysqli_real_escape_string($con, (strip_tags($_POST["otros_val_mod"], ENT_QUOTES)));
	$propina = mysqli_real_escape_string($con, (strip_tags($_POST["propina_mod"], ENT_QUOTES)));
	$codigo_documento = mysqli_real_escape_string($con, (strip_tags($_POST["codigo_unico"], ENT_QUOTES)));
	$codigo_contable = mysqli_real_escape_string($con, (strip_tags($_POST["codigo_contable"], ENT_QUOTES)));
	$fecha_registro = date("Y-m-d H:i:s");

	$sustento_tributario = mysqli_real_escape_string($con, (strip_tags($_POST["sustento_mod"], ENT_QUOTES)));
	$aut_sri_compra = mysqli_real_escape_string($con, (strip_tags($_POST["aut_sri_mod"], ENT_QUOTES)));
	$fecha_caducidad_compra = date('Y-m-d H:i:s', strtotime($_POST['caducidad_mod']));
	$numero_desde = mysqli_real_escape_string($con, (strip_tags($_POST["desde_mod"], ENT_QUOTES)));
	$numero_hasta = mysqli_real_escape_string($con, (strip_tags($_POST["hasta_mod"], ENT_QUOTES)));
	$factura_aplica_nc = mysqli_real_escape_string($con, (strip_tags($_POST["modificado_mod"], ENT_QUOTES)));
	$deducible_en = mysqli_real_escape_string($con, (strip_tags($_POST["deducible_mod"], ENT_QUOTES)));
	$ruc_empresa = mysqli_real_escape_string($con, (strip_tags($_POST["sucursal_mod"], ENT_QUOTES)));

	if ($fecha_compra > $fecha_caducidad_compra) {
		$fecha_caducidad_compra = $fecha_compra;
	}

	$detalle_pagos = mysqli_query($con, "SELECT sum(valor_ing_egr) as valor_ing_egr, numero_ing_egr as numero_ing_egr, codigo_documento as codigo_documento_egreso 
	FROM detalle_ingresos_egresos WHERE ruc_empresa= '" . $ruc_empresa_inicial . "' and codigo_documento_cv = '" . $codigo_documento . "' and tipo_documento='EGRESO' and estado='OK' group by codigo_documento_cv");
	$row_pagos = mysqli_fetch_array($detalle_pagos);
	$total_abonos = empty($row_pagos['valor_ing_egr']) ? 0 : number_format($row_pagos['valor_ing_egr'], 2, '.', '');

	$sql_empresa = mysqli_query($con, "SELECT * from empresas where mid(ruc,1,10) = '" . substr($ruc_empresa, 0, 10) . "'");
	$row_tipo = mysqli_fetch_array($sql_empresa);
	$tipo_empresa = $row_tipo['tipo'];
	// para controlar el tipo de documento y que controle la serie y secuencial
	if ($total_abonos > 0) {
		$errors[] = " Se debe eliminar los pagos o egresos realizados a este documento.";
	} else {
		$control_comprobantes = control_comprobantes($tipo_comprobante_compra);
		if ($control_comprobantes == "SI" && ((strlen($numero_comprobante_compra) != 17) or (!is_numeric(substr($numero_comprobante_compra, 0, 3))) or (!is_numeric(substr($numero_comprobante_compra, 4, 3))) or (!is_numeric(substr($numero_comprobante_compra, 8, 9))) or (substr($numero_comprobante_compra, 3, 1) != "-") or (substr($numero_comprobante_compra, 7, 1) != "-"))) {
			$errors[] = " Ingrese un número de documento correcto. ejemplo: 001-001-123456789." . mysqli_error($con);
		} else {
			$sql_compra_temporal = mysqli_query($con, "SELECT * from cuerpo_compra where codigo_documento = '" . $codigo_documento . "'");
			$count = mysqli_num_rows($sql_compra_temporal);
			if ($count == 0) {
				$errors[] = " No hay detalle de productos agregados a la compra." . mysqli_error($con);
			} else {
				//para cuando la empresa esta obligada a llevar contabilidad, 1= persona natural

				if ($tipo_empresa > 1 && $control_comprobantes == "SI") {
					if (empty($_POST['sustento_mod'])) {
						$errors[] = " Seleccione sustento tributario.";
					} else if (empty($_POST['aut_sri_mod'])) {
						$errors[] = " Ingrese un número de autorización.";
					} else if (!date($_POST['caducidad_mod'])) {
						$errors[] = " Ingrese fecha de caducidad del documento correcta";
					} else if (empty($_POST['desde_mod'])) {
						$errors[] = " Ingrese un número inicial de autorización.";
					} else if (empty($_POST['hasta_mod'])) {
						$errors[] = " Ingrese un número final de autorización.";
					} else if (
						!empty($_POST['sustento_mod']) && !empty($_POST['aut_sri_mod']) && !empty($_POST['caducidad_mod'])
						&& !empty($_POST['desde_mod']) && !empty($_POST['hasta_mod'])
					) {
						if (intval(strlen($aut_sri_compra) < 10)) {
							$errors[] = " Revisar el número de autorización, debe tener al menos 10 dígitos.";
						} else {
							$aut_sri_compra = mysqli_real_escape_string($con, (strip_tags($_POST["aut_sri_mod"], ENT_QUOTES)));
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
										'ruc_empresa_inicial' => $ruc_empresa_inicial,
										'numero_comprobante_compra' => $numero_comprobante_compra,
										'tipo_comprobante_compra' => $tipo_comprobante_compra,
										'sustento_tributario' => $sustento_tributario,
										'aut_sri_compra' => $aut_sri_compra,
										'fecha_caducidad_compra' => $fecha_caducidad_compra,
										'numero_desde' => $numero_desde,
										'numero_hasta' => $numero_hasta,
										'fecha_registro' => $fecha_registro,
										'id_usuario' => $id_usuario,
										'factura_aplica_nc' => $factura_aplica_nc,
										'tipo_comprobante' => $tipo_comprobante_mod,
										'propina' => $propina,
										'otros_val' => $otros_val,
										'deducible_en' => $deducible_en,
										'codigo_contable' => $codigo_contable
									);
									echo guardar_info_bd($array_encabezado_compra, $con, $codigo_documento);
								}
							}
							//}
						}
					} else {
						$errors[] = "Revisar información requerida de cuando la empresa lleva contabilidad.";
					}
					//hasta aqui si lleva contabilidad
				} else {
					$array_encabezado_compra = array(
						'fecha_compra' => $fecha_compra,
						'ruc_empresa' => $ruc_empresa,
						'ruc_empresa_inicial' => $ruc_empresa_inicial,
						'numero_comprobante_compra' => $numero_comprobante_compra,
						'tipo_comprobante_compra' => $tipo_comprobante_compra,
						'sustento_tributario' => $sustento_tributario,
						'aut_sri_compra' => $aut_sri_compra == "" ? "1234567890" : $aut_sri_compra,
						'fecha_caducidad_compra' => $fecha_caducidad_compra == "" ? $fecha_compra : $fecha_caducidad_compra,
						'numero_desde' => $numero_desde == "" ? substr($numero_comprobante_compra, -9, 9) : $numero_desde,
						'numero_hasta' => $numero_hasta == "" ? substr($numero_comprobante_compra, -9, 9) : $numero_hasta,
						'fecha_registro' => $fecha_registro,
						'id_usuario' => $id_usuario,
						'factura_aplica_nc' => $factura_aplica_nc,
						'tipo_comprobante' => $tipo_comprobante_mod,
						'propina' => $propina,
						'otros_val' => $otros_val,
						'deducible_en' => $deducible_en,
						'codigo_contable' => $codigo_contable
					);
					echo guardar_info_bd($array_encabezado_compra, $con, $codigo_documento);
				}
			}
		}
	}
} else {
	$errors[] = "Error desconocido.";
}

if (isset($errors)) {
	foreach ($errors as $error) {
?>
		<td><span class="label label-danger"><?php echo $error; ?></span></td>
	<?php
	}
}
if (isset($messages)) {
	foreach ($messages as $message) {
	?>
		<td><span class="label label-info"><?php echo $message; ?></span></td>
<?php
	}
}


function guardar_info_bd($array_encabezado_compra, $con, $codigo_documento)
{

	$sql_proveedor = mysqli_query($con, "SELECT * FROM encabezado_compra WHERE codigo_documento='" . $codigo_documento . "' ");
	$row_proveedor = mysqli_fetch_array($sql_proveedor);
	$id_proveedor = $row_proveedor['id_proveedor'];

	//para generar un codigo del documento
	$subtotal_general = 0;
	$total_descuento = 0;
	$sql_factura = mysqli_query($con, "SELECT sum(subtotal) as subtotal_general, sum(descuento) as total_descuento FROM cuerpo_compra WHERE codigo_documento='" . $codigo_documento . "' ");
	$row_subtotal = mysqli_fetch_array($sql_factura);
	$subtotal_general = $row_subtotal['subtotal_general'];
	$total_descuento = $row_subtotal['total_descuento'];

	$total_iva = 0;
	$subtotal_porcentaje_iva = 0;
	$sql = mysqli_query($con, "SELECT ti.tarifa as tarifa, (sum(cue_com.subtotal) * ti.tarifa /100)  as porcentaje FROM cuerpo_compra as cue_com INNER JOIN tarifa_iva as ti ON ti.codigo=cue_com.det_impuesto WHERE cue_com.codigo_documento= '" . $codigo_documento . "' and ti.tarifa > 0 group by cue_com.det_impuesto ");
	while ($row = mysqli_fetch_array($sql)) {
		$porcentaje_iva = $row['porcentaje'];
		$subtotal_porcentaje_iva = $porcentaje_iva;
		$total_iva += $subtotal_porcentaje_iva;
	}
	$total_documento = number_format($subtotal_general + $total_iva + $array_encabezado_compra['propina'] + $array_encabezado_compra['otros_val'], 2, '.', '');

	if ($array_encabezado_compra['codigo_contable'] > 0) {
		include_once("../clases/anular_registros.php");
		$anular_asiento_contable = new anular_registros();
		$resultado_anular_documento = $anular_asiento_contable->anular_asiento_contable($con, $array_encabezado_compra['codigo_contable']);
	}
	$actualiza_encabezado_compra = mysqli_query($con, "UPDATE encabezado_compra set ruc_empresa = '" . $array_encabezado_compra['ruc_empresa'] . "', fecha_compra ='" . $array_encabezado_compra['fecha_compra'] . "', numero_documento= '" . $array_encabezado_compra['numero_comprobante_compra'] . "', id_comprobante = '" . $array_encabezado_compra['tipo_comprobante_compra'] . "', id_sustento= '" . $array_encabezado_compra['sustento_tributario'] . "', aut_sri = '" . $array_encabezado_compra['aut_sri_compra'] . "', fecha_caducidad='" . $array_encabezado_compra['fecha_caducidad_compra'] . "', desde='" . $array_encabezado_compra['numero_desde'] . "', hasta='" . $array_encabezado_compra['numero_hasta'] . "', fecha_registro='" . $array_encabezado_compra['fecha_registro'] . "', id_usuario= '" . $array_encabezado_compra['id_usuario'] . "', total_compra='" . $total_documento . "', factura_aplica_nc_nd='" . $array_encabezado_compra['factura_aplica_nc'] . "', tipo_comprobante='" . $array_encabezado_compra['tipo_comprobante'] . "', propina='" . $array_encabezado_compra['propina'] . "', otros_val='" . $array_encabezado_compra['otros_val'] . "', deducible_en='" . $array_encabezado_compra['deducible_en'] . "', id_registro_contable='0'  WHERE codigo_documento='" . $codigo_documento . "'");
	$actualiza_proveedor = mysqli_query($con, "UPDATE proveedores set ruc_empresa = '" . $array_encabezado_compra['ruc_empresa'] . "' WHERE id_proveedor = '" . $id_proveedor . "'");
	$actualiza_detalle_compra = mysqli_query($con, "UPDATE cuerpo_compra set ruc_empresa = '" . $array_encabezado_compra['ruc_empresa'] . "' WHERE codigo_documento = '" . $codigo_documento . "'");
	$actualiza_detalle_adicional_compra = mysqli_query($con, "UPDATE detalle_adicional_compra set ruc_empresa = '" . $array_encabezado_compra['ruc_empresa'] . "' WHERE codigo_documento = '" . $codigo_documento . "'");
	$delete_forma_pago = mysqli_query($con, "DELETE FROM formas_pago_compras WHERE codigo_documento= '" . $codigo_documento . "' ");
	$guarda_forma_pago = mysqli_query($con, "INSERT INTO formas_pago_compras VALUES (null, '" . $array_encabezado_compra['ruc_empresa'] . "','" . $codigo_documento . "','20','" . $total_documento . "','1','Dias')");
	if ($actualiza_encabezado_compra) {
		echo "<script>
		$.notify('Documento actualizado con éxito','success');
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