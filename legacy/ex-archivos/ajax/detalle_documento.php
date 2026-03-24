<?php
include("../conexiones/conectalogin.php");
include("../clases/lee_xml.php");
ini_set('date.timezone', 'America/Guayaquil');
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
$datos_xml = new rides_sri();

if ($action == 'detalle_documento_adquisiciones') {
	$codigo = $_GET['codigo'];
	echo detalle_documento_adquisiciones($codigo);
}

//para agregar una observacion para notificaciones compras
if ($action == 'agregar_observacion_compra') {
	$codigo_documento = $_POST['codigo_documento'];
	$numero_documento = $_POST['numero_documento'];
	$id_proveedor = $_POST['id_proveedor'];
	$observacion = strClean($_POST['observacion']);
	$token = codigo_aleatorio(20);
	$guardar_notificacion = mysqli_query($con, "INSERT INTO notificaciones_adquisiciones 
	(numero_documento, id_proveedor, ruc_empresa, codigo_documento, id_usuario, observacion, token) 
	VALUES ('" . $numero_documento . "', '" . $id_proveedor . "', '" . $ruc_empresa . "' ,'" . $codigo_documento . "','" . $id_usuario . "','" . $observacion . "', '" . $token . "')");
	if ($guardar_notificacion) {
		echo "<script>
	 $.notify('Observación registrada','success');
	 </script>";
	} else {
		echo "<script>
	 $.notify('No es posible registrar, intente de nuevo','error');
	 </script>";
	}
}

//para agregar una respuesta para aprobar compras
if ($action == 'agregar_respuesta_compra') {
	$id = $_POST['id'];
	$respuesta = strClean($_POST['respuesta']);
	$sql_update = mysqli_query($con, "UPDATE notificaciones_adquisiciones SET respuesta ='" . $respuesta . "' WHERE id='" . $id . "'");
	if ($sql_update) {
		echo "<script>
	 $.notify('Respuesta registrada','success');
	 </script>";
	} else {
		echo "<script>
	 $.notify('No es posible registrar, intente de nuevo','error');
	 </script>";
	}
}

//para eliminar una observacion de aprobar compra
if ($action == 'eliminar_observacion_compra') {
	$id = $_POST['id'];
	$sql_update = mysqli_query($con, "UPDATE notificaciones_adquisiciones SET status='0', token='', id_usuario='" . $id_usuario . "' WHERE id='" . $id . "'");
}

//para eliminar una respuesta de aprobar compra
if ($action == 'eliminar_respuesta_compra') {
	$id = $_POST['id'];
	$sql_update = mysqli_query($con, "UPDATE notificaciones_adquisiciones SET respuesta='' WHERE id='" . $id . "'");
}

//para actualizar el status de aprobar compra
if ($action == 'actualizar_status_observacion_compra') {
	$id = $_POST['id'];
	$status = $_POST['status'];
	if ($status == 1) {
		$token = codigo_aleatorio(20);
	} else {
		$token = 0;
	}
	$sql_update = mysqli_query($con, "UPDATE notificaciones_adquisiciones SET status='" . $status . "', token='" . $token . "', id_usuario='" . $id_usuario . "' WHERE id='" . $id . "'");
}

//para actualizar el correo del usuario
if ($action == 'actualizar_correo_observacion_compra') {
	$id = $_POST['id'];
	$id_receptor = $_POST['id_receptor'];
	$token = codigo_aleatorio(20);
	$sql_update = mysqli_query($con, "UPDATE notificaciones_adquisiciones SET id_receptor='" . $id_receptor . "', token='" . $token . "', id_usuario='" . $id_usuario . "' WHERE id='" . $id . "'");
}



if ($action == 'actualiza_vendedor') {
	$id_documento = $_POST['id_documento'];
	$id_vendedor = $_POST['id_vendedor'];
	ini_set('date.timezone', 'America/Guayaquil');
	$fecha_registro = date("Y-m-d H:i:s");
	$limpiar_vendedor = mysqli_query($con, "DELETE FROM vendedores_ventas WHERE id_venta='" . $id_documento . "' ");
	$guardar_vendedor = mysqli_query($con, "INSERT INTO vendedores_ventas VALUES (null,'" . $id_vendedor . "','" . $id_documento . "', '" . $fecha_registro . "' ,'" . $id_usuario . "')");
}

if ($action == 'actualiza_vendedor_ncv') {
	$id_documento = $_POST['id_documento'];
	$id_vendedor = $_POST['id_vendedor'];
	ini_set('date.timezone', 'America/Guayaquil');
	$fecha_registro = date("Y-m-d H:i:s");
	$limpiar_vendedor = mysqli_query($con, "DELETE FROM vendedores_ncv WHERE id_ncv='" . $id_documento . "' ");
	$guardar_vendedor = mysqli_query($con, "INSERT INTO vendedores_ncv VALUES (null,'" . $id_vendedor . "','" . $id_documento . "', '" . $fecha_registro . "' ,'" . $id_usuario . "')");
}

if ($action == 'actualiza_vendedor_recibo') {
	$id_documento = $_POST['id_documento'];
	$id_vendedor = $_POST['id_vendedor'];
	ini_set('date.timezone', 'America/Guayaquil');
	$fecha_registro = date("Y-m-d H:i:s");
	$limpiar_vendedor = mysqli_query($con, "DELETE FROM vendedores_recibos WHERE id_recibo='" . $id_documento . "' ");
	$guardar_vendedor = mysqli_query($con, "INSERT INTO vendedores_recibos VALUES (null,'" . $id_vendedor . "','" . $id_documento . "', '" . $fecha_registro . "' ,'" . $id_usuario . "')");
}

if ($action == 'compras') {
	$codigo_documento = $_GET['codigo'];
	agregar_detalle_compra($codigo_documento);
	detalle_compras($codigo_documento);
}

if ($action == 'detalle_notificaciones_adquisiciones') {
	$codigo_documento = $_GET['codigo'];
	detalle_notificaciones_adquisiciones($codigo_documento);
}

if ($action == 'revisar_notificaciones_adquisiciones') {
	$codigo_documento = $_GET['codigo'];
	revisar_notificaciones_adquisiciones($codigo_documento);
}



if ($action == 'info_anular_sri') {
	$id = $_GET['id'];
	$documento = $_GET['documento'];

	switch ($documento) {
		case "01"; //factura
			$sql = mysqli_query($con, "SELECT * FROM encabezado_factura as enc 
			INNER JOIN clientes as cli ON cli.id=enc.id_cliente
			LEFT JOIN respuestas_sri as sri ON sri.id_documento=enc.id_encabezado_factura and sri.documento = 'factura'
			WHERE enc.id_encabezado_factura = '" . $id . "' ");
			$row_sql = mysqli_fetch_array($sql);
			$clave_acceso = $row_sql['aut_sri'];
			$fecha_autorizacion = isset($row_sql['fechaAutorizacion']) ? date("d-m-Y", strtotime($row_sql['fechaAutorizacion'])) : date("d-m-Y", strtotime($row_sql['fecha_factura']));
			$fecha_documento = date("d-m-Y", strtotime($row_sql['fecha_factura']));
			$tipo_comprobante = 'FACTURA' . ' #.' . $row_sql['serie_factura'] . '-' . str_pad($row_sql['secuencial_factura'], 9, "000000000", STR_PAD_LEFT);
			$identificacion_receptor = $row_sql['ruc'];
			$correo_electronico_receptor = $row_sql['email'];
			$cliente_proveedor = $row_sql['nombre'];
			break;
		case "04"; //nota de credito
			$sql = mysqli_query($con, "SELECT * FROM encabezado_nc as enc 
			INNER JOIN clientes as cli ON cli.id=enc.id_cliente 
			LEFT JOIN respuestas_sri as sri ON sri.id_documento=enc.id_encabezado_nc and sri.documento = 'nc'
			WHERE enc.id_encabezado_nc = '" . $id . "' ");
			$row_sql = mysqli_fetch_array($sql);
			$clave_acceso = $row_sql['aut_sri'];
			$fecha_autorizacion = isset($row_sql['fechaAutorizacion']) ? date("d-m-Y", strtotime($row_sql['fechaAutorizacion'])) : date("d-m-Y", strtotime($row_sql['fecha_nc']));
			$fecha_documento = date("d-m-Y", strtotime($row_sql['fecha_nc']));
			$tipo_comprobante = 'NOTA DE CRÉDITO' . ' #.' . $row_sql['serie_nc'] . '-' . str_pad($row_sql['secuencial_nc'], 9, "000000000", STR_PAD_LEFT);
			$identificacion_receptor = $row_sql['ruc'];
			$correo_electronico_receptor = $row_sql['email'];
			$cliente_proveedor = $row_sql['nombre'];
			break;
		case "07"; //retencion
			$sql = mysqli_query($con, "SELECT * FROM encabezado_retencion as enc 
			INNER JOIN proveedores as pro ON pro.id_proveedor=enc.id_proveedor 
			LEFT JOIN respuestas_sri as sri ON sri.id_documento=enc.id_encabezado_retencion and sri.documento = 'retencion'
			WHERE enc.id_encabezado_retencion = '" . $id . "' ");
			$row_sql = mysqli_fetch_array($sql);
			$clave_acceso = $row_sql['aut_sri'];
			$fecha_autorizacion = isset($row_sql['fechaAutorizacion']) ? date("d-m-Y", strtotime($row_sql['fechaAutorizacion'])) : date("d-m-Y", strtotime($row_sql['fecha_emision']));
			$fecha_documento = date("d-m-Y", strtotime($row_sql['fecha_emision']));
			$tipo_comprobante = 'RETENCIÓN' . ' #.' . $row_sql['serie_retencion'] . '-' . str_pad($row_sql['secuencial_retencion'], 9, "000000000", STR_PAD_LEFT);
			$identificacion_receptor = $row_sql['ruc_proveedor'];
			$correo_electronico_receptor = $row_sql['mail_proveedor'];
			$cliente_proveedor = $row_sql['razon_social'];
			break;
		case "03"; //liquidacion compra
			$sql = mysqli_query($con, "SELECT * FROM encabezado_liquidacion as enc 
			INNER JOIN proveedores as pro ON pro.id_proveedor=enc.id_proveedor
			LEFT JOIN respuestas_sri as sri ON sri.id_documento=enc.id_encabezado_liq and sri.documento = 'liquidacion' 
			WHERE enc.id_encabezado_liq = '" . $id . "' ");
			$row_sql = mysqli_fetch_array($sql);
			$clave_acceso = $row_sql['aut_sri'];
			$fecha_autorizacion = isset($row_sql['fechaAutorizacion']) ? date("d-m-Y", strtotime($row_sql['fechaAutorizacion'])) : date("d-m-Y", strtotime($row_sql['fecha_liquidacion']));
			$fecha_documento = date("d-m-Y", strtotime($row_sql['fecha_liquidacion']));
			$tipo_comprobante = 'LIQUIDACIÓN' . ' #.' . $row_sql['serie_liquidacion'] . '-' . str_pad($row_sql['secuencial_liquidacion'], 9, "000000000", STR_PAD_LEFT);
			$identificacion_receptor = $row_sql['ruc_proveedor'];
			$correo_electronico_receptor = $row_sql['mail_proveedor'];
			$cliente_proveedor = $row_sql['razon_social'];
			break;
		case "06"; //guia remision
			$sql = mysqli_query($con, "SELECT * FROM encabezado_gr as enc 
			INNER JOIN clientes as cli ON cli.id=enc.id_transportista
			LEFT JOIN respuestas_sri as sri ON sri.id_documento=enc.id_encabezado_gr and sri.documento = 'gr' 
			WHERE enc.id_encabezado_gr = '" . $id . "' ");
			$row_sql = mysqli_fetch_array($sql);
			$clave_acceso = $row_sql['aut_sri'];
			$fecha_autorizacion = isset($row_sql['fechaAutorizacion']) ? date("d-m-Y", strtotime($row_sql['fechaAutorizacion'])) : date("d-m-Y", strtotime($row_sql['fecha_gr']));
			$fecha_documento = date("d-m-Y", strtotime($row_sql['fecha_gr']));
			$tipo_comprobante = 'GUÍA DE REMISIÓN';
			$identificacion_receptor = $row_sql['ruc'];
			$correo_electronico_receptor = $row_sql['email'];
			$cliente_proveedor = $row_sql['nombre'];
			break;
	}

	$data = array(
		'cliente_proveedor' => $cliente_proveedor,
		'tipo_comprobante' => $tipo_comprobante,
		'fecha_autorizacion' => $fecha_autorizacion,
		'clave_acceso' => $clave_acceso,
		'identificacion_receptor' => $identificacion_receptor,
		'correo_electronico_receptor' => explode(", ", $correo_electronico_receptor)[0],
		'fecha_documento' => $fecha_documento
	);


	if ($sql) {
		$arrResponse = array("status" => true, "data" => $data);
	} else {
		$arrResponse = array("status" => false, "msg" => 'Datos no encontrados');
	}

	echo json_encode($arrResponse); //, JSON_UNESCAPED_UNICODE
	die();
}

//para mostrar el detalle del egreso
if ($action == 'detalle_egreso') {
	$con = conenta_login();
	$codigo_unico = $_GET['codigo_unico'];
	$busca_encabezado_ingreso = mysqli_query($con, "SELECT * FROM ingresos_egresos WHERE codigo_documento = '" . $codigo_unico . "' ");
	$encabezado_ingresos = mysqli_fetch_array($busca_encabezado_ingreso);
	$id_registro_contable = $encabezado_ingresos['codigo_contable'];
	$id_proveedor = $encabezado_ingresos['id_cli_pro'];
	$busca_detalle = mysqli_query($con, "SELECT * FROM detalle_ingresos_egresos WHERE codigo_documento = '" . $codigo_unico . "' ");
	$busca_pagos = mysqli_query($con, "SELECT * FROM formas_pagos_ing_egr WHERE codigo_documento = '" . $codigo_unico . "' ");
?>
	<div class="well well-sm" style="margin-bottom: -20px; margin-top: -10px; height: 14%;">
		<div class="modal-body">
			<div class="form-group row">
				<div class="col-sm-3">
					<div class="input-group">
						<span class="input-group-addon"><b>Egreso</b></span>
						<input type="text" class="form-control input-sm text-center" value="<?php echo $encabezado_ingresos['numero_ing_egr']; ?>" readonly>
					</div>
				</div>
				<div class="col-sm-9">
					<div class="input-group">
						<span class="input-group-addon"><b>Beneficiario</b></span>
						<input type="hidden" id="id_beneficiario_editar_egreso" name="id_beneficiario_editar_egreso" value="<?php echo $id_proveedor; ?>">
						<input type="hidden" id="codigo_unico_egreso" name="codigo_unico_egreso" value="<?php echo $codigo_unico; ?>">
						<input type="text" id="beneficiario_editar_egreso" name="beneficiario_editar_egreso" class="form-control input-sm" onkeyup="buscar_beneficiario_editar_egreso();" value="<?php echo $encabezado_ingresos['nombre_ing_egr']; ?>" placeholder="Beneficiario">
					</div>
				</div>
			</div>
			<div class="form-group row">
				<div class="col-sm-3">
					<div class="input-group">
						<span class="input-group-addon"><b>Fecha</b></span>
						<input type="text" class="form-control input-sm text-center" id="fecha_editar_egreso" name="fecha_editar_egreso" value="<?php echo date("d-m-Y", strtotime($encabezado_ingresos['fecha_ing_egr'])); ?>">
					</div>
				</div>
				<div class="col-sm-9">
					<div class="input-group">
						<span class="input-group-addon"><b>Observaciones</b></span>
						<input type="text" class="form-control input-sm" id="observaciones_editar_egreso" name="observaciones_editar_egreso" value="<?php echo $encabezado_ingresos['detalle_adicional']; ?>" placeholder="Observaciones">
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;">Tipo</th>
					<th style="padding: 2px;">Detalle</th>
					<th style="padding: 2px;" class="text-center">Valor</th>
				</tr>
				<?php
				while ($detalle = mysqli_fetch_array($busca_detalle)) {
					$id_detalle = $detalle['id_detalle_ing_egr'];
				?>
					<tr>
						<?php
						$tipo_ing_egr = $detalle['tipo_ing_egr'];
						if (!is_numeric($tipo_ing_egr)) { //cuando NO es un codigo id de las opciones de egresos
							$tipo_asiento = mysqli_query($con, "SELECT * FROM asientos_tipo WHERE codigo='" . $tipo_ing_egr . "' ");
							$row_asiento = mysqli_fetch_assoc($tipo_asiento);
						?>
							<input type="hidden" name="valor_detalle_egresos_no_editables[<?php echo $id_detalle; ?>]" value="<?php echo number_format($detalle['valor_ing_egr'], 2, '.', ''); ?>">
							<td style="padding: 2px;"><?php echo $row_asiento['tipo_asiento']; ?></td>
							<td style="padding: 2px;"><?php echo $detalle['detalle_ing_egr']; ?></td>
							<td style="padding: 2px; text-align: center;"><?php echo number_format($detalle['valor_ing_egr'], 2, '.', ''); ?></td>
						<?php
						} else {
						?>
							<input type="hidden" name="id_detalle_egreso[]" value="<?php echo $id_detalle; ?>">
							<td class='col-sm-2' style="padding: 2px;">
								<select class="form-control" style="height: 30px" title="Seleccione tipo de egreso" id="tipo_egreso<?php echo $id_detalle; ?>" name="tipo_egreso[<?php echo $id_detalle; ?>]">
									<?php
									$resultado = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE tipo_opcion ='2' and status='1' and ruc_empresa='" . $ruc_empresa . "'order by descripcion asc");
									while ($row = mysqli_fetch_assoc($resultado)) {
										$selected = ($row['id'] == $tipo_ing_egr) ? 'selected' : '';
									?>
										<option value="<?php echo $row['id']; ?>" <?php echo $selected; ?>><?php echo strtoupper($row['descripcion']); ?> </option>
									<?php
									}
									?>
								</select>
							</td>
							<td style="padding: 2px;">
								<input type="text" class="form-control input-sm" id="detalle_editar_egreso[<?php echo $id_detalle; ?>]" name="detalle_editar_egreso[<?php echo $id_detalle; ?>]" value="<?php echo $detalle['detalle_ing_egr']; ?>" placeholder="Detalle">
							</td>
							<td class="col-sm-2" style="padding: 2px;">
								<input type="text" class="form-control input-sm text-center" id="valor_detalle_editar_egreso[<?php echo $id_detalle; ?>]" name="valor_detalle_editar_egreso[<?php echo $id_detalle; ?>]" value="<?php echo number_format($detalle['valor_ing_egr'], 2, '.', '') ?>" placeholder="Valor">
							</td>
						<?php
						}
						?>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;">Forma pago</th>
					<th style="padding: 2px;">Tipo</th>
					<th style="padding: 2px;">Cheque</th>
					<th style="padding: 2px;">Fecha cheque</th>
					<th style="padding: 2px;" class="text-center">Valor</th>
				</tr>
				<?php
				while ($detalle_pagos = mysqli_fetch_array($busca_pagos)) {
					$codigo_forma_pago = $detalle_pagos['codigo_forma_pago'];
					$id_cuenta = $detalle_pagos['id_cuenta'];
					$id_fp = $detalle_pagos['id_fp'];
				?>
					<input type="hidden" name="id_forma_pago_egreso[]" value="<?php echo $id_fp; ?>">
				<?php
					echo formas_de_pago_egreso($id_fp, $con, $ruc_empresa, $detalle_pagos['codigo_forma_pago'], $detalle_pagos['id_cuenta'], $detalle_pagos['detalle_pago'], $detalle_pagos['fecha_pago'], $detalle_pagos['cheque'], $detalle_pagos['valor_forma_pago']);
				}
				?>
			</table>
		</div>
	</div>
<?php
	echo detalle_asiento_contable($con, $ruc_empresa, $id_registro_contable, 'EGRESOS');
}

//para mostrar en el modal el formulario de pagos
function formas_de_pago_egreso($id_fp, $con, $ruc_empresa, $codigo_forma_pago, $id_cuenta, $tipo, $fecha_pago, $cheque, $valor)
{
?>
	<tr>
		<td style="padding: 2px;" class="col-sm-4">
			<select onchange="cambioFormaPagoEditarEgreso('<?php echo $id_fp ?>')" style="height: 30px;" class="form-control" title="Seleccione forma de pago." id="forma_pago_editar_egreso<?php echo $id_fp; ?>" name="forma_pago_editar_egreso[<?php echo $id_fp; ?>]">
				<?php
				$query_cobros_pagos = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_opcion='2' and status='1' order by descripcion asc");
				while ($row_cobros_pagos = mysqli_fetch_array($query_cobros_pagos)) {
					$selected = ($row_cobros_pagos['id'] == $codigo_forma_pago) ? 'selected' : '';
					//el 1 junto al id en el value es para saber que los datos son de la lista de opciones de cobro
				?>
					<option value="<?php echo "1" . $row_cobros_pagos['id']; ?>" <?php echo $selected; ?>><?php echo strtoupper($row_cobros_pagos['descripcion']); ?></option>
				<?php
				}
				$cuentas = mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.ruc_empresa ='" . $ruc_empresa . "' and cue_ban.status ='1'");
				while ($row_cuenta = mysqli_fetch_array($cuentas)) {
					$selected = ($row_cuenta['id_cuenta'] == $id_cuenta) ? 'selected' : '';
					//el 2 junto al id en el value es para saber que los datos son desde bancos
				?>
					<option value="<?php echo "2" . $row_cuenta['id_cuenta']; ?>" <?php echo $selected; ?>><?php echo strtoupper($row_cuenta['cuenta_bancaria']); ?></option>
				<?php
				}
				?>
			</select>
		</td>
		<td style="padding: 2px;" class="col-sm-2">
			<select onchange="cambioTipoPagoEditarEgreso('<?php echo $id_fp ?>','<?php echo $id_cuenta ?>','<?php echo $cheque ?>','<?php echo date('d-m-Y', strtotime($fecha_pago)); ?>','<?php echo date('d-m-Y'); ?>')"
				class="form-control" style="height: 30px; <?php echo ($id_cuenta > 0) ? 'display: block;' : 'display: none;'; ?>" title="Seleccione" id="tipo_editar_forma_pago<?php echo $id_fp; ?>" name="tipo_editar_forma_pago[<?php echo $id_fp; ?>]">
				<option value="0" <?php if ($tipo == '0') echo 'selected'; ?>>Seleccione</option>
				<option value="C" <?php if ($tipo == 'C') echo 'selected'; ?>>Cheque</option>
				<option value="D" <?php if ($tipo == 'D') echo 'selected'; ?>>Débito</option>
				<option value="T" <?php if ($tipo == 'T') echo 'selected'; ?>>Transferencia</option>
			</select>
		</td>
		<td style="padding: 2px;" class="col-sm-2">
			<input type="number" class="form-control input-sm text-center" id="numero_cheque_editar_egreso<?php echo $id_fp; ?>" name="numero_cheque_editar_egreso[<?php echo $id_fp; ?>]"
				title="Ingrese número de cheque"
				placeholder="Cheque"
				value="<?php echo $cheque; ?>"
				style="display: <?php echo ($cheque > 0) ? 'block' : 'none'; ?>;" <?php echo ($cheque <= 0) ? 'disabled' : ''; ?>>
		</td>
		<td style="padding: 2px;" class="col-sm-2">
			<input onmousedown="formatFechaEditarEgreso('<?php echo $id_fp ?>')" type="text" class="form-control input-sm text-center"
				id="fecha_cobro_editar_egreso<?php echo $id_fp; ?>" name="fecha_cobro_editar_egreso[<?php echo $id_fp; ?>]" value="<?php echo date("d-m-Y", strtotime($fecha_pago)); ?>"
				style="display: <?php echo ($cheque > 0) ? 'block' : 'none'; ?>;" <?php echo ($cheque <= 0) ? 'disabled' : ''; ?>>
		</td>
		<td style="padding: 2px;">
			<input type="text" class="form-control input-sm text-center" id="valor_cobro_editar_egreso<?php echo $id_fp; ?>" name="valor_cobro_editar_egreso[<?php echo $id_fp; ?>]" value="<?php echo $valor; ?>">
		</td>
	</tr>
	<script>
		$('#fecha_editar_egreso').css('z-index', 1500);
		jQuery(function($) {
			$("#fecha_editar_egreso").mask("99-99-9999");
		});

		function formatFechaEditarEgreso(id) {
			jQuery(function($) {
				$("#fecha_cobro_editar_egreso" + id).mask("99-99-9999");
			});
			calendarioFecha_editar_egreso(id);
		}

		function calendarioFecha_editar_egreso(id) {
			$(function() {
				$("#fecha_cobro_editar_egreso" + id).datepicker({
					dateFormat: "dd-mm-yy",
					firstDay: 1,
					dayNamesMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
					dayNamesShort: ["Dom", "Lun", "Mar", "Mie", "Jue", "Vie", "Sab"],
					monthNames: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio",
						"Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
					],
					monthNamesShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun",
						"Jul", "Ago", "Sep", "Oct", "Nov", "Dic"
					]
				});
			});
		}

		$(function() {
			$("#fecha_editar_egreso").datepicker({
				dateFormat: "dd-mm-yy",
				firstDay: 1,
				dayNamesMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
				dayNamesShort: ["Dom", "Lun", "Mar", "Mie", "Jue", "Vie", "Sab"],
				monthNames: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio",
					"Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
				],
				monthNamesShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun",
					"Jul", "Ago", "Sep", "Oct", "Nov", "Dic"
				]
			});
		});

		//para cuando se cambia la forma de pago o tipo de cuenta bancaria
		function cambioFormaPagoEditarEgreso(id) {
			var selectTipo = document.getElementById('tipo_editar_forma_pago' + id);
			var inputCheque = document.getElementById('numero_cheque_editar_egreso' + id);
			var inputFechaCheque = document.getElementById('fecha_cobro_editar_egreso' + id);
			inputCheque.disabled = true; // Deshabilitar el input
			inputCheque.value = ''; // Limpiar el valor si se deshabilita
			inputCheque.style.display = 'none'; // Ocultar el input si es necesario
			inputFechaCheque.disabled = true; // Deshabilitar el input
			inputFechaCheque.value = ''; // Limpiar el valor si se deshabilita
			inputFechaCheque.style.display = 'none'; // Ocultar el input si es necesario
			var forma_pago = $("#forma_pago_editar_egreso" + id).val();
			$("#tipo_editar_forma_pago" + id).val("0");
			var origen = forma_pago.substring(0, 1);
			if (origen == "1") {
				$("#tipo_editar_forma_pago" + id).val("0");
				$("#numero_cheque_editar_egreso" + id).val("");
				selectTipo.disabled = false; // Deshabilitar el input
				selectTipo.value = '0'; // Limpiar el valor si se deshabilita
				selectTipo.style.display = 'none'; //para ocultar
				document.getElementById('valor_cobro_editar_egreso' + id).focus();
			} else {
				selectTipo.style.display = 'block'; //para mostrar
				selectTipo.disabled = false; // Habilitar el select
			}
		}

		//cuando se cambia el tipo de forma de pago, como transferencia, cheque
		function cambioTipoPagoEditarEgreso(id, id_cuenta_anterior, cheque, fecha_cheque_anterior, fecha_hoy) {
			var forma_pago = $("#forma_pago_editar_egreso" + id).val();
			var tipo = $("#tipo_editar_forma_pago" + id).val();
			var inputCheque = document.getElementById('numero_cheque_editar_egreso' + id);
			var inputFechaCheque = document.getElementById('fecha_cobro_editar_egreso' + id);
			if (tipo === 'C') { // Si se selecciona "Cheque"
				inputCheque.disabled = false; // Habilitar el input
				inputCheque.style.display = 'block'; // Mostrar el input si estaba oculto
				inputFechaCheque.disabled = false; // Habilitar el input
				inputFechaCheque.style.display = 'block'; // Mostrar el input si estaba oculto
				traer_numero_cheque(forma_pago, tipo, id, fecha_hoy, cheque, id_cuenta_anterior, fecha_cheque_anterior);
			} else {
				inputCheque.disabled = true; // Deshabilitar el input
				inputCheque.value = ''; // Limpiar el valor si se deshabilita
				inputCheque.style.display = 'none'; // Ocultar el input si es necesario
				inputFechaCheque.disabled = true; // Deshabilitar el input
				inputFechaCheque.value = ''; // Limpiar el valor si se deshabilita
				inputFechaCheque.style.display = 'none'; // Ocultar el input si es necesario
			}
		}

		function traer_numero_cheque(forma_pago, tipo, id, fecha_hoy, cheque, id_cuenta_anterior, fecha_cheque_anterior) {
			var inputCheque = document.getElementById('numero_cheque_editar_egreso' + id);
			var inputFechaCheque = document.getElementById('fecha_cobro_editar_egreso' + id);
			$("#numero_cheque_editar_egreso" + id).val('');
			var id_cuenta = forma_pago.substring(1, forma_pago.length);
			$.post('../ajax/buscar_tipo_cuenta_bancaria.php', {
				id_cuenta: id_cuenta
			}).done(function(respuesta) {
				$.each(respuesta, function(i, item) {
					var tipo_cuenta_bancaria = item.tipo_cuenta;
					var ultimo_cheque = item.ultimo_cheque;

					if (tipo_cuenta_bancaria == "2") { //cuenta corriente
						if (id_cuenta == id_cuenta_anterior && cheque > 0) {
							$("#numero_cheque_editar_egreso" + id).val(cheque);
							$("#fecha_cobro_editar_egreso" + id).val(fecha_cheque_anterior);
							document.getElementById('numero_cheque_editar_egreso' + id).focus();
						} else {
							$("#numero_cheque_editar_egreso" + id).val(ultimo_cheque);
							$("#fecha_cobro_editar_egreso" + id).val(fecha_hoy);
							document.getElementById('numero_cheque_editar_egreso' + id).focus();
						}
					}

					if (tipo_cuenta_bancaria == "1") { //cuenta de ahorros
						inputCheque.disabled = true; // Deshabilitar el input
						inputCheque.value = ''; // Limpiar el valor si se deshabilita
						inputCheque.style.display = 'none'; // Ocultar el input si es necesario
						inputFechaCheque.disabled = true; // Deshabilitar el input
						inputFechaCheque.value = ''; // Limpiar el valor si se deshabilita
						inputFechaCheque.style.display = 'none'; // Ocultar el input si es necesario
					}
				});
			});
		}

		function buscar_beneficiario_editar_egreso() {
			$("#beneficiario_editar_egreso").autocomplete({
				source: '../ajax/proveedores_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_beneficiario_editar_egreso').val(ui.item.id_proveedor);
					$('#beneficiario_editar_egreso').val(ui.item.razon_social);
				}
			});

			$("#beneficiario_editar_egreso").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_beneficiario_editar_egreso").val("");
					$("#beneficiario_editar_egreso").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_beneficiario_editar_egreso").val("");
					$("#beneficiario_editar_egreso").val("");
				}
			});
		}
	</script>
