<?php
/* Connect To Database*/
include("../clases/contabilizacion.php");
$contabilizacion = new contabilizacion();
include("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
include("../clases/anular_registros.php");
$anular_asiento_contable = new anular_registros();
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');
$fecha_registro = date("Y-m-d H:i:s");

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'actualizar_egreso') {
	$codigo_documento = $_POST['codigo_unico_egreso'];
	$id_beneficiario = $_POST['id_beneficiario_editar_egreso'];
	$nombre_beneficiario = strClean($_POST['beneficiario_editar_egreso']);
	$fecha_egreso = date("Y/m/d", strtotime($_POST['fecha_editar_egreso']));
	$observaciones = strClean($_POST['observaciones_editar_egreso']);
	$total_detalles = 0;
	$total_pagos = 0;
	$total_tipo = 0;
	$suma_detalle = 0;
	$suma_formas_pagos = 0;

	if ($id_beneficiario == 0 || empty($id_beneficiario)) {
		echo "<script>
		$.notify('Vuelva a seleccionar el proveedor o beneficiario','error')
		</script>";
		exit;
	}

	$datos_encabezado = mysqli_query($con, "SELECT * FROM ingresos_egresos WHERE codigo_documento = '" . $codigo_documento . "' ");
	$row_encabezado = mysqli_fetch_array($datos_encabezado);
	if (periodosContables($con, $fecha_egreso, $ruc_empresa) == true) {
		$periodo = date('m-Y', strtotime($fecha_egreso));
		echo "<script>
		$.notify('El período contable $periodo se encuentra cerrado para registrar transacciones','error')
		</script>";
		exit;
	}

	//anular asiento contable
	$id_registro_contable = isset($row_encabezado['codigo_contable']) ? $row_encabezado['codigo_contable'] : "";
	if ($id_registro_contable > 0) {
		$resultado_anular_documento = $anular_asiento_contable->anular_asiento_contable($con, $id_registro_contable);
	}

	//total del egreso
	$sql_total_detalle = mysqli_query($con, "SELECT round(sum(valor_ing_egr),2) as total_egreso FROM ingresos_egresos 
	WHERE codigo_documento='" . $codigo_documento . "' group by codigo_documento ");
	$row_total_detalle = mysqli_fetch_assoc($sql_total_detalle);
	$total_egreso = $row_total_detalle['total_egreso'];


	//para los detalles del egreso
	$id_detalle_egreso = isset($_POST['id_detalle_egreso']) ? $_POST['id_detalle_egreso'] : "";
	if (!empty($id_detalle_egreso)) {
		$tipo_egreso = $_POST['tipo_egreso'];
		$detalle_egreso = $_POST['detalle_editar_egreso'];
		$valor_detalle_egreso = $_POST['valor_detalle_editar_egreso'];
		$valor_detalle_egresos_no_editables = array_sum($_POST['valor_detalle_egresos_no_editables']);
		$suma_de_detalles_de_gresos = (isset($_POST['valor_detalle_editar_egreso']) ? array_sum($_POST['valor_detalle_editar_egreso']) : 0) +
			(isset($_POST['valor_detalle_egresos_no_editables']) ? array_sum($_POST['valor_detalle_egresos_no_editables']) : 0);
		if (round($total_egreso, 2) != round($suma_de_detalles_de_gresos, 2)) {
			$suma_detalle = 1;
		} else {
			$suma_detalle = 0;
		}
		//para ver si falta completar las filas de los detalles del egreso
		foreach ($id_detalle_egreso as $valor_detalle) {
			if ($valor_detalle > 0 && !empty($detalle_egreso[$valor_detalle])) {
				$total_detalles = 0;
			} else {
				$total_detalles = 1;
			}
		}
	}

	//para las formas de pagos del egreso
	$id_forma_pago_egreso = isset($_POST['id_forma_pago_egreso']) ? $_POST['id_forma_pago_egreso'] : "";
	if (!empty($id_forma_pago_egreso)) {
		$forma_pago_egreso = $_POST['forma_pago_editar_egreso'];
		$tipo_pago_egreso = $_POST['tipo_editar_forma_pago'];
		$cheque_egreso = isset($_POST['numero_cheque_editar_egreso']) ? $_POST['numero_cheque_editar_egreso'] : 0;
		$fecha_cheque_egreso = isset($_POST['fecha_cobro_editar_egreso']) ? $_POST['fecha_cobro_editar_egreso'] : $fecha_egreso;
		$valor_pago_egreso = $_POST['valor_cobro_editar_egreso'];
		if (round($total_egreso, 2) != round(array_sum($_POST['valor_cobro_editar_egreso']), 2)) {
			$suma_formas_pagos = 1;
		} else {
			$suma_formas_pagos = 0;
		}

		//para ver si falta completar las filas de los pagos del egreso
		foreach ($id_forma_pago_egreso as $id_forma_pago) {
			if (substr($forma_pago_egreso[$id_forma_pago], 0, 1) == 1) { //no es cuenta bancaria
				if ($valor_pago_egreso[$id_forma_pago] > 0) {
					$total_pagos = 0;
				} else {
					$total_pagos = 1;
				}
			}

			if (
				substr($forma_pago_egreso[$id_forma_pago], 0, 1) == 2
				&& $tipo_pago_egreso[$id_forma_pago] == 'C'
				&& (empty($cheque_egreso[$id_forma_pago]) || empty($fecha_cheque_egreso[$id_forma_pago]))
			) { //si es cuenta bancaria
				$total_tipo = 1;
			} else {
				$total_tipo = 0;
			}


			if (
				substr($forma_pago_egreso[$id_forma_pago], 0, 1) == 2
				&& $tipo_pago_egreso[$id_forma_pago] == "0"
			) {
				$total_tipo = 1;
			} else {
				$total_tipo = 0;
			}

			if ($valor_pago_egreso[$id_forma_pago] <= 0 || empty($valor_pago_egreso[$id_forma_pago])) {
				$total_pagos++;
			} else {
				$total_pagos = 0;
			}
		}
	}


	if ($total_detalles > 0) {
		echo "<script>
		$.notify('Completar detalle de egreso','error')
		</script>";
	} else if ($total_tipo > 0) {
		echo "<script>
		$.notify('Seleccione un tipo de opción bancaria','error')
		</script>";
	} else if ($total_pagos > 0) {
		echo "<script>
		$.notify('Completar valores de pago','error')
		</script>";
	} else if ($suma_detalle > 0) {
		echo "<script>
		$.notify('La suma de los detalles " . number_format($suma_de_detalles_de_gresos, 2, '.', '') . " no coincide con el total del egreso " . number_format($total_egreso, 2, '.', '') . "','error')
		</script>";
	} else if ($suma_formas_pagos > 0) {
		echo "<script>
		$.notify('La suma de los valores de pagos " . number_format(array_sum($_POST['valor_cobro_editar_egreso']), 2, '.', '') . " no coincide con el total del egreso " . number_format($total_egreso, 2, '.', '') . "','error')
		</script>";
	} else {
		//para guardar los detalles
		if (!empty($id_detalle_egreso)) {
			foreach ($id_detalle_egreso as $valor_detalle) {
				$actualiza_detalle = mysqli_query($con, "UPDATE detalle_ingresos_egresos SET tipo_ing_egr='" . $tipo_egreso[$valor_detalle] . "', detalle_ing_egr='" . $detalle_egreso[$valor_detalle] . "', valor_ing_egr='" . number_format($valor_detalle_egreso[$valor_detalle], 2, '.', '') . "'	WHERE id_detalle_ing_egr='" . $valor_detalle . "' ");
			}
		}
		//para guardar los pagos
		if (!empty($id_forma_pago_egreso)) {
			foreach ($id_forma_pago_egreso as $id_forma_pago) {
				if (substr($forma_pago_egreso[$id_forma_pago], 0, 1) == 1) { //cuando no es cuenta bancaria
					$actualiza_pagos = mysqli_query($con, "UPDATE formas_pagos_ing_egr SET codigo_forma_pago='" . substr($forma_pago_egreso[$id_forma_pago], 1) . "', id_cuenta='0', detalle_pago='', fecha_emision='" . $fecha_egreso . "' , fecha_entrega='" . $fecha_egreso . "', fecha_pago='" . $fecha_egreso . "', valor_forma_pago='" . number_format($valor_pago_egreso[$id_forma_pago], 2, '.', '') . "', cheque='0'  WHERE id_fp='" . $id_forma_pago . "' ");
				}
				if (substr($forma_pago_egreso[$id_forma_pago], 0, 1) == 2 && $tipo_pago_egreso[$id_forma_pago] == 'C') { //cuando si es cuenta bancaria y cheque
					$actualiza_pagos = mysqli_query($con, "UPDATE formas_pagos_ing_egr SET codigo_forma_pago='0', id_cuenta='" . substr($forma_pago_egreso[$id_forma_pago], 1) . "', detalle_pago='" . $tipo_pago_egreso[$id_forma_pago] . "', fecha_emision='" . $fecha_egreso . "' , fecha_entrega='" . $fecha_egreso . "', fecha_pago='" . date("Y/m/d", strtotime($fecha_cheque_egreso[$id_forma_pago])) . "', valor_forma_pago='" . number_format($valor_pago_egreso[$id_forma_pago], 2, '.', '') . "', cheque='" . $cheque_egreso[$id_forma_pago] . "'  WHERE id_fp='" . $id_forma_pago . "' ");
				}
				if (substr($forma_pago_egreso[$id_forma_pago], 0, 1) == 2 && $tipo_pago_egreso[$id_forma_pago] == 'D') { //cuando si es cuenta bancaria y debito
					$actualiza_pagos = mysqli_query($con, "UPDATE formas_pagos_ing_egr SET codigo_forma_pago='0', id_cuenta='" . substr($forma_pago_egreso[$id_forma_pago], 1) . "', detalle_pago='" . $tipo_pago_egreso[$id_forma_pago] . "', fecha_emision='" . $fecha_egreso . "' , fecha_entrega='" . $fecha_egreso . "', fecha_pago='" . $fecha_egreso . "', valor_forma_pago='" . number_format($valor_pago_egreso[$id_forma_pago], 2, '.', '') . "', cheque='0', estado_pago='PAGADO' WHERE id_fp='" . $id_forma_pago . "' ");
				}
				if (substr($forma_pago_egreso[$id_forma_pago], 0, 1) == 2 && $tipo_pago_egreso[$id_forma_pago] == 'T') { //cuando si es cuenta bancaria y transferencia
					$actualiza_pagos = mysqli_query($con, "UPDATE formas_pagos_ing_egr SET codigo_forma_pago='0', id_cuenta='" . substr($forma_pago_egreso[$id_forma_pago], 1) . "', detalle_pago='" . $tipo_pago_egreso[$id_forma_pago] . "', fecha_emision='" . $fecha_egreso . "' , fecha_entrega='" . $fecha_egreso . "', fecha_pago='" . $fecha_egreso . "', valor_forma_pago='" . number_format($valor_pago_egreso[$id_forma_pago], 2, '.', '') . "', cheque='0', estado_pago='PAGADO' WHERE id_fp='" . $id_forma_pago . "' ");
				}
			}
		}

		//guarda encabezado
		$actualiza_encabezado = mysqli_query($con, "UPDATE ingresos_egresos SET nombre_ing_egr='" . $nombre_beneficiario . "', id_cli_pro='" . $id_beneficiario . "', fecha_ing_egr='" . $fecha_egreso . "', detalle_adicional='" . $observaciones . "'	WHERE codigo_documento='" . $codigo_documento . "' ");

		//$contabilizacion->documentosEgresos($con, $ruc_empresa, $fecha_egreso, $fecha_egreso);
		//$guardar_asientos_contables_generados = $contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'egresos');

		if ($actualiza_encabezado) {
			echo "<script>
					$.notify('Egreso actualizado','success')
					</script>";
		} else {
			echo "<script>
				$.notify('Lo siento, algo salio mal, intente nuevamente','error')
				</script>";
		}
	}
}

//para actualizar la fecha de entrega del cheque
if ($action == 'actualizar_fecha_entrega_cheque') {
	$id_registro = $_POST['id_registro'];
	$nueva_fecha = date("Y/m/d", strtotime($_POST['nueva_fecha']));

	$sql_pagos = mysqli_query($con, "SELECT * from formas_pagos_ing_egr WHERE id_fp='" . $id_registro . "' ");
	$row_pagos = mysqli_fetch_array($sql_pagos);
	$codigo_documento = $row_pagos['codigo_documento'];

	$sql_egresos = mysqli_query($con, "SELECT * from ingresos_egresos WHERE codigo_documento='" . $codigo_documento . "' ");
	$row_registro_contable = mysqli_fetch_array($sql_egresos);
	$id_registro_contable = isset($row_registro_contable['codigo_contable']) ? $row_registro_contable['codigo_contable'] : "";

	if (periodosContables($con, $nueva_fecha, $ruc_empresa) == true) {
		$periodo = date('m-Y', strtotime($nueva_fecha));
		echo "<script>
		$.notify('El período contable $periodo se encuentra cerrado para registrar transacciones','error')
		</script>";
		exit;
	}

	$actualiza_estado_fecha_cheque = mysqli_query($con, "UPDATE formas_pagos_ing_egr SET fecha_entrega='" . $nueva_fecha . "' WHERE id_fp='" . $id_registro . "' ");
	$actualiza_fecha_asiento = mysqli_query($con, "UPDATE encabezado_diario SET fecha_asiento ='" . $nueva_fecha . "' WHERE id_diario ='" . $id_registro_contable  . "' ");
	if ($actualiza_estado_fecha_cheque) {
		echo "<script>
					$.notify('Actualizado','success')
					</script>";
	} else {
		echo "<script>
				$.notify('Lo siento, algo salio mal, intente nuevamente','error')
				</script>";
	}
}


//para actualizar la fecha de entrega de transferencia y debito
if ($action == 'actualizar_fecha_pago') {
	$id_registro = $_GET['id_registro'];
	$nueva_fecha = date("Y/m/d", strtotime($_GET['nueva_fecha']));

	$sql_pagos = mysqli_query($con, "SELECT * from formas_pagos_ing_egr WHERE id_fp='" . $id_registro . "' ");
	$row_pagos = mysqli_fetch_array($sql_pagos);
	$codigo_documento = $row_pagos['codigo_documento'];

	$sql_egresos = mysqli_query($con, "SELECT * from ingresos_egresos WHERE codigo_documento='" . $codigo_documento . "' ");
	$row_registro_contable = mysqli_fetch_array($sql_egresos);
	$id_registro_contable = isset($row_registro_contable['codigo_contable']) ? $row_registro_contable['codigo_contable'] : "";

	if (periodosContables($con, $nueva_fecha, $ruc_empresa) == true) {
		$periodo = date('m-Y', strtotime($nueva_fecha));
		echo "<script>
		$.notify('El período contable $periodo se encuentra cerrado para registrar transacciones','error')
		</script>";
		exit;
	}

	$actualiza_estado_fecha_transferencia = mysqli_query($con, "UPDATE formas_pagos_ing_egr SET fecha_pago='" . $nueva_fecha . "' WHERE id_fp='" . $id_registro . "' ");
	$actualiza_fecha_asiento = mysqli_query($con, "UPDATE encabezado_diario SET fecha_asiento ='" . $nueva_fecha . "' WHERE id_diario ='" . $id_registro_contable  . "' ");
	if ($actualiza_estado_fecha_transferencia && $actualiza_fecha_asiento) {
		echo "<script>
					$.notify('Actualizado','success')
					</script>";
	} else {
		echo "<script>
				$.notify('Lo siento, algo salio mal, intente nuevamente','error')
				</script>";
	}
}


//para actualizar estado del cheque
if ($action == 'actualizar_estado_cheque') {
	$id_cheque = $_GET['id_cheque'];
	$nuevo_estado = $_GET['nuevo_estado'];
	$sql_pagos = mysqli_query($con, "SELECT * from formas_pagos_ing_egr WHERE id_fp='" . $id_cheque . "' ");
	$row_pagos = mysqli_fetch_array($sql_pagos);
	$codigo_documento = $row_pagos['codigo_documento'];
	$nueva_fecha = $row_pagos['fecha_pago'];

	$sql_egresos = mysqli_query($con, "SELECT * from ingresos_egresos WHERE codigo_documento='" . $codigo_documento . "' ");
	$row_registro_contable = mysqli_fetch_array($sql_egresos);
	$id_registro_contable = isset($row_registro_contable['codigo_contable']) ? $row_registro_contable['codigo_contable'] : "";

	if (periodosContables($con, $nueva_fecha, $ruc_empresa) == true) {
		$periodo = date('m-Y', strtotime($nueva_fecha));
		echo "<script>
		$.notify('El período contable $periodo se encuentra cerrado para registrar transacciones','error')
		</script>";
		exit;
	}

	if ($nuevo_estado == "ANULADO") {
		$actualiza_estado_cheque = mysqli_query($con, "UPDATE formas_pagos_ing_egr SET valor_forma_pago=0, estado_pago='" . $nuevo_estado . "', fecha_entrega=fecha_emision WHERE id_fp='" . $id_cheque . "' ");

		$actualiza_ingresos_egresos = mysqli_query($con, "UPDATE ingresos_egresos SET valor_ing_egr=0, detalle_adicional='CHEQUE ANULADO' WHERE codigo_documento='" . $codigo_documento . "' ");
		$actualiza_detalle_ingresos_egresos = mysqli_query($con, "UPDATE detalle_ingresos_egresos SET valor_ing_egr=0, codigo_documento_cv=0, detalle_ing_egr='CHEQUE ANULADO' WHERE codigo_documento='" . $codigo_documento . "' ");

		//anular asiento contable
		if ($id_registro_contable > 0) {
			$resultado_anular_documento = $anular_asiento_contable->anular_asiento_contable($con, $id_registro_contable);
		}

		echo "<script>
		$.notify('Estado actualizado','success')
		</script>";
	} else {
		$actualiza_estado_cheque = mysqli_query($con, "UPDATE formas_pagos_ing_egr SET estado_pago='" . $nuevo_estado . "', fecha_entrega=fecha_emision WHERE id_fp='" . $id_cheque . "' ");
		if ($actualiza_estado_cheque) {
			echo "<script>
					$.notify('Estado actualizado','success')
					</script>";
		} else {
			echo "<script>
				$.notify('Lo siento, algo salio mal, intente nuevamente','error')
				</script>";
		}
	}
}

//para anular un egreso
if ($action == 'anular_egreso') {
	$id_usuario = $_SESSION['id_usuario'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$codigo_documento = $_POST['codigo_documento'];

	$datos_encabezado = mysqli_query($con, "SELECT * FROM ingresos_egresos WHERE codigo_documento = '" . $codigo_documento . "' ");
	$row_encabezado = mysqli_fetch_array($datos_encabezado);
	$nueva_fecha = $row_encabezado['fecha_ing_egr'];
	if (periodosContables($con, $nueva_fecha, $ruc_empresa) == true) {
		$periodo = date('m-Y', strtotime($nueva_fecha));
		echo "<script>
		$.notify('El período contable $periodo se encuentra cerrado para registrar transacciones','error')
		</script>";
		exit;
	}

	//anular asiento contable
	$id_registro_contable = isset($row_encabezado['codigo_contable']) ? $row_encabezado['codigo_contable'] : "";
	if ($id_registro_contable > 0) {
		$resultado_anular_documento = $anular_asiento_contable->anular_asiento_contable($con, $id_registro_contable);
	}

	//anular el egreso y detalles y formas de pagos
	$anular_encabezado_egreso = mysqli_query($con, "UPDATE ingresos_egresos SET nombre_ing_egr='ANULADO', detalle_adicional='ANULADO', valor_ing_egr=0, estado='ANULADO' WHERE codigo_documento = '" . $codigo_documento . "' ");
	$anular_detalle_egreso = mysqli_query($con, "DELETE FROM detalle_ingresos_egresos WHERE codigo_documento = '" . $codigo_documento . "'");
	if ($anular_detalle_egreso && $anular_encabezado_egreso) {
		echo "<script>
				$.notify('Egreso anulado','success')
				</script>";
	} else {
		echo "<script>
				$.notify('Lo siento, algo salio mal, intente nuevamente','error')
				</script>";
	}
}


//PARA BUSCAR LOS EGRESOS
if ($action == 'egresos') {
	// escaping, additionally removing everything that could be (html/javascript-) code
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
	$egreso = mysqli_real_escape_string($con, (strip_tags($_REQUEST['egreso'], ENT_QUOTES)));
	$estado_egreso = mysqli_real_escape_string($con, (strip_tags($_REQUEST['estado_egreso'], ENT_QUOTES)));
	$anio_egreso = mysqli_real_escape_string($con, (strip_tags($_REQUEST['anio_egreso'], ENT_QUOTES)));
	$mes_egreso = mysqli_real_escape_string($con, (strip_tags($_REQUEST['mes_egreso'], ENT_QUOTES)));

	if (empty($estado_egreso)) {
		$opciones_estado_egreso = "";
	} else {
		$opciones_estado_egreso = " and estado = '" . $estado_egreso . "' ";
	}
	if (empty($anio_egreso)) {
		$opciones_anio_egreso = "";
	} else {
		$opciones_anio_egreso = " and year(fecha_ing_egr) = '" . $anio_egreso . "' ";
	}
	if (empty($mes_egreso)) {
		$opciones_mes_agreso = "";
	} else {
		$opciones_mes_agreso = " and month(fecha_ing_egr) = '" . $mes_egreso . "' ";
	}

	$aColumns = array('nombre_ing_egr', 'numero_ing_egr', 'detalle_adicional', 'fecha_ing_egr', 'valor_ing_egr'); //Columnas de busqueda
	$sTable = "ingresos_egresos";
	$sWhere = "WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_ing_egr='EGRESO' $opciones_estado_egreso $opciones_anio_egreso $opciones_mes_agreso";

	$text_buscar = explode(' ', $egreso);
	$like = "";
	for ($i = 0; $i < count($text_buscar); $i++) {
		$like .= "%" . $text_buscar[$i];
	}
	if ($_GET['egreso'] != "") {
		$sWhere = "WHERE (ruc_empresa = '" . $ruc_empresa . "' and tipo_ing_egr='EGRESO' $opciones_estado_egreso $opciones_anio_egreso $opciones_mes_agreso AND ";

		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' and ruc_empresa = '" . $ruc_empresa . "' and tipo_ing_egr='EGRESO' $opciones_estado_egreso $opciones_anio_egreso $opciones_mes_agreso OR ";
		}

		$sWhere = substr_replace($sWhere, "AND ruc_empresa = '" . $ruc_empresa . "' and tipo_ing_egr='EGRESO' $opciones_estado_egreso $opciones_anio_egreso $opciones_mes_agreso ", -3);
		$sWhere .= ')';
	}
	$sWhere .= "order by $ordenado $por";
	include("../ajax/pagination.php"); //include pagination file
	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 20; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../egresos.php';
	//main query to fetch the data
	$sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {
?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("numero_ing_egr");'>Número</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_ing_egr");'>Fecha</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_ing_egr");'>Pagado a</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("detalle_adicional");'>Detalle</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("estado");'>Estado</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("valor_ing_egr");'>Total</button></th>
						<th class='text-right'>Opciones</th>

					</tr>
					<?php

					while ($row = mysqli_fetch_array($query)) {
						$id_egreso = $row['id_ing_egr'];
						$codigo_documento = $row['codigo_documento'];
						$fecha_egreso = $row['fecha_ing_egr'];
						$detalle_adicional = $row['detalle_adicional'];
						$nombre_egreso = $row['nombre_ing_egr'];
						$numero_egreso = $row['numero_ing_egr'];
						$valor_egreso = $row['valor_ing_egr'];
						$estado = $row['estado'];
						$id_proveedor = $row['id_cli_pro'];
						$buscar_mail_proveedor = mysqli_query($con, "SELECT * FROM proveedores WHERE id_proveedor = '" . $id_proveedor . "' ");
						$row_mail_proveedor = mysqli_fetch_array($buscar_mail_proveedor);
						$mail_proveedor = empty($row_mail_proveedor['mail_proveedor']) ? "" : $row_mail_proveedor['mail_proveedor'];

						switch ($estado) {
							case "OK":
								$label_class_estado = 'label-success';
								break;
							case "ANULADO":
								$label_class_estado = 'label-danger';
								break;
						}

					?>
						<input type="hidden" value="<?php echo $page; ?>" id="pagina">
						<tr>
							<td class="text-center"><?php echo $numero_egreso; ?></td>
							<td class="text-center"><?php echo date("d/m/Y", strtotime($fecha_egreso)); ?></td>
							<td class='col-md-3'><?php echo strtoupper($nombre_egreso); ?></td>
							<td class='col-md-3'><?php echo strtoupper($detalle_adicional); ?></td>
							<td><span class="label <?php echo $label_class_estado; ?>"><?php echo $estado; ?></span></td>
							<td class='text-right'><?php echo number_format($valor_egreso, 2, '.', ''); ?></td>
							<td class='col-md-2'><span class="pull-right">
									<?php
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'egresos')['r'] == 1) {
									?>
										<a href="../pdf/pdf_egreso.php?action=egreso&codigo_documento=<?php echo $codigo_documento; ?>" class='btn btn-default btn-xs' title='Pdf' target="_blank">Pdf</a>
									<?php
									}
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'egresos')['r'] == 1 && $row['estado'] == 'OK') {
									?>
										<a class='btn btn-info btn-xs' title='Detalle del egreso' onclick="mostrar_detalle_egreso('<?php echo $codigo_documento; ?>')" data-toggle="modal" data-target="#detalle_egreso"><i class="glyphicon glyphicon-edit"></i> </a>
									<?php
									}
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'egresos')['d'] == 1) {
									?>
										<a class='btn btn-warning btn-xs' title='Anular egreso' onclick="anular_egreso('<?php echo $codigo_documento; ?>', '<?php echo $numero_egreso; ?>')"><i class="glyphicon glyphicon-erase"></i> </a>
									<?php
									}
									?>
								</span></td>

						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="9"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?></span></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	}
}

