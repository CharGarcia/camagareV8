<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
include("../conexiones/conectalogin.php");
include("../clases/anular_registros.php");

$con = conenta_login();
session_start();
ini_set('date.timezone', 'America/Guayaquil');
$fecha_registro = date("Y-m-d H:i:s");
$anular_asiento_contable = new anular_registros();
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//para anular una factura
if ($action == '01') {
	if (!isset($_POST['id_documento_modificar'])) {
		$errors[] = "Seleccione un documento para anular.";
	} else if (empty($_POST['fecha_autorizacion'])) {
		$errors[] = "Ingrese fecha de autorización del documento";
	} else if (empty($_POST['correo_receptor'])) {
		$errors[] = "Ingrese correo receptor";
	} else if (empty($_POST['clave_sri']) && $_POST['opcion_anular'] == '2') {
		$errors[] = "Ingrese clave del SRI";
	} else if (!empty($_POST['id_documento_modificar'])) {
		$id_usuario = $_SESSION['id_usuario'];
		$id_factura = $_POST['id_documento_modificar'];
		$clave_acceso = $_POST['clave_acceso'];
		$clave_sri = $_POST['clave_sri'];
		$correo = $_POST['correo_receptor'];
		$opcion_anular = $_POST['opcion_anular'];
		$fecha_autorizacion = date("d/m/Y", strtotime($_POST['fecha_autorizacion']));
		$ruc_receptor = $_POST['ruc_receptor'];
		$tipo_comprobante = "1"; // 1 fact, 2 liq, 3 nc, 4 nd, 5 gr, 6 ret

		$datos_encabezado = mysqli_query($con, "SELECT * FROM encabezado_factura WHERE id_encabezado_factura = '" . $id_factura . "' ");
		$row_encabezado = mysqli_fetch_array($datos_encabezado);
		$ruc_empresa = $row_encabezado['ruc_empresa'];
		$serie_factura = $row_encabezado['serie_factura'];
		$secuencial = $row_encabezado['secuencial_factura'];
		$numero_documento = str_ireplace("-", "", $serie_factura) . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT);
		$id_registro_contable = $row_encabezado['id_registro_contable'];
		$id_cliente = $row_encabezado['id_cliente'];
		$ruc_contribuyente = substr($row_encabezado['ruc_empresa'], 0, 10) . "001";

		if ($opcion_anular == '1') {
			$resultado_anulacion = array(
				'data' => array(
					array(
						'message' => 'Al seleccionar la opción, documento anulado previamente en el SRI, el documento se encuentra en estado ANULADO en el sistema, pero es responsabilidad del usuario haberlo anulado en el SRI'
					)
				),
				'listError' => array(
					'errorInfo' => '',
					'errors' => array()
				),
				'status' => 1
			);
		} else {
			$resultado_anulacion = anular_documento_sri($ruc_contribuyente, $clave_sri, $clave_acceso, $correo, $ruc_receptor, $tipo_comprobante, $fecha_autorizacion);
		}

		if (isset($resultado_anulacion['status']) && $resultado_anulacion['status'] == 1) {
			$id_documento_venta = $serie_factura . "-" . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT);
			$referencia_modificada = "Factura anulada: " . $serie_factura . "-" . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT);
			//anular la factura y los datos de la factura
			$datos_encabezado_ret = mysqli_query($con, "SELECT * FROM encabezado_retencion_venta WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and id_cliente='" . $id_cliente . "' and numero_documento = '" . $numero_documento . "'");
			$row_encabezado_ret = mysqli_fetch_array($datos_encabezado_ret);
			$registro_contable = isset($row_encabezado_ret['id_registro_contable']) ? $row_encabezado_ret['id_registro_contable'] : "";
			if ($registro_contable > 0) {
				$resultado_anular_contable_ret_venta = $anular_asiento_contable->anular_asiento_contable($con, $registro_contable);
			}

			if ($id_registro_contable > 0) {
				$resultado_anular_documento = $anular_asiento_contable->anular_asiento_contable($con, $id_registro_contable);
			}
			$delete_detalle = mysqli_query($con, "DELETE FROM cuerpo_factura WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_factura = '" . $serie_factura . "' and secuencial_factura = '" . $secuencial . "'");
			$delete_pago = mysqli_query($con, "DELETE FROM formas_pago_ventas WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_factura = '" . $serie_factura . "' and secuencial_factura = '" . $secuencial . "'");
			$delete_adicional = mysqli_query($con, "DELETE FROM detalle_adicional_factura WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_factura = '" . $serie_factura . "' and secuencial_factura = '" . $secuencial . "'");
			$update_inventario = mysqli_query($con, "UPDATE inventarios SET cantidad_salida='0', precio='0', fecha_registro='" . $fecha_registro . "',referencia='" . $referencia_modificada . "',id_usuario='" . $id_usuario . "', fecha_agregado='" . $fecha_registro . "' WHERE ruc_empresa = '" . $ruc_empresa . "' and operacion='SALIDA' and id_documento_venta='" . $id_documento_venta . "'");
			$anular = mysqli_query($con, "UPDATE encabezado_factura SET observaciones_factura='ANULADA', estado_sri='ANULADA', total_factura ='0.00', id_usuario= '" . $id_usuario . "' WHERE id_encabezado_factura = '" . $id_factura . "' ");
			$delete_enc_retencion_venta = mysqli_query($con, "DELETE FROM encabezado_retencion_venta WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and id_cliente='" . $id_cliente . "' and numero_documento = '" . $numero_documento . "'");
			$delete_cue_retencion_venta = mysqli_query($con, "DELETE FROM cuerpo_retencion_venta WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and numero_documento = '" . $numero_documento . "'");

			$buscar_ingresos = mysqli_query($con, "SELECT ing_egr.fecha_ing_egr as fecha_ingreso, det_ing_egr.codigo_documento as codigo_documento, ing_egr.codigo_contable as codigo_contable FROM detalle_ingresos_egresos as det_ing_egr INNER JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento=det_ing_egr.codigo_documento WHERE det_ing_egr.codigo_documento_cv = '" . $id_factura . "' and det_ing_egr.tipo_documento='INGRESO'");
			while ($det_ingresos = mysqli_fetch_array($buscar_ingresos)) {
				//para anular el asiento contable del ingreso
				$codigo_contable = $det_ingresos['codigo_contable'];
				$codigo_unico = $det_ingresos['codigo_documento'];
				if ($codigo_contable > 0) {
					$resultado_anular_documento = $anular_asiento_contable->anular_asiento_contable($con, $codigo_contable);
				}
				//para anular los registros de ingresos
				$anular_ingreso = mysqli_query($con, "UPDATE ingresos_egresos SET detalle_adicional='ANULADO', valor_ing_egr='0.00' WHERE codigo_documento = '" . $codigo_unico . "' and tipo_ing_egr='INGRESO'");
				$delete_detalle_ingreso = mysqli_query($con, "DELETE FROM detalle_ingresos_egresos WHERE codigo_documento = '" . $codigo_unico . "' and tipo_documento='INGRESO'");
			}

			if ($delete_detalle && $delete_pago && $delete_adicional && $update_inventario && $anular) {
				$messages[] = $resultado_anulacion['data'][0]['message'];
			} else {
				$errors[] = "Lo siento algo ha salido intente de nuevo.";
			}
		} else {
			if (!empty($resultado_anulacion['listError']['errorInfo'])) {
				$errors[] = $resultado_anulacion['listError']['errorInfo'] . "<br>";
			}

			if (!empty($resultado_anulacion['listError']['errors'])) {
				$errors[] = $resultado_anulacion['listError']['errors'] . "<br>";
			}
		}
	} else {
		$errors[] = "Error desconocido, intente de nuevo.";
	}
}


