<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//para eliminar un iten del egreso temporal
if ($action == 'eliminar_item_egreso') {
	if (isset($_GET['id'])) {
		$id_tmp = intval($_GET['id']);
		$delete = mysqli_query($con, "DELETE FROM ingresos_egresos_tmp WHERE id_tmp='" . $id_tmp . "'");
	}
}

//para eliminar un iten de formas de pago del egreso temporal 
if ($action == 'eliminar_pago_egreso') {
	$intid = $_GET['id_fp_tmp'];
	$arrData = $_SESSION['arrayFormaPagoEgreso'];
	for ($i = 0; $i < count($arrData); $i++) {
		if ($arrData[$i]['id'] == $intid) {
			unset($arrData[$i]);
			echo "<script>
            $.notify('Eliminado','error');
            </script>";
		}
	}
	sort($arrData); //para reordenar el array
	$_SESSION['arrayFormaPagoEgreso'] = $arrData;
}

//para agregar la forma de pago al pagos tmp
if ($action == 'forma_de_pago_egreso') {
	$forma_pago_egreso = $_POST['forma_pago_egreso'];
	$valor_pago_egreso = $_POST['valor_pago_egreso'];
	$numero_cheque_egreso = $_POST['numero_cheque_egreso'];
	$origen = $_POST['origen'];
	$tipo = $_POST['tipo'];
	$fecha_cobro_egreso = date('Y-m-d H:i:s', strtotime($_POST['fecha_cobro_egreso']));
	//para ver si el cheque ya esta agregado al tmp o egresos anteriores

	//para ver si has cheques en los pagos ya registrados con anterioridad
	if ($numero_cheque_egreso > 0) {
		$busca_cheque_registrado = mysqli_query($con, "SELECT * FROM formas_pagos_ing_egr WHERE tipo_documento='EGRESO' and id_cuenta='" . $forma_pago_egreso . "' and cheque='" . $numero_cheque_egreso . "'");
		$cheque_registrado = mysqli_num_rows($busca_cheque_registrado);
		if ($cheque_registrado > 0) {
			echo "
			<script src='../js/notify.js'></script>
			<script>
			$.notify('El número de cheque ya esta registrado.','error');
			</script>";
		} else {
			//para guardar en PAGOS temporal
			formas_pago_egreso($forma_pago_egreso, $valor_pago_egreso, $tipo, $origen, $numero_cheque_egreso, $fecha_cobro_egreso);
		}
	} else {

		formas_pago_egreso($forma_pago_egreso, $valor_pago_egreso, $tipo, $origen, $numero_cheque_egreso, $fecha_cobro_egreso);
	}
}

function formas_pago_egreso($forma_pago, $valor_pago, $tipo, $origen, $cheque, $fecha_cheque)
{
	$arrayFormaPago = array();
	$arrayDatos = array('id' => rand(5, 500), 'id_forma' => $forma_pago, 'tipo' => $tipo, 'valor' => $valor_pago, 'origen' => $origen, 'cheque' => $cheque, 'fecha_cheque' => $fecha_cheque);
	if (isset($_SESSION['arrayFormaPagoEgreso'])) {
		$on = true;
		$arrayFormaPago = $_SESSION['arrayFormaPagoEgreso'];
		for ($pr = 0; $pr < count($arrayFormaPago); $pr++) {

			if ($tipo == "C") {
				if ($arrayFormaPago[$pr]['id_forma'] == $forma_pago && $origen == $arrayFormaPago[$pr]['origen'] && $cheque == $arrayFormaPago[$pr]['cheque']) {
					$arrayFormaPago[$pr]['valor'] += $valor_pago;
					$on = false;
				}
			} else {

				if ($arrayFormaPago[$pr]['id_forma'] == $forma_pago && $origen == $arrayFormaPago[$pr]['origen']) {
					$arrayFormaPago[$pr]['valor'] += $valor_pago;
					$on = false;
				}
			}
		}
		if ($on) {
			array_push($arrayFormaPago, $arrayDatos);
		}
		$_SESSION['arrayFormaPagoEgreso'] = $arrayFormaPago;
	} else {
		array_push($arrayFormaPago, $arrayDatos);
		$_SESSION['arrayFormaPagoEgreso'] = $arrayFormaPago;
	}
	echo "
			<script src='../js/notify.js'></script>
			<script>
				$.notify('Agregado','success');
				</script>";
}