//para detalles de egresos
if ($action == 'detalle') {
	// escaping, additionally removing everything that could be (html/javascript-) code
	//$ordenado = mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
	//$por = mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
	$detegr = mysqli_real_escape_string($con, (strip_tags($_REQUEST['detegr'], ENT_QUOTES)));
	$aColumns = array('beneficiario_cliente', 'detalle_ing_egr', 'numero_ing_egr'); //Columnas de busqueda
	$sTable = "detalle_ingresos_egresos";
	$sWhere = "WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_documento='EGRESO' ";
	if ($_GET['detegr'] != "") {
		$sWhere = "WHERE (ruc_empresa = '" . $ruc_empresa . "' and tipo_documento='EGRESO' AND ";
		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $detegr . "%' and ruc_empresa = '" . $ruc_empresa . "' and tipo_documento='EGRESO' OR ";
		}
		$sWhere = substr_replace($sWhere, "AND ruc_empresa = '" . $ruc_empresa . "' and tipo_documento='EGRESO' ", -3);
		$sWhere .= ')';
	}
	$sWhere .= " order by numero_ing_egr desc";
	include("../ajax/pagination.php"); //include pagination file
	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 10; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../egresos.php';
	//main query to fetch the data
	$sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {
	?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th>Pagado a</th>
						<th>Número</th>
						<th>Valor</th>
						<th>Tipo</th>
						<th>Descripción</th>
						<th class='text-center'>Detalle</th>
					</tr>
					<?php

					while ($row = mysqli_fetch_array($query)) {
						$nombre_cliente = $row['beneficiario_cliente'];
						$numero_ingreso = $row['numero_ing_egr'];
						$tipo_ing_egr = $row['tipo_ing_egr'];
						$codigo_documento = $row['codigo_documento'];

						if (!is_numeric($tipo_ing_egr)) {
							$tipo_asiento = mysqli_query($con, "SELECT * FROM asientos_tipo WHERE codigo='" . $tipo_ing_egr . "' ");
							$row_asiento = mysqli_fetch_assoc($tipo_asiento);
							$transaccion = $row_asiento['tipo_asiento'];
						} else {
							$tipo_pago = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE id='" . $tipo_ing_egr . "' and tipo_opcion ='2' ");
							$row_tipo_pago = mysqli_fetch_assoc($tipo_pago);
							$transaccion = $row_tipo_pago['descripcion'];
						}
						$valor_ing_egr = number_format($row['valor_ing_egr'], 2, '.', '');
						$detalle = $row['detalle_ing_egr'];
					?>
						<tr>
							<td><?php echo $nombre_cliente; ?></td>
							<td><?php echo $numero_ingreso; ?></td>
							<td><?php echo $valor_ing_egr; ?></td>
							<td><?php echo $transaccion; ?></td>
							<td><?php echo $detalle; ?></td>
							<td>
								<a class='btn btn-info btn-xs' title='Detalle del egreso' onclick="mostrar_detalle_egreso('<?php echo $codigo_documento; ?>')" data-toggle="modal" data-target="#detalle_egreso"><i class="glyphicon glyphicon-list"></i> </a>
							</td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="9"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?></span></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	}
}

