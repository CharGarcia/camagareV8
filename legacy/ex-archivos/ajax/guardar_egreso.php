<?php
include("../clases/contabilizacion.php");
$contabilizacion = new contabilizacion();
include("../conexiones/conectalogin.php");
include("../validadores/generador_codigo_unico.php");
include("../clases/asientos_contables.php");
session_start();
$id_usuario = $_SESSION['id_usuario'];
$ruc_empresa = $_SESSION['ruc_empresa'];
$con = conenta_login();

if (empty($_POST['fecha_egreso'])) {
	$errors[] = "Ingrese fecha para el egreso.";
} else if (!strtotime($_POST['fecha_egreso'])) {
	$errors[] = "Ingrese una fecha válida.";
} else if (empty($_POST['nombre_beneficiario'])) {
	$errors[] = "Ingrese o seleccione un proveedor o el nombre de un beneficiario.";
} else if (empty($_POST['total_egreso'])) {
	$errors[] = "Ingrese detalles en el egreso.";
} else if (empty($_POST['total_pagos_egreso'])) {
	$errors[] = "Ingrese valores en formas de pago";
} else if ($_POST['total_pagos_egreso'] != $_POST['total_egreso']) {
	$errors[] = "El total del egreso no es igual al total de formas de pago";
} else if (empty($ruc_empresa)) {
	$errors[] = "La sesión ha expirado, reingrese al sistema";
} else {

	$fecha_egreso = date('Y-m-d H:i:s', strtotime($_POST['fecha_egreso']));
	$id_proveedor = mysqli_real_escape_string($con, strip_tags($_POST["id_proveedor"], ENT_QUOTES));
	$nombre_beneficiario = mysqli_real_escape_string($con, strip_tags($_POST["nombre_beneficiario"], ENT_QUOTES));
	$total_egreso = mysqli_real_escape_string($con, strip_tags($_POST["total_egreso"], ENT_QUOTES));
	$pagos_egreso = mysqli_real_escape_string($con, strip_tags($_POST["total_pagos_egreso"], ENT_QUOTES));
	$detalle_adicional = mysqli_real_escape_string($con, strip_tags($_POST["detalle_adicional"], ENT_QUOTES));
	$codigo_documento = codigo_unico(20);
	$fecha_registro = date("Y-m-d H:i:s");

	$sql_tmp = mysqli_query($con, "SELECT * FROM ingresos_egresos_tmp WHERE id_usuario = '$id_usuario' AND tipo_documento='EGRESO'");
	if (mysqli_num_rows($sql_tmp) == 0) {
		$errors[] = "No hay documentos o detalles agregados al egreso.";
	} else {
		$sql_diario_tmp = mysqli_query($con, "SELECT COUNT(id_cuenta) AS cuenta, SUM(debe) AS debe, SUM(haber) AS haber FROM detalle_diario_tmp WHERE id_usuario = '$id_usuario' AND MID(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'");
		$row_asiento = mysqli_fetch_array($sql_diario_tmp);
		if ($row_asiento['cuenta'] > 0 && $row_asiento['debe'] != $row_asiento['haber']) {
			$errors[] = "El asiento contable no cumple con partida doble.";
		} else {
			mysqli_begin_transaction($con);
			try {
				$numero_egreso_query = mysqli_query($con, "SELECT MAX(numero_ing_egr) AS numero FROM ingresos_egresos WHERE ruc_empresa = '$ruc_empresa' AND tipo_ing_egr = 'EGRESO'");
				$numero_egreso = mysqli_fetch_array($numero_egreso_query)['numero'] + 1;

				$insert_encabezado = mysqli_query($con, "INSERT INTO ingresos_egresos VALUES (null, '$ruc_empresa','$fecha_egreso','$nombre_beneficiario','$numero_egreso','$total_egreso','EGRESO','$id_usuario','$fecha_registro','0','$codigo_documento','$detalle_adicional','OK','$id_proveedor')");
				if (!$insert_encabezado) throw new Exception("Error al guardar encabezado del egreso.");

				$get_id_query = mysqli_query($con, "SELECT id_ing_egr FROM ingresos_egresos WHERE id_ing_egr = LAST_INSERT_ID()");
				$id_ing_egr = mysqli_fetch_array($get_id_query)['id_ing_egr'];

				if ($row_asiento['cuenta'] > 0 && $row_asiento['debe'] == $row_asiento['haber']) {
					$asiento = new asientos_contables();
					$ok_asiento = $asiento->guarda_asiento($con, $fecha_egreso, 'EGRESO N.' . $numero_egreso . " " . $nombre_beneficiario, 'EGRESOS', $id_ing_egr, $ruc_empresa, $id_usuario, $id_proveedor);
					if (!$ok_asiento) throw new Exception("Error al guardar asiento contable.");
				}

				if (isset($_SESSION['arrayFormaPagoEgreso'])) {
					foreach ($_SESSION['arrayFormaPagoEgreso'] as $detalle) {
						$origen = $detalle['origen'];
						$codigo_forma_pago = $origen == '1' ? $detalle['id_forma'] : '0';
						$id_cuenta = $origen == '1' ? '0' : $detalle['id_forma'];
						$valor_pago = number_format($detalle['valor'], 2, '.', '');
						$tipo = $detalle['tipo'];
						$cheque = $tipo == 'C' ? $detalle['cheque'] : 0;
						$fecha_cheque = $tipo == 'C' ? $detalle['fecha_cheque'] : $fecha_egreso;
						$estado_pago = $cheque > 0 ? "ENTREGAR" : "PAGADO";

						$sql_pago = mysqli_query($con, "INSERT INTO formas_pagos_ing_egr VALUES (null, '$ruc_empresa', 'EGRESO', '$numero_egreso', '$valor_pago', '$codigo_forma_pago', '$id_cuenta', '$tipo', '$codigo_documento', '$fecha_egreso', '$fecha_egreso', '$fecha_cheque','$estado_pago','$cheque','OK')");
						if (!$sql_pago) throw new Exception("Error al guardar forma de pago.");
					}
				}

				$insert_detalle = mysqli_query($con, "INSERT INTO detalle_ingresos_egresos (id_detalle_ing_egr, ruc_empresa, beneficiario_cliente, valor_ing_egr, detalle_ing_egr, numero_ing_egr, tipo_ing_egr, tipo_documento, codigo_documento_cv, estado, codigo_documento)
                SELECT null, '$ruc_empresa', beneficiario_cliente, valor, detalle, '$numero_egreso', tipo_transaccion, 'EGRESO', id_documento,'OK', '$codigo_documento' FROM ingresos_egresos_tmp WHERE id_usuario = '$id_usuario' AND tipo_documento='EGRESO'");
				if (!$insert_detalle || mysqli_affected_rows($con) == 0) throw new Exception("No se guardó ningún detalle del egreso.");

				$contabilizacion->documentosEgresos($con, $ruc_empresa, $fecha_egreso, $fecha_egreso);
				$contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'egresos');

				mysqli_commit($con);
				unset($_SESSION['arrayFormaPagoEgreso']);
				echo "<script>
                    $.notify('Egreso guardado con éxito','success');
                    setTimeout(function () { location.reload(); }, 2000);
                </script>";
			} catch (Exception $e) {
				mysqli_rollback($con);
				$errors[] = "Error inesperado al guardar el egreso. Detalles: " . $e->getMessage();
			}
		}
	}
}

if (isset($errors)) {
	echo '<div class="alert alert-danger" role="alert">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <strong>Atención! </strong>';
	foreach ($errors as $error) {
		echo $error;
	}
	echo '</div>';
}