//para anular una liquidacion
if ($action == '03') {
	if (!isset($_POST['id_documento_modificar'])) {
		$errors[] = "Seleccione un documento para anular.";
	} else if (empty($_POST['fecha_autorizacion'])) {
		$errors[] = "Ingrese fecha de autorización del documento";
	} else if (empty($_POST['correo_receptor'])) {
		$errors[] = "Ingrese correo receptor";
	} else if (empty($_POST['clave_sri']) && $_POST['opcion_anular'] == '2') {
		$errors[] = "Ingrese clave del SRI";
	} else if (!empty($_POST['id_documento_modificar'])) {
		$id_usuario = $_SESSION['id_usuario'];
		$id_liquidacion = $_POST['id_documento_modificar'];
		$clave_acceso = $_POST['clave_acceso'];
		$clave_sri = $_POST['clave_sri'];
		$correo = $_POST['correo_receptor'];
		$opcion_anular = $_POST['opcion_anular'];
		$fecha_autorizacion = date("d/m/Y", strtotime($_POST['fecha_autorizacion']));
		$ruc_receptor = $_POST['ruc_receptor'];
		$tipo_comprobante = "2"; // 1 fact, 2 liq, 3 nc, 4 nd, 5 gr, 6 ret

		$busca_datos_liquidacion = "SELECT * FROM encabezado_liquidacion WHERE id_encabezado_liq = '" . $id_liquidacion . "' ";
		$result = $con->query($busca_datos_liquidacion);
		$datos_liquidacion = mysqli_fetch_array($result);
		$ruc_empresa = $datos_liquidacion['ruc_empresa'];
		$serie_liquidacion = $datos_liquidacion['serie_liquidacion'];
		$secuencial = $datos_liquidacion['secuencial_liquidacion'];
		$ruc_contribuyente = substr($datos_liquidacion['ruc_empresa'], 0, 10) . "001";

		//para sacar el id registro de la compra ya que la liquidacion tiene registro en la compra
		$busca_datos_compra = "SELECT * FROM encabezado_compra WHERE aut_sri = '" . $clave_acceso . "' and id_comprobante='3' ";
		$result_compra = $con->query($busca_datos_compra);
		$datos_compra = mysqli_fetch_array($result_compra);
		$id_registro_contable = isset($datos_compra['id_registro_contable']) ? $datos_compra['id_registro_contable'] : 0;


		if ($opcion_anular == '1') {
			$resultado_anulacion = array(
				'data' => array(
					array(
						'message' => 'Al seleccionar la opción, documento anulado previamente en el SRI, el documento se encuentra en estado ANULADO en el sistema, pero es responsabilidad del usuario haberlo anulado en el SRI'
					)
				),
				'listError' => array(
					'errorInfo' => '',
					'errors' => array()
				),
				'status' => 1
			);
		} else {
			$resultado_anulacion = anular_documento_sri($ruc_contribuyente, $clave_sri, $clave_acceso, $correo, $ruc_receptor, $tipo_comprobante, $fecha_autorizacion);
		}

		//anular la liQuiqdacion y los datos de la liquidacion
		if (isset($resultado_anulacion['status']) && $resultado_anulacion['status'] == 1) {
			if ($id_registro_contable > 0) {
				$resultado_anular_documento = $anular_asiento_contable->anular_asiento_contable($con, $id_registro_contable);
			}
			$delete_detalle = mysqli_query($con, "DELETE FROM cuerpo_liquidacion WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_liquidacion = '" . $serie_liquidacion . "' and secuencial_liquidacion = '" . $secuencial . "'");
			$delete_pago = mysqli_query($con, "DELETE FROM formas_pago_liquidacion WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_liquidacion = '" . $serie_liquidacion . "' and secuencial_liquidacion = '" . $secuencial . "'");
			$delete_adicional = mysqli_query($con, "DELETE FROM detalle_adicional_liquidacion WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_liquidacion = '" . $serie_liquidacion . "' and secuencial_liquidacion = '" . $secuencial . "'");
			$anular = mysqli_query($con, "UPDATE encabezado_liquidacion SET estado_sri='ANULADA', total_liquidacion ='0.00', id_usuario= '" . $id_usuario . "' WHERE id_encabezado_liq = '" . $id_liquidacion . "' ");

			if ($delete_detalle && $delete_pago && $delete_adicional && $anular) {
				$messages[] = $resultado_anulacion['data'][0]['message'];
			} else {
				$errors[] = "Lo siento algo ha salido mal intenta nuevamente.";
			}
		} else {
			if (!empty($resultado_anulacion['listError']['errorInfo'])) {
				$errors[] = $resultado_anulacion['listError']['errorInfo'] . "<br>";
			}

			if (!empty($resultado_anulacion['listError']['errors'])) {
				$errors[] = $resultado_anulacion['listError']['errors'] . "<br>";
			}
		}
	} else {
		$errors[] = "Error desconocido, intente de nuevo.";
	}
}