//para buscar los pagos en los egresos
if ($action == 'pagos_egresos') {
	// escaping, additionally removing everything that could be (html/javascript-) code
	$detpago = mysqli_real_escape_string($con, (strip_tags($_REQUEST['detpago'], ENT_QUOTES)));
	$aColumns = array('fecha_emision', 'numero_ing_egr', 'detalle_pago', 'cheque'); //Columnas de busqueda
	$sTable = "formas_pagos_ing_egr";
	$sWhere = "WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_documento='EGRESO' ";
	if ($_GET['detpago'] != "") {
		$sWhere = "WHERE (ruc_empresa = '" . $ruc_empresa . "' and tipo_documento='EGRESO' AND ";
		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $detpago . "%' and ruc_empresa = '" . $ruc_empresa . "' and tipo_documento='EGRESO' OR ";
		}
		$sWhere = substr_replace($sWhere, "AND ruc_empresa = '" . $ruc_empresa . "' and tipo_documento='EGRESO'", -3);
		$sWhere .= ')';
	}
	$sWhere .= " order by cheque desc";
	include("../ajax/pagination.php"); //include pagination file
	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 10; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../egresos.php';
	//main query to fetch the data
	$sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {
	?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th>Número egreso</th>
						<th>Forma de pago</th>
						<th>Cuenta bancaria</th>
						<th>Valor</th>
						<th>Cheque</th>
						<th>Estado pago</th>
						<th class='text-center'>Detalle</th>
					</tr>
					<?php

					while ($row = mysqli_fetch_array($query)) {
						$numero_egreso = $row['numero_ing_egr'];
						$codigo_forma_pago = $row['codigo_forma_pago'];
						$valor_forma_pago = $row['valor_forma_pago'];
						$id_cuenta = $row['id_cuenta'];
						$cheque = $row['cheque'];
						$estado_pago = $row['estado_pago'];
						$codigo_documento = $row['codigo_documento'];

						if ($id_cuenta > 0) {
							$cuentas = mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.id_cuenta ='" . $id_cuenta . "'");
							$row_cuenta = mysqli_fetch_array($cuentas);
							$cuenta_bancaria = strtoupper($row_cuenta['cuenta_bancaria']);
							$forma_pago = $row['detalle_pago'];
							switch ($forma_pago) {
								case "D":
									$tipo = 'Débito';
									break;
								case "C":
									$tipo = 'Cheque';
									break;
								case "T":
									$tipo = 'Transferencia';
									break;
							}
							$forma_pago = $tipo;
						}

						if ($codigo_forma_pago > 0) {
							$opciones_pagos = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE id ='" . $codigo_forma_pago . "'");
							$row_opciones_pagos = mysqli_fetch_array($opciones_pagos);
							$forma_pago = strtoupper($row_opciones_pagos['descripcion']);
							$cuenta_bancaria = "";
						}
						$valor_forma_pago =  number_format($row['valor_forma_pago'], 2, '.', '');
					?>
						<tr>

							<td><?php echo $numero_egreso; ?></td>
							<td><?php echo $forma_pago; ?></td>
							<td><?php echo $cuenta_bancaria; ?></td>
							<td><?php echo number_format($valor_forma_pago, 2, '.', ''); ?></td>
							<td><?php echo $cheque; ?></td>
							<td><?php echo $estado_pago; ?></td>
							<td>
								<a class='btn btn-info btn-xs' title='Detalle del egreso' onclick="mostrar_detalle_egreso('<?php echo $codigo_documento; ?>')" data-toggle="modal" data-target="#detalle_egreso"><i class="glyphicon glyphicon-list"></i> </a>
							</td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="9"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?></span></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	}
}

