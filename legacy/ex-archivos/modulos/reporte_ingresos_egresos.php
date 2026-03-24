<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

?>
	<!DOCTYPE html>
	<html lang="es">

	<head>
		<title>Reporte Ingresos Egresos</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		include("../modal/detalle_ingreso_egreso.php");
		$con = conenta_login(); ?>
	</head>

	<body>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<span id="loader"></span>
					</div>
					<h4><i class="glyphicon glyphicon-list-alt"></i> Reporte de ingresos y egresos</h4>
				</div>

				<div class="panel-body">
					<form class="form-horizontal" method="POST" target="_blank" action="../excel/reporte_excel_ingresos_egresos.php">
						<input type="hidden" name="id_cliente_proveedor" id="id_cliente_proveedor">
						<div class="form-group">
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Reporte</b></span>
									<select class="form-control input-sm" id="tipo_reporte" name="tipo_reporte">
										<option value="1" selected> Detalle Ingresos</option>
										<option value="2"> Detalle Egresos</option>
										<option value="3"> Detalle Cobros</option>
										<option value="4"> Detalle Pagos</option>
										<option value="5"> Cobros vs Pagos</option>
									</select>
								</div>
							</div>
							<div class="col-sm-2" id="label_tipo_ingreso">
								<div class="input-group">
									<span class="input-group-addon"><b>Tipo</b></span>
									<select class="form-control" style="height: 30px" title="Seleccione tipo de ingreso" name="tipo_ingreso" id="tipo_ingreso">
										<?php
										$resultado = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE tipo_opcion ='1' and ruc_empresa='" . $ruc_empresa . "' order by descripcion asc");
										?>
										<option value="">Todos</option>
										<option value="CCXCC"> Clientes</option>
										<?php
										while ($row = mysqli_fetch_assoc($resultado)) {
										?>
											<option value="<?php echo $row['id'] ?>"><?php echo ucwords($row['descripcion']); ?> </option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-sm-2" id="label_tipo_egreso">
								<div class="input-group">
									<span class="input-group-addon"><b>Tipo</b></span>
									<select class="form-control" style="height: 30px" title="Seleccione tipo de egreso" name="tipo_egreso" id="tipo_egreso">
										<?php
										$resultado = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE tipo_opcion ='2' and ruc_empresa='" . $ruc_empresa . "' order by descripcion asc");
										?>
										<option value="">Todos</option>
										<option value="CCXPP"> Proveedores</option>
										<?php
										while ($row = mysqli_fetch_assoc($resultado)) {
										?>
											<option value="<?php echo $row['id'] ?>"><?php echo $row['descripcion'] ?> </option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-sm-3" id="label_formas_cobro">
								<div class="input-group">
									<span class="input-group-addon"><b>Formas cobro</b></span>
									<select class="form-control input-sm" style="height: 30px" id="formas_cobro" name="formas_cobro">
										<option value="" selected>Todos</option>
										<?php

										$query_cobros_pagos = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_opcion='1' and status='1' order by descripcion asc");
										while ($row_cobros_pagos = mysqli_fetch_array($query_cobros_pagos)) {
											//el 1 junto al id en el value es para saber que los datos son de la lista de opciones de cobro
										?>
											<option value="<?php echo "1" . $row_cobros_pagos['id']; ?>"><?php echo ucwords($row_cobros_pagos['descripcion']); ?></option>
										<?php
										}

										$cuentas = mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.ruc_empresa ='" . $ruc_empresa . "'");
										while ($row = mysqli_fetch_array($cuentas)) {
											//el 2 junto al id en el value es para saber que los datos son desde bancos
										?>
											<option value="<?php echo "2" . $row['id_cuenta']; ?>"><?php echo ucwords($row['cuenta_bancaria']); ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>

							<div class="col-sm-3" id="label_formas_pago">
								<div class="input-group">
									<span class="input-group-addon"><b>Formas pago</b></span>
									<select class="form-control input-sm" style="height: 30px" id="formas_pago" name="formas_pago">
										<option value="" selected>Todos</option>
										<?php

										$query_cobros_pagos = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_opcion='2' and status='1' order by descripcion asc");
										while ($row_cobros_pagos = mysqli_fetch_array($query_cobros_pagos)) {
											//el 1 junto al id en el value es para saber que los datos son de la lista de opciones de cobro
										?>
											<option value="<?php echo "1" . $row_cobros_pagos['id']; ?>"><?php echo ucwords($row_cobros_pagos['descripcion']); ?></option>
										<?php
										}

										$cuentas = mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.ruc_empresa ='" . $ruc_empresa . "'");
										while ($row = mysqli_fetch_array($cuentas)) {
											//el 2 junto al id en el value es para saber que los datos son desde bancos
										?>
											<option value="<?php echo "2" . $row['id_cuenta']; ?>"><?php echo ucwords($row['cuenta_bancaria']); ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-sm-2" id="label_tipo_cobro">
								<div class="input-group">
									<span class="input-group-addon"><b>Tipo cobro</b></span>
									<select class="form-control" style="height: 30px" title="Seleccione" name="tipo_cobro" id="tipo_cobro">
										<option value="">Todos</option>
										<option value="D">Depósito</option>
										<option value="T">Transferencia</option>
									</select>
								</div>
							</div>
							<div class="col-sm-2" id="label_tipo_pago">
								<div class="input-group">
									<span class="input-group-addon"><b>Tipo pago</b></span>
									<select class="form-control" style="height: 30px" title="Seleccione" name="tipo_pago" id="tipo_pago">
										<option value="">Todos</option>
										<option value="C">Cheque</option>
										<option value="D">Débito</option>
										<option value="T">Transferencia</option>
									</select>
								</div>
							</div>
							<div class="col-sm-2" id="label_registros">
								<div class="input-group">
									<span class="input-group-addon"><b>Registros</b></span>
									<input type="number" value="2000" title="registros a mostrar" class="form-control input-sm text-right" name="cantidad" id="cantidad">
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Desde</b></span>
									<input type="text" class="form-control input-sm text-center" name="fecha_desde" id="fecha_desde" value="<?php echo date("01-m-Y"); ?>">
								</div>
							</div>

							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Hasta</b></span>
									<input type="text" class="form-control input-sm text-center" name="fecha_hasta" id="fecha_hasta" value="<?php echo date("d-m-Y"); ?>">
								</div>
							</div>
							<div class="col-sm-3" id="label_cliente_proveedor">
								<div class="input-group">
									<span class="input-group-addon"><b>Cliente/Proveedor</b></span>
									<input type="text" class="form-control input-sm text-left" name="nombre_cliente_proveedor" id="nombre_cliente_proveedor" onkeyup='buscar_cliente_proveedor();'>
								</div>
							</div>
							<div class="col-sm-3" id="label_detalle">
								<div class="input-group">
									<span class="input-group-addon"><b>Detalle</b></span>
									<input type="text" class="form-control input-sm text-left" name="detalle" id="detalle">
								</div>
							</div>
							<div class="col-sm-3" id="label_observaciones">
								<div class="input-group">
									<span class="input-group-addon"><b>Observaciones</b></span>
									<input type="text" class="form-control input-sm text-left" name="observaciones" id="observaciones">
								</div>
							</div>

							<div class="col-sm-2">
								<div class="input-group">
									<button type="button" title="Mostrar resultado" class="btn btn-info btn-sm" onclick="mostrar_reporte()"><span class="glyphicon glyphicon-search"></span></button>&nbsp
									<button type="submit" title="Descargar excel" class="btn btn-success btn-sm"><img src="../image/excel.ico" width="16" height="16"></button>
								</div>
							</div>
						</div>
					</form>

					<div id="resultados"></div><!-- Carga los datos ajax -->
					<div class="outer_div"></div><!-- Carga los datos ajax -->
				</div>
			</div>
		</div>

	<?php

} else {
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
	exit;
}
	?>

	</body>
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>

	</html>
	<script>
		jQuery(function($) {
			$("#fecha_desde").mask("99-99-9999");
			$("#fecha_hasta").mask("99-99-9999");
		});

		$(document).ready(function() {
			document.getElementById("label_formas_cobro").style.display = "none";
			document.getElementById("label_formas_pago").style.display = "none";
			document.getElementById("label_tipo_cobro").style.display = "none";
			document.getElementById("label_tipo_pago").style.display = "none";
			document.getElementById("label_detalle").style.display = "";
			document.getElementById("label_observaciones").style.display = "none";
			document.getElementById("label_tipo_ingreso").style.display = "";
			document.getElementById("label_tipo_egreso").style.display = "none";
			document.getElementById("label_cliente_proveedor").style.display = "";
			document.getElementById("label_registros").style.display = "";

		});

		$('#tipo_reporte').change(function() {
			var tipo = $("#tipo_reporte").val();
			$("#id_cliente_proveedor").val('');
			$("#detalle").val('');
			$("#nombre_cliente_proveedor").val('');
			$("#formas_cobro").val('');
			$("#formas_pago").val('');
			$("#tipo_cobro").val('');
			$("#tipo_pago").val('');
			$("#cantidad").val('2000');
			$("#observaciones").val('');
			$("#tipo_ingreso").val('');
			$("#tipo_egreso").val('');
			$(".outer_div").html('');

			if (tipo == "1") {
				document.getElementById("label_formas_cobro").style.display = "none";
				document.getElementById("label_formas_pago").style.display = "none";
				document.getElementById("label_tipo_cobro").style.display = "none";
				document.getElementById("label_tipo_pago").style.display = "none";
				document.getElementById("label_observaciones").style.display = "none";
				document.getElementById("label_detalle").style.display = "";
				document.getElementById("label_tipo_ingreso").style.display = "";
				document.getElementById("label_tipo_egreso").style.display = "none";
				document.getElementById("label_cliente_proveedor").style.display = "";
				document.getElementById("label_registros").style.display = "";
			}

			if (tipo == "2") {
				document.getElementById("label_formas_cobro").style.display = "none";
				document.getElementById("label_formas_pago").style.display = "none";
				document.getElementById("label_tipo_cobro").style.display = "none";
				document.getElementById("label_tipo_pago").style.display = "none";
				document.getElementById("label_observaciones").style.display = "none";
				document.getElementById("label_detalle").style.display = "";
				document.getElementById("label_tipo_ingreso").style.display = "none";
				document.getElementById("label_tipo_egreso").style.display = "";
				document.getElementById("label_cliente_proveedor").style.display = "";
				document.getElementById("label_registros").style.display = "";
			}

			if (tipo == "3") {
				document.getElementById("label_formas_cobro").style.display = "";
				document.getElementById("label_formas_pago").style.display = "none";
				document.getElementById("label_tipo_cobro").style.display = "";
				document.getElementById("label_tipo_pago").style.display = "none";
				document.getElementById("label_observaciones").style.display = "none";
				document.getElementById("label_detalle").style.display = "";
				document.getElementById("label_tipo_ingreso").style.display = "none";
				document.getElementById("label_tipo_egreso").style.display = "none";
				document.getElementById("label_cliente_proveedor").style.display = "";
				document.getElementById("label_registros").style.display = "";
			}

			if (tipo == "4") {
				document.getElementById("label_formas_cobro").style.display = "none";
				document.getElementById("label_formas_pago").style.display = "";
				document.getElementById("label_tipo_cobro").style.display = "none";
				document.getElementById("label_tipo_pago").style.display = "";
				document.getElementById("label_observaciones").style.display = "none";
				document.getElementById("label_detalle").style.display = "";
				document.getElementById("label_tipo_ingreso").style.display = "none";
				document.getElementById("label_tipo_egreso").style.display = "none";
				document.getElementById("label_cliente_proveedor").style.display = "";
				document.getElementById("label_registros").style.display = "";
			}

			if (tipo == "5") {
				document.getElementById("label_formas_cobro").style.display = "";
				document.getElementById("label_formas_pago").style.display = "";
				document.getElementById("label_tipo_cobro").style.display = "none";
				document.getElementById("label_tipo_pago").style.display = "none";
				document.getElementById("label_observaciones").style.display = "none";
				document.getElementById("label_detalle").style.display = "none";
				document.getElementById("label_tipo_ingreso").style.display = "none";
				document.getElementById("label_tipo_egreso").style.display = "none";
				document.getElementById("label_cliente_proveedor").style.display = "none";
				document.getElementById("label_registros").style.display = "none";
			}
		});


		$(function() {
			$("#fecha_desde").datepicker({
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

		$(function() {
			$("#fecha_hasta").datepicker({
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

		function buscar_cliente_proveedor() {
			var tipo = $("#tipo_reporte").val();
			if (tipo == 1 || tipo == 3) {

				$("#nombre_cliente_proveedor").autocomplete({
					source: '../ajax/clientes_autocompletar.php',
					minLength: 2,
					select: function(event, ui) {
						event.preventDefault();
						$('#id_cliente_proveedor').val(ui.item.id);
						$('#nombre_cliente_proveedor').val(ui.item.nombre);
					}
				});
			}

			if (tipo == 2 || tipo == 4) {

				$("#nombre_cliente_proveedor").autocomplete({
					source: '../ajax/proveedores_autocompletar.php',
					minLength: 2,
					select: function(event, ui) {
						event.preventDefault();
						$('#id_cliente_proveedor').val(ui.item.id_proveedor);
						$('#nombre_cliente_proveedor').val(ui.item.razon_social);
					}
				});
			}
			$("#nombre_cliente_proveedor").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_cliente_proveedor").val("");
					$("#nombre_cliente_proveedor").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_cliente_proveedor").val("");
					$("#nombre_cliente_proveedor").val("");
				}
			});
		}


		//generar informe
		function mostrar_reporte() {
			var tipo_reporte = $("#tipo_reporte").val();
			var id_cliente_proveedor = $("#id_cliente_proveedor").val();
			var desde = $("#fecha_desde").val();
			var hasta = $("#fecha_hasta").val();
			var detalle = $("#detalle").val();
			var nombre_cliente_proveedor = $("#nombre_cliente_proveedor").val();
			var formas_cobro = $("#formas_cobro").val();
			var formas_pago = $("#formas_pago").val();
			var tipo_cobro = $("#tipo_cobro").val();
			var tipo_pago = $("#tipo_pago").val();
			var cantidad = $("#cantidad").val();
			var observaciones = $("#observaciones").val();
			var tipo_ingreso = $("#tipo_ingreso").val();
			var tipo_egreso = $("#tipo_egreso").val();

			$.ajax({
				type: "POST",
				url: "../ajax/reporte_ingresos_egresos.php",
				data: "action=" + tipo_reporte + "&id_cliente_proveedor=" + id_cliente_proveedor + "&nombre_cliente_proveedor=" +
					nombre_cliente_proveedor + "&desde=" + desde + "&hasta=" + hasta + "&detalle=" + detalle + "&formas_cobro=" + formas_cobro +
					"&formas_pago=" + formas_pago + "&tipo_cobro=" + tipo_cobro + "&tipo_pago=" + tipo_pago + "&cantidad=" + cantidad + "&observaciones=" + observaciones +
					"&tipo_ingreso=" + tipo_ingreso + "&tipo_egreso=" + tipo_egreso,
				beforeSend: function(objeto) {
					$('#loader').html('Cargando...');
				},
				success: function(datos) {
					$(".outer_div").html(datos);
					$("#loader").html('');
				}
			});
		}

		function mostrar_detalle(codigo) {
			$(".outer_divdet").html('');
			$.ajax({
				url: "../ajax/detalle_documento.php?action=detalle_egreso&codigo_unico=" + codigo,
				beforeSend: function(objeto) {
					$("#loaderdet").html("Cargando...");
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('fast');
					$('#loaderdet').html('');
				}
			});
		}
	</script>