//para anular una retencion
if ($action == '07') {
	if (!isset($_POST['id_documento_modificar'])) {
		$errors[] = "Seleccione un documento para anular.";
	} else if (empty($_POST['fecha_autorizacion'])) {
		$errors[] = "Ingrese fecha de autorización del documento";
	} else if (empty($_POST['correo_receptor'])) {
		$errors[] = "Ingrese correo receptor";
	} else if (empty($_POST['clave_sri']) && $_POST['opcion_anular'] == '2') {
		$errors[] = "Ingrese clave del SRI";
	} else if (!empty($_POST['id_documento_modificar'])) {
		$id_usuario = $_SESSION['id_usuario'];
		$id_retencion = $_POST['id_documento_modificar'];
		$clave_acceso = $_POST['clave_acceso'];
		$clave_sri = $_POST['clave_sri'];
		$correo = $_POST['correo_receptor'];
		$opcion_anular = $_POST['opcion_anular'];
		$fecha_autorizacion = date("d/m/Y", strtotime($_POST['fecha_autorizacion']));
		$ruc_receptor = $_POST['ruc_receptor'];
		$tipo_comprobante = "6"; // 1 fact, 2 liq, 3 nc, 4 nd, 5 gr, 6 ret


		$busca_datos_retencion = "SELECT * FROM encabezado_retencion WHERE id_encabezado_retencion = '" . $id_retencion . "' ";
		$result = $con->query($busca_datos_retencion);
		$datos_retencion = mysqli_fetch_array($result);
		$ruc_empresa = $datos_retencion['ruc_empresa'];
		$serie_retencion = $datos_retencion['serie_retencion'];
		$secuencial = $datos_retencion['secuencial_retencion'];
		$id_registro_contable = $datos_retencion['id_registro_contable'];
		$ruc_contribuyente = substr($datos_retencion['ruc_empresa'], 0, 10) . "001";

		if ($opcion_anular == '1') {
			$resultado_anulacion = array(
				'data' => array(
					array(
						'message' => 'Al seleccionar la opción, documento anulado previamente en el SRI, el documento se encuentra en estado ANULADO en el sistema, pero es responsabilidad del usuario haberlo anulado en el SRI'
					)
				),
				'listError' => array(
					'errorInfo' => '',
					'errors' => array()
				),
				'status' => 1
			);
		} else {
			$resultado_anulacion = anular_documento_sri($ruc_contribuyente, $clave_sri, $clave_acceso, $correo, $ruc_receptor, $tipo_comprobante, $fecha_autorizacion);
		}

		if (isset($resultado_anulacion['status']) && $resultado_anulacion['status'] == 1) {
			//anular la retencion y los datos de la factura
			if ($id_registro_contable > 0) {
				$resultado_anular_documento = $anular_asiento_contable->anular_asiento_contable($con, $id_registro_contable);
			}
			$delete_detalle = mysqli_query($con, "DELETE FROM cuerpo_retencion WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_retencion = '" . $serie_retencion . "' and secuencial_retencion = '" . $secuencial . "'");
			$delete_adicional = mysqli_query($con, "DELETE FROM detalle_adicional_retencion WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_retencion = '" . $serie_retencion . "' and secuencial_retencion = '" . $secuencial . "'");
			$anular = mysqli_query($con, "UPDATE encabezado_retencion SET estado_sri='ANULADA', total_retencion ='0.00', id_usuario= '" . $id_usuario . "' WHERE id_encabezado_retencion = '" . $id_retencion . "' ");
			if ($delete_detalle && $delete_adicional && $anular) {
				$messages[] = $resultado_anulacion['data'][0]['message'];
			} else {
				$errors[] = "Lo siento algo ha salido mal intenta nuevamente.";
			}
		} else {
			if (!empty($resultado_anulacion['listError']['errorInfo'])) {
				$errors[] = $resultado_anulacion['listError']['errorInfo'] . "<br>";
			}

			if (!empty($resultado_anulacion['listError']['errors'])) {
				$errors[] = $resultado_anulacion['listError']['errors'] . "<br>";
			}
		}
	} else {
		$errors[] = "Error desconocido, intente de nuevo.";
	}
}