//para buscar los pagos en los egresos
if ($action == 'detalle_cheques') {
	$ordenado = $_GET['ordenado_ch'];
	$por = $_GET['por_ch'];
	$id_cuenta = $_GET['cuenta_ch'];
	$detcheque = mysqli_real_escape_string($con, (strip_tags($_REQUEST['detcheque'], ENT_QUOTES)));
	$aColumns = array('for_pag.numero_ing_egr', 'for_pag.cheque', 'nombre_ing_egr', 'for_pag.fecha_emision'); //Columnas de busqueda
	$sTable = "formas_pagos_ing_egr as for_pag LEFT JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento = for_pag.codigo_documento ";
	$sWhere = "WHERE for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='EGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='C' ";
	if ($_GET['detcheque'] != "") {
		$sWhere = "WHERE for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='EGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='C' AND ";
		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $detcheque . "%' and for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='EGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='C' OR ";
		}
		$sWhere = substr_replace($sWhere, "AND for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='EGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='C'", -3);
		//$sWhere .= '';
	}
	$sWhere .= " order by " . $ordenado . " " . $por; //for_pag.cheque desc	
	include("../ajax/pagination.php"); //include pagination file
	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 10; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../egresos.php';
	//main query to fetch the data
	$sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {
	?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("for_pag.cheque");'>Número Cheque</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("for_pag.fecha_emision");'>Fecha emisión</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("ing_egr.nombre_ing_egr");'>Beneficiario</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("for_pag.fecha_entrega");'>Fecha cobro</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("for_pag.fecha_pago");'>Fecha cheque</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("for_pag.id_cuenta");'>Cuenta bancaria</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("for_pag.valor_forma_pago");'>Valor</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("for_pag.estado_pago");'>Estado cheque</button></th>
						<th class="text-right">Imprimir</th>
						<th class="text-center">Detalle</th>
					</tr>
					<?php

					while ($row = mysqli_fetch_array($query)) {
						$codigo_documento = $row['codigo_documento'];
						$id_forma_pago = $row['id_fp'];
						$id_beneficiario = $row['id_cli_pro'];
						$numero_cheque = $row['cheque'];
						$estado_pago = $row['estado_pago'];
						if ($estado_pago == 'ENTREGAR') {
							$fecha_entrega = "PENDIENTE";
						} else {
							$fecha_entrega = date("d-m-Y", strtotime($row['fecha_entrega']));
						}
						$beneficiario = $row['nombre_ing_egr'];
						$fecha_emision = $row['fecha_emision'];
						$fecha_pago = date("d-m-Y", strtotime($row['fecha_pago']));
						$valor_forma_pago = $row['valor_forma_pago'];
						$id_cuenta = $row['id_cuenta'];
						$cheque = $row['cheque'];
						$forma_pago = $row['codigo_forma_pago'];
						//para buscar el detalle de la cuenta bancaria
						$sql_cuenta_bancaria = "SELECT * FROM cuentas_bancarias where id_cuenta='" . $id_cuenta . "' ";
						$respuesta_cuenta_bancaria = mysqli_query($con, $sql_cuenta_bancaria);
						$row_cuenta_bancaria = mysqli_fetch_array($respuesta_cuenta_bancaria);
						$numero_cuenta = $row_cuenta_bancaria['numero_cuenta'];
						$tipo_cuenta = $row_cuenta_bancaria['id_tipo_cuenta'];
						$id_banco = $row_cuenta_bancaria['id_banco'];

						//para buscar el banco
						$sql_bancos = "SELECT * FROM bancos_ecuador where id_bancos= '" . $id_banco . "'";
						$respuesta_bancos = mysqli_query($con, $sql_bancos);
						$row_banco = mysqli_fetch_array($respuesta_bancos);
						$nombre_banco = $row_banco['nombre_banco'];

						switch ($tipo_cuenta) {
							case 1:
								$tipo_cuenta_pago = 'AHORROS';
								break;
							case 2:
								$tipo_cuenta_pago = 'CORRIENTE';
								break;
							case 3:
								$tipo_cuenta_pago = 'VIRTUAL';
								break;
							case 4:
								$tipo_cuenta_pago = 'TARJETA';
							default;
								$tipo_cuenta_pago = '';
						}
						$cuenta_bancaria = $nombre_banco . "-" . $tipo_cuenta_pago . "-" . $numero_cuenta;
					?>
						<tr>
							<input type="hidden" value="<?php echo $page; ?>" id="pagina">
							<input type="hidden" id="fecha_entrega_actual_cheque<?php echo $id_forma_pago; ?>" value="<?php echo $fecha_entrega; ?>">
							<input type="hidden" id="estado_actual_cheque<?php echo $id_forma_pago; ?>" value="<?php echo $estado_pago; ?>">
							<!-- <input type="hidden" id="nombre_actual_cheque<?php echo $id_forma_pago; ?>" value="<?php echo $beneficiario; ?>">
							<input type="hidden" id="id_beneficiario_actual<?php echo $id_forma_pago; ?>" value="<?php echo $id_beneficiario; ?>"> -->
							<input type="hidden" id="codigo_documento<?php echo $id_forma_pago; ?>" value="<?php echo $codigo_documento; ?>">
							<!-- <input type="hidden" id="id_beneficiario_final"> -->
							<td><?php echo $numero_cheque; ?></td>
							<td><?php echo date("d/m/Y", strtotime($fecha_emision)); ?></td>
							<td class="col-xs-3"><?php echo $beneficiario; ?></td>
							<!-- <td class="col-xs-3"><textarea id="beneficiario_final_cheque<?php echo $id_forma_pago ?>" class="form-control text-center" title="Busque un nombre ya registrado como proveedor" onkeyup="buscar_beneficiarios('<?php echo $id_forma_pago ?>');" onchange="modificar_beneficiario('<?php echo $id_forma_pago ?>')"><?php echo $beneficiario ?></textarea></td> -->
							<td class="col-xs-2">
								<input onmousedown="formatFechaEntregaCheque('<?php echo $id_forma_pago ?>')" id="fecha_entrega_cheque<?php echo $id_forma_pago ?>" class="form-control text-center" value="<?php echo $fecha_entrega ?>" onchange="modificar_fecha_entrega_cheque('<?php echo $id_forma_pago ?>')" <?php if ($estado_pago == 'ENTREGAR') {
																																																																														echo "readonly";
																																																																													} ?>>
							</td>
							<td class="col-xs-2"><?php echo $fecha_pago; ?></td>
							<td class="col-xs-2"><?php echo $cuenta_bancaria; ?></td>
							<td><?php echo number_format($valor_forma_pago, 2, '.', ''); ?></td>
							<td class="col-xs-2">
								<select class="form-control" name="estado_cheque" id="estado_cheque<?php echo $id_forma_pago ?>" onchange="modificar_estado_cheque('<?php echo $id_forma_pago ?>')" <?php if ($estado_pago == 'ANULADO') {
																																																		echo "disabled";
																																																	} ?>>
									<?php
									$estados_pagos = array("POR COBRAR" => "POR COBRAR", "ANULADO" => "ANULADO", "ENTREGAR" => "ENTREGAR", "PAGADO" => "PAGADO");
									foreach ($estados_pagos as $estado) {
										if ($estado == $estado_pago) {
									?>
											<option value="<?php echo $estado_pago ?>" selected><?php echo $estado_pago ?> </option>
										<?php
										} else {
										?>
											<option value="<?php echo $estado ?>"><?php echo $estado ?> </option>
									<?php
										}
									}
									?>
								</select>
							</td>
							<td class='col-md-2'><span class="pull-right">
									<a href="../pdf/pdf_cheque.php?action=cheque&codigo_documento=<?php echo $id_forma_pago; ?>" class='btn btn-default btn-xs' title='Imprimir' target="_blank"><i class="glyphicon glyphicon-print"></i></a>
								</span></td>
							<td>
								<a class='btn btn-info btn-xs' title='Detalle del egreso' onclick="mostrar_detalle_egreso('<?php echo $codigo_documento; ?>')" data-toggle="modal" data-target="#detalle_egreso"><i class="glyphicon glyphicon-list"></i> </a>
							</td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="10"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?></span></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	}
}

