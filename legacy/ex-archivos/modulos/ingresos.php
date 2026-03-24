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
		<title>Ingresos</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		include("../modal/detalle_ingreso_egreso.php");
		include("../modal/enviar_documentos_mail.php");
		$con = conenta_login();
		require_once dirname(__DIR__) . '/paginas/verificar_permiso_modulo.php';
		$limpiar_saldos = mysqli_query($con, "
    DELETE FROM saldo_porcobrar_porpagar 
    WHERE id_usuario = '" . $id_usuario . "' 
    AND MID(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' 
    AND tipo IN ('POR_COBRAR', 'POR_COBRAR_RECIBOS')
");

		?>
		<style type="text/css">
			ul.ui-autocomplete {
				z-index: 1100;
			}
		</style>
	</head>

	<body>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<form method="post" action="">
							<?php
							if (getPermisos($con, $id_usuario, $ruc_empresa, 'ingresos')['w'] == 1) {
							?>
								<button type="submit" class="btn btn-info" onclick='generar_cuentas_por_cobrar();'><span class="glyphicon glyphicon-plus"></span> Nuevo ingreso</button>
							<?php
							}
							?>
						</form>
					</div>
					<h4><i class="glyphicon glyphicon-search"></i> Ingresos</h4>
				</div>
				<span id="mensaje_nuevo_ingreso"></span>
				<ul class="nav nav-tabs nav-justified">
					<li class="active"><a data-toggle="tab" href="#ingresos">Ingresos</a></li>
					<li><a data-toggle="tab" href="#detalle_ingresos">Detalle de ingresos</a></li>
					<li><a data-toggle="tab" href="#detalle_cobros_ingresos">Detalle de cobros</a></li>
					<li><a data-toggle="tab" href="#detalle_transferencias">Detalle de transferencias</a></li>
					<li><a data-toggle="tab" href="#detalle_depositos">Detalle de depósitos</a></li>
				</ul>
				<div class="tab-content">
					<div id="ingresos" class="tab-pane fade in active">
						<div class="panel-body">
							<form class="form-horizontal" method="POST">
								<div class="form-group row">
									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Estado</b></span>
											<select class="form-control input-sm" id="estado_ingreso" name="estado_ingreso" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$tipo = mysqli_query($con, "SELECT distinct estado 
												FROM ingresos_egresos WHERE ruc_empresa ='" . $ruc_empresa . "' and tipo_ing_egr ='INGRESO' order by estado asc");
												while ($row = mysqli_fetch_array($tipo)) {
												?>
													<option value="<?php echo $row['estado'] ?>"><?php echo strtoupper($row['estado']) ?></option>
												<?php
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Año</b></span>
											<?php
											$currentYear = date("Y");
											?>
											<select class="form-control input-sm" id="anio_ingreso" name="anio_ingreso" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$existsCurrentYear = false;
												$tipo = mysqli_query($con, "SELECT distinct(year(fecha_ing_egr)) as anio FROM ingresos_egresos WHERE ruc_empresa ='" . $ruc_empresa . "' and tipo_ing_egr ='INGRESO' order by year(fecha_ing_egr) desc");
												while ($row = mysqli_fetch_array($tipo)) {
													$selected = ($row['anio'] == $currentYear) ? 'selected' : '';
													if ($row['anio'] == $currentYear) {
														$existsCurrentYear = true;
													}
													echo '<option value="' . $row['anio'] . '" ' . $selected . '>' . strtoupper($row['anio']) . '</option>';
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Mes</b></span>
											<select class="form-control input-sm" id="mes_ingreso" name="mes_ingreso" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$tipo = mysqli_query($con, "SELECT distinct(DATE_FORMAT(fecha_ing_egr, '%m')) as mes FROM ingresos_egresos WHERE ruc_empresa ='" . $ruc_empresa . "' and tipo_ing_egr ='INGRESO' order by month(fecha_ing_egr) asc");
												while ($row = mysqli_fetch_array($tipo)) {
												?>
													<option value="<?php echo $row['mes'] ?>"><?php echo strtoupper($row['mes']) ?></option>
												<?php
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-md-5">
										<input type="hidden" id="ordenado" value="numero_ing_egr">
										<input type="hidden" id="por" value="desc">
										<div class="input-group">
											<span class="input-group-addon"><b>Buscar:</b></span>
											<input type="text" class="form-control input-sm" id="ingreso" placeholder="Cliente, Número, Observaciones" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" class="btn btn-default input-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
											</span>
										</div>
									</div>
									<span id="loader"></span>
								</div>
							</form>
							<div id="resultados"></div><!-- Carga los datos ajax -->
							<div class='outer_div'></div><!-- Carga los datos ajax -->
						</div>
					</div>
					<div id="detalle_ingresos" class="tab-pane fade">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<div class="col-md-5">
										<input type="hidden" id="ordenado" value="numero_ing_egr">
										<input type="hidden" id="por" value="desc">
										<div class="input-group">
											<span class="input-group-addon"><b>Buscar:</b></span>
											<input type="text" class="form-control input-sm" id="deting" placeholder="Cliente, Número de ingreso, fecha, detalle" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" class="btn btn-default input-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
											</span>
										</div>
									</div>
									<span id="loader_detalles"></span>
								</div>
							</form>
							<div id="resultados_detalles_ingresos"></div><!-- Carga los datos ajax -->
							<div class='outer_div_detalles'></div><!-- Carga los datos ajax -->
						</div>
					</div>
					<div id="detalle_cobros_ingresos" class="tab-pane fade">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<div class="col-md-5">
										<input type="hidden" id="ordenado" value="numero_ing_egr">
										<input type="hidden" id="por" value="desc">
										<div class="input-group">
											<span class="input-group-addon"><b>Buscar:</b></span>
											<input type="text" class="form-control input-sm" id="detpago" placeholder="Cliente, Número de ingreso, fecha, detalle" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" class="btn btn-default input-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
											</span>
										</div>
									</div>
									<span id="loader_detalles_pagos"></span>
								</div>

							</form>
							<div id="resultados_detalles_pagos_ingresos"></div><!-- Carga los datos ajax -->
							<div class='outer_div_detalles_pagos'></div><!-- Carga los datos ajax -->
						</div>
					</div>
					<div id="detalle_transferencias" class="tab-pane fade">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">

									<div class="col-md-5">
										<div class="input-group">
											<span class="input-group-addon"><b>Cuenta</b></span>
											<select class="form-control input-sm" id="cuenta_tr" name="cuenta_tr" onchange='load(1);'>
												<?php
												$cuentas = mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.ruc_empresa ='" . $ruc_empresa . "'");
												while ($row = mysqli_fetch_array($cuentas)) {
												?>
													<option value="<?php echo $row['id_cuenta'] ?>" selected><?php echo strtoupper($row['cuenta_bancaria']) ?></option>
												<?php
												}
												?>
											</select>
										</div>
									</div>

									<div class="col-md-5">
										<input type="hidden" id="ordenado_tr" value="for_pag.id_fp">
										<input type="hidden" id="por_tr" value="desc">
										<div class="input-group">
											<span class="input-group-addon"><b>Buscar</b></span>
											<input type="text" class="form-control input-sm" id="dettransferencia" placeholder="Fecha, detalle" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" class="btn btn-default btn-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
											</span>
										</div>
									</div>
									<div class="col-md-2">
										<span id="loader_detalles_transferencias"></span>
									</div>
								</div>
							</form>
							<div id="resultados_detalles_transferencias"></div><!-- Carga los datos ajax -->
							<div class='outer_div_detalles_transferencias'></div><!-- Carga los datos ajax -->
						</div>
					</div>
					<div id="detalle_depositos" class="tab-pane fade">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">

									<div class="col-md-5">
										<div class="input-group">
											<span class="input-group-addon"><b>Cuenta</b></span>
											<select class="form-control input-sm" id="cuenta_de" name="cuenta_de" onchange='load(1);'>
												<?php
												$cuentas = mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.ruc_empresa ='" . $ruc_empresa . "'");
												while ($row = mysqli_fetch_array($cuentas)) {
												?>
													<option value="<?php echo $row['id_cuenta'] ?>" selected><?php echo strtoupper($row['cuenta_bancaria']) ?></option>
												<?php
												}
												?>
											</select>
										</div>
									</div>

									<div class="col-md-5">
										<input type="hidden" id="ordenado_de" value="for_pag.id_fp">
										<input type="hidden" id="por_de" value="desc">
										<div class="input-group">
											<span class="input-group-addon"><b>Buscar</b></span>
											<input type="text" class="form-control input-sm" id="detdeposito" placeholder="Fecha, detalle" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" class="btn btn-default btn-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
											</span>
										</div>
									</div>
									<div class="col-md-2">
										<span id="loader_detalles_depositos"></span>
									</div>
								</div>
							</form>
							<div id="resultados_detalles_depositos"></div><!-- Carga los datos ajax -->
							<div class='outer_div_detalles_depositos'></div><!-- Carga los datos ajax -->
						</div>
					</div>
				</div>
			</div>

		</div>
	<?php

} else {
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
	exit;
}
	?>
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

	<script src="../js/ordenado.js" type="text/javascript"></script>
	<script src="../js/validar_fecha.js" type="text/javascript"></script>
	<!-- 	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script> -->
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	</body>

	</html>
	<script>
		$(document).ready(function() {
			window.addEventListener("keypress", function(event) {
				if (event.keyCode == 13) {
					event.preventDefault();
				}
			}, false);
			load(1);
		});

		/* function formatFechaIngreso(id) {
			jQuery(function($) {
				$("#fecha_nueva_ingreso" + id).mask("99-99-9999");
			});
			calendarioFecha(id);
		} */

		function formatFechaEntregaTransferenciaCobro(id) {
			jQuery(function($) {
				$("#fecha_entrega_transferencia_cobro" + id).mask("99-99-9999");
			});
			calendarioFecha(id);
		}

		function formatFechaEntregaDepositoCobro(id) {
			jQuery(function($) {
				$("#fecha_entrega_deposito_cobro" + id).mask("99-99-9999");
			});
			calendarioFecha(id);
		}

		function calendarioFecha(id) {
			$(function() {
				/* $("#fecha_nueva_ingreso" + id).datepicker({
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
				}); */

				$("#fecha_entrega_transferencia_cobro" + id).datepicker({
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

				$("#fecha_entrega_deposito_cobro" + id).datepicker({
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

		function generar_cuentas_por_cobrar() {
			$.ajax({
				url: '../ajax/detalle_ingresos.php?action=saldos_cuentas_por_cobrar',
				beforeSend: function(objeto) {
					$('#mensaje_nuevo_ingreso').html('<div class="progress"><div class="progress-bar progress-bar-info progress-bar-striped active" role="progressbar" style="width:100%;"> Actualizando saldos de cuentas por cobrar, espere por favor...</div></div>');

				},
				success: function(data) {
					$(".resultados_guardar_ingreso").html(data).fadeIn('slow');
					$('#mensaje_nuevo_ingreso').html('');
					setTimeout(function() {
						location.href = '../modulos/nuevo_ingreso.php'
					}, 500);
				}
			});
			event.preventDefault();
		}

		function load(page) {
			var ingreso = $("#ingreso").val();
			var deting = $("#deting").val();
			var detpago = $("#detpago").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			var estado_ingreso = $("#estado_ingreso").val();
			var anio_ingreso = $("#anio_ingreso").val();
			var mes_ingreso = $("#mes_ingreso").val();

			var ordenado_tr = $("#ordenado_tr").val();
			var por_tr = $("#por_tr").val();
			var cuenta_tr = $("#cuenta_tr").val();
			var dettransferencia = $("#dettransferencia").val();

			var ordenado_de = $("#ordenado_de").val();
			var cuenta_de = $("#cuenta_de").val();
			var por_de = $("#por_de").val();
			var detdeposito = $("#detdeposito").val();

			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_ingresos.php?action=ingresos&page=' + page + '&ingreso=' + ingreso +
					'&ordenado=' + ordenado + '&por=' + por + '&estado_ingreso=' + estado_ingreso + '&anio_ingreso=' + anio_ingreso +
					'&mes_ingreso=' + mes_ingreso,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			});

			$("#loader_detalles").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_ingresos.php?action=detalle&page=' + page + '&deting=' + deting,
				beforeSend: function(objeto) {
					$('#loader_detalles').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div_detalles").html(data).fadeIn('slow');
					$('#loader_detalles').html('');
				}
			})

			$("#loader_detalles_pagos").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_ingresos.php?action=pagos_ingresos&page=' + page + '&detpago=' + detpago,
				beforeSend: function(objeto) {
					$('#loader_detalles_pagos').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div_detalles_pagos").html(data).fadeIn('slow');
					$('#loader_detalles_pagos').html('');
				}
			})

			$("#loader_detalles_transferencias").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_ingresos.php?action=detalle_transferencias&page=' + page +
					'&dettransferencia=' + dettransferencia + "&por_tr=" + por_tr + "&ordenado_tr=" + ordenado_tr + "&cuenta_tr=" + cuenta_tr,
				beforeSend: function(objeto) {
					$('#loader_detalles_transferencias').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div_detalles_transferencias").html(data).fadeIn('slow');
					$('#loader_detalles_transferencias').html('');
				}
			})

			$("#loader_detalles_depositos").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_ingresos.php?action=detalle_depositos&page=' + page + '&detdeposito=' + detdeposito + "&por_de=" +
					por_de + "&ordenado_de=" + ordenado_de + "&cuenta_de=" + cuenta_de,
				beforeSend: function(objeto) {
					$('#loader_detalles_depositos').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div_detalles_depositos").html(data).fadeIn('slow');
					$('#loader_detalles_depositos').html('');
				}
			})

		}

		function ordenar(ordenado) {
			$("#ordenado").val(ordenado);
			var por = $("#por").val();
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			$("#loader").fadeIn('slow');
			var value_por = document.getElementById('por').value;
			if (value_por == "asc") {
				$("#por").val("desc");
			}
			if (value_por == "desc") {
				$("#por").val("asc");
			}
			load(1);
		}

		//anular ingreso
		function anular_ingreso(codigo, numero_ingreso) {
			var q = $("#q").val();
			if (confirm("Realmente desea anular el ingreso " + numero_ingreso + " ?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/buscar_ingresos.php",
					data: "action=anular_ingreso&codigo_documento=" + codigo,
					"q": q,
					beforeSend: function(objeto) {
						$("#resultados").html("Cargando...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						load(1);
					}
				});
			}
		}

		//DETALLE de ingreso
		function mostrar_detalle_ingreso(codigo) {
			$(".outer_divdet_ingreso").html('');
			$.ajax({
				url: "../ajax/detalle_ingresos.php?action=detalle_ingreso&codigo_unico=" + codigo,
				beforeSend: function(objeto) {
					$("#loaderdet_ingreso").html("Cargando...");
				},
				success: function(data) {
					$(".outer_divdet_ingreso").html(data).fadeIn('fast');
					$('#loaderdet_ingreso').html('');
				}
			});
		}


		//para modifcar la fecha de pago de la trasnferencia
		function modificar_fecha_entrega_transferencia_cobro(id) {
			var fecha_actual = $("#fecha_entrega_actual_transferencia_cobro" + id).val();
			var nueva_fecha = $("#fecha_entrega_transferencia_cobro" + id).val();
			var pagina = $("#pagina").val();
			var ordenado_tr = $("#ordenado_tr").val();
			var por_tr = $("#por_tr").val();
			var cuenta_tr = $("#cuenta_tr").val();
			var dettransferencia = $("#dettransferencia").val();

			if (validarFecha(nueva_fecha) != true) {
				alert("Error en fecha, formato: dd-mm-aaaa");
				$("#fecha_entrega_transferencia_cobro" + id).val(fecha_actual);
				return false;
			}

			if (nueva_fecha == "") {
				$("#fecha_entrega_transferencia_cobro" + id).val(fecha_actual);
				return false;
			}

			if (confirm("Desea cambiar la fecha?")) {
				$.ajax({
					type: "GET",
					url: "../ajax/buscar_ingresos.php",
					data: "action=actualizar_fecha_pago&id_registro=" + id + "&nueva_fecha=" + nueva_fecha,
					beforeSend: function(objeto) {
						$("#loader_detalles_transferencias").html("Actualizando...");
					},
					success: function(datos) {
						$("#loader_detalles_transferencias").html(datos);
						$.ajax({
							url: '../ajax/buscar_ingresos.php?action=detalle_transferencias&page=' + pagina + "&ordenado_tr=" + ordenado_tr + "&por_tr=" + por_tr + "&cuenta_tr=" + cuenta_tr + "&dettransferencia=" + dettransferencia,
							beforeSend: function(objeto) {
								$('#loader_detalles_transferencias').html('<img src="../image/ajax-loader.gif"> Cargando...');
							},
							success: function(data) {
								$(".outer_div_detalles_transferencias").html(data).fadeIn('slow');
								$('#loader_detalles_transferencias').html('');
								load(pagina);
							}
						});
						event.preventDefault();
					}
				})
			} else {
				$("#fecha_entrega_transferencia_cobro" + id).val(fecha_actual);
			}
		}


		//para modifcar la fecha de pago del deposito
		function modificar_fecha_entrega_deposito_cobro(id) {
			var fecha_actual = $("#fecha_entrega_actual_deposito_cobro" + id).val();
			var nueva_fecha = $("#fecha_entrega_deposito_cobro" + id).val();
			var pagina = $("#pagina").val();
			var ordenado_de = $("#ordenado_de").val();
			var cuenta_de = $("#cuenta_de").val();
			var por_de = $("#por_de").val();
			var detdeposito = $("#detdeposito").val();

			if (validarFecha(nueva_fecha) != true) {
				alert("Error en fecha, formato: dd-mm-aaaa");
				$("#fecha_entrega_deposito_cobro" + id).val(fecha_actual);
				return false;
			}

			if (nueva_fecha == "") {
				$("#fecha_entrega_deposito_cobro" + id).val(fecha_actual);
				return false;
			}

			if (confirm("Desea cambiar la fecha?")) {
				$.ajax({
					type: "GET",
					url: "../ajax/buscar_ingresos.php",
					data: "action=actualizar_fecha_pago&id_registro=" + id + "&nueva_fecha=" + nueva_fecha,
					beforeSend: function(objeto) {
						$("#loader_detalles_depositos").html("Actualizando...");
					},
					success: function(datos) {
						$("#loader_detalles_depositos").html(datos);
						$.ajax({
							url: '../ajax/buscar_ingresos.php?action=detalle_depositos&page=' + pagina + "&ordenado_de=" + ordenado_de + "&por_de=" + por_de + "&cuenta_de=" + cuenta_de + "&detdeposito=" + detdeposito,
							beforeSend: function(objeto) {
								$('#loader_detalles_depositos').html('<img src="../image/ajax-loader.gif"> Cargando...');
							},
							success: function(data) {
								$(".outer_div_detalles_depositos").html(data).fadeIn('slow');
								$('#loader_detalles_depositos').html('');
								load(pagina);
							}
						});
						event.preventDefault();
					}
				})
			} else {
				$("#fecha_entrega_deposito_cobro" + id).val(fecha_actual);
			}
		}


		$("#actualizar_ingreso").submit(function(event) {
			var codigo_unico_ingreso = $("#codigo_unico_ingreso").val().trim();
			var id_cliente = $("#id_cliente_editar_ingreso").val().trim();
			var nombre_cliente = $("#cliente_editar_ingreso").val().trim();
			var fecha_ingreso = $("#fecha_editar_ingreso").val().trim();
			var observaciones = $("#observaciones_editar_ingreso").val().trim();

			if (codigo_unico_ingreso === "") {
				alert("Vuelva a seleccionar un ingreso para editar");
				return false;
			}

			if (id_cliente === "") {
				alert("Seleccione un cliente");
				$("#cliente_editar_ingreso").focus();
				return false;
			}

			if (fecha_ingreso === "") {
				alert("Ingrese fecha de ingreso");
				$("#fecha_editar_ingreso").focus();
				return false;
			}
			$('#actualiza_ingreso').attr("disabled", true);
			var parametros = $(this).serialize();
			if (confirm("Desea actualizar el ingreso?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/buscar_ingresos.php?action=actualizar_ingreso",
					data: parametros,
					beforeSend: function(objeto) {
						$("#loaderdet_ingreso").html("Mensaje: Guardando...");
					},
					success: function(datos) {
						$("#loaderdet_ingreso").html(datos);
						$('#actualiza_ingreso').attr("disabled", false);
						load(1);
					}
				});
				event.preventDefault();
			}
		})

		function enviar_ingreso_mail(id, mail) {
			$("#id_documento").val(id);
			$("#mail_receptor").val(mail);
			$("#tipo_documento").val("ingreso");
		};
	</script>