//para anular una nota de credito
if ($action == '04') {
	if (!isset($_POST['id_documento_modificar'])) {
		$errors[] = "Seleccione un documento para anular.";
	} else if (empty($_POST['fecha_autorizacion'])) {
		$errors[] = "Ingrese fecha de autorización del documento";
	} else if (empty($_POST['correo_receptor'])) {
		$errors[] = "Ingrese correo receptor";
	} else if (empty($_POST['clave_sri']) && $_POST['opcion_anular'] == '2') {
		$errors[] = "Ingrese clave del SRI";
	} else if (!empty($_POST['id_documento_modificar'])) {
		$id_usuario = $_SESSION['id_usuario'];
		$id_nc = $_POST['id_documento_modificar'];
		$clave_acceso = $_POST['clave_acceso'];
		$clave_sri = $_POST['clave_sri'];
		$correo = $_POST['correo_receptor'];
		$opcion_anular = $_POST['opcion_anular'];
		$fecha_autorizacion = date("d/m/Y", strtotime($_POST['fecha_autorizacion']));
		$ruc_receptor = $_POST['ruc_receptor'];
		$tipo_comprobante = "3"; // 1 fact, 2 liq, 3 nc, 4 nd, 5 gr, 6 ret

		$busca_datos_nc = "SELECT * FROM encabezado_nc WHERE id_encabezado_nc = '" . $id_nc . "' ";
		$result = $con->query($busca_datos_nc);
		$datos_nc = mysqli_fetch_array($result);
		$ruc_empresa = $datos_nc['ruc_empresa'];
		$serie_nc = $datos_nc['serie_nc'];
		$secuencial = $datos_nc['secuencial_nc'];
		$id_registro_contable = $datos_nc['id_registro_contable'];
		$ruc_contribuyente = substr($datos_nc['ruc_empresa'], 0, 10) . "001";

		if ($opcion_anular == '1') {
			$resultado_anulacion = array(
				'data' => array(
					array(
						'message' => 'Al seleccionar la opción, documento anulado previamente en el SRI, el documento se encuentra en estado ANULADO en el sistema, pero es responsabilidad del usuario haberlo anulado en el SRI'
					)
				),
				'listError' => array(
					'errorInfo' => '',
					'errors' => array()
				),
				'status' => 1
			);
		} else {
			$resultado_anulacion = anular_documento_sri($ruc_contribuyente, $clave_sri, $clave_acceso, $correo, $ruc_receptor, $tipo_comprobante, $fecha_autorizacion);
		}

		if (isset($resultado_anulacion['status']) && $resultado_anulacion['status'] == 1) {
			//anular la nc y los datos de la factura
			if ($id_registro_contable > 0) {
				$resultado_anular_documento = $anular_asiento_contable->anular_asiento_contable($con, $id_registro_contable);
			}
			$delete_detalle = mysqli_query($con, "DELETE FROM cuerpo_nc WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_nc = '" . $serie_nc . "' and secuencial_nc = '" . $secuencial . "'");
			$delete_adicional = mysqli_query($con, "DELETE FROM detalle_adicional_nc WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_nc = '" . $serie_nc . "' and secuencial_nc = '" . $secuencial . "'");
			$anular = mysqli_query($con, "UPDATE encabezado_nc SET estado_sri='ANULADA', total_nc ='0.00', id_usuario= '" . $id_usuario . "' WHERE id_encabezado_nc = '" . $id_nc . "' ");
			if ($delete_detalle && $delete_adicional && $anular) {
				$messages[] = $resultado_anulacion['data'][0]['message'];
			} else {
				$errors[] = "Lo siento algo ha salido mal intenta nuevamente.";
			}
		} else {
			if (!empty($resultado_anulacion['listError']['errorInfo'])) {
				$errors[] = $resultado_anulacion['listError']['errorInfo'] . "<br>";
			}

			if (!empty($resultado_anulacion['listError']['errors'])) {
				$errors[] = $resultado_anulacion['listError']['errors'] . "<br>";
			}
		}
	} else {
		$errors[] = "Error desconocido, intente de nuevo.";
	}
}

