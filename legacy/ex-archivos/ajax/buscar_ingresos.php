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
$fecha_registro = date("Y-m-d H:i:s");

//PARA BUSCAR LOS INGRESOS
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';


if ($action == 'actualizar_ingreso') {
	$codigo_documento = $_POST['codigo_unico_ingreso'];
	$id_cliente = $_POST['id_cliente_editar_ingreso'];
	$nombre_cliente = strClean($_POST['cliente_editar_ingreso']);
	$fecha_ingreso = date("Y/m/d", strtotime($_POST['fecha_editar_ingreso']));
	$observaciones = strClean($_POST['observaciones_editar_ingreso']);
	$total_detalles = 0;
	$total_pagos = 0;
	$total_tipo = 0;
	$suma_detalle = 0;
	$suma_formas_pagos = 0;

	if ($id_cliente == 0 || empty($id_cliente)) {
		echo "<script>
		$.notify('Vuelva a seleccionar el cliente o recibido de','error')
		</script>";
		exit;
	}

	$datos_encabezado = mysqli_query($con, "SELECT * FROM ingresos_egresos WHERE codigo_documento = '" . $codigo_documento . "' ");
	$row_encabezado = mysqli_fetch_array($datos_encabezado);
	if (periodosContables($con, $fecha_ingreso, $ruc_empresa) == true) {
		$periodo = date('m-Y', strtotime($fecha_ingreso));
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
	//total del ingreso
	$sql_total_detalle = mysqli_query($con, "SELECT round(sum(valor_ing_egr),2) as total_ingreso FROM ingresos_egresos 
	WHERE codigo_documento='" . $codigo_documento . "' group by codigo_documento ");
	$row_total_detalle = mysqli_fetch_assoc($sql_total_detalle);
	$total_ingreso = $row_total_detalle['total_ingreso'];

	//para los detalles del ingreso
	$id_detalle_ingreso = isset($_POST['id_detalle_ingreso']) ? $_POST['id_detalle_ingreso'] : "";
	if (!empty($id_detalle_ingreso)) {
		$tipo_ingreso = $_POST['tipo_ingreso'];
		$detalle_ingreso = $_POST['detalle_editar_ingreso'];
		$valor_detalle_ingreso = $_POST['valor_detalle_editar_ingreso'];
		$valor_detalle_ingresos_no_editables = array_sum($_POST['valor_detalle_ingresos_no_editables']);
		$suma_de_detalles_de_ingresos = (isset($_POST['valor_detalle_editar_ingreso']) ? array_sum($_POST['valor_detalle_editar_ingreso']) : 0) +
			(isset($_POST['valor_detalle_ingresos_no_editables']) ? array_sum($_POST['valor_detalle_ingresos_no_editables']) : 0);
		if (round($total_ingreso, 2) == round($suma_de_detalles_de_ingresos, 2)) {
			$suma_detalle = 0;
		} else {
			$suma_detalle = 1;
		}
		//para ver si falta completar las filas de los detalles del ingreso
		foreach ($id_detalle_ingreso as $valor_detalle) {
			if ($valor_detalle > 0 && !empty($detalle_ingreso[$valor_detalle])) {
				$total_detalles = 0;
			} else {
				$total_detalles = 1;
			}
		}
	}

	//para las formas de pagos del ingreso
	$id_forma_pago_ingreso = isset($_POST['id_forma_pago_ingreso']) ? $_POST['id_forma_pago_ingreso'] : "";
	if (!empty($id_forma_pago_ingreso)) {
		$forma_pago_ingreso = $_POST['forma_pago_editar_ingreso'];
		$tipo_pago_ingreso = $_POST['tipo_editar_forma_pago'];
		$valor_pago_ingreso = $_POST['valor_cobro_editar_ingreso'];
		if (round($total_ingreso, 2) != round(array_sum($_POST['valor_cobro_editar_ingreso']), 2)) {
			$suma_formas_pagos = 1;
		} else {
			$suma_formas_pagos = 0;
		}

		//para ver si falta completar las filas de los pagos del ingreso
		foreach ($id_forma_pago_ingreso as $id_forma_pago) {
			if (substr($forma_pago_ingreso[$id_forma_pago], 0, 1) == 1) { //no es cuenta bancaria
				if ($valor_pago_ingreso[$id_forma_pago] > 0) {
					$total_pagos = 0;
				} else {
					$total_pagos = 1;
				}
			}

			if (
				substr($forma_pago_ingreso[$id_forma_pago], 0, 1) == 2
				&& $tipo_pago_ingreso[$id_forma_pago] == "0"
			) {
				$total_tipo = 1;
			} else {
				$total_tipo = 0;
			}

			if ($valor_pago_ingreso[$id_forma_pago] <= 0 || empty($valor_pago_ingreso[$id_forma_pago])) {
				$total_pagos = 1;
			} else {
				$total_pagos = 0;
			}
		}
	}


	if ($total_detalles > 0) {
		echo "<script>
		$.notify('Completar detalle de ingreso','error')
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
		$.notify('La suma de los detalles " . number_format($suma_de_detalles_de_ingresos, 2, '.', '') . " no coincide con el total del ingreso " . number_format($total_ingreso, 2, '.', '') . "','error')
		</script>";
	} else if ($suma_formas_pagos > 0) {
		echo "<script>
		$.notify('La suma de los valores de cobros " . number_format(array_sum($_POST['valor_cobro_editar_ingreso']), 2, '.', '') . " no coincide con el total del ingreso " . $total_ingreso . "','error')
		</script>";
	} else {
		//para guardar los detalles
		if (!empty($id_detalle_ingreso)) {
			foreach ($id_detalle_ingreso as $valor_detalle) {
				$actualiza_detalle = mysqli_query($con, "UPDATE detalle_ingresos_egresos SET tipo_ing_egr='" . $tipo_ingreso[$valor_detalle] . "', detalle_ing_egr='" . $detalle_ingreso[$valor_detalle] . "', valor_ing_egr='" . number_format($valor_detalle_ingreso[$valor_detalle], 2, '.', '') . "' WHERE id_detalle_ing_egr='" . $valor_detalle . "' ");
			}
		}
		//para guardar los pagos
		if (!empty($id_forma_pago_ingreso)) {
			foreach ($id_forma_pago_ingreso as $id_forma_pago) {
				if (substr($forma_pago_ingreso[$id_forma_pago], 0, 1) == 1) { //cuando no es cuenta bancaria
					$actualiza_pagos = mysqli_query($con, "UPDATE formas_pagos_ing_egr SET codigo_forma_pago='" . substr($forma_pago_ingreso[$id_forma_pago], 1) . "', id_cuenta='0', detalle_pago='', fecha_emision='" . $fecha_ingreso . "' , fecha_entrega='" . $fecha_ingreso . "', fecha_pago='" . $fecha_ingreso . "', valor_forma_pago='" . number_format($valor_pago_ingreso[$id_forma_pago], 2, '.', '') . "', cheque='0'  WHERE id_fp='" . $id_forma_pago . "' ");
				}
				if (substr($forma_pago_ingreso[$id_forma_pago], 0, 1) == 2 && $tipo_pago_ingreso[$id_forma_pago] == 'D') { //cuando si es cuenta bancaria y deposito
					$actualiza_pagos = mysqli_query($con, "UPDATE formas_pagos_ing_egr SET codigo_forma_pago='0', id_cuenta='" . substr($forma_pago_ingreso[$id_forma_pago], 1) . "', detalle_pago='" . $tipo_pago_ingreso[$id_forma_pago] . "', fecha_emision='" . $fecha_ingreso . "' , fecha_entrega='" . $fecha_ingreso . "', fecha_pago='" . $fecha_ingreso . "', valor_forma_pago='" . number_format($valor_pago_ingreso[$id_forma_pago], 2, '.', '') . "', cheque='0'  WHERE id_fp='" . $id_forma_pago . "' ");
				}
				if (substr($forma_pago_ingreso[$id_forma_pago], 0, 1) == 2 && $tipo_pago_ingreso[$id_forma_pago] == 'T') { //cuando si es cuenta bancaria y transferencia
					$actualiza_pagos = mysqli_query($con, "UPDATE formas_pagos_ing_egr SET codigo_forma_pago='0', id_cuenta='" . substr($forma_pago_ingreso[$id_forma_pago], 1) . "', detalle_pago='" . $tipo_pago_ingreso[$id_forma_pago] . "', fecha_emision='" . $fecha_ingreso . "' , fecha_entrega='" . $fecha_ingreso . "', fecha_pago='" . $fecha_ingreso . "', valor_forma_pago='" . number_format($valor_pago_ingreso[$id_forma_pago], 2, '.', '') . "', cheque='0'  WHERE id_fp='" . $id_forma_pago . "' ");
				}
			}
		}

		//guarda encabezado
		$actualiza_encabezado = mysqli_query($con, "UPDATE ingresos_egresos SET nombre_ing_egr='" . $nombre_cliente . "', id_cli_pro='" . $id_cliente . "', fecha_ing_egr='" . $fecha_ingreso . "', detalle_adicional='" . $observaciones . "'	WHERE codigo_documento='" . $codigo_documento . "' ");
		//$contabilizacion->documentosIngresos($con, $ruc_empresa, $fecha_ingreso, $fecha_ingreso);
		//$guardar_asientos_contables_generados = $contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'ingresos');
		if ($actualiza_encabezado) {
			echo "<script>
					$.notify('Ingreso actualizado','success')
					</script>";
		} else {
			echo "<script>
				$.notify('Lo siento, algo salio mal, intente nuevamente','error')
				</script>";
		}
	}
}


//para anular un ingreso
if ($action == 'anular_ingreso') {
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
				$.notify('Ingreso anulado','success')
				</script>";
	} else {
		echo "<script>
				$.notify('Lo siento, algo salio mal, intente nuevamente','error')
				</script>";
	}
}

//para buscar los ingresos
if ($action == 'ingresos') {
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
	$ing = mysqli_real_escape_string($con, (strip_tags($_REQUEST['ingreso'], ENT_QUOTES)));
	$estado_ingreso = mysqli_real_escape_string($con, (strip_tags($_REQUEST['estado_ingreso'], ENT_QUOTES)));
	$anio_ingreso = mysqli_real_escape_string($con, (strip_tags($_REQUEST['anio_ingreso'], ENT_QUOTES)));
	$mes_ingreso = mysqli_real_escape_string($con, (strip_tags($_REQUEST['mes_ingreso'], ENT_QUOTES)));

	if (empty($estado_ingreso)) {
		$opciones_estado_ingreso = "";
	} else {
		$opciones_estado_ingreso = " and estado = '" . $estado_ingreso . "' ";
	}
	if (empty($anio_ingreso)) {
		$opciones_anio_ingreso = "";
	} else {
		$opciones_anio_ingreso = " and year(fecha_ing_egr) = '" . $anio_ingreso . "' ";
	}
	if (empty($mes_ingreso)) {
		$opciones_mes_ingreso = "";
	} else {
		$opciones_mes_ingreso = " and month(fecha_ing_egr) = '" . $mes_ingreso . "' ";
	}

	$aColumns = array('nombre_ing_egr', 'numero_ing_egr', 'detalle_adicional', 'fecha_ing_egr', 'valor_ing_egr'); //Columnas de busqueda
	$sTable = "ingresos_egresos";
	$sWhere = "WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_ing_egr='INGRESO' $opciones_estado_ingreso $opciones_anio_ingreso $opciones_mes_ingreso ";
	$text_buscar = explode(' ', $ing);
	$like = "";
	for ($i = 0; $i < count($text_buscar); $i++) {
		$like .= "%" . $text_buscar[$i];
	}
	if ($_GET['ingreso'] != "") {
		$sWhere = "WHERE (ruc_empresa = '" . $ruc_empresa . "' and tipo_ing_egr='INGRESO' $opciones_estado_ingreso $opciones_anio_ingreso $opciones_mes_ingreso AND ";

		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' and ruc_empresa = '" . $ruc_empresa . "' and tipo_ing_egr='INGRESO' $opciones_estado_ingreso $opciones_anio_ingreso $opciones_mes_ingreso OR ";
		}

		$sWhere = substr_replace($sWhere, "AND ruc_empresa = '" . $ruc_empresa . "' and tipo_ing_egr='INGRESO' $opciones_estado_ingreso $opciones_anio_ingreso $opciones_mes_ingreso ", -3);
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
	$reload = '../ingresos.php';
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
						<th style="padding: 0px;" class='col-md-3'><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_ing_egr");'>Recibido de</button></th>
						<th style="padding: 0px;" class='col-md-3'><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("detalle_adicional");'>Detalle</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("estado");'>Estado</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("valor_ing_egr");'>Total</button></th>
						<th class='text-right'>Opciones</th>

					</tr>
					<?php

					while ($row = mysqli_fetch_array($query)) {
						$codigo_unico = $row['codigo_documento'];
						$id_ingreso = $row['id_ing_egr'];
						$fecha_ingreso = $row['fecha_ing_egr'];
						$nombre_ingreso = $row['nombre_ing_egr'];
						$numero_ingreso = $row['numero_ing_egr'];
						$valor_ingreso = $row['valor_ing_egr'];
						$detalle = $row['detalle_adicional'];
						$estado = $row['estado'];
						switch ($estado) {
							case "OK":
								$label_class_estado = 'label-success';
								break;
							case "ANULADO":
								$label_class_estado = 'label-danger';
								break;
						}

						$busca_datos_cliente = mysqli_query($con, "SELECT * FROM clientes WHERE id = '" . $row['id_cli_pro'] . "' ");
						$row_cliente = mysqli_fetch_array($busca_datos_cliente);
						$email = isset($row_cliente['email']) ? $row_cliente['email'] : "";
					?>
						<input type="hidden" value="<?php echo $page; ?>" id="pagina">
						<tr>
							<td class="text-center"><?php echo $numero_ingreso; ?></td>
							<td class="text-center"><?php echo date("d/m/Y", strtotime($fecha_ingreso)); ?></td>
							<td class='col-md-3'><?php echo strtoupper($nombre_ingreso); ?></td>
							<td style="width:200px; white-space:normal; word-break:break-word;"><?php echo strtoupper($detalle); ?></td>
							<td><span class="label <?php echo $label_class_estado; ?>"><?php echo $estado; ?></span></td>
							<td class='text-right'><?php echo number_format($valor_ingreso, 2, '.', ''); ?></td>
							<td class='col-md-2'><span class="pull-right">
									<?php
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'ingresos')['r'] == 1) {
									?>
										<a title='Imprimir pdf' href="../pdf/pdf_ingreso.php?action=ingreso&codigo_unico=<?php echo $codigo_unico ?>" class='btn btn-default btn-xs' title='Pdf' target="_blank">Pdf</a>
									<?php
									}
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'ingresos')['r'] == 1) {
									?>
										<a class='btn btn-info btn-xs' title='Editar ingreso' onclick="mostrar_detalle_ingreso('<?php echo $codigo_unico; ?>')" data-toggle="modal" data-target="#detalle_ingreso"><i class="glyphicon glyphicon-edit"></i> </a>
										<a class="btn btn-info btn-xs" onclick="enviar_ingreso_mail('<?php echo $id_ingreso; ?>', '<?php echo $email; ?>');" title='Enviar mail' data-toggle="modal" data-target="#EnviarDocumentosMail"><i class="glyphicon glyphicon-envelope"></i> </a>
									<?php
									}
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'ingresos')['d'] == 1) {
									?>
										<a href="#" class='btn btn-danger btn-xs' title='Anular ingreso' onclick="anular_ingreso('<?php echo $codigo_unico; ?>', '<?php echo $numero_ingreso; ?>')"><i class="glyphicon glyphicon-erase"></i> </a>
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

//para detalles de ingresos
if ($action == 'detalle') {
	// escaping, additionally removing everything that could be (html/javascript-) code
	$deting = mysqli_real_escape_string($con, (strip_tags($_REQUEST['deting'], ENT_QUOTES)));
	$aColumns = array('beneficiario_cliente', 'detalle_ing_egr', 'numero_ing_egr'); //Columnas de busqueda
	$sTable = "detalle_ingresos_egresos";
	$sWhere = "WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_documento='INGRESO' ";
	if ($_GET['deting'] != "") {
		$sWhere = "WHERE (ruc_empresa = '" . $ruc_empresa . "' and tipo_documento='INGRESO' AND ";
		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $deting . "%' and ruc_empresa = '" . $ruc_empresa . "' and tipo_documento='INGRESO' OR ";
		}
		$sWhere = substr_replace($sWhere, "AND ruc_empresa = '" . $ruc_empresa . "' and tipo_documento='INGRESO' ", -3);
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
						<th>Recibido de</th>
						<th>Número</th>
						<th>Valor</th>
						<th>Tipo</th>
						<th>Descripción</th>
						<th>Detalle</th>
					</tr>
					<?php

					while ($row = mysqli_fetch_array($query)) {
						$codigo_documento = $row['codigo_documento'];
						$nombre_cliente = $row['beneficiario_cliente'];
						$numero_ingreso = $row['numero_ing_egr'];
						$valor_ing_egr = $row['valor_ing_egr'];
						$detalle_ing_egr = $row['detalle_ing_egr'];
						$tipo_ing_egr = $row['tipo_ing_egr'];
						$tipo_pago = mysqli_query($con, "SELECT * FROM tipo_ingreso_egreso WHERE codigo='" . $tipo_ing_egr . "' and aplica ='INGRESO' ");
						$row_tipo_pago = mysqli_fetch_assoc($tipo_pago);
						$transaccion = isset($row_tipo_pago['nombre']) ? $row_tipo_pago['nombre'] : "";
					?>
						<tr>
							<td><?php echo $nombre_cliente; ?></td>
							<td><?php echo $numero_ingreso; ?></td>
							<td><?php echo $valor_ing_egr; ?></td>
							<td><?php echo $transaccion; ?></td>
							<td><?php echo $detalle_ing_egr; ?></td>
							<td class="text-center">
								<a class='btn btn-info btn-xs' title='Detalle del ingreso' onclick="mostrar_detalle_ingreso('<?php echo $codigo_documento; ?>')" data-toggle="modal" data-target="#detalle_ingreso_egreso"><i class="glyphicon glyphicon-list"></i> </a>
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

//para buscar los pagos en los INgresos
if ($action == 'pagos_ingresos') {
	// escaping, additionally removing everything that could be (html/javascript-) code
	$detpago = mysqli_real_escape_string($con, (strip_tags($_REQUEST['detpago'], ENT_QUOTES)));
	$aColumns = array('fecha_emision', 'numero_ing_egr', 'detalle_pago', 'cheque'); //Columnas de busqueda
	$sTable = "formas_pagos_ing_egr";
	$sWhere = "WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_documento='INGRESO' ";
	if ($_GET['detpago'] != "") {
		$sWhere = "WHERE (ruc_empresa = '" . $ruc_empresa . "' and tipo_documento='INGRESO' AND ";
		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $detpago . "%' and ruc_empresa = '" . $ruc_empresa . "'' and tipo_documento='INGRESO' OR ";
		}
		$sWhere = substr_replace($sWhere, "AND ruc_empresa = '" . $ruc_empresa . "' and tipo_documento='INGRESO'", -3);
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
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../ingresos.php';
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
						<th>Número ingreso</th>
						<th>Forma de cobro</th>
						<th>Cuenta bancaria</th>
						<th>Valor</th>
						<th>Detalle</th>
					</tr>
					<?php

					while ($row = mysqli_fetch_array($query)) {
						$codigo_documento = $row['codigo_documento'];
						$numero_ingreso = $row['numero_ing_egr'];
						$codigo_forma_pago = $row['codigo_forma_pago'];
						$valor_forma_pago = $row['valor_forma_pago'];
						$id_cuenta = $row['id_cuenta'];
						$cheque = $row['cheque'];
						$estado_pago = $row['estado_pago'];
						if ($id_cuenta > 0) {
							$cuentas = mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.id_cuenta ='" . $id_cuenta . "'");
							$row_cuenta = mysqli_fetch_array($cuentas);
							$cuenta_bancaria = strtoupper($row_cuenta['cuenta_bancaria']);
							$forma_pago = $row['detalle_pago'];
							switch ($forma_pago) {
								case "D":
									$tipo = 'Depósito';
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

					?>
						<tr>

							<td><?php echo $numero_ingreso; ?></td>
							<td><?php echo $forma_pago; ?></td>
							<td><?php echo $cuenta_bancaria; ?></td>
							<td><?php echo number_format($valor_forma_pago, 2, '.', ''); ?></td>
							<td class="text-center">
								<a class='btn btn-info btn-xs' title='Detalle del ingreso' onclick="mostrar_detalle_ingreso('<?php echo $codigo_documento; ?>')" data-toggle="modal" data-target="#detalle_ingreso_egreso"><i class="glyphicon glyphicon-list"></i> </a>
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

//para actualizar la fecha de entrega de transferencia y depositos
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
	$actualiza_estado_fecha_pago = mysqli_query($con, "UPDATE formas_pagos_ing_egr SET fecha_pago='" . $nueva_fecha . "' WHERE id_fp='" . $id_registro . "' ");
	$actualiza_fecha_asiento = mysqli_query($con, "UPDATE encabezado_diario SET fecha_asiento ='" . $nueva_fecha . "' WHERE id_diario ='" . $id_registro_contable  . "' ");
	if ($actualiza_estado_fecha_pago && $actualiza_fecha_asiento) {
		echo "<script>
					$.notify('Actualizado','success')
					</script>";
	} else {
		echo "<script>
				$.notify('Lo siento, algo salio mal, intente nuevamente','error')
				</script>";
	}
}


//para buscar los pagos con treansferencias en los INgresos
if ($action == 'detalle_transferencias') {
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado_tr'], ENT_QUOTES)));
	$id_cuenta = mysqli_real_escape_string($con, (strip_tags($_GET['cuenta_tr'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por_tr'], ENT_QUOTES)));
	$dettransferencia = mysqli_real_escape_string($con, (strip_tags($_REQUEST['dettransferencia'], ENT_QUOTES)));
	$aColumns = array('for_pag.numero_ing_egr', 'ing_egr.nombre_ing_egr', 'for_pag.fecha_emision', 'for_pag.fecha_pago', 'ing_egr.numero_ing_egr'); //Columnas de busqueda
	$sTable = "formas_pagos_ing_egr as for_pag LEFT JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento = for_pag.codigo_documento ";
	$sWhere = "WHERE for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='INGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='T' ";
	if ($_GET['dettransferencia'] != "") {
		$sWhere = "WHERE for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='INGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='T' AND ";
		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $dettransferencia . "%' and for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='INGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='T' OR ";
		}
		$sWhere = substr_replace($sWhere, "AND for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='INGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='T'", -3);
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
	$reload = '../ingresos.php';
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
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Ingreso</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("for_pag.fecha_entrega");'>Fecha cobro</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("ing_egr.nombre_ing_egr");'>Recibido de</button></th>
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
							<input type="hidden" id="fecha_entrega_actual_transferencia_cobro<?php echo $id_forma_pago; ?>" value="<?php echo $fecha_pago; ?>">
							<input type="hidden" id="codigo_documento_cobro<?php echo $id_forma_pago; ?>" value="<?php echo $codigo_documento; ?>">
							<td><?php echo date("d-m-Y", strtotime($fecha_emision)); ?></td>
							<td><?php echo $numero_ing_egr; ?></td>
							<td class="col-xs-2">
								<input onmousedown="formatFechaEntregaTransferenciaCobro('<?php echo $id_forma_pago ?>')" id="fecha_entrega_transferencia_cobro<?php echo $id_forma_pago ?>" class="form-control text-center" value="<?php echo $fecha_pago ?>" onchange="modificar_fecha_entrega_transferencia_cobro('<?php echo $id_forma_pago ?>')">
							</td>
							<td class="col-xs-3"><?php echo $beneficiario; ?></td>
							<td><?php echo number_format($valor_forma_pago, 2, '.', ''); ?></td>
							<td class="text-center">
								<a class='btn btn-info btn-xs' title='Detalle del ingreso' onclick="mostrar_detalle_ingreso('<?php echo $codigo_documento; ?>')" data-toggle="modal" data-target="#detalle_ingreso_egreso"><i class="glyphicon glyphicon-list"></i> </a>
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


//para buscar los pagos con depositos en los INgresos
if ($action == 'detalle_depositos') {
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado_de'], ENT_QUOTES)));
	$id_cuenta = mysqli_real_escape_string($con, (strip_tags($_GET['cuenta_de'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por_de'], ENT_QUOTES)));
	$detdeposito = mysqli_real_escape_string($con, (strip_tags($_REQUEST['detdeposito'], ENT_QUOTES)));
	$aColumns = array('for_pag.numero_ing_egr', 'ing_egr.nombre_ing_egr', 'for_pag.fecha_emision', 'for_pag.fecha_pago', 'ing_egr.numero_ing_egr'); //Columnas de busqueda
	$sTable = "formas_pagos_ing_egr as for_pag LEFT JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento = for_pag.codigo_documento ";
	$sWhere = "WHERE for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='INGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='D' ";
	if ($_GET['detdeposito'] != "") {
		$sWhere = "WHERE for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='INGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='D' AND ";
		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $detdeposito . "%' and for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='INGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='D' OR ";
		}
		$sWhere = substr_replace($sWhere, "AND for_pag.ruc_empresa = '" . $ruc_empresa . "' and for_pag.tipo_documento='INGRESO' and for_pag.codigo_forma_pago='0' and for_pag.id_cuenta='" . $id_cuenta . "' and for_pag.detalle_pago='D'", -3);
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
	$reload = '../ingresos.php';
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
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Ingreso</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("for_pag.fecha_entrega");'>Fecha cobro</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar_ch("ing_egr.nombre_ing_egr");'>Recibido de</button></th>
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
							<input type="hidden" id="fecha_entrega_actual_deposito_cobro<?php echo $id_forma_pago; ?>" value="<?php echo $fecha_pago; ?>">
							<input type="hidden" id="codigo_documento_cobro<?php echo $id_forma_pago; ?>" value="<?php echo $codigo_documento; ?>">
							<td><?php echo date("d-m-Y", strtotime($fecha_emision)); ?></td>
							<td><?php echo $numero_ing_egr; ?></td>
							<td class="col-xs-2">
								<input onmousedown="formatFechaEntregaDepositoCobro('<?php echo $id_forma_pago ?>')" id="fecha_entrega_deposito_cobro<?php echo $id_forma_pago ?>" class="form-control text-center" value="<?php echo $fecha_pago ?>" onchange="modificar_fecha_entrega_deposito_cobro('<?php echo $id_forma_pago ?>')">
							</td>
							<td class="col-xs-3"><?php echo $beneficiario; ?></td>
							<td><?php echo number_format($valor_forma_pago, 2, '.', ''); ?></td>
							<td class="text-center">
								<a class='btn btn-info btn-xs' title='Detalle del ingreso' onclick="mostrar_detalle_ingreso('<?php echo $codigo_documento; ?>')" data-toggle="modal" data-target="#detalle_ingreso_egreso"><i class="glyphicon glyphicon-list"></i> </a>
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