//para proveedores
if ($action == 'agrega_facturas_compras') {
	if (isset($_POST['id'])) {
		$id_compra = $_POST['id'];
	}
	if (isset($_POST['a_pagar'])) {
		$a_pagar = $_POST['a_pagar'];
	}

	if (!empty($id_compra) and !empty($a_pagar)) {
		//para buscar datos de la compra a pagar
		$sql_compra = mysqli_query($con, "SELECT * FROM saldos_compras_tmp  as sc, proveedores as pro 
		WHERE mid(sc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' 
		and sc.id_saldo = '" . $id_compra . "' and sc.id_proveedor = pro.id_proveedor");
		$row_compra = mysqli_fetch_array($sql_compra);
		$nombre_proveedor = $row_compra['razon_social'];
		$numero_documento = $row_compra['numero_documento'] . " " . $nombre_proveedor;
		$codigo_documento = $row_compra['codigo_documento'];
		$id_comprobante = $row_compra["id_comprobante"];
		//tipo de documento
		$busca_tipo_documento = "SELECT *  FROM comprobantes_autorizados WHERE id_comprobante = '" . $id_comprobante . "'";
		$result_tipo_documento = $con->query($busca_tipo_documento);
		$row_tipo_comprobante = mysqli_fetch_array($result_tipo_documento);
		$nombre_comprobante = $row_tipo_comprobante['comprobante'];

		if ($id_comprobante == "04") {
			$a_pagar = $a_pagar * -1;
		}

		//para guardar en el egreso temporal
		$insert_tmp = mysqli_query($con, "INSERT INTO ingresos_egresos_tmp 
		VALUES (null,'EGRESO','" . $nombre_proveedor . "',
		'" . $nombre_comprobante . " " . $numero_documento . "',
		'" . $a_pagar . "','CCXPP','" . $id_usuario . "',
		'" . $codigo_documento . "')");
	}
}

//para sueldos por pagar
if ($action == 'agrega_sueldos_por_pagar') {
	if (isset($_POST['id'])) {
		$id_rol = $_POST['id'];
	}
	if (isset($_POST['a_pagar'])) {
		$a_pagar = $_POST['a_pagar'];
	}

	if (!empty($id_rol) and !empty($a_pagar)) {
		$nombre_empleado = $_POST['nombre_empleado'];
		$numero_documento = $_POST['mes_ano'] . " " . $nombre_empleado;
		$codigo_documento = "ROL_PAGOS" . $_POST['id'];
		$nombre_comprobante = "Rol de pagos";
		//para guardar en el egreso temporal
		$insert_tmp = mysqli_query($con, "INSERT INTO ingresos_egresos_tmp 
		VALUES (null,'EGRESO','" . $nombre_empleado . "',
		'" . $nombre_comprobante . " " . $numero_documento . "',
		'" . $a_pagar . "','CCXRPP','" . $id_usuario . "',
		'" . $codigo_documento . "')");
	}
}

//para quincenas por pagar
if ($action == 'agrega_quincena_por_pagar') {
	if (isset($_POST['id'])) {
		$id_rol = $_POST['id'];
	}
	if (isset($_POST['a_pagar'])) {
		$a_pagar = $_POST['a_pagar'];
	}

	if (!empty($id_rol) and !empty($a_pagar)) {
		$nombre_empleado = $_POST['nombre_empleado'];
		$numero_documento = $_POST['mes_ano'] . " " . $nombre_empleado;
		$codigo_documento = "QUINCENA" . $_POST['id'];
		$nombre_comprobante = "Quincena";

		//para guardar en el egreso temporal
		$insert_tmp = mysqli_query($con, "INSERT INTO ingresos_egresos_tmp 
		VALUES (null,'EGRESO','" . $nombre_empleado . "','" . $nombre_comprobante . " " . $numero_documento . "',
		'" . $a_pagar . "','CCXQPP','" . $id_usuario . "',
		'" . $codigo_documento . "')");
	}
}

//para otros diferentes egresos
if ($action == 'agrega_diferentes_egresos') {
	$tipo_egreso = $_GET["tipo_egreso"];
	$valor_egreso = $_GET["valor_egreso"];
	$nombre_beneficiario = $_GET["nombre_beneficiario"];
	$detalle_egreso = $_GET["detalle_egreso"];
	$agregar_egreso = mysqli_query($con, "INSERT INTO ingresos_egresos_tmp VALUES (null, 'EGRESO', '" . $nombre_beneficiario . "', '" . $detalle_egreso . "', '" . $valor_egreso . "', '" . $tipo_egreso . "', '" . $id_usuario . "','0')");
}

?>
<div class="row">
	<div class="col-md-7">
		<div class="panel-group" id="accordion">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion" href="#collapse1"><span class="caret"></span> Detalle de documentos agregados al egreso</a>
				<div id="collapse1" class="panel-collapse">

					<div class="panel panel-info">
						<div class="table-responsive">
							<table class="table">
								<tr class="info">
									<td style="padding: 2px;">Nombre</td>
									<td style="padding: 2px;">Detalle</td>
									<td style="padding: 2px;" class='text-center'>Valor</td>
									<td style="padding: 2px;">Tipo</td>
									<td style="padding: 2px;" class="text-center">Eliminar</td>
								</tr>
								<?php
								// PARA MOSTRAR LOS ITEMS DEL EGRESO
								$total_egreso = 0;
								$sql = mysqli_query($con, "SELECT * from ingresos_egresos_tmp where id_usuario = '" . $id_usuario . "' and tipo_documento='EGRESO'");
								while ($row = mysqli_fetch_array($sql)) {
									$id_tmp = $row["id_tmp"];
									$beneficiario_cliente = strtolower($row['beneficiario_cliente']);
									$detalle = ucfirst($row['detalle']);
									$valor = number_format($row['valor'], 2, '.', '');
									$tipo_transaccion = $row['tipo_transaccion'];

									if (!is_numeric($tipo_transaccion)) {
										$tipo_asiento = mysqli_query($con, "SELECT * FROM asientos_tipo WHERE codigo='" . $tipo_transaccion . "' ");
										$row_asiento = mysqli_fetch_assoc($tipo_asiento);
										$transaccion = $row_asiento['tipo_asiento'];
									} else {
										$tipo_pago = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE id='" . $tipo_transaccion . "' ");
										$row_tipo_pago = mysqli_fetch_assoc($tipo_pago);
										$transaccion = $row_tipo_pago['descripcion'];
									}
									$total_egreso += $row['valor'];
								?>
									<tr>
										<td style="padding: 2px;"><?php echo strtoupper($beneficiario_cliente); ?></td>
										<td style="padding: 2px;"><?php echo $detalle; ?></td>
										<td style="padding: 2px;" class='text-right'><?php echo $valor; ?></td>
										<td style="padding: 2px;"><?php echo strtoupper($transaccion); ?></td>
										<td style="padding: 2px;" class="text-center">
											<a href="#" class='btn btn-danger btn-sm' onclick="eliminar_fila_egreso('<?php echo $id_tmp; ?>')" title="Eliminar item"><i class="glyphicon glyphicon-trash"></i></a>
										</td>
									</tr>
								<?php
								}
								?>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-md-5">
		<div class="panel-group" id="accordion_pago">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion_pago" href="#collapse2"><span class="caret"></span> Detalle de formas de pagos</a>
				<div id="collapse2" class="panel-collapse">

					<input type="hidden" id="buscar_de" name="buscar_de">
					<div class="panel panel-info">
						<div class="table-responsive">
							<input type="hidden" id="suma_egreso" value="<?php echo number_format($total_egreso, 2, '.', ''); ?>">
							<table class="table table-bordered">
								<tr class="info">
									<td style="padding: 2px;">Forma</td>
									<td style="padding: 2px;" class="text-center">Tipo</td>
									<td style="padding: 2px;" class="text-center">Valor</td>
									<td style="padding: 2px;" class="text-center">Cheque</td>
									<td style="padding: 2px;" class="text-center">Fecha</td>
									<td style="padding: 2px;" class="text-center">Eliminar</td>
								</tr>
								<?php
								$valor_total_pago = 0;
								if (isset($_SESSION['arrayFormaPagoEgreso'])) {
									foreach ($_SESSION['arrayFormaPagoEgreso'] as $detalle) {
										$id = $detalle['id'];
										$id_forma = $detalle['id_forma'];
										$tipo = $detalle['tipo'];
										switch ($tipo) {
											case "0":
												$tipo = 'N/A';
												break;
											case "C":
												$tipo = 'Cheque';
												break;
											case "D":
												$tipo = 'Débito';
												break;
											case "T":
												$tipo = 'Transferencia';
												break;
										}
										$origen = $detalle['origen'];
										$valor_pago = number_format($detalle['valor'], 2, '.', '');
										$valor_total_pago += $valor_pago;

										if ($origen == 1) {
											$query_cobros_pagos = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE id='" . $id_forma . "' and ruc_empresa='" . $ruc_empresa . "' ");
											$row_cobros_pagos = mysqli_fetch_array($query_cobros_pagos);
											$forma_pago = strtoupper($row_cobros_pagos['descripcion']);
										} else {

											$cuentas_bancarias = mysqli_query($con, "SELECT concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.id_cuenta ='" . $id_forma . "'");
											$row_cuentas_bancarias = mysqli_fetch_array($cuentas_bancarias);
											$forma_pago = strtoupper($row_cuentas_bancarias['cuenta_bancaria']);
										}
								?>
										<tr>
											<td style="padding: 2px;" class='col-xs-2'><?php echo $forma_pago; ?></td>
											<td style="padding: 2px;" class='col-xs-2'><?php echo $tipo; ?></td>
											<td style="padding: 2px;" class='col-xs-1 text-right'><?php echo $valor_pago; ?></td>
											<td style="padding: 2px;" class='col-xs-1 text-center'><?php echo $detalle['cheque'] > 0 ? $detalle['cheque'] : ""; ?></td>
											<td style="padding: 2px;" class='col-xs-2 text-center'><?php echo $detalle['fecha_cheque'] != 0 ? date('d-m-Y', strtotime($detalle['fecha_cheque'])) : ""; ?></td>
											<td style="padding: 2px;" class='col-xs-1 text-center'>
												<a href="#" class='btn btn-danger btn-sm' onclick="eliminar_fila_pago_egreso('<?php echo $id; ?>')" title="Eliminar item"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
								<?php
									}
								}
								?>
								<input type="hidden" id="suma_pagos_egreso" value="<?php echo number_format($valor_total_pago, 2, '.', ''); ?>">
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>



<!-- desde aqui asiento contable-->
<div class="row">
	<div class="col-md-12">
		<div class="panel-group" id="accordion1">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion1" href="#collapse3"><span class="caret"></span> Asiento contable (Opcional)</a>
				<div id="collapse3" class="panel-collapse collapse">

					<div class="table-responsive">
						<input type="hidden" name="codigo_unico" id="codigo_unico">
						<input type="hidden" name="id_cuenta" id="id_cuenta">
						<input type="hidden" name="cod_cuenta" id="cod_cuenta">
						<div class="panel panel-info" style="margin-bottom: 5px; margin-top: 5px;">
							<table class="table table-bordered">
								<td class='col-xs-4'>
									<input type="text" class="form-control input-sm focusNext" name="cuenta_diario" id="cuenta_diario" onkeyup='buscar_cuentas();' autocomplete="off" tabindex="4" placeholder="Buscar cuenta contable">
								</td>
								<td class='col-xs-2'><input type="number" class="form-control input-sm focusNext text-left" name="debe_diario" id="debe_diario" tabindex="5" placeholder="Debe"></td>
								<td class='col-xs-2'><input type="number" class="form-control input-sm focusNext text-left" name="haber_cuenta" id="haber_cuenta" tabindex="6" placeholder="Haber"></td>
								<td class='col-xs-4'><input type="text" class="form-control input-sm focusNext" name="det_cuenta" id="det_cuenta" tabindex="7" placeholder="Referencia"></td>
								<td class='col-xs-1 text-center'><button type="button" class="btn btn-info btn-md focusNext" title="Agregar detalle de diario" tabindex="8" onclick="agregar_item_diario()"><span class="glyphicon glyphicon-plus"></span></button> </td>
							</table>
						</div>
						<div id="muestra_detalle_diario"></div><!-- Carga gif animado -->
						<div class="outer_divdet"></div><!-- Datos ajax Final -->
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	var total_pago_egreso = $("#suma_pagos_egreso").val();
	$("#total_pagos_egreso").val(total_pago_egreso);
	var total_egreso = $("#suma_egreso").val();
	$("#total_egreso").val(total_egreso);
</script>