//para anular una guia de remision
if ($action == '06') {
	if (!isset($_POST['id_documento_modificar'])) {
		$errors[] = "Seleccione un documento para anular.";
	} else if (empty($_POST['fecha_autorizacion'])) {
		$errors[] = "Ingrese fecha de autorización del documento";
	} else if (empty($_POST['correo_receptor'])) {
		$errors[] = "Ingrese correo receptor";
	} else if (empty($_POST['clave_sri']) && $_POST['opcion_anular'] == '2') {
		$errors[] = "Ingrese clave del SRI";
	} else if (!empty($_POST['id_documento_modificar'])) {
		$id_usuario = $_SESSION['id_usuario'];
		$id_guia = $_POST['id_documento_modificar'];
		$clave_acceso = $_POST['clave_acceso'];
		$clave_sri = $_POST['clave_sri'];
		$correo = $_POST['correo_receptor'];
		$opcion_anular = $_POST['opcion_anular'];
		$fecha_autorizacion = date("d/m/Y", strtotime($_POST['fecha_autorizacion']));
		$ruc_receptor = $_POST['ruc_receptor'];
		$tipo_comprobante = "5"; // 1 fact, 2 liq, 3 nc, 4 nd, 5 gr, 6 ret

		$busca_datos_guia = "SELECT * FROM encabezado_gr WHERE id_encabezado_gr = '" . $id_guia . "' ";
		$result = $con->query($busca_datos_guia);
		$datos_guia = mysqli_fetch_array($result);
		$ruc_empresa = $datos_guia['ruc_empresa'];
		$serie_guia = $datos_guia['serie_gr'];
		$secuencial = $datos_guia['secuencial_gr'];
		$ruc_contribuyente = substr($datos_guia['ruc_empresa'], 0, 10) . "001";

		if ($opcion_anular == '1') {
			$resultado_anulacion = array(
				'data' => array(
					array(
						'message' => 'Al seleccionar la opción, documento anulado previamente en el SRI, el documento se encuentra en estado ANULADO en el sistema, pero es responsabilidad del usuario haberlo anulado en el SRI'
					)
				),
				'listError' => array(
					'errorInfo' => '',
					'errors' => array()
				),
				'status' => 1
			);
		} else {
			$resultado_anulacion = anular_documento_sri($ruc_contribuyente, $clave_sri, $clave_acceso, $correo, $ruc_receptor, $tipo_comprobante, $fecha_autorizacion);
		}

		if (isset($resultado_anulacion['status']) && $resultado_anulacion['status'] == 1) {
			//anular la guia y los datos de la guia
			$delete_detalle = mysqli_query($con, "DELETE FROM cuerpo_gr WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_gr = '" . $serie_guia . "' and secuencial_gr = '" . $secuencial . "'");
			$anular = mysqli_query($con, "UPDATE encabezado_gr SET estado_sri='ANULADA', id_usuario= '" . $id_usuario . "' WHERE id_encabezado_gr = '" . $id_guia . "' ");
			if ($delete_detalle && $anular) {
				$messages[] = $resultado_anulacion['data'][0]['message'];
			} else {
				$errors[] = "Lo siento algo ha salido mal intenta nuevamente.";
			}
		} else {
			if (!empty($resultado_anulacion['listError']['errorInfo'])) {
				$errors[] = $resultado_anulacion['listError']['errorInfo'] . "<br>";
			}

			if (!empty($resultado_anulacion['listError']['errors'])) {
				$errors[] = $resultado_anulacion['listError']['errors'] . "<br>";
			}
		}
	} else {
		$errors[] = "Error desconocido, intente de nuevo.";
	}
}