//para buscar las transferencias en los egresos
if ($action == 'detalle_transferencias') {
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado_tr'], ENT_QUOTES)));
	$id_cuenta = mysqli_real_escape_string($con, (strip_tags($_GET['cuenta_tr'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por_tr'], ENT_QUOTES)));
	$dettransferencia = mysqli_real_escape_string($con, (strip_tags($_REQUEST['dettransferencia'], ENT_QUOTES)));
	$aColumns = array('for_pag.numero_ing_egr', 'ing_egr.nombre_ing_egr', 'for_pag.fecha_emision', 'for_pag.fecha_pago', 'ing_egr.numero_ing_egr'); //Columnas de busqueda
	$sTable = "formas_pagos_ing_egr as for_pag LEFT JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento = for_pag.codigo_documento ";
	$sWhere = "WHERE for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='EGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='T' ";
	if ($_GET['dettransferencia'] != "") {
		$sWhere = "WHERE for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='EGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='T' AND ";
		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $dettransferencia . "%' and for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='EGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='T' OR ";
		}
		$sWhere = substr_replace($sWhere, "AND for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='EGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='T'", -3);
		//$sWhere .= '';
	}
	$sWhere .= " order by " . $ordenado . " " . $por; //for_pag.cheque desc	
	include("../ajax/pagination.php"); //include pagination file
	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 10; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../egresos.php';
	//main query to fetch the data
	$sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {
	?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("for_pag.fecha_emision");'>Fecha emisión</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Egreso</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("for_pag.fecha_entrega");'>Fecha pago</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("ing_egr.nombre_ing_egr");'>Beneficiario</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("for_pag.valor_forma_pago");'>Valor</button></th>
						<th class="text-center">Detalle</th>
					</tr>
					<?php

					while ($row = mysqli_fetch_array($query)) {
						$codigo_documento = $row['codigo_documento'];
						$id_forma_pago = $row['id_fp'];
						$id_beneficiario = $row['id_cli_pro'];
						$fecha_entrega = date("d-m-Y", strtotime($row['fecha_entrega']));
						$beneficiario = $row['nombre_ing_egr'];
						$fecha_emision = $row['fecha_emision'];
						$fecha_pago = date("d-m-Y", strtotime($row['fecha_pago']));
						$valor_forma_pago = $row['valor_forma_pago'];
						$numero_ing_egr = $row['numero_ing_egr'];
					?>
						<tr>
							<input type="hidden" value="<?php echo $page; ?>" id="pagina">
							<input type="hidden" id="fecha_entrega_actual_transferencia_pago<?php echo $id_forma_pago; ?>" value="<?php echo $fecha_pago; ?>">
							<input type="hidden" id="codigo_documento_pago<?php echo $id_forma_pago; ?>" value="<?php echo $codigo_documento; ?>">
							<td><?php echo date("d-m-Y", strtotime($fecha_emision)); ?></td>
							<td><?php echo $numero_ing_egr; ?></td>
							<td class="col-xs-2">
								<input onmousedown="formatFechaEntregaTransferenciaPago('<?php echo $id_forma_pago ?>')" id="fecha_entrega_transferencia_pago<?php echo $id_forma_pago ?>" class="form-control text-center" value="<?php echo $fecha_pago ?>" onchange="modificar_fecha_entrega_transferencia_pago('<?php echo $id_forma_pago ?>')">
							</td>
							<td class="col-xs-3"><?php echo $beneficiario; ?></td>
							<td><?php echo number_format($valor_forma_pago, 2, '.', ''); ?></td>
							<td class="text-center">
								<a class='btn btn-info btn-xs' title='Detalle del egreso' onclick="mostrar_detalle_egreso('<?php echo $codigo_documento; ?>')" data-toggle="modal" data-target="#detalle_egreso"><i class="glyphicon glyphicon-list"></i> </a>
							</td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="6"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?></span></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	}
}


//para buscar los debitos en los egresos
if ($action == 'detalle_debitos') {
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado_de'], ENT_QUOTES)));
	$id_cuenta = mysqli_real_escape_string($con, (strip_tags($_GET['cuenta_de'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por_de'], ENT_QUOTES)));
	$detdebito = mysqli_real_escape_string($con, (strip_tags($_REQUEST['detdebito'], ENT_QUOTES)));
	$aColumns = array('for_pag.numero_ing_egr', 'ing_egr.nombre_ing_egr', 'for_pag.fecha_emision', 'for_pag.fecha_pago', 'ing_egr.numero_ing_egr'); //Columnas de busqueda
	$sTable = "formas_pagos_ing_egr as for_pag LEFT JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento = for_pag.codigo_documento ";
	$sWhere = "WHERE for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='EGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='D' ";
	if ($_GET['detdebito'] != "") {
		$sWhere = "WHERE for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='EGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='D' AND ";
		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $detdebito . "%' and for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='EGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='D' OR ";
		}
		$sWhere = substr_replace($sWhere, "AND for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='EGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='D'", -3);
		//$sWhere .= '';
	}
	$sWhere .= " order by " . $ordenado . " " . $por; //for_pag.cheque desc	
	include("../ajax/pagination.php"); //include pagination file
	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 10; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../egresos.php';
	//main query to fetch the data
	$sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {
	?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("for_pag.fecha_emision");'>Fecha emisión</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Egreso</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("for_pag.fecha_entrega");'>Fecha pago</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("ing_egr.nombre_ing_egr");'>Beneficiario</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("for_pag.valor_forma_pago");'>Valor</button></th>
						<th class="text-center">Detalle</th>
					</tr>
					<?php

					while ($row = mysqli_fetch_array($query)) {
						$codigo_documento = $row['codigo_documento'];
						$id_forma_pago = $row['id_fp'];
						$id_beneficiario = $row['id_cli_pro'];
						$fecha_entrega = date("d-m-Y", strtotime($row['fecha_entrega']));
						$beneficiario = $row['nombre_ing_egr'];
						$fecha_emision = $row['fecha_emision'];
						$fecha_pago = date("d-m-Y", strtotime($row['fecha_pago']));
						$valor_forma_pago = $row['valor_forma_pago'];
						$numero_ing_egr = $row['numero_ing_egr'];
					?>
						<tr>
							<input type="hidden" value="<?php echo $page; ?>" id="pagina">
							<input type="hidden" id="fecha_entrega_actual_debito_pago<?php echo $id_forma_pago; ?>" value="<?php echo $fecha_pago; ?>">
							<input type="hidden" id="codigo_documento_pago<?php echo $id_forma_pago; ?>" value="<?php echo $codigo_documento; ?>">
							<td><?php echo date("d-m-Y", strtotime($fecha_emision)); ?></td>
							<td><?php echo $numero_ing_egr; ?></td>
							<td class="col-xs-2">
								<input onmousedown="formatFechaEntregaDebitoPago('<?php echo $id_forma_pago ?>')" id="fecha_entrega_debito_pago<?php echo $id_forma_pago ?>" class="form-control text-center" value="<?php echo $fecha_pago ?>" onchange="modificar_fecha_entrega_debito_pago('<?php echo $id_forma_pago ?>')">
							</td>
							<td class="col-xs-3"><?php echo $beneficiario; ?></td>
							<td><?php echo number_format($valor_forma_pago, 2, '.', ''); ?></td>
							<td class="text-center">
								<a class='btn btn-info btn-xs' title='Detalle del egreso' onclick="mostrar_detalle_egreso('<?php echo $codigo_documento; ?>')" data-toggle="modal" data-target="#detalle_egreso"><i class="glyphicon glyphicon-list"></i> </a>
							</td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="6"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?></span></td>
					</tr>
				</table>
			</div>
		</div>
<?php
	}
}
?>