<?php
}


//para mostrar detalle de liquidaciones de compras
if ($action == 'liquidacion_compras') {
	$id = $_GET['id'];
	//detalle de valores de la liquidacion
	$encabezado = mysqli_query($con, "SELECT enc.fecha_liquidacion as fecha, enc.total_liquidacion as total, 
	pro.razon_social as proveedor, enc.serie_liquidacion as serie, enc.secuencial_liquidacion as secuencial, enc.aut_sri as aut_sri 
	FROM encabezado_liquidacion as enc 
	INNER JOIN cuerpo_liquidacion as cue
	ON cue.serie_liquidacion=enc.serie_liquidacion and cue.secuencial_liquidacion = enc.secuencial_liquidacion and 
	cue.ruc_empresa=enc.ruc_empresa 
	INNER JOIN tarifa_iva as ti 
	ON cue.tarifa_iva = ti.codigo 
	INNER JOIN proveedores as pro 
	ON pro.id_proveedor=enc.id_proveedor 
	WHERE enc.id_encabezado_liq = '" . $id . "' ");

	$encabezado_liquidacion = mysqli_fetch_array($encabezado);
	$proveedor = $encabezado_liquidacion['proveedor'];
	$ser_liquidacion = $encabezado_liquidacion['serie'];
	$num_liquidacion = $encabezado_liquidacion['secuencial'];
	$liquidacion = $ser_liquidacion . "-" . str_pad($num_liquidacion, 9, "000000000", STR_PAD_LEFT);
	$fecha = $encabezado_liquidacion['fecha'];
	$total = $encabezado_liquidacion['total'];
	$aut_sri = $encabezado_liquidacion['aut_sri'];
	//para saber el detalle adicional de cada liquidacion
	$sql_detalle = "SELECT * FROM detalle_adicional_liquidacion WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_liquidacion = '" . $ser_liquidacion . "' and secuencial_liquidacion = '" . $num_liquidacion . "' and adicional_concepto !='' and adicional_descripcion !='' ";
	$query_detalle = mysqli_query($con, $sql_detalle);

	//para mostrar la forma de pago y tiempo
	$sql_pago = "SELECT * FROM formas_pago_liquidacion pagos, formas_de_pago formas WHERE pagos.ruc_empresa = '" . $ruc_empresa . "' and pagos.serie_liquidacion = '" . $ser_liquidacion . "' and pagos.secuencial_liquidacion = '" . $num_liquidacion . "' and pagos.id_forma_pago=formas.codigo_pago ";
	$query_pago = mysqli_query($con, $sql_pago);
	$row_pago = mysqli_fetch_array($query_pago);
	$forma_de_pago = $row_pago['nombre_pago'];

?>
	<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-info" role="alert">
		<b>Fecha:</b> <?php echo date("d/m/Y", strtotime($fecha)); ?> <b>Proveedor: </b><?php echo $proveedor; ?> <b>Documento: </b><?php echo $liquidacion; ?> <br><b>Aut. SRI: </b><?php echo $aut_sri; ?>
	</div>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table style="margin-bottom: 5px; margin-top: -10px; height: 14%" class="table table-bordered">
				<tr class="info">
					<th>Producto</th>
					<th>Cantidad</th>
					<th>Precio</th>
					<th>Descuento</th>
					<th>Subtotal</th>
					<th>Tarifa IVA</th>
				</tr>
				<?php
				$query = mysqli_query($con, "SELECT cue.nombre_producto as producto, 
					cue.cantidad as cantidad, cue.valor_unitario as precio, cue.subtotal as subtotal, 
					ti.tarifa as iva, cue.descuento as descuento
					FROM encabezado_liquidacion as enc INNER JOIN cuerpo_liquidacion as cue
					ON cue.serie_liquidacion=enc.serie_liquidacion and cue.secuencial_liquidacion = enc.secuencial_liquidacion and 
					cue.ruc_empresa=enc.ruc_empresa INNER JOIN tarifa_iva as ti ON
					cue.tarifa_iva = ti.codigo WHERE enc.id_encabezado_liq = '" . $id . "' ");

				while ($row = mysqli_fetch_array($query)) {
					$nombre_producto = $row['producto'];
					$cantidad_producto = number_format($row['cantidad'], 2, '.', '');
					$precio_producto = number_format($row["precio"], 2, '.', '');
					$descuento = number_format($row["descuento"], 2, '.', '');
					$subtotal = number_format($row["subtotal"] - $descuento, 2, '.', '');
					$tarifa_iva = $row['iva'];
				?>
					<tr>
						<td><?php echo $nombre_producto; ?></td>
						<td class="text-right"><?php echo $cantidad_producto; ?></td>
						<td class="text-right"><?php echo $precio_producto; ?></td>
						<td class="text-right"><?php echo $descuento; ?></td>
						<td class="text-right"><?php echo $subtotal; ?></td>
						<td class="text-right"><?php echo $tarifa_iva; ?></td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>

	<div class="row">
		<div class="col-xs-6">
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table">
						<tr class="info">
							<th>Concepto</th>
							<th>Detalle</th>
						</tr>
						<?php
						while ($row_detalle = mysqli_fetch_array($query_detalle)) {
							$concepto = $row_detalle['adicional_concepto'];
							$descripcion = $row_detalle['adicional_descripcion'];
						?>
							<tr>
								<td><?php echo $concepto; ?></td>
								<td><?php echo $descripcion; ?></td>
							</tr>
						<?php
						}
						?>
						<tr>
							<td>Forma Pago</td>
							<td><?php echo $forma_de_pago; ?></td>
						</tr>
					</table>
				</div>
			</div>
		</div>

		<!-- para agregar los subtotales-->
		<?php
		$subtotal_general = 0;
		$total_descuento = 0;
		$sql_factura = mysqli_query($con, "SELECT ROUND(sum(subtotal - descuento),2) as subtotal_general, round(sum(descuento),2) as total_descuento 
		FROM cuerpo_liquidacion WHERE ruc_empresa='" . $ruc_empresa . "' and serie_liquidacion = '" . $ser_liquidacion . "' and secuencial_liquidacion='" . $num_liquidacion . "'");
		$row_subtotal = mysqli_fetch_array($sql_factura);
		$subtotal_general = $row_subtotal['subtotal_general'];
		$total_descuento = $row_subtotal['total_descuento'];
		?>

		<div class="col-md-6">
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table">
						<tr class="info">
							<td class='text-right'>SUBTOTAL GENERAL: </td>
							<td class='text-right'><?php echo number_format($subtotal_general, 2, '.', ''); ?></td>
							<td></td>
							<td></td>
						</tr>
						<?php
						//PARA MOSTRAR LOS NOMBRES DE CADA TARIFA DE IVA Y LOS VALORES DE CADA SUBTOTAL
						$subtotal_tarifa_iva = 0;
						$sql = mysqli_query($con, "SELECT ti.tarifa as tarifa, 
						round(sum(cl.subtotal - cl.descuento),2) as suma_tarifa_iva 
						FROM cuerpo_liquidacion cl, tarifa_iva ti WHERE cl.ruc_empresa= '" . $ruc_empresa . "' and cl.serie_liquidacion= '" . $ser_liquidacion . "' and cl.secuencial_liquidacion ='" . $num_liquidacion . "' and ti.codigo = cl.tarifa_iva group by cl.tarifa_iva ");
						while ($row = mysqli_fetch_array($sql)) {
							$nombre_tarifa_iva = strtoupper($row["tarifa"]);
							$subtotal_tarifa_iva = number_format($row['suma_tarifa_iva'], 2, '.', '');
						?>
							<tr class="info">
								<td class='text-right'>SUBTOTAL <?php echo ($nombre_tarifa_iva); ?>:</td>
								<td class='text-right'><?php echo number_format($subtotal_tarifa_iva, 2, '.', ''); ?></td>
								<td></td>
								<td></td>
							</tr>

						<?php
						}
						?>
						<tr class="info">
							<td class='text-right'>TOTAL DESCUENTO: </td>
							<td class='text-right'><?php echo number_format($total_descuento, 2, '.', ''); ?></td>
							<td></td>
							<td></td>
						</tr>
						<?php
						//PARA MOSTRAR LOS IVAS
						$total_iva = 0;
						$suma_iva = 0;
						$sql = mysqli_query($con, "SELECT ti.tarifa as tarifa, 
						round(sum((cl.cantidad * cl.valor_unitario - cl.descuento) * ti.porcentaje_iva /100),2) as total_iva 
						FROM cuerpo_liquidacion as cl
						INNER JOIN tarifa_iva as ti ON ti.codigo = cl.tarifa_iva
						 WHERE cl.ruc_empresa= '" . $ruc_empresa . "' and cl.serie_liquidacion= '" . $ser_liquidacion . "' 
						 and cl.secuencial_liquidacion ='" . $num_liquidacion . "' 
						 and ti.porcentaje_iva > 0 group by cl.tarifa_iva ");
						while ($row = mysqli_fetch_array($sql)) {
							$nombre_porcentaje_iva = strtoupper($row["tarifa"]);
							$total_iva = $row['total_iva'];
							$suma_iva += $row['total_iva'];
						?>
							<tr class="info">
								<td class='text-right'>IVA <?php echo ($nombre_porcentaje_iva); ?>:</td>
								<td class='text-right'><?php echo number_format($total_iva, 2, '.', ''); ?></td>
								<td></td>
								<td></td>
							</tr>
						<?php
						}
						?>

						<tr class="info">
							<td class='text-right'>TOTAL: </td>
							<td class='text-right'><?php echo number_format($subtotal_general + $suma_iva, 2, '.', ''); ?></td>
							<td></td>
							<td></td>
						</tr>
					</table>
				</div>
			</div>
		</div>
	</div>
<?php
}

//para mostrar detalle de proformas
if ($action == 'proformas') {
	$codigo_unico = $_GET['codigo_unico'];
	//detalle de valores de la proforma
	$query = mysqli_query($con, "SELECT cp.nombre_producto as producto, 
	cp.cantidad as cantidad, cp.valor_unitario as precio, cp.subtotal as subtotal, 
	ti.tarifa as iva, cp.descuento as descuento, med.abre_medida as abre_medida 
	FROM cuerpo_proforma as cp 
	INNER JOIN tarifa_iva as ti 
	ON cp.tarifa_iva = ti.codigo
	LEFT JOIN unidad_medida as med
	ON cp.id_medida_salida = med.id_medida
	WHERE cp.ruc_empresa = '" . $ruc_empresa . "' and cp.codigo_unico = '" . $codigo_unico . "' ");

	//detalle de encabezado de la proforma
	$query_encabezado = mysqli_query($con, "SELECT ep.factura_venta as factura_venta, 
	ep.id_encabezado_proforma as id_encabezado_proforma, ep.fecha_proforma as fecha, 
	ep.total_proforma as total, cl.nombre as cliente, ep.serie_proforma as ser, ep.secuencial_proforma as secu 
	FROM encabezado_proforma ep, clientes cl 
	WHERE ep.ruc_empresa = '" . $ruc_empresa . "' and ep.codigo_unico = '" . $codigo_unico . "' and ep.id_cliente = cl.id ");
	$encabezado_proforma = mysqli_fetch_array($query_encabezado);
	$cliente = $encabezado_proforma['cliente'];
	$ser_proforma = $encabezado_proforma['ser'];
	$num_proforma = $encabezado_proforma['secu'];
	$factura = $encabezado_proforma['factura_venta'];
	$proforma = $ser_proforma . "-" . str_pad($num_proforma, 9, "000000000", STR_PAD_LEFT);
	$fecha = $encabezado_proforma['fecha'];
	$total = $encabezado_proforma['total'];
	$id_encabezado_proforma = $encabezado_proforma['id_encabezado_proforma'];

	//para saber el detalle adicional de cada factura
	$sql_detalle = "SELECT * FROM detalle_adicional_proforma WHERE ruc_empresa = '" . $ruc_empresa . "' and codigo_unico = '" . $codigo_unico . "' ";
	$query_detalle = mysqli_query($con, $sql_detalle);

	//para mostrar la forma de pago y tiempo
?>
	<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-info" role="alert">
		<b>Fecha:</b> <?php echo date("d/m/Y", strtotime($fecha)); ?> <b>Proforma: </b><?php echo $proforma; ?> <b>Factura: </b><?php echo $factura; ?> <b>Cliente: </b><?php echo $cliente; ?>
	</div>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table style="margin-bottom: 5px; margin-top: -10px; height: 14%" class="table table-bordered">
				<tr class="info">
					<th>Producto</th>
					<th>Medida</th>
					<th>Cantidad</th>
					<th>Precio</th>
					<th>Descuento</th>
					<th>Subtotal</th>
					<th>Tarifa_IVA</th>
				</tr>
				<?php
				while ($row = mysqli_fetch_array($query)) {
					$nombre_producto = $row['producto'];
					$cantidad_producto = number_format($row['cantidad'], 4, '.', '');
					$precio_producto = number_format($row["precio"], 4, '.', '');
					$descuento = number_format($row["descuento"], 2, '.', '');
					$subtotal = number_format($row["subtotal"] - $descuento, 2, '.', '');
					$tarifa_iva = $row['iva'];
					$medida = $row['abre_medida'];
				?>
					<tr>
						<td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
						<td style="padding: 2px;"><?php echo $medida; ?></td>
						<td style="padding: 2px;" class="text-right"><?php echo $cantidad_producto; ?></td>
						<td style="padding: 2px;" class="text-right"><?php echo $precio_producto; ?></td>
						<td style="padding: 2px;" class="text-right"><?php echo $descuento; ?></td>
						<td style="padding: 2px;" class="text-right"><?php echo $subtotal; ?></td>
						<td style="padding: 2px;" class="text-right"><?php echo $tarifa_iva; ?></td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>

	<div class="row">
		<div class="col-xs-6">
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table table-bordered">
						<tr class="info">
							<th style="padding: 2px;">Concepto</th>
							<th style="padding: 2px;">Detalle</th>
						</tr>
						<?php
						while ($row_detalle = mysqli_fetch_array($query_detalle)) {
							$concepto = $row_detalle['adicional_concepto'];
							$descripcion = $row_detalle['adicional_descripcion'];
						?>
							<tr>
								<td><?php echo $concepto; ?></td>
								<td><?php echo $descripcion; ?></td>
							</tr>
						<?php
						}
						?>

					</table>
				</div>
			</div>
		</div>

		<!-- para agregar los subtotales-->
		<?php
		$subtotal_general = 0;
		$total_descuento = 0;
		$sql_proforma = mysqli_query($con, "SELECT round(sum(subtotal - descuento),2) as subtotal_general, 
		round(sum(descuento),2) as total_descuento 
		FROM cuerpo_proforma WHERE ruc_empresa='" . $ruc_empresa . "' and codigo_unico = '" . $codigo_unico . "'");
		$row_subtotal = mysqli_fetch_array($sql_proforma);
		$subtotal_general = $row_subtotal['subtotal_general'];
		$total_descuento = $row_subtotal['total_descuento'];
		?>

		<div class="col-xs-6">
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table">
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>SUBTOTAL GENERAL: </td>
							<td style="padding: 2px;" class='text-right'><?php echo $subtotal_general; ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
						<?php
						//PARA MOSTRAR LOS NOMBRES DE CADA TARIFA DE IVA Y LOS VALORES DE CADA SUBTOTAL
						$subtotal_tarifa_iva = 0;
						$sql = mysqli_query($con, "SELECT ti.tarifa as tarifa, 
						round(sum(cf.cantidad * cf.valor_unitario - cf.descuento),2) as suma_tarifa_iva 
						FROM cuerpo_proforma as cf INNER JOIN tarifa_iva as ti ON ti.codigo = cf.tarifa_iva 
						WHERE cf.ruc_empresa= '" . $ruc_empresa . "' and cf.codigo_unico= '" . $codigo_unico . "' group by cf.tarifa_iva ");
						while ($row = mysqli_fetch_assoc($sql)) {
							$nombre_tarifa_iva = strtoupper($row["tarifa"]);
							$subtotal_tarifa_iva = $row['suma_tarifa_iva'];
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>SUBTOTAL <?php echo ($nombre_tarifa_iva); ?>:</td>
								<td style="padding: 2px;" class='text-right'><?php echo number_format($subtotal_tarifa_iva, 2, '.', ''); ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>

						<?php
						}
						?>
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>TOTAL DESCUENTO: </td>
							<td style="padding: 2px;" class='text-right'><?php echo $total_descuento; ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
						<?php
						//PARA MOSTRAR LOS IVAS
						$total_iva = 0;
						$suma_iva = 0;
						$sql = mysqli_query($con, "SELECT ti.tarifa as tarifa, 
						  round(sum((cf.cantidad * cf.valor_unitario - cf.descuento) * (ti.porcentaje_iva/100)),2) as total_iva 
						  FROM cuerpo_proforma as cf INNER JOIN tarifa_iva as ti ON ti.codigo = cf.tarifa_iva 
						  WHERE cf.ruc_empresa= '" . $ruc_empresa . "' 
						  and cf.codigo_unico= '" . $codigo_unico . "' and ti.porcentaje_iva > 0 group by cf.tarifa_iva ");
						while ($row = mysqli_fetch_assoc($sql)) {
							$nombre_porcentaje_iva = strtoupper($row["tarifa"]);
							$total_iva = $row['total_iva'];
							$suma_iva += $row['total_iva'];
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>IVA <?php echo ($nombre_porcentaje_iva); ?>:</td>
								<td style="padding: 2px;" class='text-right'><?php echo $total_iva; ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						}
						?>
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>TOTAL: </td>
							<td style="padding: 2px;" class='text-right'><?php echo number_format($subtotal_general + $suma_iva, 2, '.', ''); ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
					</table>
				</div>
			</div>
		</div>
	</div>
<?php
}


//para mostrar detalle de notas de credito
if ($action == 'notas_credito') {
	$serie = $_GET['serie_nc'];
	$secuencial = $_GET['secuencial_nc'];
	//detalle de valores de la nc
	$query = mysqli_query($con, "SELECT df.codigo_producto as codigo, df.nombre_producto as producto, 
	df.cantidad_nc as cantidad, df.valor_unitario_nc as precio, df.subtotal_nc as subtotal, ti.tarifa as iva, 
	df.descuento as descuento FROM cuerpo_nc as df INNER JOIN tarifa_iva as ti ON df.tarifa_iva = ti.codigo 
	WHERE df.ruc_empresa = '" . $ruc_empresa . "' and df.serie_nc = '" . $serie . "' and df.secuencial_nc = '" . $secuencial . "' ");

	//detalle de encabezado de la nc
	$sql_encabezado = mysqli_query($con, "SELECT ef.motivo as motivo, ef.factura_modificada as factura_modificada, 
	ef.id_encabezado_nc as id_encabezado_nc,ef.id_registro_contable as id_registro_contable, ef.fecha_nc as fecha, 
	ef.total_nc as total, cl.nombre as cliente, ef.serie_nc as ser, ef.secuencial_nc as secu 
	FROM encabezado_nc as ef INNER JOIN clientes as cl ON ef.id_cliente = cl.id WHERE ef.ruc_empresa = '" . $ruc_empresa . "' 
	and ef.serie_nc = '" . $serie . "' and ef.secuencial_nc = '" . $secuencial . "' ");
	$encabezado_nc = mysqli_fetch_array($sql_encabezado);
	$cliente = $encabezado_nc['cliente'];
	$ser_nc = $encabezado_nc['ser'];
	$num_nc = $encabezado_nc['secu'];
	$nc = $ser_nc . "-" . str_pad($num_nc, 9, "000000000", STR_PAD_LEFT);
	$fecha = $encabezado_nc['fecha'];
	$total = $encabezado_nc['total'];
	$id_registro_contable = $encabezado_nc['id_registro_contable'];
	$id_encabezado_nc = $encabezado_nc['id_encabezado_nc'];
	$factura = $encabezado_nc['factura_modificada'];
	$motivo = $encabezado_nc['motivo'];

	$query_vendedor = mysqli_query($con, "SELECT * FROM vendedores_ncv WHERE id_ncv = '" . $id_encabezado_nc . "' ");
	$row_vendedor = mysqli_fetch_array($query_vendedor);
	$id_vendedor = isset($row_vendedor['id_vendedor']) ? $row_vendedor['id_vendedor'] : "";

	//para saber el detalle adicional de cada nc
	$sql_detalle = "SELECT * FROM detalle_adicional_nc WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_nc = '" . $serie . "' and secuencial_nc = '" . $secuencial . "' ";
	$query_detalle = mysqli_query($con, $sql_detalle);

?>
	<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-info" role="alert">
		<b>Fecha:</b> <?php echo date("d/m/Y", strtotime($fecha)); ?> <b>NC: </b><?php echo $nc; ?> <b>Factura: </b><?php echo $factura; ?> <b>Cliente: </b><?php echo $cliente; ?>
		<b>Motivo: </b><?php echo $motivo; ?>
	</div>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table style="margin-bottom: 5px; margin-top: -10px; height: 14%" class="table table-bordered">
				<tr class="info">
					<th>Código</th>
					<th>Producto</th>
					<th>Cantidad</th>
					<th>Precio</th>
					<th>Descuento</th>
					<th>Subtotal</th>
					<th>Tarifa_IVA</th>
				</tr>
				<?php
				while ($row = mysqli_fetch_array($query)) {
					$codigo_producto = $row['codigo'];
					$nombre_producto = $row['producto'];
					$cantidad_producto = number_format($row['cantidad'], 4, '.', '');
					$precio_producto = number_format($row["precio"], 4, '.', '');
					$descuento = number_format($row["descuento"], 2, '.', '');
					$subtotal = number_format($row["subtotal"] - $descuento, 2, '.', '');
					$tarifa_iva = $row['iva'];
				?>
					<tr>
						<td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
						<td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
						<td style="padding: 2px;"><?php echo $cantidad_producto; ?></td>
						<td style="padding: 2px;"><?php echo $precio_producto; ?></td>
						<td style="padding: 2px;"><?php echo $descuento; ?></td>
						<td style="padding: 2px;"><?php echo $subtotal; ?></td>
						<td style="padding: 2px;"><?php echo $tarifa_iva; ?></td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>

	<div class="row">
		<div class="col-xs-6">
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table table-bordered">
						<tr class="info">
							<th style="padding: 2px;">Concepto</th>
							<th style="padding: 2px;">Detalle</th>
						</tr>
						<?php
						while ($row_detalle = mysqli_fetch_array($query_detalle)) {
							$concepto = $row_detalle['adicional_concepto'];
							$descripcion = $row_detalle['adicional_descripcion'];
						?>
							<tr>
								<td><?php echo $concepto; ?></td>
								<td><?php echo $descripcion; ?></td>
							</tr>
						<?php
						}
						?>
						<tr class="info">
							<th style="padding: 2px; text-align: right;">Asesor</th>
							<td style="padding: 2px;">
								<input type="hidden" value="<?php echo $id_encabezado_nc ?>" id="id_encabezado_ncv">
								<select class="form-control input-sm" name="vendedor_ncv" id="vendedor_ncv">
									<option value="0">Ninguno</option>
									<?php
									$vendedores = mysqli_query($con, "SELECT * FROM vendedores where ruc_empresa ='" . $ruc_empresa . "' and status = '1' order by nombre asc ");
									while ($row_vendedores = mysqli_fetch_assoc($vendedores)) {
										if ($id_vendedor == $row_vendedores['id_vendedor']) {
									?>
											<option value="<?php echo $id_vendedor ?>" selected><?php echo $row_vendedores['nombre'] ?></option>
										<?php
										} else {
										?>
											<option value="<?php echo $row_vendedores['id_vendedor'] ?>"><?php echo $row_vendedores['nombre'] ?></option>
									<?php
										}
									}
									?>
								</select>

							</td>
						</tr>
					</table>
				</div>
			</div>
		</div>

		<script>
			$(function() {
				$('#vendedor_ncv').change(function() {
					var id_vendedor = $("#vendedor_ncv").val();
					var id_documento = $("#id_encabezado_ncv").val();
					$.ajax({
						type: "POST",
						url: "../ajax/detalle_documento.php",
						data: "action=actualiza_vendedor_ncv&id_vendedor=" + id_vendedor + "&id_documento=" + id_documento,
						success: function(datos) {
							$.notify('Vendedor actualizado', 'success');
						}
					});
				});
			});
		</script>


		<!-- para agregar los subtotales-->
		<?php
		$subtotal_general = 0;
		$total_descuento = 0;
		$sql_nc = mysqli_query($con, "SELECT round(sum(subtotal_nc - descuento),2) as subtotal_general, 
		round(sum(descuento),2) as total_descuento 
		FROM cuerpo_nc WHERE ruc_empresa='" . $ruc_empresa . "' and serie_nc = '" . $serie . "' and secuencial_nc='" . $secuencial . "'");
		$row_subtotal = mysqli_fetch_array($sql_nc);
		$subtotal_general = $row_subtotal['subtotal_general'];
		$total_descuento = $row_subtotal['total_descuento'];
		?>

		<div class="col-xs-6">
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table">
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>SUBTOTAL GENERAL: </td>
							<td style="padding: 2px;" class='text-center'><?php echo $subtotal_general; ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
						<?php
						//PARA MOSTRAR LOS NOMBRES DE CADA TARIFA DE IVA Y LOS VALORES DE CADA SUBTOTAL
						$subtotal_tarifa_iva = 0;
						$sql = mysqli_query($con, "SELECT ti.tarifa as tarifa, 
						round(sum(cf.subtotal_nc - cf.descuento),2) as suma_tarifa_iva 
						FROM cuerpo_nc as cf INNER JOIN tarifa_iva as ti ON ti.codigo = cf.tarifa_iva WHERE cf.ruc_empresa= '" . $ruc_empresa . "' 
						and cf.serie_nc= '" . $serie . "' and cf.secuencial_nc ='" . $secuencial . "' group by cf.tarifa_iva ");
						while ($row = mysqli_fetch_array($sql)) {
							$nombre_tarifa_iva = strtoupper($row["tarifa"]);
							$subtotal_tarifa_iva = $row['suma_tarifa_iva'];
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>SUBTOTAL <?php echo ($nombre_tarifa_iva); ?>:</td>
								<td style="padding: 2px;" class='text-center'><?php echo $subtotal_tarifa_iva; ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>

						<?php
						}
						?>
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>TOTAL DESCUENTO: </td>
							<td style="padding: 2px;" class='text-center'><?php echo $total_descuento; ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
						<?php
						//PARA MOSTRAR LOS IVAS
						$total_iva = 0;
						$suma_iva = 0;
						$sql = mysqli_query($con, "SELECT ti.tarifa as tarifa,
					round(sum((cf.cantidad_nc * cf.valor_unitario_nc - cf.descuento) * (ti.porcentaje_iva /100)),2)  as total_iva
						FROM cuerpo_nc as cf 
						INNER JOIN tarifa_iva as ti ON ti.codigo = cf.tarifa_iva 
						WHERE cf.ruc_empresa= '" . $ruc_empresa . "' 
						and cf.serie_nc= '" . $serie . "' and cf.secuencial_nc ='" . $secuencial . "' and ti.porcentaje_iva > 0 group by cf.tarifa_iva ");
						while ($row = mysqli_fetch_array($sql)) {
							$nombre_porcentaje_iva = strtoupper($row["tarifa"]);
							$total_iva = $row['total_iva'];
							$suma_iva += $row['total_iva'];
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>IVA <?php echo ($nombre_porcentaje_iva); ?>:</td>
								<td style="padding: 2px;" class='text-center'><?php echo $total_iva; ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						}

						?>
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>TOTAL: </td>
							<td style="padding: 2px;" class='text-center'><?php echo number_format($subtotal_general + $suma_iva, 2, '.', ''); ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
					</table>
				</div>
			</div>
		</div>
	</div>
<?php
	echo detalle_asiento_contable($con, $ruc_empresa, $id_registro_contable, 'NC_VENTAS');
}

//detalle de recibos de venta
if ($action == 'recibo_venta') {
	$id_encabezado_recibo = $_GET['id'];
	$sql_encabezado = "SELECT ef.fecha_registro as registro, ef.id_encabezado_recibo as id_encabezado_recibo,
	ef.id_registro_contable as id_registro_contable, ef.fecha_recibo as fecha, ef.total_recibo as total, 
	cl.nombre as cliente, ef.serie_recibo as ser, ef.secuencial_recibo as secu, ef.propina as propina, 
	ef.tasa_turistica as tasa, cl.email as email FROM encabezado_recibo as ef 
	INNER JOIN clientes as cl ON cl.id=ef.id_cliente WHERE ef.id_encabezado_recibo = '" . $id_encabezado_recibo . "' ";
	$query_encabezado = mysqli_query($con, $sql_encabezado);
	$encabezado_recibo = mysqli_fetch_array($query_encabezado);
	$cliente = $encabezado_recibo['cliente'];
	$ser_recibo = $encabezado_recibo['ser'];
	$num_recibo = $encabezado_recibo['secu'];
	$recibo = $ser_recibo . "-" . str_pad($num_recibo, 9, "000000000", STR_PAD_LEFT);
	$fecha = $encabezado_recibo['fecha'];
	$fecha_registro = $encabezado_recibo['registro'];
	$total = $encabezado_recibo['total'];
	$total_propina = $encabezado_recibo['propina'];
	$total_tasa = $encabezado_recibo['tasa'];
	$id_registro_contable = $encabezado_recibo['id_registro_contable'];
	$email = $encabezado_recibo['email'];

	$impuestos_recibo = decimales($con, $ruc_empresa, $ser_recibo)['impuestos_recibo'];

	$query = mysqli_query($con, "SELECT df.adicional as adicional , df.codigo_producto as codigo, df.nombre_producto as producto, 
	df.cantidad as cantidad, df.valor_unitario as precio, df.subtotal as subtotal, ti.tarifa as iva, 
	df.descuento as descuento, df.id_medida as medida FROM cuerpo_recibo as df INNER JOIN tarifa_iva as ti ON df.tarifa_iva = ti.codigo WHERE df.id_encabezado_recibo = '" . $id_encabezado_recibo . "' ");

	$sql_trabaja_inventario = mysqli_query($con, "SELECT * FROM configuracion_facturacion where ruc_empresa ='" . $ruc_empresa . "' and serie_sucursal ='" . $ser_recibo . "'");
	$row_trabaja_inventario = mysqli_fetch_array($sql_trabaja_inventario);
	$trabaja_inventario = isset($row_trabaja_inventario['inventario']) == 'SI' ? "1" : "2";

	//vendedor
	$query_vendedor = mysqli_query($con, "SELECT * FROM vendedores_recibos WHERE id_recibo = '" . $id_encabezado_recibo . "' ");
	$row_vendedor = mysqli_fetch_array($query_vendedor);
	$id_vendedor = isset($row_vendedor['id_vendedor']) ? $row_vendedor['id_vendedor'] : "";

	//para saber el detalle adicional de cada recibo
	$sql_detalle = "SELECT * FROM detalle_adicional_recibo WHERE id_encabezado_recibo = '" . $id_encabezado_recibo . "' ";
	$query_detalle = mysqli_query($con, $sql_detalle);

?>
	<div style="padding: 0px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-info" role="alert">
		<div class="panel-heading" style="padding: 4px;">
			<div class="btn-group pull-center">
				<button type="button" class="btn btn-info btn-sm" id="generarFactura" onclick="generar_factura('<?php echo $id_encabezado_recibo; ?>');" title="Facturar recibo de venta"><span class="glyphicon glyphicon-duplicate"></span> Facturar</button>
			</div>
			<div class="btn-group pull-center">
				<button type="button" class="btn btn-info btn-sm" onclick="enviar_recibo_mail('<?php echo $id_encabezado_recibo; ?>', '<?php echo $email; ?>');" title="Enviar recibo por mail" data-toggle="modal" data-target="#EnviarDocumentosMail"><span class="glyphicon glyphicon-envelope"></span></button>
			</div>
		</div>
	</div>
	<div style="padding: 1px; margin-bottom: 5px;" class="alert alert-info" role="alert">
		<b>Fecha:</b> <?php echo date("d/m/Y", strtotime($fecha)); ?> <b>Hora:</b> <?php echo date("H:i:s", strtotime($fecha_registro)); ?> <b>Número: </b><?php echo $recibo; ?> <b>Cliente: </b><?php echo $cliente; ?>
	</div>

	<div class="panel panel-info" style="height: 150px; overflow-y: auto; margin-bottom: 5px;">
		<div class="table-responsive">
			<table style="margin-bottom: 5px; margin-top: -10px;" class="table table-bordered">
				<tr class="info">
					<th>Código</th>
					<th>Producto</th>
					<th>Adicional</th>
					<th>Medida</th>
					<th>Cantidad</th>
					<th>Precio</th>
					<th>Descuento</th>
					<th>Subtotal</th>
					<th>Tarifa_IVA</th>
				</tr>
				<?php
				while ($row = mysqli_fetch_array($query)) {
					$codigo_producto = $row['codigo'];
					$nombre_producto = $row['producto'];
					$adicional = $row['adicional'] != "0" ? $row['adicional'] : "";
					$cantidad_producto = number_format($row['cantidad'], 4, '.', '');
					$precio_producto = number_format($row["precio"], 4, '.', '');
					$descuento = number_format($row["descuento"], 2, '.', '');
					$subtotal = number_format($row["subtotal"] - $descuento, 2, '.', '');
					$tarifa_iva = $row['iva'];
					$id_medida = $row['medida'];
					$sql_medida = mysqli_query($con, "SELECT * FROM unidad_medida WHERE id_medida ='" . $id_medida . "' ");
					$row_medida = mysqli_fetch_array($sql_medida);
					$medida = isset($row_medida['abre_medida']) ? $row_medida['abre_medida'] : "";
				?>
					<tr>
						<td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
						<td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
						<td style="padding: 2px;"><?php echo $adicional; ?></td>
						<td style="padding: 2px;"><?php echo $medida; ?></td>
						<td style="padding: 2px;" align="right"><?php echo $cantidad_producto; ?></td>
						<td style="padding: 2px;" align="right"><?php echo $precio_producto; ?></td>
						<td style="padding: 2px;" align="right"><?php echo $descuento; ?></td>
						<td style="padding: 2px;" align="right"><?php echo $subtotal; ?></td>
						<td style="padding: 2px;" align="right"><?php echo $tarifa_iva; ?></td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>

	<div class="row">
		<div class="col-xs-6">
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table table-bordered">
						<tr class="info">
							<th style="padding: 2px;">Concepto</th>
							<th style="padding: 2px;">Detalle</th>
						</tr>
						<?php
						while ($row_detalle = mysqli_fetch_array($query_detalle)) {
							$concepto = $row_detalle['adicional_concepto'];
							$descripcion = $row_detalle['adicional_descripcion'];
						?>
							<tr>
								<td style="padding: 2px;"><?php echo $concepto; ?></td>
								<td style="padding: 2px;"><?php echo $descripcion; ?></td>
							</tr>
						<?php
						}
						?>
						<tr class="info">
							<th style="padding: 2px; text-align: right;">Asesor</th>
							<td style="padding: 2px;">
								<input type="hidden" value="<?php echo $id_encabezado_recibo ?>" id="id_encabezado_recibo">
								<select class="form-control input-sm" name="vendedor_recibo" id="vendedor_recibo">
									<option value="0">Ninguno</option>
									<?php
									$vendedores = mysqli_query($con, "SELECT * FROM vendedores where ruc_empresa ='" . $ruc_empresa . "' and status='1' order by nombre asc ");
									while ($row_vendedores = mysqli_fetch_assoc($vendedores)) {
										if ($id_vendedor == $row_vendedores['id_vendedor']) {
									?>
											<option value="<?php echo $id_vendedor ?>" selected><?php echo $row_vendedores['nombre'] ?></option>
										<?php
										} else {
										?>
											<option value="<?php echo $row_vendedores['id_vendedor'] ?>"><?php echo $row_vendedores['nombre'] ?></option>
									<?php
										}
									}
									?>
								</select>

							</td>
						</tr>
					</table>
				</div>
			</div>
		</div>
		<script>
			$(function() {
				$('#vendedor_recibo').change(function() {
					var id_vendedor = $("#vendedor_recibo").val();
					var id_documento = $("#id_encabezado_recibo").val();
					$.ajax({
						type: "POST",
						url: "../ajax/detalle_documento.php",
						data: "action=actualiza_vendedor_recibo&id_vendedor=" + id_vendedor + "&id_documento=" + id_documento,
						success: function(datos) {
							$.notify('Vendedor actualizado', 'success');
						}
					});
				});
			});
		</script>
		<!-- para agregar los subtotales-->
		<div class="col-xs-6">
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table">

						<?php
						$subtotal = 0;
						if ($impuestos_recibo == '2') {
							$detalle_subtotal = mysqli_query($con, "SELECT tar.tarifa as tarifa, 
							round(sum(cue.subtotal - cue.descuento),2) as subtotal
							 FROM cuerpo_recibo as cue INNER JOIN tarifa_iva as tar ON tar.codigo=cue.tarifa_iva
							 WHERE cue.id_encabezado_recibo = '" . $id_encabezado_recibo . "' group by cue.tarifa_iva ");
							while ($row_detalle = mysqli_fetch_assoc($detalle_subtotal)) {
								$subtotal_tarifa = $row_detalle['tarifa'];
								$subtotal = $row_detalle['subtotal'];
						?>
								<tr class="info">
									<td class="text-right" style="padding: 2px;">Subtotal <?php echo ($subtotal_tarifa); ?>:</td>
									<td class="text-right" style="padding: 2px;"><?php echo $subtotal; ?></td>
									<td style="padding: 2px;"></td>
									<td style="padding: 2px;"></td>
								</tr>
							<?php
							}

							/* $total_iva = 0;
							$detalle_iva = mysqli_query($con, "SELECT tar.tarifa as tarifa, 
							round(sum((cue.cantidad * cue.valor_unitario - cue.descuento) * (tar.porcentaje_iva/100)),2) as total_iva
							FROM cuerpo_recibo as cue INNER JOIN tarifa_iva as tar ON tar.codigo=cue.tarifa_iva
							WHERE cue.id_encabezado_recibo = '" . $id_encabezado_recibo . "' and tar.porcentaje_iva > 0 group by cue.tarifa_iva ");
							while ($row_detalle = mysqli_fetch_assoc($detalle_iva)) {
								$nombre_tarifa = $row_detalle['tarifa'];
								$total_iva = $row_detalle['total_iva'];
							?>
								<tr class="info">
									<td class="text-right" style="padding: 2px;">IVA <?php echo ($nombre_tarifa); ?>:</td>
									<td class="text-right" style="padding: 2px;"><?php echo $total_iva; ?></td>
									<td style="padding: 2px;"></td>
									<td style="padding: 2px;"></td>
								</tr>
						<?php
							} */
							$total_iva = 0;
							$detalle_iva = mysqli_query($con, "
	SELECT 
		tar.tarifa AS tarifa, 
		tar.porcentaje_iva,
		ROUND(SUM(cue.cantidad * cue.valor_unitario - cue.descuento), 2) AS subtotal
	FROM cuerpo_recibo AS cue
	INNER JOIN tarifa_iva AS tar ON tar.codigo = cue.tarifa_iva
	WHERE 
		cue.id_encabezado_recibo = '" . $id_encabezado_recibo . "' 
		AND tar.porcentaje_iva > 0
	GROUP BY cue.tarifa_iva
");

							while ($row_detalle = mysqli_fetch_assoc($detalle_iva)) {
								$nombre_tarifa = $row_detalle['tarifa'];
								$porcentaje_iva = $row_detalle['porcentaje_iva'];
								$subtotal = $row_detalle['subtotal'];
								$iva_calculado = round($subtotal * $porcentaje_iva / 100, 2);
								$total_iva += $iva_calculado;
							?>
								<tr class="info">
									<td class="text-right" style="padding: 2px;">IVA <?php echo ($nombre_tarifa); ?>:</td>
									<td class="text-right" style="padding: 2px;"><?php echo number_format($iva_calculado, 2); ?></td>
									<td style="padding: 2px;"></td>
									<td style="padding: 2px;"></td>
								</tr>
						<?php
							}
						}

						?>
						<tr class="info">
							<td style="padding: 2px;" align="right">TOTAL: </td>
							<td style="padding: 2px;" align="right"><?php echo number_format($total, 2, '.', ''); ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
					</table>
				</div>
			</div>
		</div>
	</div>
<?php
	echo detalle_asiento_contable($con, $ruc_empresa, $id_registro_contable, 'RECIBOS');
	echo detalle_pago_recibo($con, $ruc_empresa, $id_encabezado_recibo);
}


//para mostrar detalle de facturas de ventas
if ($action == 'facturas_ventas') {
	$id_encabezado_factura = $_GET['id'];
	$sql_encabezado = "SELECT ef.fecha_registro as registro, ef.id_encabezado_factura as id_encabezado_factura,
	ef.id_registro_contable as id_registro_contable, ef.fecha_factura as fecha, ef.total_factura as total, 
	cl.nombre as cliente, ef.serie_factura as ser, ef.secuencial_factura as secu, ef.propina as propina, 
	ef.tasa_turistica as tasa, ef.aut_sri as aut_sri, ef.estado_sri as estado_sri, cl.ruc as ruc_cliente, cl.telefono as telefono 
	FROM encabezado_factura as ef 
	INNER JOIN clientes as cl ON cl.id=ef.id_cliente 
	WHERE ef.id_encabezado_factura = '" . $id_encabezado_factura . "' ";
	$query_encabezado = mysqli_query($con, $sql_encabezado);
	$encabezado_factura = mysqli_fetch_array($query_encabezado);
	$cliente = $encabezado_factura['cliente'];
	$ser_factura = $encabezado_factura['ser'];
	$num_factura = $encabezado_factura['secu'];
	$factura = $ser_factura . "-" . str_pad($num_factura, 9, "000000000", STR_PAD_LEFT);
	$fecha = $encabezado_factura['fecha'];
	$fecha_registro = $encabezado_factura['registro'];
	$total = $encabezado_factura['total'];
	$total_propina = $encabezado_factura['propina'];
	$total_tasa = $encabezado_factura['tasa'];
	$id_registro_contable = $encabezado_factura['id_registro_contable'];
	$ruc_cliente = $encabezado_factura['ruc_cliente'];
	$telefono_whatsapp = $encabezado_factura['telefono'];
	$aut_sri = $encabezado_factura['aut_sri'];
	$estado_sri = $encabezado_factura['estado_sri'] == 'AUTORIZADO' ? "1" : "2";

	$query = mysqli_query($con, "SELECT df.tarifa_bp as adicional , df.codigo_producto as codigo, 
	df.nombre_producto as producto, df.cantidad_factura as cantidad, df.valor_unitario_factura as precio, 
	df.subtotal_factura as subtotal, ti.tarifa as iva, df.descuento as descuento, med.abre_medida as abre_medida 
	FROM cuerpo_factura as df 
	INNER JOIN tarifa_iva as ti
	ON ti.codigo=df.tarifa_iva
	LEFT JOIN unidad_medida as med
	ON med.id_medida = df.id_medida_salida 
	WHERE df.tarifa_iva = ti.codigo and df.ruc_empresa = '" . $ruc_empresa . "' and df.serie_factura = '" . $ser_factura . "' and df.secuencial_factura = '" . $num_factura . "' ");

	$sql_trabaja_inventario = mysqli_query($con, "SELECT * FROM configuracion_facturacion where ruc_empresa ='" . $ruc_empresa . "' and serie_sucursal ='" . $ser_factura . "'");
	$row_trabaja_inventario = mysqli_fetch_array($sql_trabaja_inventario);
	$trabaja_inventario = isset($row_trabaja_inventario['inventario']) == 'SI' ? "1" : "2";

	//vendedor
	$query_vendedor = mysqli_query($con, "SELECT * FROM vendedores_ventas WHERE id_venta = '" . $id_encabezado_factura . "' ");
	$row_vendedor = mysqli_fetch_array($query_vendedor);
	$id_vendedor = isset($row_vendedor['id_vendedor']) ? $row_vendedor['id_vendedor'] : "";

	//para saber el detalle adicional de cada factura
	$sql_detalle = "SELECT * FROM detalle_adicional_factura WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_factura = '" . $ser_factura . "' and secuencial_factura = '" . $num_factura . "' ";
	$query_detalle = mysqli_query($con, $sql_detalle);

	//nombre de la empresa que envia el whatsapp
	$sql_empresa = mysqli_query($con, "SELECT nombre_comercial FROM empresas WHERE ruc = '" . $ruc_empresa . "' ");
	$row_empresa = mysqli_fetch_array($sql_empresa);
	$nombre_comercial_empresa = "Factura de " . $row_empresa['nombre_comercial'];

	//para mostrar la forma de pago y tiempo
	$query_detalle_forma_pago = mysqli_query($con, "SELECT * FROM formas_pago_ventas as pagos LEFT JOIN formas_de_pago as formas ON pagos.id_forma_pago=formas.codigo_pago WHERE pagos.ruc_empresa = '" . $ruc_empresa . "' and pagos.serie_factura = '" . $ser_factura . "' and pagos.secuencial_factura = '" . $num_factura . "' and formas.aplica_a='VENTAS'");

	$query_puerto_whatsapp = mysqli_query($con, "SELECT count(*) FROM puerto_whatsapp WHERE ruc_empresa = '" . $ruc_empresa . "' and  status= '1'");
	$row_whatsapp = mysqli_fetch_row($query_puerto_whatsapp);
	$count_whatsapp = $row_whatsapp[0];
?>

	<div style="padding: 0px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-info" role="alert">
		<div class="panel-heading" style="padding: 4px;">
			<div class="btn-group pull-center">
				<?php
				if ($encabezado_factura['estado_sri'] != 'ANULADA') {
					if (getPermisos($con, $id_usuario, $ruc_empresa, 'facturas')['w'] == 1) {
				?>
						<button type="button" class="btn btn-info btn-sm" id="duplicarFactura" onclick="duplicar_factura('<?php echo $id_encabezado_factura; ?>');" title="Duplicar factura"><span class="glyphicon glyphicon-duplicate"></span> Duplicar</button>
					<?php
					}
				}
				if ($encabezado_factura['estado_sri'] == 'PENDIENTE') {
					?>
					<button type="button" class="btn btn-info btn-sm" id="reciboVenta" onclick="generar_recibo_venta('<?php echo $id_encabezado_factura; ?>');" title="Crear recibo de venta"><span class="glyphicon glyphicon-duplicate"></span> Recibo venta</button>
				<?php
				}
				if ($estado_sri == '1') {
				?>
					<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_factura) ?>&tipo_documento=factura&tipo_archivo=pdf" class="btn btn-default btn-sm" title='Descargar' target="_blank" download><span class="glyphicon glyphicon-cloud-download"></span> Pdf</i> </a>
					<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_factura) ?>&tipo_documento=factura&tipo_archivo=xml" class="btn btn-default btn-sm" title='Descargar' target="_blank" download><span class="glyphicon glyphicon-cloud-download"></span> Xml</i> </a>
					<button type="button" class="btn btn-info btn-sm" onclick="enviar_factura_mail('<?php echo $id_encabezado_factura; ?>');" title="Enviar factura por mail" data-toggle="modal" data-target="#EnviarDocumentosMail"><span class="glyphicon glyphicon-envelope"></span></button>
					<?php
					if ($count_whatsapp >= 1) {
					?>
						<button type="button" class="btn btn-success btn-sm" onclick="enviar_factura_whatsapp('<?php echo $id_encabezado_factura; ?>','<?php echo $telefono_whatsapp; ?>', '<?php echo $nombre_comercial_empresa; ?>');" title="Enviar factura por whatsapp" data-toggle="modal" data-target="#EnviarDocumentosWhatsapp"><img src="../image/whatsapp.png" alt="Logo" width="15px"></button>
					<?php
					}
				}
				if ($encabezado_factura['estado_sri'] == 'PENDIENTE') {
					?>
					<button type="button" class="btn btn-info btn-sm" onclick="generar_pdf('<?php echo $id_encabezado_factura; ?>');" title="Generar pdf y ver como va a quedar la factura"><span class="glyphicon glyphicon-duplicate"></span> Vista previa PDF</button>
				<?php
				}
				?>
				<a onmouseover="this.style.color='green';" onmouseout="this.style.color='black';" onclick="imprimir_ticket('ticket_factura_venta_a2','<?php echo base64_encode($id_encabezado_factura) ?>')" target="_blank" class='btn btn-default btn-sm' title='Descargar ticket pdf'><span class='glyphicon glyphicon-cloud-download'></span> Ticket</i> </a>
			</div>
		</div>
	</div>
	<div style="padding: 1px; margin-bottom: 5px;" class="alert alert-info" role="alert">
		<b>Fecha:</b> <?php echo date("d/m/Y", strtotime($fecha)); ?> <b>Hora:</b> <?php echo date("H:i:s", strtotime($fecha_registro)); ?> <b>Número: </b><?php echo $factura; ?> <b>Cliente: </b><?php echo $cliente; ?> <b>Aut SRI: </b><?php echo $aut_sri; ?>
	</div>

	<div class="panel panel-info" style="height: 150px; overflow-y: auto; margin-bottom: 5px;">
		<div class="table-responsive">
			<table style="margin-bottom: 5px; margin-top: -10px;" class="table table-bordered">
				<tr class="info">
					<th>Código</th>
					<th>Producto</th>
					<th>Adicional</th>
					<th>Medida</th>
					<th>Cantidad</th>
					<th>Precio</th>
					<th>Descuento</th>
					<th>Subtotal</th>
					<th>Tarifa_IVA</th>
				</tr>
				<?php
				while ($row = mysqli_fetch_array($query)) {
					$codigo_producto = $row['codigo'];
					$nombre_producto = $row['producto'];
					$adicional = $row['adicional'] != "0" ? $row['adicional'] : "";
					$cantidad_producto = number_format($row['cantidad'], 4, '.', '');
					$precio_producto = number_format($row['precio'], 4, '.', '');
					$descuento = number_format($row['descuento'], 2, '.', '');
					$subtotal = number_format($row['subtotal'] - $descuento, 2, '.', '');
					$tarifa_iva = $row['iva'];
					$medida = $row['abre_medida'];
				?>
					<tr>
						<td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
						<td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
						<td style="padding: 2px;"><?php echo $adicional; ?></td>
						<td style="padding: 2px;"><?php echo $medida; ?></td>
						<td style="padding: 2px;" align="right"><?php echo $cantidad_producto; ?></td>
						<td style="padding: 2px;" align="right"><?php echo $precio_producto; ?></td>
						<td style="padding: 2px;" align="right"><?php echo $descuento; ?></td>
						<td style="padding: 2px;" align="right"><?php echo $subtotal; ?></td>
						<td style="padding: 2px;" align="right"><?php echo $tarifa_iva; ?></td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>

	<div class="row">
		<div class="col-xs-5">
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table table-bordered">
						<tr class="info">
							<th style="padding: 2px;">Concepto</th>
							<th style="padding: 2px;">Detalle</th>
						</tr>
						<?php
						while ($row_detalle = mysqli_fetch_array($query_detalle)) {
							$concepto = $row_detalle['adicional_concepto'];
							$descripcion = $row_detalle['adicional_descripcion'];
						?>
							<tr>
								<td style="padding: 2px;"><?php echo $concepto; ?></td>
								<td style="padding: 2px;"><?php echo $descripcion; ?></td>
							</tr>
						<?php
						}
						?>
						<tr class="info">
							<th style="padding: 2px; text-align: right;">Asesor</th>
							<td style="padding: 2px;">
								<input type="hidden" value="<?php echo $id_encabezado_factura ?>" id="id_encabezado_venta">
								<select class="form-control input-sm" name="vendedor" id="vendedor">
									<option value="0">Ninguno</option>
									<?php
									$vendedores = mysqli_query($con, "SELECT * FROM vendedores where ruc_empresa ='" . $ruc_empresa . "' and status='1' order by nombre asc ");
									while ($row_vendedores = mysqli_fetch_assoc($vendedores)) {
										if ($id_vendedor == $row_vendedores['id_vendedor']) {
									?>
											<option value="<?php echo $id_vendedor ?>" selected><?php echo $row_vendedores['nombre'] ?></option>
										<?php
										} else {
										?>
											<option value="<?php echo $row_vendedores['id_vendedor'] ?>"><?php echo $row_vendedores['nombre'] ?></option>
									<?php
										}
									}
									?>
								</select>

							</td>
						</tr>
					</table>
				</div>
			</div>
		</div>
		<script>
			$(function() {
				$('#vendedor').change(function() {
					var id_vendedor = $("#vendedor").val();
					var id_documento = $("#id_encabezado_venta").val();
					$.ajax({
						type: "POST",
						url: "../ajax/detalle_documento.php",
						data: "action=actualiza_vendedor&id_vendedor=" + id_vendedor + "&id_documento=" + id_documento,
						success: function(datos) {
							$.notify('Vendedor actualizado', 'success');
						}
					});
				});
			});
		</script>
		<div class="col-xs-3" style="padding: 0px;">
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table table-bordered">
						<tr class="info">
							<th style="padding: 2px;">Formas de pago SRI</th>
							<th style="padding: 2px;">Valor</th>
						</tr>
						<?php
						while ($row_detalle = mysqli_fetch_array($query_detalle_forma_pago)) {
						?>
							<tr>
								<td style="padding: 2px;"><?php echo $row_detalle['nombre_pago']; ?></td>
								<td style="padding: 2px;"><?php echo $row_detalle['valor_pago']; ?></td>
							</tr>
						<?php
						}
						?>
					</table>
				</div>
			</div>
		</div>
		<!-- para agregar los subtotales-->
		<?php
		$subtotal_general = 0;
		$total_descuento = 0;
		$sql_factura = mysqli_query($con, "SELECT round(sum(subtotal_factura - descuento),2) as subtotal_general, round(sum(descuento),2) as total_descuento  FROM cuerpo_factura WHERE ruc_empresa='" . $ruc_empresa . "' and serie_factura = '" . $ser_factura . "' and secuencial_factura='" . $num_factura . "'");
		$row_subtotal = mysqli_fetch_array($sql_factura);
		$subtotal_general = $row_subtotal['subtotal_general'];
		$total_descuento = $row_subtotal['total_descuento'];
		?>

		<div class="col-xs-4">
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table">
						<tr class="info">
							<td style="padding: 2px;" align="right">SUBTOTAL GENERAL: </td>
							<td style="padding: 2px;" align="right"><?php echo $subtotal_general; ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
						<?php
						//PARA MOSTRAR LOS NOMBRES DE CADA TARIFA DE IVA Y LOS VALORES DE CADA SUBTOTAL
						$subtotal_tarifa_iva = 0;
						$sql_tarifa = mysqli_query($con, "SELECT ti.tarifa as tarifa, 
						round(sum(cf.subtotal_factura - cf.descuento),2) as suma_tarifa_iva 
						FROM cuerpo_factura as cf INNER JOIN tarifa_iva as ti ON ti.codigo = cf.tarifa_iva 
						WHERE cf.ruc_empresa= '" . $ruc_empresa . "' and cf.serie_factura= '" . $ser_factura . "' 
						and cf.secuencial_factura ='" . $num_factura . "' group by cf.tarifa_iva ");
						while ($row = mysqli_fetch_assoc($sql_tarifa)) {
							$nombre_tarifa_iva = strtoupper($row['tarifa']);
							$subtotal_tarifa_iva = $row['suma_tarifa_iva'];
						?>
							<tr class="info">
								<td style="padding: 2px;" align="right">SUBTOTAL <?php echo ($nombre_tarifa_iva); ?>:</td>
								<td style="padding: 2px;" align="right"><?php echo $subtotal_tarifa_iva; ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>

						<?php
						}
						?>
						<tr class="info">
							<td style="padding: 2px;" align="right">TOTAL DESCUENTO: </td>
							<td style="padding: 2px;" align="right"><?php echo number_format($total_descuento, 2, '.', ''); ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
						<?php
						//PARA MOSTRAR LOS IVAS
						/* 	$total_iva = 0;
						$suma_iva = 0;
						$sql = mysqli_query($con, "SELECT ti.tarifa as tarifa, 
						 round(sum((cf.cantidad_factura * cf.valor_unitario_factura - cf.descuento) * (ti.porcentaje_iva/100)),2) as total_iva 
						 FROM cuerpo_factura as cf INNER JOIN tarifa_iva as ti ON ti.codigo = cf.tarifa_iva 
						 WHERE cf.ruc_empresa= '" . $ruc_empresa . "' and cf.serie_factura= '" . $ser_factura . "' 
						 and cf.secuencial_factura ='" . $num_factura . "' and ti.porcentaje_iva > 0 group by cf.tarifa_iva ");
						while ($row = mysqli_fetch_array($sql)) {
							$nombre_porcentaje_iva = strtoupper($row['tarifa']);
							$total_iva =  $row['total_iva'];
							$suma_iva +=  $row['total_iva'];
						?>
							<tr class="info">
								<td style="padding: 2px;" align="right">IVA <?php echo ($nombre_porcentaje_iva); ?>:</td>
								<td style="padding: 2px;" align="right"><?php echo $total_iva; ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						} */
						$total_iva = 0;
						$suma_iva = 0;

						$sql = mysqli_query($con, "
	SELECT 
		ti.tarifa AS tarifa,
		ti.porcentaje_iva,
		ROUND(SUM(cf.cantidad_factura * cf.valor_unitario_factura - cf.descuento), 2) AS subtotal
	FROM cuerpo_factura AS cf
	INNER JOIN tarifa_iva AS ti ON ti.codigo = cf.tarifa_iva
	WHERE 
		cf.ruc_empresa = '" . $ruc_empresa . "' 
		AND cf.serie_factura = '" . $ser_factura . "' 
		AND cf.secuencial_factura = '" . $num_factura . "'
		AND ti.porcentaje_iva > 0
	GROUP BY cf.tarifa_iva
");

						while ($row = mysqli_fetch_array($sql)) {
							$nombre_porcentaje_iva = strtoupper($row['tarifa']);
							$porcentaje_iva = $row['porcentaje_iva'];
							$subtotal = $row['subtotal'];
							$total_iva = round($subtotal * $porcentaje_iva / 100, 2);
							$suma_iva += $total_iva;
						?>
							<tr class="info">
								<td style="padding: 2px;" align="right">IVA <?php echo ($nombre_porcentaje_iva); ?>:</td>
								<td style="padding: 2px;" align="right"><?php echo number_format($total_iva, 2); ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						}

						if ($total_propina > 0) {
						?>
							<tr class="info">
								<td style="padding: 2px;" align="right">SERVICIO: </td>
								<td style="padding: 2px;" align="right"><?php echo number_format($total_propina, 2, '.', ''); ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						}
						if ($total_tasa > 0) {
						?>
							<tr class="info">
								<td style="padding: 2px;" align="right">TASA TURISTICA: </td>
								<td style="padding: 2px;" align="right"><?php echo number_format($total_tasa, 2, '.', ''); ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						}
						?>
						<tr class="info">
							<td style="padding: 2px;" align="right">TOTAL: </td>
							<td style="padding: 2px;" align="right"><?php echo number_format($subtotal_general + $suma_iva + $total_propina + $total_tasa, 2, '.', ''); ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
					</table>
				</div>
			</div>
		</div>
	</div>
<?php
	echo detalle_asiento_contable($con, $ruc_empresa, $id_registro_contable, 'VENTAS');
	echo detalle_pago_venta($con, $ruc_empresa, $id_encabezado_factura);
}

function agregar_detalle_compra($codigo_documento)
{
	$con = conenta_login();
	$busca_encabezado = mysqli_query($con, "SELECT * FROM encabezado_compra as enc_com 
	INNER JOIN proveedores as pro ON enc_com.id_proveedor=pro.id_proveedor 
	WHERE enc_com.codigo_documento = '" . $codigo_documento . "' ");
	$row_proveedor = mysqli_fetch_array($busca_encabezado);
	$fecha_compra = $row_proveedor['fecha_compra'];
	$numero_documento = $row_proveedor['numero_documento'];
	$caducidad = $row_proveedor['fecha_caducidad'];
	$desde = $row_proveedor['desde'];
	$hasta = $row_proveedor['hasta'];
	$aut_sri = $row_proveedor['aut_sri'];
	$documento_modificado = $row_proveedor['factura_aplica_nc_nd'];
	$propina = $row_proveedor['propina'];
	$otros_val = $row_proveedor['otros_val'];
	$total_compra = $row_proveedor['total_compra'];
	$id_comprobante = $row_proveedor['id_comprobante'];
	$id_sustento = $row_proveedor['id_sustento'];
	$codigo_contable = $row_proveedor['id_registro_contable'];
	$tipo_comprobante = $row_proveedor['tipo_comprobante'];
	$id_proveedor = $row_proveedor['id_proveedor'];
	$ruc_empresa = $row_proveedor['ruc_empresa'];
	$deducible_en = $row_proveedor['deducible_en'];

	$detalle_encabezado_ret_compra = mysqli_query($con, "SELECT count(secuencial_retencion) as registros 
	FROM encabezado_retencion WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' 
	and numero_comprobante='" . $numero_documento . "' and id_proveedor='" . $id_proveedor . "' and estado_sri !='ANULADA' ");
	$row_encabezado_ret_compras = mysqli_fetch_array($detalle_encabezado_ret_compra);
	$total_registros = $row_encabezado_ret_compras['registros'];

	if ($total_registros > 0) {
		$bloqueo_retencion = "readonly"; //readonly
		$bloqueo_retencion_select = "style='display:none;'";
	} else {
		$bloqueo_retencion = "";
		$bloqueo_retencion_select = "";
	}
?>

	<!--desde aqui el encabezado de la factura de compras -->
	<div class="panel-group" id="accordion_encabezado_compra"><!--style="margin-bottom: 5px; margin-top: -10px;" style="height:35px;"-->
		<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion_encabezado_compra" href="#collapse_encabezado" title="Click aquí para modificar el encabezado del documento">
			<font color="red"><span class="glyphicon glyphicon-arrow-down"></span></font> <b>Proveedor:</b> <?php echo $row_proveedor['razon_social']; ?> <b>Documento: </b><?php echo $row_proveedor['numero_documento']; ?><font color="red"><span class="glyphicon glyphicon-arrow-down"></span></font>
		</a>
		<div id="collapse_encabezado" class="panel-collapse collapse">
			<div class="panel panel-info">
				<form class="form-horizontal" method="POST" id="actualizar_encabezado" name="actualizar_encabezado">
					<div class="modal-body">
						<div class="form-group">
							<div class="col-sm-6">
								<div class="input-group">
									<span class="input-group-addon"><b>Sucursal</b></span>
									<select class="form-control input-sm" name="sucursal_mod" id="sucursal_mod">
										<?php
										$sucursales = mysqli_query($con, "SELECT * FROM sucursales WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' order by nombre_sucursal asc ");
										while ($row_documentos = mysqli_fetch_assoc($sucursales)) {
											if ($ruc_empresa == $row_documentos['ruc_empresa']) {
										?>
												<option value="<?php echo $ruc_empresa ?>" selected><?php echo $row_documentos['nombre_sucursal']; ?></option>
											<?php
											} else {
											?>
												<option value="<?php echo $row_documentos['ruc_empresa'] ?>"><?php echo $row_documentos['nombre_sucursal']; ?></option>
										<?php
											}
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-sm-6">
								<div class="input-group">
									<span class="input-group-addon"><b>Deducible en</b></span>
									<select class="form-control input-sm" name="deducible_mod" id="deducible_mod">
										<?php
										if ($deducible_en == "05") {
										?>
											<option value="05" selected>No deducible o gasto personal</option>
											<option value="04">Deducible para impuestos</option>
										<?php
										} else if ($deducible_en == "04") {
										?>
											<option value="04" selected>Deducible para impuestos</option>
											<option value="05">No deducible o gasto personal</option>
										<?php
										} else {
										?>
											<option value="00" selected>No asignado</option>
											<option value="04">Deducible para impuestos</option>
											<option value="05">No deducible o gasto personal</option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-3">
								<input type="hidden" id="codigo_unico" name="codigo_unico" value="<?php echo $codigo_documento; ?>">
								<input type="hidden" id="codigo_contable" name="codigo_contable" value="<?php echo $codigo_contable; ?>">
								<div class="input-group">
									<span class="input-group-addon"><b>Emisión</b></span>
									<input type="text" class="form-control input-sm" id="fecha_compra_mod" name="fecha_compra_mod" value="<?php echo date("d-m-Y", strtotime($fecha_compra)) ?>">
								</div>
							</div>

							<div class="col-sm-5">
								<div class="input-group">
									<span class="input-group-addon"><b>Número documento</b></span>
									<input class="form-control input-sm" id="numero_documento_mod" name="numero_documento_mod" value="<?php echo $numero_documento ?>" <?php echo $bloqueo_retencion ?>>
								</div>
							</div>
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon"><b>Tipo</b></span>
									<select class="form-control input-sm" name="tipo_comprobante_mod" id="tipo_comprobante_mod">

										<?php
										if ($tipo_comprobante == "FÍSICA") {
										?>
											<option value="<?php echo $tipo_comprobante ?>" selected><?php echo $tipo_comprobante ?></option>
											<option value="ELECTRÓNICA">ELECTRÓNICA</option>
										<?php
										}
										?>

										<?php
										if ($tipo_comprobante == "ELECTRÓNICA") {
										?>
											<option value="<?php echo $tipo_comprobante ?>" selected><?php echo $tipo_comprobante ?></option>
											<option value="FÍSICA">FÍSICA</option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-7">
								<div class="input-group">
									<span class="input-group-addon" <?php echo $bloqueo_retencion_select ?>><b>Tipo documento</b></span>
									<select class="form-control input-sm" name="tipo_comprobante_compra_mod" id="tipo_comprobante_compra_mod" <?php echo $bloqueo_retencion_select ?>>
										<?php
										$res = mysqli_query($con, "SELECT * FROM comprobantes_autorizados order by comprobante asc ");
										while ($row_documentos = mysqli_fetch_assoc($res)) {

											if ($id_comprobante == $row_documentos['id_comprobante']) {
										?>
												<option value="<?php echo $id_comprobante ?>" selected><?php echo $row_documentos['comprobante'] ?></option>
											<?php
											} else {
											?>
												<option value="<?php echo $row_documentos['id_comprobante'] ?>"><?php echo $row_documentos['comprobante'] ?></option>
										<?php
											}
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-sm-5">
								<div class="input-group">
									<span class="input-group-addon"><b>Modifica a documento #</b></span>
									<input class="form-control input-sm" id="modificado_mod" name="modificado_mod" value="<?php echo $documento_modificado ?>" <?php echo $bloqueo_retencion ?>>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-12">
								<div class="input-group">
									<span class="input-group-addon"><b>Sustento Tributario</b></span>
									<select class="form-control input-sm" name="sustento_mod" id="sustento_mod">
										<?php
										$busca_sustento_tributario = "SELECT * FROM sustento_tributario order by nombre_sustento asc ";
										$resultado_sustento = $con->query($busca_sustento_tributario);
										//$count = mysqli_num_rows($resultado_sustento);
										while ($row_sustento_tributario = mysqli_fetch_array($resultado_sustento)) {
											$tipo_comprobante = $row_sustento_tributario['tipo_comprobante'];
											$id_sustento_tabla = $row_sustento_tributario['id_sustento'];
											$nombre_sustento = $row_sustento_tributario['nombre_sustento'];
											if ($id_sustento == $id_sustento_tabla) {
										?>
												<option value="<?php echo $id_sustento ?>" selected><?php echo $nombre_sustento ?></option>
											<?php
											} else {
											?>
												<option value="<?php echo $id_sustento_tabla ?>"><?php echo $nombre_sustento ?></option>
										<?php
											}
										}
										?>
									</select>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-8">
								<div class="input-group">
									<span class="input-group-addon"><b>Autorización SRI</b></span>
									<input class="form-control input-sm" id="aut_sri_mod" name="aut_sri_mod" value="<?php echo $aut_sri ?>">
								</div>
							</div>
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon"><b>Fecha caducidad</b></span>
									<input type="text" class="form-control input-sm" id="caducidad_mod" name="caducidad_mod" value="<?php echo date("d-m-Y", strtotime($caducidad)) ?>">
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Desde</b></span>
									<input class="form-control input-sm text-right" id="desde_mod" name="desde_mod" value="<?php echo $desde ?>">
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Hasta</b></span>
									<input class="form-control input-sm text-right" id="hasta_mod" name="hasta_mod" value="<?php echo $hasta ?>">
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Otros Val</b></span>
									<input class="form-control input-sm text-right" id="otros_val_mod" name="otros_val_mod" value="<?php echo number_format($otros_val, 2, '.', ''); ?>" <?php echo $bloqueo_retencion ?>>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Propina</b></span>
									<input class="form-control input-sm text-right" id="propina_mod" name="propina_mod" value="<?php echo number_format($propina, 2, '.', ''); ?>" <?php echo $bloqueo_retencion ?>>
								</div>
							</div>
						</div>
					</div>
					<div class="modal-footer"><!--style="margin-top: -10px;"-->
						<span id="loader_actualizar_compra"></span><span id="resultados_actualizar_encabezado"></span><button type="submit" class="btn btn-primary input-md" id="guardar_datos">Actualizar</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!--hasta aqui el encabezado de la factura de compras -->

	<div class="panel panel-info">
		<div id="resultados_ajax"></div>
		<div id="resultados_editar_compra"></div>
		<div class="table-responsive">
			<table class="table table-bordered">
				<tr class="info">
					<th style="padding: 2px;">Código</th>
					<th style="padding: 2px;">Detalle</th>
					<th style="padding: 2px;">Cantidad</th>
					<th style="padding: 2px;">Precio</th>
					<th style="padding: 2px;">Descuento</th>
					<th style="padding: 2px;">Impuesto</th>
					<th style="padding: 2px;">Det. Imp.</th>
					<th style="padding: 2px;">Agregar</th>
				</tr>

				<tr>
					<input type="hidden" id="codigo_unico" name="codigo_unico" value="<?php echo $codigo_documento; ?>">
					<td class="col-xs-2" style="padding: 2px;">
						<input type="text" class="form-control input-sm" id="editar_codigo_compra" name="editar_codigo_compra" placeholder="Código">
					</td>
					<td class="col-xs-4" style="padding: 2px;">
						<input type="text" class="form-control input-sm" id="editar_detalle_compra" name="editar_detalle_compra" placeholder="Detalle" title="Detalle de compra" onkeyup='buscar_productos();'>
					</td>
					<td class='col-xs-1' style="padding: 2px;">
						<input type="text" class="form-control input-sm" id="editar_cantidad_compra" name="editar_cantidad_compra" placeholder="Cant." title="Cantidad">
					</td>
					<td class='col-xs-1' style="padding: 2px;">
						<input type="text" class="form-control input-sm" id="editar_val_uni_compra" name="editar_val_uni_compra" placeholder="v/u">
					</td>
					<td class='col-xs-1' style="padding: 2px;">
						<input type="text" class="form-control input-sm" id="editar_descuento_compra" name="editar_descuento_compra">
					</td>
					<td class='col-xs-1' style="padding: 2px;">
						<select class="form-control" name="editar_tipo_impuesto" id="editar_tipo_impuesto" style="padding: 2px; height: 30px;">
							<?php
							$conexion = conenta_login();
							$sql = "SELECT * FROM impuestos_ventas WHERE status='1' order by id_impuesto desc ";
							$res = mysqli_query($conexion, $sql);
							while ($o = mysqli_fetch_assoc($res)) {
							?>
								<option value="<?php echo $o['codigo_impuesto']; ?>" selected><?php echo $o['nombre_impuesto'] ?></option>
							<?php
							}
							?>
						</select>
					</td>
					<td class='col-xs-2' style="padding: 2px;">
						<select class="form-control" name="editar_codigo_impuesto" id="editar_codigo_impuesto" style="padding: 2px; height: 30px;">
							<option value="" selected>Seleccione</option>
						</select>
					</td>
					<td class='col-xs-1' style="padding: 2px;">
						<button type="button" class="btn btn-info btn-sm" title="Agregar item" onclick="agregar_item_editar_compra()"><span class="glyphicon glyphicon-plus"></span></button>
					</td>
				</tr>

			</table>
		</div>
	</div>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script>
		var tipo_impuesto = $("#editar_tipo_impuesto").val();
		//para cargar el tipo de impuesto
		$.post('../ajax/select_detalle_impuestos.php', {
			impuesto: tipo_impuesto
		}).done(function(respuesta) {
			$("#editar_codigo_impuesto").html(respuesta);
		});

		$('#editar_tipo_impuesto').change(function() {
			var tipo_impuesto = $("#editar_tipo_impuesto").val();
			$.post('../ajax/select_detalle_impuestos.php', {
				impuesto: tipo_impuesto
			}).done(function(respuesta) {
				$("#editar_codigo_impuesto").html(respuesta);
			});
		});

		//agrega un item
		function agregar_item_editar_compra() {
			var codigo_unico = $("#codigo_unico").val();
			var codigo_producto = $("#editar_codigo_compra").val();
			var nombre_producto = $("#editar_detalle_compra").val();
			var cantidad_agregar = $("#editar_cantidad_compra").val();
			var editar_val_uni_compra = $("#editar_val_uni_compra").val();
			var editar_tipo_impuesto = $("#editar_tipo_impuesto").val();
			var descuento_compra = $("#editar_descuento_compra").val();
			var editar_codigo_impuesto = $("#editar_codigo_impuesto").val();

			if (Number.isNaN(Number.parseFloat(descuento_compra))) {
				descuento_compra = 0;
			}

			//Inicia validacion
			if (nombre_producto == '') {
				alert('Ingrese producto o servicio');
				document.getElementById('editar_detalle_compra').focus();
				return false;
			}
			if (cantidad_agregar == '') {
				alert('Ingrese cantidad');
				document.getElementById('editar_cantidad_compra').focus();
				return false;
			}

			if (isNaN(cantidad_agregar)) {
				alert('El dato ingresado en cantidad, no es un número');
				document.getElementById('editar_cantidad_compra').focus();
				return false;
			}

			if (editar_val_uni_compra == '') {
				alert('Ingrese precio');
				document.getElementById('editar_val_uni_compra').focus();
				return false;
			}

			if (isNaN(editar_val_uni_compra)) {
				alert('El dato ingresado en valor unitario, no es un número');
				document.getElementById('editar_val_uni_compra').focus();
				return false;
			}

			if (editar_codigo_impuesto == '') {
				alert('Seleccione un tipo de impuesto');
				document.getElementById('editar_codigo_impuesto').focus();
				return false;
			}

			//Fin validacion
			$("#outer_divdet").fadeIn('fast');

			$.ajax({
				type: "POST",
				url: "../ajax/detalle_documento.php",
				data: "action=agregar_detalle_compra&codigo_producto=" + codigo_producto + "&nombre_producto=" + nombre_producto + "&cantidad_agregar=" + cantidad_agregar + "&editar_val_uni_compra=" + editar_val_uni_compra + "&editar_codigo_impuesto=" + editar_codigo_impuesto + "&descuento_compra=" + descuento_compra + "&editar_tipo_impuesto=" + editar_tipo_impuesto + "&codigo_unico=" + codigo_unico,
				beforeSend: function(objeto) {
					$("#outer_divdet").html("Guardando...");
				},
				success: function(datos) {
					$(".outer_divdet").html(datos).fadeIn('fast');
					$('#outer_divdet').html('');
					document.getElementById("editar_codigo_compra").value = "";
					document.getElementById("editar_detalle_compra").value = "";
					document.getElementById("editar_cantidad_compra").value = "";
					document.getElementById("editar_val_uni_compra").value = "";
					document.getElementById("editar_descuento_compra").value = "";
				}
			});
		}

		function eliminar_detalle_compra(id) {
			var codigo_unico = $("#codigo_unico").val();
			if (confirm("Realmente desea eliminar el registro?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/detalle_documento.php",
					data: "action=eliminar_detalle_compra&id_registro=" + id + "&codigo_unico=" + codigo_unico,
					beforeSend: function(objeto) {
						$("#outer_divdet").html("Eliminando...");
					},
					success: function(datos) {
						$(".outer_divdet").html(datos).fadeIn('fast');
						$('#outer_divdet').html('');
					}
				});
			};
		};

		jQuery(function($) {
			$("#fecha_compra_mod").mask("99-99-9999");
			$("#caducidad_mod").mask("99-99-9999");
			$("#modificado_mod").mask("999-999-9?99999999");
			$("#numero_documento_mod").mask("999-999-9?99999999");
			$("#desde_mod").mask("9?99999999");
			$("#hasta_mod").mask("9?99999999");
		});


		//para cuando cambia en el secuencial de factura y se aplique los ceros a la izquierda
		$(function() {
			$('#numero_documento_mod').change(function() {
				var numero_comprobante = $("#numero_documento_mod").val();
				var serie = numero_comprobante.substr(0, 8);
				var secuencial = numero_comprobante.substr(8, 9);
				while (secuencial.length < 9) {
					var secuencial = '0' + secuencial;
					$("#numero_documento_mod").val(serie + secuencial);
				}
			});

			$('#desde_mod').change(function() {
				var numero_comprobante = $("#desde_mod").val();
				var secuencial = numero_comprobante.substr(0, 9);
				while (secuencial.length < 9) {
					var secuencial = '0' + secuencial;
					$("#desde_mod").val(secuencial);
				}
			});
			$('#hasta_mod').change(function() {
				var numero_comprobante = $("#hasta_mod").val();
				var secuencial = numero_comprobante.substr(0, 9);
				while (secuencial.length < 9) {
					var secuencial = '0' + secuencial;
					$("#hasta_mod").val(secuencial);
				}
			});
		});
		//para editar el encabezado de la compra
		$("#actualizar_encabezado").submit(function(event) {
			$('#guardar_datos').attr("disabled", true);
			//de aqui para abajo para guardar la factura
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: '../ajax/editar_compra.php',
				data: parametros,
				beforeSend: function(objeto) {
					$("#loader_actualizar_compra").html("Actualizando...");
				},
				success: function(datos) {
					$("#resultados_actualizar_encabezado").html(datos);
					$("#loader_actualizar_compra").html("");
					$('#guardar_datos').attr("disabled", false);
					load(1);
				}
			});
			event.preventDefault();
		});
	</script>
<?php
}


if ($action == 'agregar_detalle_compra') {
	$codigo_documento = $_POST['codigo_unico'];
	$codigo_producto = $_POST['codigo_producto'];
	$nombre_producto = $_POST['nombre_producto'];
	$cantidad_agregar = $_POST['cantidad_agregar'];
	$editar_val_uni_compra = $_POST['editar_val_uni_compra'];
	$editar_tipo_impuesto = $_POST['editar_tipo_impuesto'];
	$descuento_compra =  number_format($_POST['descuento_compra'], 2, '.', '');
	$editar_codigo_impuesto = $_POST['editar_codigo_impuesto'];
	$subtotal = ($cantidad_agregar * $editar_val_uni_compra) - $descuento_compra;

	//para eliminar el asiento contable
	$busca_registro_asiento = mysqli_query($con, "SELECT * FROM encabezado_compra WHERE codigo_documento = '" . $codigo_documento . "' ");
	$datos_asiento_factura = mysqli_fetch_array($busca_registro_asiento);
	$id_registro_contable = $datos_asiento_factura['id_registro_contable'];
	if ($id_registro_contable > 0) {
		include_once("../clases/anular_registros.php");
		$anular_asiento_contable = new anular_registros();
		$resultado_anular_documento = $anular_asiento_contable->anular_asiento_contable($con, $id_registro_contable);
	}

	$guardar_detalle_compra = mysqli_query($con, "INSERT INTO cuerpo_compra VALUES (null,'" . $ruc_empresa . "','" . $codigo_documento . "','" . $codigo_producto . "','" . $nombre_producto . "','" . $cantidad_agregar . "','" . $editar_val_uni_compra . "','" . $descuento_compra . "','" . $editar_tipo_impuesto . "','" . $editar_codigo_impuesto . "','" . $subtotal . "','0')");
	$total_compra = total_compra($codigo_documento);
	$sql_update = mysqli_query($con, "UPDATE encabezado_compra SET total_compra='" . $total_compra . "', id_registro_contable='0' WHERE codigo_documento='" . $codigo_documento . "'");

	agregar_detalle_compra($codigo_documento);
	detalle_compras($codigo_documento);
	echo "<script>load(1)</script>";
}

if ($action == 'eliminar_detalle_compra') {
	$id_registro = $_POST['id_registro'];
	$codigo_documento = $_POST['codigo_unico'];

	//para eliminar el asiento contable
	$busca_registro_asiento = mysqli_query($con, "SELECT * FROM encabezado_compra WHERE codigo_documento = '" . $codigo_documento . "' ");
	$datos_asiento_factura = mysqli_fetch_array($busca_registro_asiento);
	$id_registro_contable = $datos_asiento_factura['id_registro_contable'];
	$ruc_emp = $datos_asiento_factura['ruc_empresa'];
	$id_usu = $datos_asiento_factura['id_usuario'];
	$fecha_compra = $datos_asiento_factura['fecha_compra'];
	if ($id_registro_contable > 0) {
		include_once("../clases/anular_registros.php");
		$anular_asiento_contable = new anular_registros();
		$resultado_anular_documento = $anular_asiento_contable->anular_asiento_contable($con, $id_registro_contable);
	}

	$consultar_inventario = mysqli_query($con, "SELECT * FROM cuerpo_compra WHERE id_cuerpo_compra='" . $id_registro . "'");
	$row_inventario = mysqli_fetch_array($consultar_inventario);
	$cantidad_inventario = $row_inventario['cantidad_inv'];

	$consultar_registros = mysqli_query($con, "SELECT * FROM cuerpo_compra WHERE codigo_documento='" . $codigo_documento . "'");
	$total_registros = mysqli_num_rows($consultar_registros);

	if ($total_registros > 1) {
		if ($cantidad_inventario == 0) {
			$eliminar_detalle_compra = mysqli_query($con, "DELETE FROM cuerpo_compra WHERE id_cuerpo_compra='" . $id_registro . "'");
			$total_compra = total_compra($codigo_documento);
			$sql_update = mysqli_query($con, "UPDATE encabezado_compra SET total_compra='" . $total_compra . "' WHERE codigo_documento='" . $codigo_documento . "'");
			agregar_detalle_compra($codigo_documento);
			detalle_compras($codigo_documento);
			echo "<script>load(1)</script>";
		} else {
			echo "<script>$.notify('Primero, eliminar el registro de inventarios.','error')</script>";
		}
	} else {
		agregar_detalle_compra($codigo_documento);
		detalle_compras($codigo_documento);
		echo "<script>$.notify('Al menos debe tener un registro el detalle.','error')</script>";
	}
}


function total_compra($codigo_documento)
{
	$con = conenta_login();
	$subtotal = mysqli_query($con, "SELECT sum(subtotal) as subtotal FROM cuerpo_compra WHERE codigo_documento='" . $codigo_documento . "' ");
	$row_subtotal = mysqli_fetch_array($subtotal);
	$total_subtotal = $row_subtotal["subtotal"];

	//$total_iva = 0;
	$sql_iva = mysqli_query($con, "SELECT round(sum((cue_com.cantidad * cue_com.precio - cue_com.descuento) * (tar_iva.porcentaje_iva/100)),2) as total_iva
	FROM cuerpo_compra as cue_com INNER JOIN tarifa_iva as tar_iva ON tar_iva.codigo = cue_com.det_impuesto 
	WHERE cue_com.codigo_documento='" . $codigo_documento . "' and cue_com.impuesto = '2' 
	and tar_iva.porcentaje_iva > 0 group by cue_com.det_impuesto ");
	$row = mysqli_fetch_array($sql_iva);
	$total_iva = $row['total_iva'];
	$total_pagado = $total_subtotal + $total_iva;
	$sql_update = mysqli_query($con, "UPDATE formas_pago_compras SET total_pago='" . $total_pagado . "' WHERE codigo_documento='" . $codigo_documento . "'");
	return $total_pagado;
}

function detalle_compras($codigo_documento)
{
	$con = conenta_login();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	//para consultar cuerpo
	$busca_encabezado_compra = mysqli_query($con, "SELECT * FROM encabezado_compra WHERE codigo_documento= '" . $codigo_documento . "' ");
	$row_encabezado_compra = mysqli_fetch_array($busca_encabezado_compra);
	$id_registro_contable = $row_encabezado_compra['id_registro_contable'];
	$codigo_documento = $row_encabezado_compra['codigo_documento'];
	$propina = $row_encabezado_compra['propina'];
	$otros_val = $row_encabezado_compra['otros_val'];

	//para consultar cuerpo
	$busca_detalle_compra = "SELECT * FROM cuerpo_compra as cue INNER JOIN tarifa_iva as tar On tar.codigo=cue.det_impuesto
	 WHERE cue.codigo_documento= '" . $codigo_documento . "' ";
	$result_detalle_compra = $con->query($busca_detalle_compra);
	//para consultar adicional info
	$busca_info_compra = "SELECT * FROM detalle_adicional_compra WHERE codigo_documento= '" . $codigo_documento . "' ";
	$result_info_compra = $con->query($busca_info_compra);
	//para consultar pago
	$busca_info_pago = "SELECT * FROM formas_pago_compras AS fpc INNER JOIN formas_de_pago AS fp ON fp.codigo_pago=fpc.forma_pago
	WHERE fpc.codigo_documento= '" . $codigo_documento . "' and fp.aplica_a='VENTAS' ";
	$result_info_pago = $con->query($busca_info_pago);
?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-bordered">
				<tr class="info">
					<th style="padding: 2px;">Código</th>
					<th style="padding: 2px;">Producto</th>
					<th style="padding: 2px;" class='text-center'>Cantidad</th>
					<th style="padding: 2px;" class='text-center'>Precio</th>
					<th style="padding: 2px;" class='text-center'>Descuento</th>
					<th style="padding: 2px;" class='text-center'>Subtotal</th>
					<th style="padding: 2px;">IVA</th>
					<th style="padding: 2px;">Eliminar</th>
				</tr>
				<?php
				while ($row_detalle_compra = mysqli_fetch_array($result_detalle_compra)) {
					$codigo_documento = $row_detalle_compra['codigo_documento'];
					$id_cuerpo_compra = $row_detalle_compra['id_cuerpo_compra'];
					$codigo_producto = $row_detalle_compra['codigo_producto'];
					$nombre_producto = $row_detalle_compra['detalle_producto'];
					$cantidad_producto = $row_detalle_compra['cantidad'];
					$precio_producto = $row_detalle_compra['precio'];
					$descuento_producto = $row_detalle_compra['descuento'];
					$subtotal = $row_detalle_compra['subtotal'];
					$tarifa_iva = $row_detalle_compra['tarifa'];

				?>
					<tr>
						<td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
						<td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
						<td style="padding: 2px;" class='text-right'><?php echo $cantidad_producto; ?></td>
						<td style="padding: 2px;" class='text-right'><?php echo $precio_producto; ?></td>
						<td style="padding: 2px;" class='text-right'><?php echo $descuento_producto; ?></td>
						<td style="padding: 2px;" class='text-right'><?php echo $subtotal; ?></td>
						<td style="padding: 2px;"><?php echo $tarifa_iva; ?></td>
						<td style="padding: 2px;" class='text-right'><a class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_detalle_compra('<?php echo $id_cuerpo_compra; ?>')"><i class="glyphicon glyphicon-remove"></i></a></td>
					<?php
				}
					?>
			</table>
		</div>
	</div>

	<div class="row">
		<input type="hidden" class="form-control input-sm" id="codigo_documento" name="codigo_documento" value="<?php echo $codigo_documento ?>">
		<div class="panel-group" id="accordion_adicionales_compra" style="margin-bottom: -10px; margin-top: -15px;">
			<div class="col-xs-6">
				<a class="list-group-item list-group-item-info" style="height:35px;" data-toggle="collapse" data-parent="#accordion_adicionales_compra" href="#collapseAC"><span class="caret"></span> Detalles adicionales y forma de pago</a>
				<div id="collapseAC" class="panel-collapse collapse">
					<div class="panel panel-info">
						<div class="table-responsive">
							<table class="table table-bordered">
								<tr class="info">
									<td class='col-xs-3'>
										<input type="text" class="form-control input-sm" id="adicional_concepto" name="adicional_concepto" placeholder="Concepto">
									</td>
									<td class="col-xs-7">
										<input type="text" class="form-control input-sm" id="adicional_descripcion" name="adicional_descripcion" placeholder="Descripción del detalle">
									</td>
									<td class="text-center"><a class='btn btn-info btn-xs' title='Agregar' onclick="agregar_info_adicional_compra()"><i class="glyphicon glyphicon-plus"></i></a></td>
								</tr>

								<tr class="info">
									<th style="padding: 2px;">Concepto</th>
									<th style="padding: 2px;">Detalle</th>
									<th style="padding: 2px;">Eliminar</th>
								</tr>
								<?php
								while ($row_info_adicional = mysqli_fetch_array($result_info_compra)) {
									$id_adicional_compra = $row_info_adicional['id_detalle'];
									$concepto = $row_info_adicional['adicional_concepto'];
									$descripcion = $row_info_adicional['adicional_descripcion'];
								?>
									<tr>
										<td class='col-md-2'><?php echo $concepto; ?></td>
										<td class='col-md-4'><?php echo $descripcion; ?></td>
										<td class='text-center'><a class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_detalle_adicional_compra('<?php echo $id_adicional_compra; ?>')"><i class="glyphicon glyphicon-remove"></i></a></td>
									</tr>
								<?php
								}
								?>
							</table>
						</div>

						<div class="table-responsive">
							<table class="table table-bordered">
								<tr class="info">
									<th style="padding: 2px;">Forma Pago</th>
									<th style="padding: 2px;">Valor</th>
									<th style="padding: 2px;">Plazo</th>
									<th style="padding: 2px;">Tiempo</th>
								</tr>
								<?php
								while ($row_detalle_pago = mysqli_fetch_array($result_info_pago)) {
									$forma_pago = $row_detalle_pago['nombre_pago'];
									$valor_pago = $row_detalle_pago['total_pago'];
									$plazo_pago = $row_detalle_pago['plazo_pago'];
									$tiempo_pago = $row_detalle_pago['tiempo_pago'];
								?>
									<tr>
										<td><?php echo $forma_pago; ?></td>
										<td><?php echo $valor_pago; ?></td>
										<td><?php echo $plazo_pago; ?></td>
										<td><?php echo $tiempo_pago; ?></td>
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

		<script>
			//agregar detalle adicional compras
			function agregar_info_adicional_compra() {
				var adicional_concepto = $("#adicional_concepto").val();
				var adicional_descripcion = $("#adicional_descripcion").val();
				var codigo_unico = $("#codigo_documento").val();

				//Inicia validacion
				if (adicional_concepto == '') {
					alert('Ingrese un concepto de adicional');
					document.getElementById('adicional_concepto').focus();
					return false;
				}
				if (adicional_descripcion == '') {
					alert('Ingrese un detalle de adicional');
					document.getElementById('adicional_descripcion').focus();
					return false;
				}

				//Fin validacion
				$("#outer_divdet").fadeIn('fast');
				$.ajax({
					type: "POST",
					url: "../ajax/detalle_documento.php",
					data: "action=agregar_detalle_adicional_compra&adicional_concepto=" + adicional_concepto + "&adicional_descripcion=" + adicional_descripcion + "&codigo_unico=" + codigo_unico,
					beforeSend: function(objeto) {
						$("#outer_divdet").html("Agregando...");
					},
					success: function(datos) {
						$(".outer_divdet").html(datos).fadeIn('fast');
						$('#outer_divdet').html('');
						document.getElementById("adicional_concepto").value = "";
						document.getElementById("adicional_descripcion").value = "";
					}
				});
			}

			function eliminar_detalle_adicional_compra(id) {
				var codigo_unico = $("#codigo_documento").val();
				if (confirm("Realmente desea eliminar el registro?")) {
					$.ajax({
						type: "POST",
						url: "../ajax/detalle_documento.php",
						data: "action=eliminar_detalle_adicional_compra&id_registro=" + id + "&codigo_unico=" + codigo_unico,
						beforeSend: function(objeto) {
							$("#outer_divdet").html("Eliminando...");
						},
						success: function(datos) {
							$(".outer_divdet").html(datos).fadeIn('fast');
							$('#outer_divdet').html('');
						}
					});
				};
			};
		</script>


		<!-- detalle de subtotales-->
		<?php
		$subtotal_general = 0;
		$total_descuento = 0;
		$sql_factura = mysqli_query($con, "SELECT round(sum(subtotal),2) as subtotal_general, round(sum(descuento),2) as total_descuento  FROM cuerpo_compra WHERE codigo_documento='" . $codigo_documento . "' ");
		$row_subtotal = mysqli_fetch_array($sql_factura);
		$subtotal_general = $row_subtotal['subtotal_general'];
		$total_descuento = $row_subtotal['total_descuento'];
		?>

		<div class="col-xs-6">
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table">
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>SUBTOTAL GENERAL: </td>
							<td style="padding: 2px;" class='text-right'><?php echo $subtotal_general; ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
						<?php
						//PARA MOSTRAR LOS NOMBRES DE CADA TARIFA DE IVA Y LOS VALORES DE CADA SUBTOTAL
						$subtotal_tarifa_iva = 0;
						$sql = mysqli_query($con, "SELECT ti.tarifa as tarifa, 
						round(sum(cue_com.subtotal),2) as suma_tarifa_iva 
						FROM cuerpo_compra as cue_com 
						INNER JOIN tarifa_iva as ti ON ti.codigo=cue_com.det_impuesto 
						WHERE cue_com.codigo_documento='" . $codigo_documento . "' and cue_com.impuesto='2' group by cue_com.det_impuesto ");
						while ($row = mysqli_fetch_array($sql)) {
							$nombre_tarifa_iva = strtoupper($row["tarifa"]);
							$subtotal_tarifa_iva = $row['suma_tarifa_iva'];
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>SUBTOTAL <?php echo ($nombre_tarifa_iva); ?>:</td>
								<td style="padding: 2px;" class='text-right'><?php echo $subtotal_tarifa_iva; ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						}
						?>
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>TOTAL DESCUENTO: </td>
							<td style="padding: 2px;" class='text-right'><?php echo $total_descuento; ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
						<?php
						//PARA MOSTRAR LOS IVAS
						$total_iva = 0;
						$suma_iva = 0;
						$sql = mysqli_query($con, "SELECT ti.tarifa as tarifa, 
						round(sum((cue_com.cantidad * cue_com.precio - cue_com.descuento) * (ti.porcentaje_iva /100)),2) as total_iva 
						FROM cuerpo_compra as cue_com 
						INNER JOIN tarifa_iva as ti ON ti.codigo=cue_com.det_impuesto 
						WHERE cue_com.codigo_documento= '" . $codigo_documento . "' 
						and ti.porcentaje_iva > 0 group by cue_com.det_impuesto ");
						while ($row = mysqli_fetch_array($sql)) {
							$nombre_porcentaje_iva = strtoupper($row["tarifa"]);
							$total_iva = $row['total_iva'];
							$suma_iva += $row['total_iva'];
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>IVA <?php echo ($nombre_porcentaje_iva); ?>:</td>
								<td style="padding: 2px;" class='text-right'><?php echo $total_iva; ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						}
						if ($propina > 0) {
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>SERVICIO O PROPINA: </td>
								<td style="padding: 2px;" class='text-right'><?php echo number_format($propina, 2, '.', ''); ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						}
						if ($otros_val > 0) {
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>ICE, OTROS: </td>
								<td style="padding: 2px;" class='text-right'><?php echo number_format($otros_val, 2, '.', ''); ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						}
						?>
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>TOTAL: </td>
							<td style="padding: 2px;" class='text-right'><?php echo number_format($subtotal_general + $suma_iva + $propina + $otros_val, 2, '.', ''); ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
					</table>
				</div>
			</div>
		</div>
		<!-- hasta aqui detalle de subtotales-->
	</div>
<?php
	echo detalle_asiento_contable($con, $ruc_empresa, $id_registro_contable, 'COMPRAS_SERVICIOS');
	echo detalle_pago_compra($con, $codigo_documento);
}
//detalle tributario


//para agregar un detalle adicional en las edicion de compras
if ($action == 'agregar_detalle_adicional_compra') {
	$codigo_documento = $_POST['codigo_unico'];
	$adicional_concepto = $_POST['adicional_concepto'];
	$adicional_descripcion = $_POST['adicional_descripcion'];
	$guardar_detalle_adicional_compra = mysqli_query($con, "INSERT INTO detalle_adicional_compra VALUES (null,'" . $ruc_empresa . "','" . $codigo_documento . "','" . $adicional_concepto . "','" . $adicional_descripcion . "')");
	detalle_compras($codigo_documento);
	echo "<script>
		$.notify('Detalle agregado.','success');
		load(1);
		</script>";
}

//para eliminar un detalle adicional en las edicion de compras
if ($action == 'eliminar_detalle_adicional_compra') {
	$id_registro = $_POST['id_registro'];
	$codigo_documento = $_POST['codigo_unico'];

	$eliminar_detalle_compra = mysqli_query($con, "DELETE FROM detalle_adicional_compra WHERE id_detalle='" . $id_registro . "'");
	detalle_compras($codigo_documento);
	echo "<script>
			$.notify('Detalle eliminado.','success');
			load(1);
			</script>";
}

if ($action == 'detalle_tributario') {
	$id_documento = $_GET['id_documento'];
	$codigo_sustento = $_GET['codigo_sustento'];
	$codigo_deducible = $_GET['codigo_deducible'];
	$id_encabezado_compra = $_GET['id_encabezado_compra'];
	$cod_dod_mod = $_GET['cod_doc_mod'];
	$codigo_comprobante = $_GET['codigo_comprobante'];

?>
	<input type="hidden" name="sustento_viene" id="sustento_viene" value="<?php echo $codigo_sustento; ?>">
	<input type="hidden" name="deducible_viene" id="deducible_viene" value="<?php echo $codigo_deducible; ?>">
	<input type="hidden" name="cod_doc_mod_viene" id="cod_doc_mod_viene" value="<?php echo $cod_dod_mod; ?>">
	<script>
		$(document).ready(function() {
			var codigo_sustento = $("#sustento_viene").val();
			var codigo_deducible = $("#deducible_viene").val();
			var cod_doc_modificar = $("#cod_doc_mod_viene").val();
			$("#sustento_tributario").val(codigo_sustento);
			$("#deducible_en").val(codigo_deducible);
			$("#documento_modificado").val(cod_doc_modificar);
		});

		//editar sustento tributario	
		$("#detalle_tributario").submit(function(event) {
			$('#actualizar_datos').attr("disabled", true);
			var parametros = $(this).serialize();

			$.ajax({
				type: "POST",
				url: "../ajax/buscar_compras.php?action=actualiza_detalle_tributario",
				data: parametros,
				beforeSend: function(objeto) {
					$("#resultados_tributario").html("Mensaje: Actualizando...");
				},
				success: function(datos) {
					$("#resultados_tributario").html(datos);
					$('#actualizar_datos').attr("disabled", false);
					load(1);
				}
			});
			event.preventDefault();
		})
	</script>

	<form class="form-horizontal" method="post" id="detalle_tributario" name="detalle_tributario">
		<input type="hidden" name="id_encabezado_compra" id="id_encabezado_compra" value="<?php echo $id_encabezado_compra; ?>">
		<div class="panel panel-info">
			<div id="resultados_tributario"></div>
			<div class="form-group">
				<div class="col-sm-4">
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">Deducible para</label>
				<div class="col-sm-4">
					<select class="form-control" id="deducible_en" name="deducible_en">
						<option value="01">Declaración de IVA</option>
						<option value="02">Declaración de RENTA</option>
						<option value="03">Anexo gasto personal</option>
						<option value="04">No deducible</option>
					</select>
				</div>
			</div>

			<!-- buscar si lleva contabilidad para que aperezca el sustento tributario-->
			<?php
			$info_empresa = new empresas();
			$tipo_empresa = $info_empresa->datos_empresas($ruc_empresa)['tipo'];
			if (intval($tipo_empresa != 1)) {
			?>

				<div class="form-group">
					<label for="estado" class="col-sm-3 control-label">Sustento Tributario</label>
					<div class="col-sm-8">
						<select class="form-control" id="sustento_tributario" name="sustento_tributario">
							<?php
							$con = conenta_login();
							$busca_sustento_tributario = "SELECT * FROM sustento_tributario order by nombre_sustento asc ";
							$resultado_sustento = $con->query($busca_sustento_tributario);
							$count = mysqli_num_rows($resultado_sustento);

							while ($row_sustento_tributario = mysqli_fetch_array($resultado_sustento)) {
								$tipo_comprobante = $row_sustento_tributario['tipo_comprobante'];
								$id_sustento = $row_sustento_tributario['codigo_sustento'];
								$codigo_sustento = $row_sustento_tributario['codigo_sustento'];
								$nombre_sustento = $row_sustento_tributario['nombre_sustento'];
								$array_fila = explode(",", $tipo_comprobante); //traigo los datos de cada fila ya sin comas a un array
								$contador_fila = count($array_fila); //cuento cuantos datos hay en cada fila

								for ($i = 0; $i < $contador_fila; $i++) {
									if ($array_fila[$i] == intval($id_documento)) {
							?>
										<option value="<?php echo $id_sustento ?>"><?php echo $nombre_sustento ?></option>
							<?php
									}
								}
							}
							?>
						</select>
					</div>
				</div>
				<?php
				if ($codigo_comprobante == '04') {
				?>
					<div class="form-group">
						<label for="estado" class="col-sm-3 control-label">Documento modificado</label>
						<div class="col-sm-8">
							<select class="form-control" id="documento_modificado" name="documento_modificado">
								<?php
								$con = conenta_login();
								$busca_comprobantes_autorizados = "SELECT * FROM comprobantes_autorizados order by comprobante asc ";
								$resultado_documento = $con->query($busca_comprobantes_autorizados);

								while ($row_comprobantes_autorizados = mysqli_fetch_array($resultado_documento)) {
									$id_comprobante = $row_comprobantes_autorizados['id_comprobante'];
									$codigo_comprobante = $row_comprobantes_autorizados['codigo_comprobante'];
									$nombre_comprobante = $row_comprobantes_autorizados['comprobante'];
								?>
									<option value="<?php echo $codigo_comprobante ?>"><?php echo $nombre_comprobante ?></option>
								<?php
								}
								?>
							</select>
						</div>
					</div>
				<?php
				}
			} else {
				$con = conenta_login();
				$busca_sustento_tributario = "SELECT * FROM sustento_tributario order by nombre_sustento asc ";
				$resultado_sustento = $con->query($busca_sustento_tributario);
				$count = mysqli_num_rows($resultado_sustento);

				while ($row_sustento_tributario = mysqli_fetch_array($resultado_sustento)) {
					$codigo_sustento = $row_sustento_tributario['codigo_sustento'];
				?>
					<input type="hidden" name="sustento_tributario" id="sustento_tributario" value="<?php echo $id_sustento; ?>">
			<?php
				}
			}
			?>
			<div class="form-group">
				<div class="col-sm-9">
				</div>
				<div class="col-sm-2">
					<button type="submit" class="btn btn-info" id="actualizar_datos"><i class="glyphicon glyphicon-refresh"></i> Actualizar</button>
				</div>
			</div>

	</form>
	</div>
<?php
}

//detalle de compras a pasar a inventario
if ($action == 'pasa_inventario') {
	//para consultar cuerpo
	$busca_detalle_compra = "SELECT * FROM cuerpo_compra WHERE codigo_documento= '" . $codigo_documento . "' ";
	$result_detalle_compra = $con->query($busca_detalle_compra);
?>
	<form class="form-horizontal" method="POST" id="guarda_a_inventario" name="guarda_a_inventario">
		<div class="panel panel-info">
			<div id="resultados_enviar_inventario"></div>
			<div class="table-responsive">
				<table class="table table-bordered">
					<tr class="info">
						<td colspan="9"><span class="pull-center">
								<div><span class="glyphicon glyphicon-ok"></span> Productos disponibles para pasar al inventario</div>
							</span></td>
					</tr>
					<tr class="info">
						<th>Producto compra</th>
						<th>Mi Producto</th>
						<th>Cantidad</th>
						<th>Medida</th>
						<th>Bodega</th>
						<th>Precio</th>
						<th>Caducidad</th>
						<th>Lote</th>
						<th>Opciones</th>
					</tr>
					<?php
					while ($row_detalle_compra = mysqli_fetch_array($result_detalle_compra)) {
						$id_registro_compra = $row_detalle_compra['id_cuerpo_compra'];
						$codigo_producto = $row_detalle_compra['codigo_producto'];
						$nombre_producto = $row_detalle_compra['detalle_producto'];
						$precio_producto = $row_detalle_compra['precio'];
						$cantidad_producto = $row_detalle_compra['cantidad'];
						$saldo_producto = $row_detalle_compra['cantidad'] - $row_detalle_compra['cantidad_inv'];
						$codigo_documento = $row_detalle_compra['codigo_documento'];

						//datos del proveedor y factura
						$busca_datos_proveedor = mysqli_query($con, "SELECT * FROM encabezado_compra ec, proveedores pro WHERE ec.codigo_documento='" . $codigo_documento . "' and ec.id_proveedor=pro.id_proveedor");
						$row_datos_proveedor = mysqli_fetch_array($busca_datos_proveedor);
						$nombre_proveedor = $row_datos_proveedor['razon_social'];
						$numero_documento_compra = $row_datos_proveedor['numero_documento'];
						if ($saldo_producto > 0) {
					?>
							<tr>
								<td><?php echo $nombre_producto; ?></td>
								<input type="hidden" name="id_producto[<?php echo $id_registro_compra; ?>]" id="id_producto<?php echo $id_registro_compra; ?>">
								<input type="hidden" name="codigo_producto[<?php echo $id_registro_compra; ?>]" id="codigo_producto<?php echo $id_registro_compra; ?>">
								<input type="hidden" name="saldo_producto[<?php echo $id_registro_compra; ?>]" value="<?php echo $saldo_producto; ?>">
								<input type="hidden" name="id_registro[]" value="<?php echo $id_registro_compra; ?>">
								<input type="hidden" name="proveedor" value="<?php echo $nombre_proveedor; ?>">
								<input type="hidden" name="numero_documento" value="<?php echo $numero_documento_compra; ?>">
								<input type="hidden" name="codigo_compra" value="<?php echo $codigo_documento; ?>">
								<input type="hidden" name="codigo_registro[<?php echo $id_registro_compra; ?>]" value="<?php echo $id_registro_compra; ?>">

								<td class="col-xs-2">
									<input type="text" class="form-control input-sm" id="mi_producto<?php echo $id_registro_compra; ?>" name="mi_producto[<?php echo $id_registro_compra; ?>]" onkeyup="buscar_productos('<?php echo $id_registro_compra; ?>');" autocomplete="off" placeholder="Ingrese producto">
								</td>
								<td class="col-xs-1">
									<input type="text" class="form-control input-sm text-right" name="cantidad_producto[<?php echo $id_registro_compra; ?>]" placeholder="Ingrese cantidad" value="<?php echo $saldo_producto; ?>">
								</td>

								<td class="col-xs-2">
									<select class="form-control" name="unidad_medida[<?php echo $id_registro_compra; ?>]" id="unidad_medida<?php echo $id_registro_compra; ?>">
										<option value="">Seleccione</option>
									</select>
								</td>
								<td class="col-xs-2">
									<?php
									$conexion = conenta_login();
									?>
									<select class="form-control" name="bodega[<?php echo $id_registro_compra; ?>]" id="bodega<?php echo $id_registro_compra; ?>">
										<?php
										$sql = "SELECT * FROM bodega where mid(ruc_empresa,1,10)='" . substr($ruc_empresa, 0, 10) . "';";
										$res = mysqli_query($conexion, $sql);
										?> <option value="">Seleccione</option>
										<?php
										while ($o = mysqli_fetch_assoc($res)) {
										?>
											<option value="<?php echo $o['id_bodega'] ?>" selected><?php echo strtoupper($o['nombre_bodega']) ?> </option>
										<?php
										}
										?>
									</select>
								</td>
								<td class="col-xs-1"><input type="text" class="form-control input-sm text-right" name="precio_producto[<?php echo $id_registro_compra; ?>]" value="<?php echo $precio_producto; ?>" readonly></td>
								<td>
									<input type="date" class="form-control input-sm" name="caducidad[<?php echo $id_registro_compra; ?>]">
								</td>
								<td class="col-xs-2">
									<input type="text" class="form-control input-sm" name="lote[<?php echo $id_registro_compra; ?>]" placeholder="Lote">
								</td>
								<td><input class="form-control" type="checkbox" name="enviar_inventario[<?php echo $id_registro_compra; ?>]" checked></td>
							</tr>
					<?php
						}
					}
					?>
					<tr class="info">
						<td colspan="9"><span class="pull-right">
								<button type='submit' class="btn btn-warning" id="enviar_datos"><span class="glyphicon glyphicon-log-out"></span> Enviar</button>
							</span></td>
					</tr>
				</table>
			</div>
	</form>
	</div>
	<script>
		//para guardar en el inventario de entradas
		$("#guarda_a_inventario").submit(function(event) {
			$('#enviar_datos').attr("disabled", true);
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: "../ajax/guardar_compras_inventario.php",
				data: parametros,
				beforeSend: function(objeto) {
					$("#resultados_enviar_inventario").html("Mensaje: Guardando...");
				},
				success: function(datos) {
					$("#resultados_enviar_inventario").html(datos);
					$('#enviar_datos').attr("disabled", false);
				}
			});
			event.preventDefault();
		})
	</script>
	<?php

}

//detalle retenciones compras
if ($action == 'detalle_retenciones_compras') {
	$id_encabezado_compra = mysqli_real_escape_string($con, (strip_tags($_GET["id_encabezado_compra"], ENT_QUOTES)));
	//eliminar registros previos
	$delete_retencion_tmp = mysqli_query($con, "DELETE FROM retencion_tmp WHERE id_usuario = '" . $id_usuario . "';");
	$delete_adicional_tmp = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '" . $id_usuario . "';");

	//para traer datos del proveedor
	$sql_encabezado_compra = mysqli_query($con, "SELECT * FROM encabezado_compra WHERE id_encabezado_compra='" . $id_encabezado_compra . "'");
	$datos_compras = mysqli_fetch_array($sql_encabezado_compra);
	$id_proveedor_compra = $datos_compras['id_proveedor'];
	$numero_documento = $datos_compras['numero_documento'];
	$codigo_documento = $datos_compras['codigo_documento'];
	$fecha_compra = $datos_compras['fecha_compra'];
	$id_comprobante = $datos_compras['id_comprobante'];

	//para sacar el codigo del comprobante
	$sql_tipo_comprobante = mysqli_query($con, "SELECT * FROM comprobantes_autorizados WHERE id_comprobante='" . $id_comprobante . "'");
	$datos_comprobante = mysqli_fetch_array($sql_tipo_comprobante);
	$codigo_comprobante = $datos_comprobante['codigo_comprobante'];

	//para traer base de la factura
	$sql_subtotales = mysqli_query($con, "SELECT sum(subtotal) as subtotal FROM cuerpo_compra WHERE codigo_documento='" . $codigo_documento . "'");
	$datos_subtotales = mysqli_fetch_array($sql_subtotales);
	$subtotal_compra = $datos_subtotales['subtotal'];

	$sql_proveedor = mysqli_query($con, "SELECT * FROM proveedores WHERE id_proveedor='" . $id_proveedor_compra . "' ");
	$datos_proveedor = mysqli_fetch_array($sql_proveedor);
	$nombre_proveedor = $datos_proveedor['razon_social'];
	$mail_proveedor = $datos_proveedor['mail_proveedor'];
	$ruc_proveedor = $datos_proveedor['ruc_proveedor'];

	//para mostrar la retencion sugerida	
	$detalle_encabezado_ret_compra = mysqli_query($con, "SELECT * FROM encabezado_retencion WHERE ruc_empresa= '" . $ruc_empresa . "' and id_proveedor='" . $id_proveedor_compra . "' and id_compras='" . $id_encabezado_compra . "' order by id_encabezado_retencion desc ");
	$row_encabezado_ret_compras = mysqli_fetch_array($detalle_encabezado_ret_compra);
	$serie_retencion = isset($row_encabezado_ret_compras['serie_retencion']) ? $row_encabezado_ret_compras['serie_retencion'] : "";
	$secuencial_retencion = isset($row_encabezado_ret_compras['secuencial_retencion']) ? $row_encabezado_ret_compras['secuencial_retencion'] : "";
	$numero_comprobante = isset($row_encabezado_ret_compras['numero_comprobante']) ? $row_encabezado_ret_compras['numero_comprobante'] : "";
	$id_registro_contable = isset($row_encabezado_ret_compras['id_registro_contable']) ? $row_encabezado_ret_compras['id_registro_contable'] : "";

	$detalle_retenciones_compra = mysqli_query($con, "SELECT * FROM cuerpo_retencion WHERE ruc_empresa='" . $ruc_empresa . "' and serie_retencion='" . $serie_retencion . "' and secuencial_retencion='" . $secuencial_retencion . "' and impuesto='RENTA'");
	$row_detalle_ret_compras = mysqli_fetch_array($detalle_retenciones_compra);
	$codigo_impuesto = isset($row_detalle_ret_compras['codigo_impuesto']) ? $row_detalle_ret_compras['codigo_impuesto'] : "";

	$detalle_retenciones_sri = mysqli_query($con, "SELECT * FROM retenciones_sri WHERE codigo_ret='" . $codigo_impuesto . "'");
	$row_detalle_retenciones_sri = mysqli_fetch_array($detalle_retenciones_sri);
	$nombre_retencion = isset($row_detalle_retenciones_sri['concepto_ret']) ? $row_detalle_retenciones_sri['concepto_ret'] : "";
	$id_retencion = isset($row_detalle_retenciones_sri['id_ret']) ? $row_detalle_retenciones_sri['id_ret'] : "";
	$porcentaje_retencion = isset($row_detalle_retenciones_sri['porcentaje_ret']) ? $row_detalle_retenciones_sri['porcentaje_ret'] : "";

	$busca_retenciones_autorizadas = mysqli_query($con, "SELECT * FROM encabezado_retencion WHERE ruc_empresa= '" . $ruc_empresa . "' and numero_comprobante='" . $numero_documento . "' and id_proveedor='" . $id_proveedor_compra . "' and estado_sri != 'ANULADA' and id_compras='" . $id_encabezado_compra . "'");
	$count_ret_autorizada = mysqli_num_rows($busca_retenciones_autorizadas);

	if ($count_ret_autorizada == 0) {
	?>
		<div id="resultados_guardar_retencion_electronica"></div>
		<div id="resultados_retener_iva"></div>
		<div id="observacion_agente_micro"></div>

		<div class="well well-sm" style="margin-bottom: 5px; margin-top: 5px;">
			<div class="row">
				<div class="col-sm-9">

					<div class="form-group row">
						<div class="col-sm-12">
							<div class="input-group">
								<span class="input-group-addon"><b>Proveedor</b></span>
								<input type="text" class="form-control input-sm" name="proveedor_compra" id="proveedor_compra" value="<?php echo $nombre_proveedor; ?>" readonly>
							</div>
						</div>
						<div class="col-sm-12">
							<div class="input-group">
								<span class="input-group-addon"><b>Documento</b></span>
								<input type="text" class="form-control input-sm" name="documento_retencion_compra" id="documento_retencion_compra" value="<?php echo $numero_documento; ?>" readonly>
							</div>
						</div>
					</div>
					<div class="form-group row">
						<div class="col-sm-6">
							<div class="input-group">
								<span class="input-group-addon"><b>Fecha Retención</b></span>
								<input type="text" class="form-control input-sm" name="fecha_emision_retencion" id="fecha_emision_retencion" value="<?php echo date('d-m-Y'); ?>">
							</div>
						</div>
						<div class="col-sm-6">
							<div class="input-group">
								<span class="input-group-addon"><b>Fecha factura</b></span>
								<input type="text" class="form-control input-sm" name="fecha_emision_factura" id="fecha_emision_factura" value="<?php echo date('d-m-Y', strtotime($fecha_compra)); ?>" readonly>
							</div>
						</div>
					</div>
					<div class="form-group row">
						<div class="col-sm-6">
							<div class="input-group">
								<span class="input-group-addon"><b>Sucursal</b></span>
								<select class="form-control input-sm" name="serie_retencion_compra" id="serie_retencion_compra" required>
									<option value="0">Seleccione serie</option>
									<?php
									$sql = mysqli_query($con, "SELECT * FROM sucursales where ruc_empresa ='" . $ruc_empresa . "' order by id_sucursal asc;");
									while ($o = mysqli_fetch_assoc($sql)) {
									?>
										<option value="<?php echo $o['serie']; ?>" selected><?php echo $o['serie']; ?></option>
									<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="col-sm-6">
							<div class="input-group">
								<span class="input-group-addon"><b>Secuencial</b></span>
								<input type="text" class="form-control input-sm" name="secuencial_ret_compra" id="secuencial_ret_compra" readonly>
							</div>
						</div>
					</div>
					<div class="form-group row">
						<div class="col-sm-12">
							<div class="input-group">
								<span class="input-group-addon"><b>Mail</b></span>
								<input type="email" class="form-control input-sm" id="mail_proveedor" name="mail_proveedor" value="<?php echo $mail_proveedor; ?>">
							</div>
						</div>
					</div>
				</div>

				<!--aqui el detalle de los subtotales de la factura-->
				<div class="col-sm-3">
					<div style="border:1px solid #000; padding:10px; border-radius:5px;">

						<h4 style="text-align:center; margin-top:0;">
							<strong>DETALLE DE FACTURA</strong>
						</h4>

						<table class="table table-condensed">
							<?php
							$sql_tarifas_iva = mysqli_query($con, "SELECT sum(cue_com.subtotal) as suma_tarifa_iva, 
            tar_iva.porcentaje_iva as porcentaje_iva, tar_iva.tarifa as tarifa 
            FROM cuerpo_compra as cue_com 
            INNER JOIN tarifa_iva as tar_iva 
            ON tar_iva.codigo = cue_com.det_impuesto 
            WHERE cue_com.codigo_documento = '" . $codigo_documento . "' 
            GROUP BY cue_com.det_impuesto ");

							while ($row = mysqli_fetch_array($sql_tarifas_iva)) {
								$nombre_tarifa_iva = strtoupper($row['tarifa']);
								$subtotal_tarifa_iva = $row['suma_tarifa_iva'];
							?>
								<tr>
									<td class='text-right'><strong>Subtotal <?php echo $nombre_tarifa_iva; ?> :</strong></td>
									<td class='text-right'><?php echo number_format($subtotal_tarifa_iva, 2); ?></td>
								</tr>
							<?php
							}

							$sql_iva = mysqli_query($con, "SELECT ti.tarifa as tarifa, 
            ROUND(SUM((cc.cantidad * cc.precio - cc.descuento) * (ti.porcentaje_iva/100)),2) as total_iva 
            FROM cuerpo_compra as cc 
            INNER JOIN tarifa_iva as ti ON ti.codigo = cc.det_impuesto 
            WHERE cc.codigo_documento = '" . $codigo_documento . "' 
            AND ti.porcentaje_iva > 0 
            GROUP BY cc.det_impuesto ");

							while ($row = mysqli_fetch_array($sql_iva)) {
								$nombre_porcentaje_iva = strtoupper($row['tarifa']);
								$total_iva = $row['total_iva'];
							?>
								<tr>
									<td class='text-right'><strong>IVA <?php echo $nombre_porcentaje_iva; ?> :</strong></td>
									<td class='text-right'><?php echo number_format($total_iva, 2); ?></td>
								</tr>
							<?php
							}
							?>
						</table>

					</div>
				</div>


			</div>
		</div>

		<!-- hasta qui detalle de subtotales-->

		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 2px;">Concepto</th>
						<th style="padding: 2px;">Porcentaje</th>
						<th style="padding: 2px;">Base imponible</th>
						<th style="padding: 2px;" class='text-right'>Agregar</th>
					</tr>
					<tr>
						<td class="col-sm-8">
							<input type="text" class="form-control input-sm" name="concepto_retencion_compra" id="concepto_retencion_compra" onkeyup='buscar_retenciones_compras();' autocomplete="off" value="<?php echo $nombre_retencion; ?>" placeholder="Buscar concepto de retención">
						</td>
						<td class="col-sm-1">
							<input type="text" class="form-control input-sm text-right" name="porcentaje_retencion_compra" id="porcentaje_retencion_compra" value="<?php echo $porcentaje_retencion; ?>">
						</td>
						<td class="col-sm-2">
							<input type="text" class="form-control input-sm text-right" name="base_retencion_compra" id="base_retencion_compra" value="<?php echo $subtotal_compra; ?>">
						</td>
						<td class='text-right'><a href="#" class='btn btn-info btn-sm' title='Agregar item' onclick="agregar_item_retencion_compra()"><i class="glyphicon glyphicon-plus"></i></a></td>
						<input type="hidden" id="id_proveedor_ret_compra" name="id_proveedor_ret_compra" value="<?php echo $id_proveedor_compra; ?>">
						<input type="hidden" id="id_concepto_ret_compra" name="id_concepto_ret_compra" value="<?php echo $id_retencion; ?>">
						<input type="hidden" id="total_retencion_compra" name="total_retencion_compra">
						<input type="hidden" id="ruc_proveedor" name="ruc_proveedor" value="<?php echo $ruc_proveedor; ?>">
						<input type="hidden" id="tipo_comprobante" name="tipo_comprobante" value="<?php echo $codigo_comprobante; ?>">
						<input type="hidden" id="id_compra" name="id_compra" value="<?php echo $id_encabezado_compra; ?>">
					</tr>
				</table>
			</div>
		</div>
		<div id="resultados"></div><!-- Carga los datos ajax del detalle de la retencion -->
		<span id="loader"></span>
		<div class="form-group row">
			<div class="panel-heading">
				<div class="btn-group pull-right">
					<button id="guardar_datos_retencion_compra" onclick="guardar_nueva_retencion_compra()" type="submit" class="btn btn-info btn-md"><span class='glyphicon glyphicon-floppy-disk'></span> Guardar</button>
				</div>
			</div>
		</div>

	<?php
	} else {
		muestra_detalle_retenciones_compras($id_encabezado_compra);
	}
	?>
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script>
		$('#fecha_emision_retencion').css('z-index', 1500);
		jQuery(function($) {
			$("#fecha_emision_retencion").mask("99-99-9999");
		});

		//al iniciar el form
		$(document).ready(function() {
			var id_serie = $("#serie_retencion_compra").val();
			$.post('../ajax/buscar_ultima_retencion.php', {
				serie_re: id_serie
			}).done(function(respuesta) {
				var retencion_final = respuesta;
				$("#secuencial_ret_compra").val(retencion_final);
			});

			//para saber si debe retener iva
			var id_proveedor = $("#id_proveedor_ret_compra").val();
			$.ajax({
				url: '../ajax/observacion_retener_iva.php?&id_proveedor=' + id_proveedor,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$("#resultados_retener_iva").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			})

			//para saber si es agente de ret o microtime
			var ruc_proveedor = $("#ruc_proveedor").val();
			$.ajax({
				type: "POST",
				url: "../clases/info_agente_micro_especial.php?action=info_agente_micro_especial",
				data: "ruc_proveedor=" + ruc_proveedor,
				beforeSend: function(objeto) {
					$("#loader").html('Cargando...');
				},
				success: function(datos) {
					$("#observacion_agente_micro").html(datos).fadeIn('slow');
					$("#loader").html('');
				}
			});

			document.getElementById('concepto_retencion_compra').focus();
		});
		//cuando se cambia el select de serie
		$(function() {
			$('#serie_retencion_compra').change(function() {
				var id_serie = $("#serie_retencion_compra").val();
				$.post('../ajax/buscar_ultima_retencion.php', {
					serie_re: id_serie
				}).done(function(respuesta) {
					var retencion_final = respuesta;
					$("#secuencial_ret_compra").val(retencion_final);
				});
				document.getElementById('concepto_retencion_compra').focus();
			});

		});


		$(function() {
			$("#fecha_emision_retencion").datepicker({
				dateFormat: "dd-mm-yy",
				firstDay: 1,
				dayNamesMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
				dayNamesShort: ["Dom", "Lun", "Mar", "Mie", "Jue", "Vie", "Sab"],
				monthNames: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio",
					"Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
				],
				monthNamesShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun",
					"Jul", "Ago", "Sep", "Oct", "Nov", "Dic"
				]
			});

		});



		function agregar_item_retencion_compra() {
			var id_proveedor = $("#id_proveedor_ret_compra").val();
			var id_ret = $("#id_concepto_ret_compra").val();
			var concepto_ret = $("#concepto_retencion_compra").val();
			var porcentaje_ret = $("#porcentaje_retencion_compra").val();
			var base_imponible_ret = $("#base_retencion_compra").val();
			var fecha_ret = $("#fecha_emision_retencion").val();
			var fecha_fac = $("#fecha_emision_factura").val();
			var codigo_proveedor = $("#id_proveedor_ret_compra").val();
			var mail_proveedor = $("#mail_proveedor").val();
			//Inicia validacion
			if (mail_proveedor == "") {
				alert('El proveedor no tiene mail asignado.');
				document.getElementById('concepto_retencion_compra').focus();
				return false;
			}
			if (id_ret == "") {
				alert('Seleccione concepto de retención.');
				document.getElementById('concepto_retencion_compra').focus();
				return false;
			}
			if (id_proveedor == "") {
				alert('Seleccione un proveedor.');
				document.getElementById('concepto_retencion_compra').focus();
				return false;
			}
			if (isNaN(porcentaje_ret)) {
				alert('El dato ingresado en porcentaje, no es un número');
				document.getElementById('porcentaje_retencion_compra').focus();
				return false;
			}
			if (isNaN(base_imponible_ret)) {
				alert('El dato ingresado en base imponible, no es un número');
				document.getElementById('base_retencion_compra').focus();
				return false;
			}
			if ((base_imponible_ret) == 0) {
				alert('Ingrese valor en base imponible');
				document.getElementById('base_retencion_compra').focus();
				return false;
			}
			//Fin validacion
			$.ajax({
				type: "POST",
				url: "../ajax/agregar_retenciones.php",
				data: "id_ret=" + id_ret + "&porcentaje_ret=" + porcentaje_ret + "&base_imponible_ret=" + base_imponible_ret + "&fecha_fac=" + fecha_fac + "&fecha_ret=" + fecha_ret + "&codigo_proveedor=" + codigo_proveedor,
				beforeSend: function(objeto) {
					$("#resultados").html("Mensaje: Cargando...");
				},
				success: function(datos) {
					$("#resultados").html(datos);
					$("#id_concepto_ret_compra").val("");
					$("#concepto_retencion_compra").val("");
					$("#porcentaje_retencion_compra").val("");
					$("#base_retencion_compra").val("");
					document.getElementById('concepto_retencion_compra').focus();
				}
			});
		}

		//eliminar item
		function eliminar_concepto_retencion(id) {
			$.ajax({
				type: "GET",
				url: "../ajax/agregar_retenciones.php",
				data: "id=" + id,
				beforeSend: function(objeto) {
					$("#resultados").html("Mensaje: Cargando...");
				},
				success: function(datos) {
					$("#resultados").html(datos);
				}
			});

		};

		//GUARDAR RETENCION COMPRA
		function guardar_nueva_retencion_compra() {
			var fecha_emision = $("#fecha_emision_retencion").val();
			var fecha_factura = $("#fecha_emision_factura").val();
			var serie_sucursal = $("#serie_retencion_compra").val();
			var secuencial_retencion = $("#secuencial_ret_compra").val();
			var tipo_comprobante = $("#tipo_comprobante").val();
			var numero_comprobante = $("#documento_retencion_compra").val();
			var id_proveedor = $("#id_proveedor_ret_compra").val();
			var total_retencion = $("#suma_retencion").val();
			var mail_proveedor = $("#mail_proveedor").val();
			var id_compra = $("#id_compra").val();
			//inicia validacion
			if (id_proveedor == "") {
				alert('Seleccione un documento.');
				return false;
			}
			if (numero_comprobante == "") {
				alert('Seleccione un documento.');
				return false;
			}
			if (fecha_factura == "") {
				alert('Ingrese fecha del emisión de la retención.');
				document.getElementById('fecha_emision_factura').focus();
				return false;
			}
			if (serie_sucursal == "") {
				alert('Seleccion serie.');
				document.getElementById('serie_retencion_compra').focus();
				return false;
			}
			if (secuencial_retencion == "") {
				alert('Seleccion serie para obtener el secuencial.');
				document.getElementById('serie_retencion_compra').focus();
				return false;
			}

			//fin validacion
			$.ajax({
				type: "POST",
				url: '../ajax/guardar_retencion_electronica.php',
				data: 'fecha_retencion_e=' + fecha_emision + '&fecha_comprobante_e=' +
					fecha_factura + '&serie_retencion_e=' + serie_sucursal + '&secuencial_retencion_e=' +
					secuencial_retencion + '&tipo_comprobante=' + tipo_comprobante + '&numero_comprobante=' +
					numero_comprobante + '&id_proveedor_e=' + id_proveedor + '&total_retencion_e=' +
					total_retencion + '&mail_proveedor=' + mail_proveedor + '&id_compra=' + id_compra,
				beforeSend: function(objeto) {
					$("#resultados_guardar_retencion_electronica").html("Mensaje: Cargando...");
				},

				success: function(datos) {
					$("#resultados_guardar_retencion_electronica").html(datos);
					$('#guardar_datos_retencion_compra').attr("disabled", false);
					setTimeout(function() {
						location.reload()
					}, 1000);
				}

			});


		};
	</script>
	<?php
}

//detalle de proceso de produccion
if ($action == 'detalle_precios') {
	$id_producto = mysqli_real_escape_string($con, (strip_tags($_GET["id_producto"], ENT_QUOTES)));
	muestra_detalle_precios($id_producto);
}

//para agregar un nuevo precio al producto seleccionado
if ($action == 'agregar_nuevo_precio') {
	$con = conenta_login();
	$fecha_agregado = date("Y-m-d H:i:s");
	$id_producto = mysqli_real_escape_string($con, (strip_tags($_GET["id_producto"], ENT_QUOTES)));
	$precio_nuevo = mysqli_real_escape_string($con, (strip_tags($_GET["precio_nuevo"], ENT_QUOTES)));
	$aplica_desde = date('Y-m-d H:i:s', strtotime($_GET['aplica_desde']));
	$aplica_hasta = date('Y-m-d H:i:s', strtotime($_GET['aplica_hasta']));
	$detalle_precio = mysqli_real_escape_string($con, (strip_tags($_GET["detalle_precio"], ENT_QUOTES)));
	$detalle_precios = mysqli_query($con, "INSERT INTO precios_productos VALUES(null, '" . $precio_nuevo . "', '" . $aplica_desde . "', '" . $aplica_hasta . "', '" . $id_producto . "', '" . $detalle_precio . "', '" . $id_usuario . "', '" . $fecha_agregado . "')");
	muestra_detalle_precios($id_producto);
}


function muestra_detalle_precios($id_producto)
{
	if (isset($id_producto)) {
		$con = conenta_login();
		$busca_detalle_precios = mysqli_query($con, "SELECT * FROM precios_productos WHERE id_producto = '" . $id_producto . "' order by id_precio asc ");
	?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th>Precio</th>
						<th>Fecha desde</th>
						<th>Fecha hasta</th>
						<th>Detalle</th>
						<th class='text-right'>Eliminar</th>
					</tr>
					<?php
					while ($detalle_de_precios = mysqli_fetch_array($busca_detalle_precios)) {
						$id_precio = $detalle_de_precios['id_precio'];
						$precio_producto = $detalle_de_precios['precio'];
						$fecha_desde = $detalle_de_precios['fecha_desde'];
						$fecha_hasta = $detalle_de_precios['fecha_hasta'];
						$detalle_precio = $detalle_de_precios['detalle_precio'];
						$id_producto = $detalle_de_precios['id_producto'];
					?>
						<input type="hidden" id="id_producto<?php echo $id_precio; ?>" value="<?php echo $id_producto; ?>">
						<tr>
							<td><?php echo $precio_producto; ?></td>
							<td><?php echo date('d-m-Y', strtotime($fecha_desde)); ?></td>
							<td><?php echo date('d-m-Y', strtotime($fecha_hasta)); ?></td>
							<td><?php echo $detalle_precio; ?></td>
							<td class='text-right'><a class='btn btn-danger btn-xs' title='Eliminar precio' onclick="eliminar_precio('<?php echo $id_precio; ?>')"><i class="glyphicon glyphicon-remove"></i></a></td>
						</tr>
					<?php
					}
					?>
				</table>
			</div>
		</div>
	<?php
	}
}

//para eliminar precios asignados a productos
if ($action == 'eliminar_precio') {
	$con = conenta_login();
	$id_producto = mysqli_real_escape_string($con, (strip_tags($_GET["id_producto"], ENT_QUOTES)));
	$id_precio = mysqli_real_escape_string($con, (strip_tags($_GET["id_precio"], ENT_QUOTES)));
	$delete_precio = mysqli_query($con, "DELETE FROM precios_productos WHERE id_precio ='" . $id_precio . "'");
	muestra_detalle_precios($id_producto);
}


//muestra detalle de retenciones de compras con detalle de compras
function muestra_detalle_retenciones_compras($id_encabezado_compra)
{
	if (isset($id_encabezado_compra)) {
		$con = conenta_login();
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$busca_detalle_encabezado_compra = mysqli_query($con, "SELECT * FROM encabezado_compra WHERE id_encabezado_compra = '" . $id_encabezado_compra . "' ");
		$row_encabezado_compra = mysqli_fetch_array($busca_detalle_encabezado_compra);
		$id_proveedor = $row_encabezado_compra['id_proveedor'];
		$numero_documento = $row_encabezado_compra['numero_documento'];
		$detalle_encabezado_ret_compra = mysqli_query($con, "SELECT * FROM encabezado_retencion WHERE ruc_empresa= '" . $ruc_empresa . "' and numero_comprobante='" . $numero_documento . "' and id_proveedor='" . $id_proveedor . "' and estado_sri !='ANULADA' and id_compras= '" . $id_encabezado_compra . "'");
		$row_encabezado_ret_compras = mysqli_fetch_array($detalle_encabezado_ret_compra);
		$serie_retencion = $row_encabezado_ret_compras['serie_retencion'];
		$secuencial_retencion = $row_encabezado_ret_compras['secuencial_retencion'];
		$estado_retencion = $row_encabezado_ret_compras['estado_sri'];
		$id_registro_contable = $row_encabezado_ret_compras['id_registro_contable'];
		$fecha = $row_encabezado_ret_compras['fecha_emision'];
		$aut_sri = $row_encabezado_ret_compras['aut_sri'];
		if ($estado_retencion == 'AUTORIZADO') {
			$class_badge = 'label-success';
		} else {
			$class_badge = 'label-warning';
		}

		$detalle_retenciones_compra = mysqli_query($con, "SELECT * FROM cuerpo_retencion WHERE ruc_empresa='" . $ruc_empresa . "' and serie_retencion='" . $serie_retencion . "' and secuencial_retencion='" . $secuencial_retencion . "' ");
	?>
		<div style="padding: 1px; margin-bottom: 5px;" class="alert alert-info" role="alert">
			<b>Fecha:</b> <?php echo date("d/m/Y", strtotime($fecha)); ?> <b>Número: </b><?php echo $serie_retencion . '-' . str_pad($secuencial_retencion, 9, "000000000", STR_PAD_LEFT); ?><b> Aut SRI: </b><?php echo $aut_sri; ?>
		</div>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th>Año fiscal</th>
						<th>Base imponible</th>
						<th>Impuesto</th>
						<th>Código</th>
						<th>Concepto</th>
						<th>Porcentaje</th>
						<th>Valor</th>
						<th>Estado SRI</th>
					</tr>
					<?php
					$total_ret = 0;
					while ($row_detalle_ret_compras = mysqli_fetch_array($detalle_retenciones_compra)) {
						$ejercicio_fiscal = $row_detalle_ret_compras['ejercicio_fiscal'];
						$base_imponible = $row_detalle_ret_compras['base_imponible'];
						$impuesto = $row_detalle_ret_compras['impuesto'];
						$codigo = $row_detalle_ret_compras['codigo_impuesto'];
						$concepto = $row_detalle_ret_compras['nombre_retencion'];
						$porcentaje = $row_detalle_ret_compras['porcentaje_retencion'];
						$valor = $row_detalle_ret_compras['valor_retenido'];
						$total_ret += $valor;
					?>
						<tr>
							<td><?php echo $ejercicio_fiscal; ?></td>
							<td><?php echo $base_imponible; ?></td>
							<td><?php echo $impuesto; ?></td>
							<td><?php echo $codigo; ?></td>
							<td><?php echo $concepto; ?></td>
							<td><?php echo $porcentaje . "%"; ?></td>
							<td><?php echo number_format($valor, 2, '.', ''); ?></td>
							<td><span class="label <?php echo $class_badge; ?>"><?php echo $estado_retencion; ?></span></td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="5"></td>
						<td>Total retención</td>
						<td><?php echo number_format($total_ret, 2, '.', ''); ?></td>
						<td></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
		echo detalle_asiento_contable($con, $ruc_empresa, $id_registro_contable, 'RETENCIONES_COMPRAS');
	}
}

//para mostrar el detalle de retencion de compras
if ($action == 'detalle_retencion_compras') {
	$id_retencion_compra = mysqli_real_escape_string($con, (strip_tags($_GET["id_ret"], ENT_QUOTES)));
	detalle_retenciones_compras($id_retencion_compra);
}
//para mostrar el detalle de retencion de ventas
if ($action == 'detalle_retencion_ventas') {
	$id_retencion_venta = mysqli_real_escape_string($con, (strip_tags($_GET["id_ret"], ENT_QUOTES)));
	detalle_retenciones_ventas($id_retencion_venta);
}

//muestra detalle de retenciones de compras 
function detalle_retenciones_compras($id_retencion_compra)
{
	if (isset($id_retencion_compra)) {
		$con = conenta_login();
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$detalle_encabezado_ret_compra = mysqli_query($con, "SELECT * FROM encabezado_retencion as enc inner join usuarios as usu on usu.id=enc.id_usuario WHERE enc.id_encabezado_retencion='" . $id_retencion_compra . "'");
		$row_encabezado_ret_compras = mysqli_fetch_array($detalle_encabezado_ret_compra);
		$serie_retencion = $row_encabezado_ret_compras['serie_retencion'];
		$secuencial_retencion = $row_encabezado_ret_compras['secuencial_retencion'];
		$estado_retencion = $row_encabezado_ret_compras['estado_sri'];
		$id_registro_contable = $row_encabezado_ret_compras['id_registro_contable'];
		$fecha = $row_encabezado_ret_compras['fecha_emision'];
		$aut_sri = $row_encabezado_ret_compras['aut_sri'];
		$usuario = $row_encabezado_ret_compras['nombre'];

		if ($estado_retencion == 'AUTORIZADO') {
			$class_badge = 'label-success';
		} else {
			$class_badge = 'label-warning';
		}
		$detalle_retenciones_compra = mysqli_query($con, "SELECT * FROM cuerpo_retencion WHERE ruc_empresa='" . $ruc_empresa . "' and serie_retencion='" . $serie_retencion . "' and secuencial_retencion='" . $secuencial_retencion . "' ");
	?>
		<div style="padding: 1px; margin-bottom: 5px;" class="alert alert-info" role="alert">
			<b>Fecha:</b> <?php echo date("d/m/Y", strtotime($fecha)); ?> <b>Número: </b><?php echo $serie_retencion . '-' . str_pad($secuencial_retencion, 9, "000000000", STR_PAD_LEFT); ?>
			<b> Aut SRI: </b><?php echo $aut_sri; ?><b> Realizado por: </b><?php echo strtoupper($usuario); ?><b> Estado SRI: </b><span class="label <?php echo $class_badge; ?>"><?php echo $estado_retencion; ?></span>
		</div>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th>Año_fiscal</th>
						<th>Base_imponible</th>
						<th>Impuesto</th>
						<th>Código</th>
						<th>Concepto</th>
						<th>Porcentaje</th>
						<th>Valor</th>
					</tr>
					<?php
					$total_ret = 0;
					while ($row_detalle_ret_compras = mysqli_fetch_array($detalle_retenciones_compra)) {
						$ejercicio_fiscal = $row_detalle_ret_compras['ejercicio_fiscal'];
						$base_imponible = $row_detalle_ret_compras['base_imponible'];
						$impuesto = $row_detalle_ret_compras['impuesto'];
						$codigo = $row_detalle_ret_compras['codigo_impuesto'];
						$concepto = $row_detalle_ret_compras['nombre_retencion'];
						$porcentaje = $row_detalle_ret_compras['porcentaje_retencion'];
						$valor = $row_detalle_ret_compras['valor_retenido'];
						$total_ret += $valor;
					?>
						<tr>
							<td><?php echo $ejercicio_fiscal; ?></td>
							<td><?php echo $base_imponible; ?></td>
							<td><?php echo $impuesto; ?></td>
							<td><?php echo $codigo; ?></td>
							<td><?php echo $concepto; ?></td>
							<td><?php echo $porcentaje . "%"; ?></td>
							<td><?php echo number_format($valor, 2, '.', ''); ?></td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="4" class="info"></td>
						<td colspan="2" class="info" align="right">Total retención</td>
						<td class="info"><?php echo number_format($total_ret, 2, '.', ''); ?></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
		echo detalle_asiento_contable($con, $ruc_empresa, $id_registro_contable, 'RETENCIONES_COMPRAS');
	}
}

//muestra detalle de retenciones de ventas
function detalle_retenciones_ventas($id_retencion_venta)
{
	if (isset($id_retencion_venta)) {
		$con = conenta_login();
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$detalle_encabezado_ret_venta = mysqli_query($con, "SELECT * FROM encabezado_retencion_venta WHERE id_encabezado_retencion='" . $id_retencion_venta . "'");
		$row_encabezado_ret_ventas = mysqli_fetch_array($detalle_encabezado_ret_venta);
		$codigo_unico = $row_encabezado_ret_ventas['codigo_unico'];
		$id_registro_contable = $row_encabezado_ret_ventas['id_registro_contable'];
		$fecha = $row_encabezado_ret_ventas['fecha_emision'];
		$aut_sri = $row_encabezado_ret_ventas['aut_sri'];
		$serie_retencion = $row_encabezado_ret_ventas['serie_retencion'];
		$secuencial_retencion = str_pad($row_encabezado_ret_ventas['secuencial_retencion'], 9, "000000000", STR_PAD_LEFT);
		$detalle_retenciones_venta = mysqli_query($con, "SELECT * FROM cuerpo_retencion_venta WHERE codigo_unico='" . $codigo_unico . "' ");

	?>
		<div style="padding: 1px; margin-bottom: 5px;" class="alert alert-info" role="alert">
			<b>Fecha:</b> <?php echo date("d/m/Y", strtotime($fecha)); ?> <b>Número: </b><?php echo $serie_retencion . '-' . $secuencial_retencion; ?><b> Aut SRI: </b><?php echo $aut_sri; ?>
		</div>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 2px;">Documento</th>
						<th style="padding: 2px;">Año fiscal</th>
						<th style="padding: 2px;">Base imponible</th>
						<th style="padding: 2px;">Impuesto</th>
						<th style="padding: 2px;">Código</th>
						<th style="padding: 2px;">Concepto</th>
						<th style="padding: 2px;">Porcentaje</th>
						<th style="padding: 2px;">Valor</th>
					</tr>
					<?php
					$total_ret = 0;
					while ($row_detalle_ret_compras = mysqli_fetch_array($detalle_retenciones_venta)) {
						$ejercicio_fiscal = $row_detalle_ret_compras['ejercicio_fiscal'];
						$base_imponible = $row_detalle_ret_compras['base_imponible'];
						$impuesto = $row_detalle_ret_compras['impuesto'];
						switch ($impuesto) {
							case "1":
								$impuesto = 'RENTA';
								break;
							case "2":
								$impuesto = 'IVA';
								break;
							case "6":
								$impuesto = 'ISD';
								break;
						}

						$codigo = $row_detalle_ret_compras['codigo_impuesto'];
						$conceptos_retenciones = mysqli_query($con, "SELECT * FROM retenciones_sri WHERE codigo_ret='" . $codigo . "'");
						$row_conceptos_retenciones = mysqli_fetch_array($conceptos_retenciones);
						$concepto = isset($row_conceptos_retenciones['concepto_ret']) ? $row_conceptos_retenciones['concepto_ret'] : $impuesto;

						$porcentaje = $row_detalle_ret_compras['porcentaje_retencion'];
						$valor = $row_detalle_ret_compras['valor_retenido'];
						$total_ret += $valor;
					?>
						<tr>
							<td style="padding: 2px;"><?php echo $row_detalle_ret_compras['numero_documento']; ?></td>
							<td style="padding: 2px;"><?php echo $ejercicio_fiscal; ?></td>
							<td style="padding: 2px;" class="text-right"><?php echo $base_imponible; ?></td>
							<td style="padding: 2px;"><?php echo $impuesto; ?></td>
							<td style="padding: 2px;"><?php echo $codigo; ?></td>
							<td style="padding: 2px;"><?php echo $concepto; ?></td>
							<td style="padding: 2px;"><?php echo $porcentaje . "%"; ?></td>
							<td style="padding: 2px;" class="text-right"><?php echo number_format($valor, 2, '.', ''); ?></td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="5"></td>
						<td colspan="2" class="text-right">Total</td>
						<td class="text-right"><?php echo number_format($total_ret, 2, '.', ''); ?></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
		echo detalle_asiento_contable($con, $ruc_empresa, $id_registro_contable, 'RETENCIONES_VENTAS');
	}
}

if ($action == 'formas_pagos_factura') {
	$id_documento = mysqli_real_escape_string($con, (strip_tags($_GET["id_documento"], ENT_QUOTES)));
	//para traer datos la factura
	$sql_encabezado_factura = mysqli_query($con, "SELECT * FROM encabezado_factura WHERE id_encabezado_factura='" . $id_documento . "'");
	$datos_factura = mysqli_fetch_array($sql_encabezado_factura);
	$total_factura = $datos_factura['total_factura'];
	$serie_factura = $datos_factura['serie_factura'];
	$secuencial_factura = $datos_factura['secuencial_factura'];
	$numero_factura = $datos_factura['serie_factura'] . "-" . $datos_factura['secuencial_factura'];
	//eliminar el temporal con datos de ese registro de factura que se va a modificar las formas de pago
	$delete_pagos_tmp = mysqli_query($con, "DELETE FROM pago_factura_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and serie='" . $serie_factura . "' and secuencial='" . $secuencial_factura . "';");
	$query_guarda_pagos_tmp = mysqli_query($con, "INSERT INTO pago_factura_tmp (id_pago_tmp, ruc_empresa, codigo_forma_pago,serie, secuencial, valor) 
		SELECT null,'" . $ruc_empresa . "', id_forma_pago, serie_factura, secuencial_factura, valor_pago FROM formas_pago_ventas WHERE ruc_empresa ='" . $ruc_empresa . "' and serie_factura='" . $serie_factura . "' and secuencial_factura='" . $secuencial_factura . "'");
	detalle_formas_de_pago($serie_factura, $secuencial_factura);
}

//para agregar un nuevo detalle de forma de pago en facturas de venta
if ($action == 'agregar_forma_pago') {
	$con = conenta_login();
	$serie_factura = mysqli_real_escape_string($con, (strip_tags($_GET["serie_factura"], ENT_QUOTES)));
	$secuencial_factura = mysqli_real_escape_string($con, (strip_tags($_GET["secuencial_factura"], ENT_QUOTES)));
	$codigo_forma_pago = mysqli_real_escape_string($con, (strip_tags($_GET["forma_pago"], ENT_QUOTES)));
	$valor_forma_pago = mysqli_real_escape_string($con, (strip_tags($_GET["valor_pago"], ENT_QUOTES)));
	$detalle_precios = mysqli_query($con, "INSERT INTO pago_factura_tmp VALUES(null, '" . $ruc_empresa . "', '" . $codigo_forma_pago . "', '" . $serie_factura . "', '" . $secuencial_factura . "', '" . $valor_forma_pago . "')");
	detalle_formas_de_pago($serie_factura, $secuencial_factura);
}


//para eliminar forma de pago
if ($action == 'eliminar_forma_pago') {
	$con = conenta_login();
	$serie_factura = mysqli_real_escape_string($con, (strip_tags($_GET["serie_factura"], ENT_QUOTES)));
	$secuencial_factura = mysqli_real_escape_string($con, (strip_tags($_GET["secuencial_factura"], ENT_QUOTES)));
	$id_forma_pago = mysqli_real_escape_string($con, (strip_tags($_GET["id_pago"], ENT_QUOTES)));
	$delete_pago = mysqli_query($con, "DELETE FROM pago_factura_tmp WHERE id_pago_tmp ='" . $id_forma_pago . "'");
	detalle_formas_de_pago($serie_factura, $secuencial_factura);
}

//mostrar detalle de formas de pago en detalle de la compra
function detalle_formas_de_pago($serie_factura, $secuencial_factura)
{
	if (isset($secuencial_factura)) {
		$con = conenta_login();
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$detalle_factura = mysqli_query($con, "SELECT * FROM encabezado_factura WHERE ruc_empresa= '" . $ruc_empresa . "' and serie_factura= '" . $serie_factura . "' and secuencial_factura ='" . $secuencial_factura . "'");
		$row_total_factura = mysqli_fetch_array($detalle_factura);
		$total_factura = $row_total_factura['total_factura'];

		$detalle_formas_de_pago = mysqli_query($con, "SELECT * FROM pago_factura_tmp WHERE ruc_empresa= '" . $ruc_empresa . "' and serie= '" . $serie_factura . "' and secuencial ='" . $secuencial_factura . "'");
	?>
		<div class="panel panel-info" style="margin-bottom: 1px;">
			<div class="table-responsive">
				<table class="table table-bordered">
					<tr class="info">
						<th style="padding: 2px;">Forma pago</th>
						<th style="padding: 2px;">Valor</th>
						<th style="padding: 2px;">Eliminar</th>
					</tr>
					<?php
					$valor_total = 0;
					while ($row_detalle_pagos = mysqli_fetch_array($detalle_formas_de_pago)) {
						$id_registro_forma_pago = $row_detalle_pagos['id_pago_tmp'];
						$id_forma_pago = $row_detalle_pagos['codigo_forma_pago'];
						$valor = $row_detalle_pagos['valor'];
						$valor_total += $row_detalle_pagos['valor'];
						$nombres_formas_pago = mysqli_query($con, "SELECT * FROM formas_de_pago WHERE codigo_pago='" . $id_forma_pago . "' and aplica_a='VENTAS'");
						$row_nombres_formas_pago = mysqli_fetch_array($nombres_formas_pago);
						$nombre_pago = $row_nombres_formas_pago['nombre_pago'];
					?>
						<tr>
							<td class='col-md-8' style="padding: 2px;"><?php echo $nombre_pago; ?></td>
							<td class="col-md-2 text-right" style="padding: 2px;"><?php echo number_format($valor, 2, '.', ''); ?></td>
							<td style="padding: 2px;" class='col-md-2 text-right'><a href="#" class='btn btn-danger btn-xs' title='Eliminar item' onclick="eliminar_forma_pago('<?php echo $id_registro_forma_pago; ?>')"><i class="glyphicon glyphicon-remove"></i></a></td>
						</tr>
					<?php
					}
					?>
					<input type="hidden" value="<?php echo $valor_total; ?>" id="total_pagos_agregados" name="total_pagos_agregados">
					<tr class="info">
						<td class="text-right">Total pagos:</td>
						<td class="text-right"><?php echo number_format($valor_total, 2, '.', ''); ?></td>
						<td class="text-right">
							<font color="red"><b>Dif: <?php echo number_format($total_factura - $valor_total, 2, '.', ''); ?></b></font>
						</td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	}
}

//detalle de asiento contable de la compra el asiento es la obligacion inicial
function detalle_asiento_contable($con, $ruc_empresa, $id_registro_contable, $tipo_asiento)
{
	$detalle_contable = mysqli_query($con, "SELECT plan.codigo_cuenta as codigo_cuenta,
 plan.nombre_cuenta as nombre_cuenta, det_dia.detalle_item as detalle_item, 
 det_dia.debe as debe, det_dia.haber as haber, 
 enc_dia.id_diario as numero_asiento, 
 enc_dia.codigo_unico as codigo_unico, enc_dia.tipo as tipo, enc_dia.id_diario as id_diario FROM encabezado_diario as enc_dia 
 INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico and enc_dia.codigo_unico_bloque=det_dia.codigo_unico_bloque and enc_dia.ruc_empresa=det_dia.ruc_empresa 
 INNER JOIN plan_cuentas as plan ON det_dia.id_cuenta=plan.id_cuenta 
 WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and enc_dia.id_diario= '" . $id_registro_contable . "'
 and enc_dia.tipo= '" . $tipo_asiento . "' order by det_dia.debe desc");

	if ($detalle_contable->num_rows > 0) {
	?>
		<div class="row">
			<div class="panel-group" id="accordionAsiento" style="margin-bottom: 5px; margin-top: -15px;">
				<div class="col-xs-12">
					<div class="panel panel-info">
						<a class="list-group-item list-group-item-info" style="height:35px;" data-toggle="collapse" data-parent="#accordionAsiento" href="#<?php echo $id_registro_contable ?>"><span class="caret"></span> Asiento Contable</a>
						<div id="<?php echo $id_registro_contable ?>" class="panel-collapse collapse">

							<div class="panel panel-info">
								<div class="table-responsive">
									<table class="table table-bordered">
										<tr class="info">
											<th style="padding: 2px;">Código</th>
											<th style="padding: 2px;">Cuenta</th>
											<th style="padding: 2px;">Asiento</th>
											<th style="padding: 2px;">Detalle</th>
											<th style="padding: 2px; text-align: center;">Debe</th>
											<th style="padding: 2px; text-align: center;">Haber</th>
										</tr>
										<?php
										$suma_debe = 0;
										$suma_haber = 0;
										foreach ($detalle_contable as $detalle) {
											$suma_debe += $detalle['debe'];
											$suma_haber += $detalle['haber'];
										?>
											<tr>
												<td style="padding: 2px;"><?php echo $detalle['codigo_cuenta']; ?></td>
												<td style="padding: 2px;"><?php echo $detalle['nombre_cuenta']; ?></td>
												<td style="padding: 2px;"><a href="../pdf/pdf_diario_contable.php?action=diario_contable&id_diario=<?php echo $detalle['id_diario']; ?>" class='label label-default' title='Descargar asiento de <?php echo $detalle['tipo']; ?>' target="_blank"><i class="glyphicon glyphicon-print"></i> <?php echo $detalle['numero_asiento']; ?></a></td>
												<td style="padding: 2px;"><?php echo $detalle['detalle_item']; ?></td>
												<td style="padding: 2px; text-align: right;"><?php echo $detalle['debe']; ?></td>
												<td style="padding: 2px; text-align: right;"><?php echo $detalle['haber']; ?></td>
											</tr>
										<?php
										}
										?>
										<tr class="info">
											<th style="padding: 2px; text-align: right;" colspan="4" text="right">Sumas:</th>
											<th style="padding: 2px; text-align: right;"><?php echo number_format($suma_debe, 2, '.', ''); ?></th>
											<th style="padding: 2px; text-align: right;"><?php echo number_format($suma_haber, 2, '.', ''); ?></th>
										</tr>
									</table>
								</div>
							</div>

						</div>
					</div>
				</div>
			</div>
		</div>
	<?php
	}
}

//detalle de compras del proveedor me sirve en configurar cuentas de compras para generar asientos
if ($action == 'detalle_compras_proveedor') {
	$con = conenta_login();
	$id_proveedor = $_GET['id_proveedor'];
	$result_detalle_compra =  mysqli_query($con, "SELECT DISTINCT(cue_com.detalle_producto) as detalle FROM encabezado_compra as enc_com INNER JOIN cuerpo_compra as cue_com ON enc_com.codigo_documento=cue_com.codigo_documento WHERE enc_com.id_proveedor= '" . $id_proveedor . "' and enc_com.ruc_empresa = '" . $ruc_empresa . "' order by cue_com.id_cuerpo_compra desc LIMIT 10");
	$datos_proveedor =  mysqli_query($con, "SELECT * FROM proveedores WHERE id_proveedor= '" . $id_proveedor . "' ");
	$row_proveedores = mysqli_fetch_array($datos_proveedor);
	?>
	<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-info" role="alert">
		<b>Proveedor:</b> <?php echo $row_proveedores['razon_social']; ?>
	</div>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-bordered">
				<tr class="info">
					<th style="padding: 2px;">Descripción de compras y servicios adquiridos</th>
				</tr>
				<?php
				while ($row_detalle_compra = mysqli_fetch_array($result_detalle_compra)) {
					$detalle_compra = $row_detalle_compra['detalle'];
				?>
					<tr>
						<td style="padding: 2px;"><?php echo $detalle_compra; ?></td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
	<?php
}

// para ver el detalle del pago del documento de una compra		
function detalle_pago_compra($con, $codigo_documento)
{
	$detalle_documento = mysqli_query($con, "SELECT * FROM encabezado_compra WHERE codigo_documento = '" . $codigo_documento . "' and id_comprobante !='4' ");
	$row_documento = mysqli_fetch_array($detalle_documento);
	$id_comprobante = isset($row_documento['id_comprobante']) ? $row_documento['id_comprobante'] : "";
	$detalle_pagos = mysqli_query($con, "SELECT fp.fecha_emision as fecha_emision, 
	fp.valor_forma_pago as valor, det.numero_ing_egr as numero_ing_egr,
	det.codigo_documento as codigo_documento, fp.codigo_forma_pago as forma_pago, 
	fp.id_cuenta as banco, fp.detalle_pago as tipo, fp.cheque as cheque 
	FROM formas_pagos_ing_egr as fp 
	INNER JOIN detalle_ingresos_egresos as det ON
	fp.codigo_documento = det.codigo_documento 
	WHERE det.codigo_documento_cv = '" . $codigo_documento . "' 
	and fp.tipo_documento='EGRESO' ");
	$cuenta_registros = mysqli_num_rows($detalle_pagos);
	$suma_pagos = 0;
	if (!empty($id_comprobante)) {
		if ($cuenta_registros > 0) {
	?>
			<div class="row">
				<div class="panel-group" id="accordion_pago_compra" style="margin-bottom: 5px; margin-top: 5px;">
					<div class="col-xs-12">
						<div class="panel panel-info">
							<a class="list-group-item list-group-item-info" style="height:35px;" data-toggle="collapse" data-parent="#accordion_pago_compra" href="#collapsePagoCompra"><span class="caret"></span> Detalle de pagos</a>
							<div id="collapsePagoCompra" class="panel-collapse collapse">
								<div class="panel panel-info">
									<div class="table-responsive">
										<table class="table table-bordered">
											<tr class="info">
												<th style="padding: 2px;">Fecha emisión</th>
												<th style="padding: 2px;">Forma de pago</th>
												<th style="padding: 2px;">Tipo</th>
												<th style="padding: 2px;">Cheque</th>
												<th style="padding: 2px;">Valor</th>
												<th style="padding: 2px; text-align: center;">No. Egreso</th>
											</tr>
											<?php
											while ($row_detalle_pago = mysqli_fetch_array($detalle_pagos)) {
												$codigo_documento_egreso = $row_detalle_pago['codigo_documento'];
												$valor_del_documento = $row_detalle_pago['valor'];
												$numero_egreso = $row_detalle_pago['numero_ing_egr'];
												$forma_pago = $row_detalle_pago['forma_pago'];
												$banco = $row_detalle_pago['banco'];
												$cheque = $row_detalle_pago['cheque'] > 0 ? $row_detalle_pago['cheque'] : "--";
												$suma_pagos += $valor_del_documento;
												$fecha_emision = $row_detalle_pago['fecha_emision'];
												$tipo = $row_detalle_pago['tipo'];
												if ($tipo == 'C') {
													$tipo = 'Cheque';
												} else if ($tipo == 'T') {
													$tipo = 'Transferencia';
												} else if ($tipo == 'D') {
													$tipo = 'Débito';
												} else {
													$tipo = '--';
												}

												if ($forma_pago > 0) {
													$query_cobros_pagos = mysqli_query($con, "SELECT descripcion FROM opciones_cobros_pagos WHERE id = '" . $forma_pago . "'");
													$row_cobros_pagos = mysqli_fetch_array($query_cobros_pagos);
													$forma_pago = $row_cobros_pagos['descripcion'];
												}

												if ($banco > 0) {
													$cuentas = mysqli_query($con, "SELECT concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria 
												FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos 
												WHERE cue_ban.id_cuenta ='" . $banco . "'");
													$row_cuenta = mysqli_fetch_array($cuentas);
													$forma_pago = strtoupper($row_cuenta['cuenta_bancaria']);
												}
											?>
												<tr>
													<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_emision)); ?></td>
													<td style="padding: 2px; text-align: left;"><?php echo $forma_pago; ?></td>
													<td style="padding: 2px; text-align: center;"><?php echo $tipo; ?></td>
													<td style="padding: 2px; text-align: center;"><?php echo $cheque; ?></td>
													<td style="padding: 2px; text-align: right;"><?php echo number_format($valor_del_documento, 2, '.', ''); ?></td>
													<td style="padding: 2px; text-align: center;">
														<a href="../pdf/pdf_egreso.php?action=egreso&codigo_documento=<?php echo $codigo_documento_egreso; ?>" class='label label-default' title='Descargar egreso Pdf' target="_blank"><i class="glyphicon glyphicon-print"></i> <?php echo $numero_egreso; ?></a>
													</td>
												</tr>
											<?php
											}
											?>
											<tr class="info">
												<td style="padding: 2px; text-align: right;" colspan="4">Total pagos </td>
												<td style="padding: 2px; text-align: right;">
													<?php echo number_format($suma_pagos, 2, '.', ''); ?>
												</td>
												<td style="padding: 2px;"></td>
											</tr>
										</table>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

			</div>

		<?php
		}
	}
}


// para ver el detalle del pago del documento de venta	
function detalle_pago_recibo($con, $ruc_empresa, $id_encabezado_recibo)
{

	$detalle_pagos = mysqli_query($con, "SELECT * FROM detalle_ingresos_egresos WHERE ruc_empresa= '" . $ruc_empresa . "' and codigo_documento_cv = concat('RV','" . $id_encabezado_recibo . "') and tipo_documento='INGRESO' and estado='OK'");
	$cuenta_registros = mysqli_num_rows($detalle_pagos);
	$detalle_documento = mysqli_query($con, "SELECT * FROM encabezado_recibo WHERE id_encabezado_recibo = '" . $id_encabezado_recibo . "'");
	$row_documento = mysqli_fetch_array($detalle_documento);
	$total_documento = $row_documento['total_recibo'];
	$suma_cobros = 0;
	if ($cuenta_registros > 0) {
		?>
		<div class="row">
			<div class="panel-group" id="accordion_pago" style="margin-bottom: 5px; margin-top: 5px;">
				<div class="col-xs-12">
					<div class="panel panel-info">
						<a class="list-group-item list-group-item-info" style="height:35px;" data-toggle="collapse" data-parent="#accordion_pago" href="#collapse2"><span class="caret"></span> Detalle del cobro</a>
						<div id="collapse2" class="panel-collapse collapse">
							<div class="panel panel-info">
								<div class="table-responsive">
									<table class="table table-bordered">
										<tr class="info">
											<th style="padding: 2px;">Fecha ingreso</th>
											<th style="padding: 2px;">Forma pago</th>
											<th style="padding: 2px;">Valor</th>
											<th style="padding: 2px; text-align: center;">No. Ingreso</th>
											<th style="padding: 2px;">Cuenta</th>
											<th style="padding: 2px;">Estado</th>
										</tr>
										<?php
										while ($row_detalle_ingreso = mysqli_fetch_array($detalle_pagos)) {
											$codigo_documento = $row_detalle_ingreso['codigo_documento'];
											$valor_documento = $row_detalle_ingreso['valor_ing_egr'];
											$numero_ingreso = $row_detalle_ingreso['numero_ing_egr'];
											$suma_cobros += $valor_documento;
											$detalle_formas_pagos = mysqli_query($con, "SELECT * FROM formas_pagos_ing_egr WHERE codigo_documento = '" . $codigo_documento . "' and tipo_documento='INGRESO' and estado='OK'");
											$row_forma_pago = mysqli_fetch_array($detalle_formas_pagos);
											$fecha_emision = $row_forma_pago['fecha_emision'];

											$estado_pago = $row_forma_pago['estado_pago'];
											$codigo_forma_pago = $row_forma_pago['codigo_forma_pago'];
											$id_cuenta = $row_forma_pago['id_cuenta'];

											if ($id_cuenta > 0) {
												$cuentas = mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.id_cuenta ='" . $id_cuenta . "'");
												$row_cuenta = mysqli_fetch_array($cuentas);
												$cuenta_bancaria = strtoupper($row_cuenta['cuenta_bancaria']);
												$forma_pago = $row_forma_pago['detalle_pago'];
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


											$valor_forma_pago = $valor_documento;

										?>
											<tr>
												<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_emision)); ?></td>
												<td style="padding: 2px;"><?php echo $forma_pago; ?></td>
												<td style="padding: 2px; text-align: right;"><?php echo number_format($valor_forma_pago, 2, '.', ''); ?></td>
												<td style="padding: 2px; text-align: center;">
													<a href="../pdf/pdf_ingreso.php?action=ingreso&codigo_unico=<?php echo $codigo_documento; ?>" class='label label-default' title='Descargar ingreso Pdf' target="_blank"><i class="glyphicon glyphicon-print"></i> <?php echo $numero_ingreso; ?></a>
												</td>
												<td style="padding: 2px;"><?php echo $cuenta_bancaria; ?></td>
												<td style="padding: 2px;"><?php echo $estado_pago; ?></td>
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
		</div>
	<?php
	}
	?>
	<div class="panel panel-info">
		<table class="table table-bordered">
			<tr class="info">
				<td style="padding: 2px;">
					Total pagos recibidos: <?php echo number_format($suma_cobros, 2, '.', ''); ?>
				</td>
				<th style="padding: 2px; ">
					Saldo pendiente de cobro: <?php echo number_format($total_documento - $suma_cobros, 2, '.', ''); ?>
				</th>
			</tr>
		</table>
	</div>
	<?php
}


// para ver el detalle del pago del documento de venta	
function detalle_pago_venta($con, $ruc_empresa, $id_encabezado_factura)
{

	$detalle_pagos = mysqli_query($con, "SELECT * FROM detalle_ingresos_egresos WHERE ruc_empresa= '" . $ruc_empresa . "' and codigo_documento_cv = '" . $id_encabezado_factura . "' and tipo_documento='INGRESO' and estado='OK'");
	$cuenta_registros = mysqli_num_rows($detalle_pagos);

	$detalle_documento = mysqli_query($con, "SELECT * FROM encabezado_factura WHERE id_encabezado_factura = '" . $id_encabezado_factura . "'");
	$row_documento = mysqli_fetch_array($detalle_documento);
	$numero_documento = $row_documento['serie_factura'] . "-" . str_pad($row_documento['secuencial_factura'], 9, "000000000", STR_PAD_LEFT);
	$total_documento = $row_documento['total_factura'];

	$detalle_nc = mysqli_query($con, "SELECT round(sum(total_nc),2) as total_nc FROM encabezado_nc WHERE factura_modificada = '" . $numero_documento . "' and mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' group by factura_modificada");
	$row_nc = mysqli_fetch_array($detalle_nc);
	$total_nc = isset($row_nc['total_nc']) ? $row_nc['total_nc'] : 0;

	$detalle_retenciones = mysqli_query($con, "SELECT round(sum(valor_retenido),2) as valor_retenido 
		FROM cuerpo_retencion_venta as cue_ret 
		INNER JOIN encabezado_retencion_venta as enc_ret ON enc_ret.codigo_unico=cue_ret.codigo_unico 
		WHERE cue_ret.numero_documento = '" . str_replace("-", "", $numero_documento) . "' 
		and mid(enc_ret.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and mid(cue_ret.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' group by cue_ret.numero_documento");
	$row_retenciones = mysqli_fetch_array($detalle_retenciones);

	$total_retencion = isset($row_retenciones['valor_retenido']) ? $row_retenciones['valor_retenido'] : 0;

	$suma_cobros = 0;

	if ($cuenta_registros > 0) {
	?>
		<div class="row">
			<div class="panel-group" id="accordion_pago" style="margin-bottom: 5px; margin-top: 5px;">
				<div class="col-xs-12">
					<div class="panel panel-info">
						<a class="list-group-item list-group-item-info" style="height:35px;" data-toggle="collapse" data-parent="#accordion_pago" href="#collapse2"><span class="caret"></span> Detalle del cobro</a>
						<div id="collapse2" class="panel-collapse collapse">
							<div class="panel panel-info">
								<div class="table-responsive">
									<table class="table table-bordered">
										<tr class="info">
											<th style="padding: 2px;">Fecha ingreso</th>
											<th style="padding: 2px;">Forma pago</th>
											<th style="padding: 2px;">Valor</th>
											<th style="padding: 2px; text-align: center;">No. Ingreso</th>
											<th style="padding: 2px;">Cuenta</th>
											<th style="padding: 2px;">Estado</th>
										</tr>
										<?php


										while ($row_detalle_ingreso = mysqli_fetch_array($detalle_pagos)) {
											$codigo_documento = $row_detalle_ingreso['codigo_documento'];
											$valor_documento = $row_detalle_ingreso['valor_ing_egr'];
											$numero_ingreso = $row_detalle_ingreso['numero_ing_egr'];
											$suma_cobros += $valor_documento;
											$detalle_formas_pagos = mysqli_query($con, "SELECT * FROM formas_pagos_ing_egr WHERE codigo_documento = '" . $codigo_documento . "' and tipo_documento='INGRESO' and estado='OK'");
											$row_forma_pago = mysqli_fetch_array($detalle_formas_pagos);
											$fecha_emision = $row_forma_pago['fecha_emision'];

											$estado_pago = $row_forma_pago['estado_pago'];
											$codigo_forma_pago = $row_forma_pago['codigo_forma_pago'];
											$id_cuenta = $row_forma_pago['id_cuenta'];

											if ($id_cuenta > 0) {
												$cuentas = mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.id_cuenta ='" . $id_cuenta . "'");
												$row_cuenta = mysqli_fetch_array($cuentas);
												$cuenta_bancaria = strtoupper($row_cuenta['cuenta_bancaria']);
												$forma_pago = $row_forma_pago['detalle_pago'];
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
											$valor_forma_pago = $valor_documento;

										?>
											<tr>
												<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_emision)); ?></td>
												<td style="padding: 2px;"><?php echo $forma_pago; ?></td>
												<td style="padding: 2px; text-align: right;"><?php echo number_format($valor_forma_pago, 2, '.', ''); ?></td>
												<td style="padding: 2px; text-align: center;">
													<a href="../pdf/pdf_ingreso.php?action=ingreso&codigo_unico=<?php echo $codigo_documento; ?>" class='label label-default' title='Descargar ingreso Pdf' target="_blank"><i class="glyphicon glyphicon-print"></i> <?php echo $numero_ingreso; ?></a>
												</td>
												<td style="padding: 2px;"><?php echo $cuenta_bancaria; ?></td>
												<td style="padding: 2px;"><?php echo $estado_pago; ?></td>
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
		</div>
	<?php
	}
	?>
	<div class="panel panel-info">
		<table class="table table-bordered">
			<tr class="info">
				<td style="padding: 2px;">
					Total pagos recibidos: <?php echo number_format($suma_cobros, 2, '.', ''); ?>
				</td>
				<td style="padding: 2px;">
					Total Notas de crédito: <?php echo number_format($total_nc, 2, '.', ''); ?>
				</td>
				<td style="padding: 2px;">
					Total retenciones: <?php echo number_format($total_retencion, 2, '.', ''); ?>
				</td>
				<th style="padding: 2px; ">
					Saldo pendiente de cobro: <?php echo number_format($total_documento - $suma_cobros - $total_nc - $total_retencion, 2, '.', ''); ?>
				</th>
			</tr>
		</table>
	</div>
<?php
}


function decimales($con, $ruc_empresa, $serie_recibo)
{
	$sql_decimales = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' and serie = '" . $serie_recibo . "' ");
	$row_decimales = mysqli_fetch_array($sql_decimales);
	$decimal_precio = intval($row_decimales['decimal_doc'] = "" ? "2" : $row_decimales['decimal_doc']);
	$decimal_cant = intval($row_decimales['decimal_cant'] = "" ? "2" : $row_decimales['decimal_cant']);
	$impuestos_recibo = $row_decimales['impuestos_recibo'];
	$array_decimales = array('decimal_precio' => $decimal_precio, 'decimal_cantidad' => $decimal_cant, 'impuestos_recibo' => $impuestos_recibo);
	return $array_decimales;
}


function detalle_documento_adquisiciones($codigo_documento)
{
	$con = conenta_login();
	//para consultar cuerpo
	$busca_encabezado_compra = mysqli_query($con, "SELECT * FROM encabezado_compra as enc INNER JOIN proveedores as pro 
	ON pro.id_proveedor = enc.id_proveedor WHERE enc.codigo_documento= '" . $codigo_documento . "' ");
	$row_encabezado_compra = mysqli_fetch_array($busca_encabezado_compra);
	$codigo_documento = isset($row_encabezado_compra['codigo_documento']) ? $row_encabezado_compra['codigo_documento'] : "";
	$propina = isset($row_encabezado_compra['propina']) ? $row_encabezado_compra['propina'] : 0;
	$otros_val = isset($row_encabezado_compra['otros_val']) ? $row_encabezado_compra['otros_val'] : 0;
	$proveedor = isset($row_encabezado_compra['razon_social']) ? $row_encabezado_compra['razon_social'] : "";
	$fecha = isset($row_encabezado_compra['fecha_compra']) ? $row_encabezado_compra['fecha_compra'] : "";
	$numero_documento = isset($row_encabezado_compra['numero_documento']) ? $row_encabezado_compra['numero_documento'] : 0;

	//para consultar cuerpo
	$busca_detalle_compra = "SELECT * FROM cuerpo_compra WHERE codigo_documento= '" . $codigo_documento . "' ";
	$result_detalle_compra = $con->query($busca_detalle_compra);
	//para consultar adicional info
	$busca_info_compra = "SELECT * FROM detalle_adicional_compra WHERE codigo_documento= '" . $codigo_documento . "' ";
	$result_info_compra = $con->query($busca_info_compra);
	//para consultar pago
	$busca_info_pago = "SELECT * FROM formas_pago_compras as fpc, formas_de_pago as fp WHERE fpc.codigo_documento= '" . $codigo_documento . "' and fp.codigo_pago=fpc.forma_pago and fp.aplica_a='VENTAS' ";
	$result_info_pago = $con->query($busca_info_pago);
?>
	<div style="padding: 2px; margin-bottom: 5px; margin-top: -5px;" class="alert alert-info" role="alert">
		<b>Fecha:</b> <?php echo date("d/m/Y", strtotime($fecha)); ?> <b>Documento: </b><?php echo $numero_documento; ?> <b>Proveedor: </b><?php echo $proveedor; ?>
	</div>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-bordered">
				<tr class="info">
					<th style="padding: 2px;">Código</th>
					<th style="padding: 2px;">Producto</th>
					<th style="padding: 2px;">Cantidad</th>
					<th style="padding: 2px;">Precio</th>
					<th style="padding: 2px;">Descuento</th>
					<th style="padding: 2px;">Subtotal</th>
					<th style="padding: 2px;">IVA</th>
				</tr>
				<?php
				while ($row_detalle_compra = mysqli_fetch_array($result_detalle_compra)) {
					$codigo_documento = $row_detalle_compra['codigo_documento'];
					$codigo_producto = $row_detalle_compra['codigo_producto'];
					$nombre_producto = $row_detalle_compra['detalle_producto'];
					$cantidad_producto = $row_detalle_compra['cantidad'];
					$precio_producto = $row_detalle_compra['precio'];
					$descuento_producto = $row_detalle_compra['descuento'];
					$subtotal = $row_detalle_compra['subtotal'];
					$det_impuesto = $row_detalle_compra['det_impuesto'];

					$detalle_tipo_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE codigo = '" . $det_impuesto . "'");
					$row_detalle_iva = mysqli_fetch_array($detalle_tipo_iva);

				?>
					<tr>
						<td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
						<td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
						<td style="padding: 2px;" class="text-right"><?php echo $cantidad_producto; ?></td>
						<td style="padding: 2px;" class="text-right"><?php echo $precio_producto; ?></td>
						<td style="padding: 2px;" class="text-right"><?php echo $descuento_producto; ?></td>
						<td style="padding: 2px;" class="text-right"><?php echo $subtotal; ?></td>
						<td style="padding: 2px;"><?php echo $row_detalle_iva['tarifa'] ?></td>
					<?php
				}
					?>
			</table>
		</div>
	</div>

	<div class="row">
		<!-- detalle de subtotales-->
		<!--para mostrar los adicionales que vienen en la compra -->
		<div class="col-xs-6">
			<div class="panel-group" id="accordion_adicionales_compra" style="margin-bottom: 5px; margin-top: -15px;">
				<a class="list-group-item list-group-item-info" style="height:35px;" data-toggle="collapse" data-parent="#accordion_adicionales_compra" href="#collapseAC"><span class="caret"></span> Adicionales y forma de pago</a>
				<div id="collapseAC" class="panel-collapse collapse">
					<div class="panel panel-info">
						<div class="table-responsive">
							<table class="table table-bordered">
								<tr class="info">
									<th style="padding: 2px;">Concepto</th>
									<th style="padding: 2px;">Detalle</th>
								</tr>
								<?php
								while ($row_info_adicional = mysqli_fetch_array($result_info_compra)) {
									$concepto = $row_info_adicional['adicional_concepto'];
									$descripcion = $row_info_adicional['adicional_descripcion'];
								?>
									<tr>
										<td class='col-md-2'><?php echo $concepto; ?></td>
										<td class='col-md-4'><?php echo $descripcion; ?></td>
									</tr>
								<?php
								}
								?>
							</table>
						</div>

						<div class="table-responsive">
							<table class="table table-bordered">
								<tr class="info">
									<th style="padding: 2px;">Forma Pago</th>
									<th style="padding: 2px;">Valor</th>
									<th style="padding: 2px;">Plazo</th>
									<th style="padding: 2px;">Tiempo</th>
								</tr>
								<?php
								while ($row_detalle_pago = mysqli_fetch_array($result_info_pago)) {
									$forma_pago = $row_detalle_pago['nombre_pago'];
									$valor_pago = $row_detalle_pago['total_pago'];
									$plazo_pago = $row_detalle_pago['plazo_pago'];
									$tiempo_pago = $row_detalle_pago['tiempo_pago'];
								?>
									<tr>
										<td><?php echo $forma_pago; ?></td>
										<td><?php echo $valor_pago; ?></td>
										<td><?php echo $plazo_pago; ?></td>
										<td><?php echo $tiempo_pago; ?></td>
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
		<!--hasta aqui para mostrar los adiconales que vienen en la compra -->
		<?php
		$subtotal_general = 0;
		$total_descuento = 0;
		$sql_factura = mysqli_query($con, "select sum(subtotal) as subtotal_general, sum(descuento) as total_descuento  FROM cuerpo_compra WHERE codigo_documento='" . $codigo_documento . "' ");
		$row_subtotal = mysqli_fetch_array($sql_factura);
		$subtotal_general = $row_subtotal['subtotal_general'];
		$total_descuento = $row_subtotal['total_descuento'];
		?>

		<div class="col-xs-6">
			<div class="panel panel-info" style="margin-bottom: 5px; margin-top: -15px;">
				<div class="table-responsive">
					<table class="table">
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>SUBTOTAL GENERAL: </td>
							<td style="padding: 2px;" class='text-center'><?php echo number_format($subtotal_general, 2, '.', ''); ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
						<?php
						//PARA MOSTRAR LOS NOMBRES DE CADA TARIFA DE IVA Y LOS VALORES DE CADA SUBTOTAL
						$subtotal_tarifa_iva = 0;
						$sql = mysqli_query($con, "select ti.tarifa as tarifa, sum(round(cue_com.subtotal,2)) as suma_tarifa_iva FROM cuerpo_compra as cue_com INNER JOIN tarifa_iva as ti ON ti.codigo=cue_com.det_impuesto WHERE cue_com.codigo_documento='" . $codigo_documento . "' and cue_com.impuesto='2' group by cue_com.det_impuesto ");
						while ($row = mysqli_fetch_array($sql)) {
							$nombre_tarifa_iva = strtoupper($row["tarifa"]);
							$subtotal_tarifa_iva = number_format($row['suma_tarifa_iva'], 2, '.', '');
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>SUBTOTAL <?php echo ($nombre_tarifa_iva); ?>:</td>
								<td style="padding: 2px;" class='text-center'><?php echo number_format($subtotal_tarifa_iva, 2, '.', ''); ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>

						<?php
						}
						?>
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>TOTAL DESCUENTO: </td>
							<td style="padding: 2px;" class='text-center'><?php echo number_format($total_descuento, 2, '.', ''); ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
						<?php
						//PARA MOSTRAR LOS IVAS
						$total_iva = 0;
						$subtotal_porcentaje_iva = 0;
						$sql = mysqli_query($con, "select ti.tarifa as tarifa, (sum(cue_com.subtotal) * ti.tarifa /100)  as porcentaje FROM cuerpo_compra as cue_com INNER JOIN tarifa_iva as ti ON ti.codigo=cue_com.det_impuesto WHERE cue_com.codigo_documento= '" . $codigo_documento . "' and ti.tarifa > 0 group by cue_com.det_impuesto ");
						while ($row = mysqli_fetch_array($sql)) {
							$nombre_porcentaje_iva = strtoupper($row["tarifa"]);
							$porcentaje_iva = $row['porcentaje'];
							$subtotal_porcentaje_iva = $porcentaje_iva;
							$total_iva += $subtotal_porcentaje_iva;
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>IVA <?php echo ($nombre_porcentaje_iva); ?>:</td>
								<td style="padding: 2px;" class='text-center'><?php echo number_format($subtotal_porcentaje_iva, 2, '.', ''); ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						}
						if ($propina > 0) {
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>SERVICIO O PROPINA: </td>
								<td style="padding: 2px;" class='text-center'><?php echo number_format($propina, 2, '.', ''); ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						}
						if ($otros_val > 0) {
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>ICE, OTROS: </td>
								<td style="padding: 2px;" class='text-center'><?php echo number_format($otros_val, 2, '.', ''); ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						}
						?>
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>TOTAL: </td>
							<td style="padding: 2px;" class='text-center'><?php echo number_format($subtotal_general + $total_iva + $propina + $otros_val, 2, '.', ''); ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
					</table>
				</div>
			</div>
		</div>
		<!-- hasta aqui detalle de subtotales-->
	</div>
<?php
}

function detalle_notificaciones_adquisiciones($codigo_documento)
{
	$con = conenta_login();
	//para consultar cuerpo
	$busca_encabezado_compra = mysqli_query($con, "SELECT * FROM encabezado_compra as enc INNER JOIN proveedores as pro 
	ON pro.id_proveedor = enc.id_proveedor WHERE enc.codigo_documento= '" . $codigo_documento . "' ");
	$row_encabezado_compra = mysqli_fetch_array($busca_encabezado_compra);
	$codigo_documento = isset($row_encabezado_compra['codigo_documento']) ? $row_encabezado_compra['codigo_documento'] : "";
	$propina = isset($row_encabezado_compra['propina']) ? $row_encabezado_compra['propina'] : 0;
	$otros_val = isset($row_encabezado_compra['otros_val']) ? $row_encabezado_compra['otros_val'] : 0;
	$proveedor = isset($row_encabezado_compra['razon_social']) ? $row_encabezado_compra['razon_social'] : "";
	$fecha = isset($row_encabezado_compra['fecha_compra']) ? $row_encabezado_compra['fecha_compra'] : "";
	$numero_documento = isset($row_encabezado_compra['numero_documento']) ? $row_encabezado_compra['numero_documento'] : 0;
	$id_proveedor = isset($row_encabezado_compra['id_proveedor']) ? $row_encabezado_compra['id_proveedor'] : 0;

	//para consultar cuerpo
	$busca_detalle_compra = "SELECT * FROM cuerpo_compra WHERE codigo_documento= '" . $codigo_documento . "' ";
	$result_detalle_compra = $con->query($busca_detalle_compra);
	//para consultar adicional info
	$busca_info_compra = "SELECT * FROM detalle_adicional_compra WHERE codigo_documento= '" . $codigo_documento . "' ";
	$result_info_compra = $con->query($busca_info_compra);
	//para consultar pago
	$busca_info_pago = "SELECT * FROM formas_pago_compras as fpc, formas_de_pago as fp WHERE fpc.codigo_documento= '" . $codigo_documento . "' and fp.codigo_pago=fpc.forma_pago and fp.aplica_a='VENTAS' ";
	$result_info_pago = $con->query($busca_info_pago);
	//para consultar detalle de observaciones
	$busca_info_observaciones = "SELECT * FROM notificaciones_adquisiciones WHERE codigo_documento= '" . $codigo_documento . "' and status !=0 ";
	$result_info_observaciones = $con->query($busca_info_observaciones);
?>
	<div style="padding: 2px; margin-bottom: 5px; margin-top: -5px;" class="alert alert-info" role="alert">
		<b>Fecha:</b> <?php echo date("d/m/Y", strtotime($fecha)); ?> <b>Documento: </b><?php echo $numero_documento; ?> <b>Proveedor: </b><?php echo $proveedor; ?>
	</div>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-bordered">
				<tr class="info">
					<th style="padding: 2px;">Código</th>
					<th style="padding: 2px;">Producto</th>
					<th style="padding: 2px;">Cantidad</th>
					<th style="padding: 2px;">Precio</th>
					<th style="padding: 2px;">Descuento</th>
					<th style="padding: 2px;">Subtotal</th>
					<th style="padding: 2px;">IVA</th>
				</tr>
				<?php
				while ($row_detalle_compra = mysqli_fetch_array($result_detalle_compra)) {
					$codigo_documento = $row_detalle_compra['codigo_documento'];
					$id_cuerpo_compra = $row_detalle_compra['id_cuerpo_compra'];
					$codigo_producto = $row_detalle_compra['codigo_producto'];
					$nombre_producto = $row_detalle_compra['detalle_producto'];
					$cantidad_producto = $row_detalle_compra['cantidad'];
					$precio_producto = $row_detalle_compra['precio'];
					$descuento_producto = $row_detalle_compra['descuento'];
					$subtotal = $row_detalle_compra['subtotal'];
					$impuesto = $row_detalle_compra['impuesto'];
					$det_impuesto = $row_detalle_compra['det_impuesto'];

					$detalle_tipo_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE codigo = '" . $det_impuesto . "'");
					$row_detalle_iva = mysqli_fetch_array($detalle_tipo_iva);

				?>
					<tr>
						<td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
						<td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
						<td style="padding: 2px;" class="text-right"><?php echo $cantidad_producto; ?></td>
						<td style="padding: 2px;" class="text-right"><?php echo $precio_producto; ?></td>
						<td style="padding: 2px;" class="text-right"><?php echo $descuento_producto; ?></td>
						<td style="padding: 2px;" class="text-right"><?php echo $subtotal; ?></td>
						<td style="padding: 2px;"><?php echo $row_detalle_iva['tarifa'] ?></td>
					<?php
				}
					?>
			</table>
		</div>
	</div>

	<div class="row">
		<!-- detalle de subtotales-->
		<!--para mostrar los adicionales que vienen en la compra -->
		<div class="col-xs-6">
			<div class="panel-group" id="accordion_adicionales_compra" style="margin-bottom: 5px; margin-top: -15px;">
				<a class="list-group-item list-group-item-info" style="height:35px;" data-toggle="collapse" data-parent="#accordion_adicionales_compra" href="#collapseAC"><span class="caret"></span> Adicionales y forma de pago</a>
				<div id="collapseAC" class="panel-collapse collapse">
					<div class="panel panel-info">
						<div class="table-responsive">
							<table class="table table-bordered">
								<tr class="info">
									<th style="padding: 2px;">Concepto</th>
									<th style="padding: 2px;">Detalle</th>
								</tr>
								<?php
								while ($row_info_adicional = mysqli_fetch_array($result_info_compra)) {
									$concepto = $row_info_adicional['adicional_concepto'];
									$descripcion = $row_info_adicional['adicional_descripcion'];
								?>
									<tr>
										<td class='col-md-2'><?php echo $concepto; ?></td>
										<td class='col-md-4'><?php echo $descripcion; ?></td>
									</tr>
								<?php
								}
								?>
							</table>
						</div>

						<div class="table-responsive">
							<table class="table table-bordered">
								<tr class="info">
									<th style="padding: 2px;">Forma Pago</th>
									<th style="padding: 2px;">Valor</th>
									<th style="padding: 2px;">Plazo</th>
									<th style="padding: 2px;">Tiempo</th>
								</tr>
								<?php
								while ($row_detalle_pago = mysqli_fetch_array($result_info_pago)) {
									$forma_pago = $row_detalle_pago['nombre_pago'];
									$valor_pago = $row_detalle_pago['total_pago'];
									$plazo_pago = $row_detalle_pago['plazo_pago'];
									$tiempo_pago = $row_detalle_pago['tiempo_pago'];
								?>
									<tr>
										<td><?php echo $forma_pago; ?></td>
										<td><?php echo $valor_pago; ?></td>
										<td><?php echo $plazo_pago; ?></td>
										<td><?php echo $tiempo_pago; ?></td>
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
		<!--hasta aqui para mostrar los adiconales que vienen en la compra -->
		<?php
		$subtotal_general = 0;
		$total_descuento = 0;
		$sql_factura = mysqli_query($con, "select sum(subtotal) as subtotal_general, sum(descuento) as total_descuento  FROM cuerpo_compra WHERE codigo_documento='" . $codigo_documento . "' ");
		$row_subtotal = mysqli_fetch_array($sql_factura);
		$subtotal_general = $row_subtotal['subtotal_general'];
		$total_descuento = $row_subtotal['total_descuento'];
		?>

		<div class="col-xs-6">
			<div class="panel panel-info" style="margin-bottom: 5px; margin-top: -15px;">
				<div class="table-responsive">
					<table class="table">
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>SUBTOTAL GENERAL: </td>
							<td style="padding: 2px;" class='text-center'><?php echo number_format($subtotal_general, 2, '.', ''); ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
						<?php
						//PARA MOSTRAR LOS NOMBRES DE CADA TARIFA DE IVA Y LOS VALORES DE CADA SUBTOTAL
						$subtotal_tarifa_iva = 0;
						$sql = mysqli_query($con, "select ti.tarifa as tarifa, sum(round(cue_com.subtotal,2)) as suma_tarifa_iva FROM cuerpo_compra as cue_com INNER JOIN tarifa_iva as ti ON ti.codigo=cue_com.det_impuesto WHERE cue_com.codigo_documento='" . $codigo_documento . "' and cue_com.impuesto='2' group by cue_com.det_impuesto ");
						while ($row = mysqli_fetch_array($sql)) {
							$nombre_tarifa_iva = strtoupper($row["tarifa"]);
							$subtotal_tarifa_iva = number_format($row['suma_tarifa_iva'], 2, '.', '');
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>SUBTOTAL <?php echo ($nombre_tarifa_iva); ?>:</td>
								<td style="padding: 2px;" class='text-center'><?php echo number_format($subtotal_tarifa_iva, 2, '.', ''); ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>

						<?php
						}
						?>
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>TOTAL DESCUENTO: </td>
							<td style="padding: 2px;" class='text-center'><?php echo number_format($total_descuento, 2, '.', ''); ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
						<?php
						//PARA MOSTRAR LOS IVAS
						$total_iva = 0;
						$subtotal_porcentaje_iva = 0;
						$sql = mysqli_query($con, "select ti.tarifa as tarifa, (sum(cue_com.subtotal) * ti.tarifa /100)  as porcentaje FROM cuerpo_compra as cue_com INNER JOIN tarifa_iva as ti ON ti.codigo=cue_com.det_impuesto WHERE cue_com.codigo_documento= '" . $codigo_documento . "' and ti.tarifa > 0 group by cue_com.det_impuesto ");
						while ($row = mysqli_fetch_array($sql)) {
							$nombre_porcentaje_iva = strtoupper($row["tarifa"]);
							$porcentaje_iva = $row['porcentaje'];
							$subtotal_porcentaje_iva = $porcentaje_iva;
							$total_iva += $subtotal_porcentaje_iva;
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>IVA <?php echo ($nombre_porcentaje_iva); ?>:</td>
								<td style="padding: 2px;" class='text-center'><?php echo number_format($subtotal_porcentaje_iva, 2, '.', ''); ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						}
						if ($propina > 0) {
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>SERVICIO O PROPINA: </td>
								<td style="padding: 2px;" class='text-center'><?php echo number_format($propina, 2, '.', ''); ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						}
						if ($otros_val > 0) {
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>ICE, OTROS: </td>
								<td style="padding: 2px;" class='text-center'><?php echo number_format($otros_val, 2, '.', ''); ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						}
						?>
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>TOTAL: </td>
							<td style="padding: 2px;" class='text-center'><?php echo number_format($subtotal_general + $total_iva + $propina + $otros_val, 2, '.', ''); ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
					</table>
				</div>
			</div>
		</div>
		<!-- hasta aqui detalle de subtotales-->
	</div>

	<!-- para las observaciones-->
	<div class="row">
		<div class="col-xs-12">
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table table-bordered">
						<tr class="info">
							<th style="padding: 2px;">Observaciones <button type="button" title="Agregar observación" onclick="agregar_observacion('<?php echo $codigo_documento ?>', '<?php echo $numero_documento ?>', '<?php echo $id_proveedor ?>')" class="btn btn-info btn-xs" id="agregar_observacion_compra"><i class="glyphicon glyphicon-plus"></i> Agregar</button></th>
							<th style="padding: 2px;">Respuestas</th>
							<th style="padding: 2px;" class="text-center">Estado</th>
							<th style="padding: 2px;">Responsable</th>
							<th style="padding: 2px;" class="text-center">Opciones</th>
						</tr>
						<?php
						while ($row_observaciones = mysqli_fetch_array($result_info_observaciones)) {
							$id_observaciones = $row_observaciones['id'];
						?>
							<tr>
								<input type="hidden" value="<?php echo $row_observaciones['codigo_documento'] ?>" id="codigo_documento_aprobar_compra">
								<td class='col-md-3'><?php echo $row_observaciones['observacion']; ?></td>
								<td class='col-md-3'><?php echo $row_observaciones['respuesta']; ?></td>
								<td class='col-md-2'>
									<select class="form-control input-sm" id="listStatus<?php echo $row_observaciones['id'] ?>" onchange="actualizar_status(<?php echo $id_observaciones ?>)">
										<?php
										if ($row_observaciones['status'] == 1) {
										?>
											<option value="1" selected>Por revisar</option>
											<option value="2">Aprobado</option>
										<?php
										}
										if ($row_observaciones['status'] == 2) {
										?>
											<option value="1">Por revisar</option>
											<option value="2" selected>Aprobado</option>
										<?php
										}
										?>
									</select>
								</td>
								<td class='col-md-3'>
									<?php
									if ($row_observaciones['status'] == 2) {
										$status_select = "disabled";
									} else {
										$status_select = "";
									}
									?>

									<select class="form-control input-sm" id="listUsuarios<?php echo $row_observaciones['id'] ?>" onchange="actualizar_correo(<?php echo $id_observaciones ?>)" <?php echo $status_select ?>>
										<option value="" selected>Seleccione</option>
										<?php
										$con = conenta_login();
										$id_empresa = $_SESSION['id_empresa'];
										$sql_usuarios = mysqli_query($con, "SELECT * FROM empresa_asignada as asi 
										INNER JOIN usuarios as usu ON usu.id=asi.id_usuario  
										WHERE asi.id_empresa ='" . $id_empresa . "' and usu.estado='1' order by usu.nombre asc");
										foreach ($sql_usuarios as $usuarios) {
											if ($row_observaciones['id_receptor'] == $usuarios['id']) {
										?>
												<option value="<?php echo $row_observaciones['id_receptor'] ?>" selected><?php echo strtoupper($usuarios['nombre']) ?></option>
											<?php
											} else {
											?>
												<option value="<?php echo $usuarios['id'] ?>"><?php echo strtoupper($usuarios['nombre']) ?></option>
										<?php
											}
										}
										?>
									</select>
								</td>
								<td class='col-md-1'>
									<?php
									if ($row_observaciones['status'] == 1 && $row_observaciones['id_receptor'] != 0) {
										$sql_usuarios_receptor = mysqli_query($con, "SELECT * FROM usuarios WHERE id ='" . $row_observaciones['id_receptor'] . "' ");
										$row_usuario_receptor = mysqli_fetch_array($sql_usuarios_receptor);
									?>
										<button class="btn btn-info btn-xs" onclick="enviar_notificacion_mail('<?php echo $row_observaciones['id']; ?>', '<?php echo $row_usuario_receptor['mail']; ?>');" title='Enviar notificación' data-toggle="modal" data-target="#EnviarDocumentosMail"><i class="glyphicon glyphicon-envelope"></i> </button>
									<?php
									}
									if ($row_observaciones['status'] == 1) {
									?>
										<button class="btn btn-danger btn-xs" onclick="eliminar_notificacion('<?php echo $row_observaciones['id']; ?>', '<?php echo $row_observaciones['codigo_documento']; ?>');" title='Eliminar notificación'><i class="glyphicon glyphicon-erase"></i> </button>
									<?php
									}
									?>
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
	<!-- hasta aqui las observaciones-->
<?php
}


function revisar_notificaciones_adquisiciones($codigo_documento)
{
	$con = conenta_login();
	//para consultar cuerpo
	$busca_encabezado_compra = mysqli_query($con, "SELECT * FROM encabezado_compra as enc INNER JOIN proveedores as pro 
	ON pro.id_proveedor = enc.id_proveedor WHERE enc.codigo_documento= '" . $codigo_documento . "' ");
	$row_encabezado_compra = mysqli_fetch_array($busca_encabezado_compra);
	$codigo_documento = isset($row_encabezado_compra['codigo_documento']) ? $row_encabezado_compra['codigo_documento'] : "";
	$propina = isset($row_encabezado_compra['propina']) ? $row_encabezado_compra['propina'] : 0;
	$otros_val = isset($row_encabezado_compra['otros_val']) ? $row_encabezado_compra['otros_val'] : 0;
	$proveedor = isset($row_encabezado_compra['razon_social']) ? $row_encabezado_compra['razon_social'] : "";
	$fecha = isset($row_encabezado_compra['fecha_compra']) ? $row_encabezado_compra['fecha_compra'] : "";
	$numero_documento = isset($row_encabezado_compra['numero_documento']) ? $row_encabezado_compra['numero_documento'] : 0;
	$id_proveedor = isset($row_encabezado_compra['id_proveedor']) ? $row_encabezado_compra['id_proveedor'] : 0;

	//para consultar cuerpo
	$busca_detalle_compra = "SELECT * FROM cuerpo_compra WHERE codigo_documento= '" . $codigo_documento . "' ";
	$result_detalle_compra = $con->query($busca_detalle_compra);
	//para consultar adicional info
	$busca_info_compra = "SELECT * FROM detalle_adicional_compra WHERE codigo_documento= '" . $codigo_documento . "' ";
	$result_info_compra = $con->query($busca_info_compra);
	//para consultar pago
	$busca_info_pago = "SELECT * FROM formas_pago_compras as fpc, formas_de_pago as fp WHERE fpc.codigo_documento= '" . $codigo_documento . "' and fp.codigo_pago=fpc.forma_pago and fp.aplica_a='VENTAS' ";
	$result_info_pago = $con->query($busca_info_pago);
	//para consultar detalle de observaciones
	$busca_info_observaciones = "SELECT usu.nombre as nombre, apro.id as id,
	apro.id_receptor as id_receptor, apro.status as status, apro.codigo_documento as codigo_documento,
	apro.observacion as observacion, apro.respuesta as respuesta, usu.mail as email FROM notificaciones_adquisiciones as apro INNER JOIN usuarios as usu ON usu.id=apro.id_usuario WHERE codigo_documento= '" . $codigo_documento . "' and status !=0 ";
	$result_info_observaciones = $con->query($busca_info_observaciones);
?>
	<div style="padding: 2px; margin-bottom: 5px; margin-top: -5px;" class="alert alert-info" role="alert">
		<b>Fecha:</b> <?php echo date("d/m/Y", strtotime($fecha)); ?> <b>Documento: </b><?php echo $numero_documento; ?> <b>Proveedor: </b><?php echo $proveedor; ?>
	</div>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-bordered">
				<tr class="info">
					<th style="padding: 2px;">Código</th>
					<th style="padding: 2px;">Producto</th>
					<th style="padding: 2px;">Cantidad</th>
					<th style="padding: 2px;">Precio</th>
					<th style="padding: 2px;">Descuento</th>
					<th style="padding: 2px;">Subtotal</th>
					<th style="padding: 2px;">IVA</th>
				</tr>
				<?php
				while ($row_detalle_compra = mysqli_fetch_array($result_detalle_compra)) {
					$codigo_documento = $row_detalle_compra['codigo_documento'];
					$id_cuerpo_compra = $row_detalle_compra['id_cuerpo_compra'];
					$codigo_producto = $row_detalle_compra['codigo_producto'];
					$nombre_producto = $row_detalle_compra['detalle_producto'];
					$cantidad_producto = $row_detalle_compra['cantidad'];
					$precio_producto = $row_detalle_compra['precio'];
					$descuento_producto = $row_detalle_compra['descuento'];
					$subtotal = $row_detalle_compra['subtotal'];
					$impuesto = $row_detalle_compra['impuesto'];
					$det_impuesto = $row_detalle_compra['det_impuesto'];

					$detalle_tipo_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE codigo = '" . $det_impuesto . "'");
					$row_detalle_iva = mysqli_fetch_array($detalle_tipo_iva);

				?>
					<tr>
						<td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
						<td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
						<td style="padding: 2px;" class="text-right"><?php echo $cantidad_producto; ?></td>
						<td style="padding: 2px;" class="text-right"><?php echo $precio_producto; ?></td>
						<td style="padding: 2px;" class="text-right"><?php echo $descuento_producto; ?></td>
						<td style="padding: 2px;" class="text-right"><?php echo $subtotal; ?></td>
						<td style="padding: 2px;"><?php echo $row_detalle_iva['tarifa'] ?></td>
					<?php
				}
					?>
			</table>
		</div>
	</div>

	<div class="row">
		<!-- detalle de subtotales-->
		<!--para mostrar los adicionales que vienen en la compra -->
		<div class="col-xs-6">
			<div class="panel-group" id="accordion_adicionales_compra" style="margin-bottom: 5px; margin-top: -15px;">
				<a class="list-group-item list-group-item-info" style="height:35px;" data-toggle="collapse" data-parent="#accordion_adicionales_compra" href="#collapseAC"><span class="caret"></span> Adicionales y forma de pago</a>
				<div id="collapseAC" class="panel-collapse collapse">
					<div class="panel panel-info">
						<div class="table-responsive">
							<table class="table table-bordered">
								<tr class="info">
									<th style="padding: 2px;">Concepto</th>
									<th style="padding: 2px;">Detalle</th>
								</tr>
								<?php
								while ($row_info_adicional = mysqli_fetch_array($result_info_compra)) {
									$concepto = $row_info_adicional['adicional_concepto'];
									$descripcion = $row_info_adicional['adicional_descripcion'];
								?>
									<tr>
										<td class='col-md-2'><?php echo $concepto; ?></td>
										<td class='col-md-4'><?php echo $descripcion; ?></td>
									</tr>
								<?php
								}
								?>
							</table>
						</div>

						<div class="table-responsive">
							<table class="table table-bordered">
								<tr class="info">
									<th style="padding: 2px;">Forma Pago</th>
									<th style="padding: 2px;">Valor</th>
									<th style="padding: 2px;">Plazo</th>
									<th style="padding: 2px;">Tiempo</th>
								</tr>
								<?php
								while ($row_detalle_pago = mysqli_fetch_array($result_info_pago)) {
									$forma_pago = $row_detalle_pago['nombre_pago'];
									$valor_pago = $row_detalle_pago['total_pago'];
									$plazo_pago = $row_detalle_pago['plazo_pago'];
									$tiempo_pago = $row_detalle_pago['tiempo_pago'];
								?>
									<tr>
										<td><?php echo $forma_pago; ?></td>
										<td><?php echo $valor_pago; ?></td>
										<td><?php echo $plazo_pago; ?></td>
										<td><?php echo $tiempo_pago; ?></td>
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
		<!--hasta aqui para mostrar los adiconales que vienen en la compra -->
		<?php
		$subtotal_general = 0;
		$total_descuento = 0;
		$sql_factura = mysqli_query($con, "select sum(subtotal) as subtotal_general, sum(descuento) as total_descuento  FROM cuerpo_compra WHERE codigo_documento='" . $codigo_documento . "' ");
		$row_subtotal = mysqli_fetch_array($sql_factura);
		$subtotal_general = $row_subtotal['subtotal_general'];
		$total_descuento = $row_subtotal['total_descuento'];
		?>

		<div class="col-xs-6">
			<div class="panel panel-info" style="margin-bottom: 5px; margin-top: -15px;">
				<div class="table-responsive">
					<table class="table">
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>SUBTOTAL GENERAL: </td>
							<td style="padding: 2px;" class='text-center'><?php echo number_format($subtotal_general, 2, '.', ''); ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
						<?php
						//PARA MOSTRAR LOS NOMBRES DE CADA TARIFA DE IVA Y LOS VALORES DE CADA SUBTOTAL
						$subtotal_tarifa_iva = 0;
						$sql = mysqli_query($con, "select ti.tarifa as tarifa, sum(round(cue_com.subtotal,2)) as suma_tarifa_iva FROM cuerpo_compra as cue_com INNER JOIN tarifa_iva as ti ON ti.codigo=cue_com.det_impuesto WHERE cue_com.codigo_documento='" . $codigo_documento . "' and cue_com.impuesto='2' group by cue_com.det_impuesto ");
						while ($row = mysqli_fetch_array($sql)) {
							$nombre_tarifa_iva = strtoupper($row["tarifa"]);
							$subtotal_tarifa_iva = number_format($row['suma_tarifa_iva'], 2, '.', '');
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>SUBTOTAL <?php echo ($nombre_tarifa_iva); ?>:</td>
								<td style="padding: 2px;" class='text-center'><?php echo number_format($subtotal_tarifa_iva, 2, '.', ''); ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>

						<?php
						}
						?>
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>TOTAL DESCUENTO: </td>
							<td style="padding: 2px;" class='text-center'><?php echo number_format($total_descuento, 2, '.', ''); ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
						<?php
						//PARA MOSTRAR LOS IVAS
						$total_iva = 0;
						$subtotal_porcentaje_iva = 0;
						$sql = mysqli_query($con, "select ti.tarifa as tarifa, (sum(cue_com.subtotal) * ti.tarifa /100)  as porcentaje FROM cuerpo_compra as cue_com INNER JOIN tarifa_iva as ti ON ti.codigo=cue_com.det_impuesto WHERE cue_com.codigo_documento= '" . $codigo_documento . "' and ti.tarifa > 0 group by cue_com.det_impuesto ");
						while ($row = mysqli_fetch_array($sql)) {
							$nombre_porcentaje_iva = strtoupper($row["tarifa"]);
							$porcentaje_iva = $row['porcentaje'];
							$subtotal_porcentaje_iva = $porcentaje_iva;
							$total_iva += $subtotal_porcentaje_iva;
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>IVA <?php echo ($nombre_porcentaje_iva); ?>:</td>
								<td style="padding: 2px;" class='text-center'><?php echo number_format($subtotal_porcentaje_iva, 2, '.', ''); ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						}
						if ($propina > 0) {
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>SERVICIO O PROPINA: </td>
								<td style="padding: 2px;" class='text-center'><?php echo number_format($propina, 2, '.', ''); ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						}
						if ($otros_val > 0) {
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>ICE, OTROS: </td>
								<td style="padding: 2px;" class='text-center'><?php echo number_format($otros_val, 2, '.', ''); ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						}
						?>
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>TOTAL: </td>
							<td style="padding: 2px;" class='text-center'><?php echo number_format($subtotal_general + $total_iva + $propina + $otros_val, 2, '.', ''); ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
					</table>
				</div>
			</div>
		</div>
		<!-- hasta aqui detalle de subtotales-->
	</div>

	<!-- para las observaciones-->
	<div class="row">
		<div class="col-xs-12">
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table table-bordered">
						<tr class="info">
							<th style="padding: 2px;">Observaciones</th>
							<th style="padding: 2px;">Respuestas</th>
							<th style="padding: 2px;" class="text-center">Estado</th>
							<th style="padding: 2px;">Solicitado por</th>
							<th style="padding: 2px;" class="text-center">Opciones</th>
						</tr>
						<?php
						while ($row_observaciones = mysqli_fetch_array($result_info_observaciones)) {
						?>
							<tr>
								<input type="hidden" value="<?php echo $row_observaciones['codigo_documento'] ?>" id="codigo_documento_aprobar_compra">
								<td class='col-md-3'><?php echo $row_observaciones['observacion']; ?></td>
								<td class='col-md-3'><?php echo $row_observaciones['respuesta']; ?><div class="text-right">
										<button type="button" title="Agregar respuesta" onclick="agregar_respuesta('<?php echo $row_observaciones['id'] ?>', '<?php echo $row_observaciones['codigo_documento'] ?>')" class="btn btn-info btn-xs" id="agregar_respuesta_compra"><i class="glyphicon glyphicon-plus"></i></button>
									</div>
								</td>
								<td class='col-md-2 text-center'>
									<?php
									if ($row_observaciones['status'] == 1) {
									?>
										<span class="label label-warning">Revisar</span>
									<?php
									}
									if ($row_observaciones['status'] == 2) {
									?>
										<span class="label label-success">Aprobado</span>
									<?php
									}
									?>
								</td>
								<td class='col-md-3'><?php echo $row_observaciones['nombre']; ?></td>
								<td class='col-md-1'>
									<?php
									if (!empty($row_observaciones['respuesta'])) {
									?>
										<button class="btn btn-info btn-xs" onclick="enviar_respuesta_mail('<?php echo $row_observaciones['id']; ?>', '<?php echo $row_observaciones['email']; ?>');" title='Enviar respuesta' data-toggle="modal" data-target="#EnviarDocumentosMail"><i class="glyphicon glyphicon-envelope"></i> </button>
										<button class="btn btn-danger btn-xs" onclick="eliminar_respuesta('<?php echo $row_observaciones['id']; ?>', '<?php echo $row_observaciones['codigo_documento']; ?>');" title='Eliminar respuesta'><i class="glyphicon glyphicon-erase"></i> </button>
									<?php
									}
									?>
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
	<!-- hasta aqui las observaciones-->
<?php
}
?>