//para mostrar los mensajes 
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
		<?php
		foreach ($messages as $message) {
			echo $message;
		}
		?>
	</div>
<?php
}

function anular_documento_sri($ruc_empresa, $password, $claveAcceso, $correo, $ruc_receptor, $tipo_comprobante, $fecha_autorizacion)
{
	//$url_anulacion = "http://137.184.159.242:3500/api/sri-anulacion";
	$url_anulacion = "http://159.89.235.139:3500/api/sri-anulacion";
	$data_anulacion = array_map('strval', array(
		"ruc" => $ruc_empresa,
		"password" => $password,
		"claveAcceso" => $claveAcceso,
		"autorizacion" => $claveAcceso,
		"correo" => $correo,
		"receptor" => $ruc_receptor,
		"tipoComprobante" => $tipo_comprobante,
		"fecha" => $fecha_autorizacion
	));

	$ch = curl_init($url_anulacion);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Content-Type: application/json',
		'Connection: keep-alive'
	]);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_anulacion));
	curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1); // Usa HTTP/2
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300); // Espera hasta 300s para conectar
	curl_setopt($ch, CURLOPT_TIMEOUT, 300); // Espera hasta 300s para respuesta

	$response = curl_exec($ch);

	if (curl_errno($ch)) {
		echo 'Error en la petición: ' . curl_error($ch);
	} else {
		// Decodificar la respuesta JSON
		$responseData = json_decode($response, true);
		return $responseData;
	}
	curl_close($ch);
}




/* {
    "data": [
        {
            "message": "La solicitud del contribuyente ha sido enviada con éxito, al momento el comprobante con clave de acceso 0801202501171713657400120010010000017711234567811 se encuentra en estado ANULADO"
        }
    ],
    "listError": {
        "errorInfo": "",
        "errors": ""
    },
    "status": true
} 

Array ( [data] => Array ( [0] => Array ( [message] => La solicitud del contribuyente ha sido enviada con éxito, al momento el comprobante con clave de acceso 2612202401171713657400120010010000017661234567811 se encuentra en estado ANULADO ) ) [listError] => Array ( [errorInfo] => [errors] => ) [status] => 1 )

Array ( [data] => Array ( ) [listError] => Array ( [errorInfo] => Error al realizar solicitud [errors] => La información ingresada no corresponde a un Comprobante Electrónico AUTORIZADO. Por favor vuelva a ingresar su solicitud de anulación. ) [status] => )
*/

?>