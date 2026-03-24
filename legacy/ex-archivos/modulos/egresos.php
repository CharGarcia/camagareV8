<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
?>
	<!DOCTYPE html>
	<html lang="en">

	<head>
		<title>Egresos</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		include("../modal/detalle_ingreso_egreso.php");
		include("../modal/enviar_documentos_mail.php");
		$con = conenta_login();
		require_once dirname(__DIR__) . '/paginas/verificar_permiso_modulo.php';
		?>
	</head>

	<body>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<form method="post" action="../modulos/nuevo_egreso.php">
							<?php
							if (getPermisos($con, $id_usuario, $ruc_empresa, 'egresos')['w'] == 1) {
							?>
								<button type='submit' class="btn btn-info"><span class="glyphicon glyphicon-plus"></span> Nuevo Egreso</button>
							<?php
							}
							?>
						</form>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Egresos</h4>
				</div>

				<ul class="nav nav-tabs nav-justified">
					<li class="active"><a data-toggle="tab" href="#egresos">Egresos</a></li>
					<li><a data-toggle="tab" href="#detalle_egresos">Detalle de egresos</a></li>
					<li><a data-toggle="tab" href="#detalle_pagos_egresos">Detalle de pagos</a></li>
					<li><a data-toggle="tab" href="#detalle_cheques">Detalle de cheques</a></li>
					<li><a data-toggle="tab" href="#detalle_transferencias">Detalle de transferencias</a></li>
					<li><a data-toggle="tab" href="#detalle_debitos">Detalle de débitos</a></li>
				</ul>

				<div class="tab-content">
					<div id="egresos" class="tab-pane fade in active">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Estado</b></span>
											<select class="form-control input-sm" id="estado_egreso" name="estado_egreso" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$tipo = mysqli_query($con, "SELECT distinct estado 
												FROM ingresos_egresos WHERE ruc_empresa ='" . $ruc_empresa . "' and tipo_ing_egr ='EGRESO' order by estado asc");
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
											<select class="form-control input-sm" id="anio_egreso" name="anio_egreso" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$existsCurrentYear = false;
												$tipo = mysqli_query($con, "SELECT distinct(year(fecha_ing_egr)) as anio FROM ingresos_egresos WHERE ruc_empresa ='" . $ruc_empresa . "' and tipo_ing_egr ='EGRESO' order by year(fecha_ing_egr) desc");
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
											<select class="form-control input-sm" id="mes_egreso" name="mes_egreso" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$tipo = mysqli_query($con, "SELECT distinct(DATE_FORMAT(fecha_ing_egr, '%m')) as mes FROM ingresos_egresos WHERE ruc_empresa ='" . $ruc_empresa . "' and tipo_ing_egr ='EGRESO' order by month(fecha_ing_egr) asc");
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
											<input type="text" class="form-control input-sm" id="egreso" placeholder="Proveedor, Número de egreso, fecha, detalle" onkeyup='load(1);'>
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
					<div id="detalle_egresos" class="tab-pane fade">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<div class="col-md-5">
										<input type="hidden" id="ordenado" value="numero_ing_egr">
										<input type="hidden" id="por" value="desc">
										<div class="input-group">
											<span class="input-group-addon"><b>Buscar:</b></span>
											<input type="text" class="form-control input-sm" id="detegr" placeholder="Proveedor, Número de egreso, fecha, detalle" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" class="btn btn-default input-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
											</span>
										</div>
									</div>
									<span id="loader_detalles"></span>
								</div>
							</form>
							<div id="resultados_detalles_egresos"></div><!-- Carga los datos ajax -->
							<div class='outer_div_detalles'></div><!-- Carga los datos ajax -->
						</div>
					</div>

					<div id="detalle_pagos_egresos" class="tab-pane fade">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<div class="col-md-5">
										<input type="hidden" id="ordenado" value="numero_ing_egr">
										<input type="hidden" id="por" value="desc">
										<div class="input-group">
											<span class="input-group-addon"><b>Buscar:</b></span>
											<input type="text" class="form-control input-sm" id="detpago" placeholder="Proveedor, Número de egreso, fecha, detalle" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" class="btn btn-default input-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
											</span>
										</div>
									</div>
									<span id="loader_detalles_pagos"></span>
								</div>

							</form>
							<div id="resultados_detalles_pagos_egresos"></div><!-- Carga los datos ajax -->
							<div class='outer_div_detalles_pagos'></div><!-- Carga los datos ajax -->
						</div>
					</div>

					<div id="detalle_cheques" class="tab-pane fade">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">

									<div class="col-md-5">
										<div class="input-group">
											<span class="input-group-addon"><b>Cuenta</b></span>
											<select class="form-control input-sm" id="cuenta_ch" name="cuenta_ch" onchange='load(1);'>
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
										<input type="hidden" id="ordenado_ch" value="for_pag.cheque">
										<input type="hidden" id="por_ch" value="desc">
										<div class="input-group">
											<span class="input-group-addon"><b>Buscar</b></span>
											<input type="text" class="form-control input-sm" id="detcheque" placeholder="Fecha, cheque, detalle" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" class="btn btn-default btn-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
											</span>
										</div>
									</div>
									<div class="col-md-2">
										<span id="loader_detalles_cheques"></span>
									</div>
								</div>
							</form>
							<div id="resultados_detalles_cheques"></div><!-- Carga los datos ajax -->
							<div class='outer_div_detalles_cheques'></div><!-- Carga los datos ajax -->
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
					<div id="detalle_debitos" class="tab-pane fade">
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
											<input type="text" class="form-control input-sm" id="detdebito" placeholder="Fecha, detalle" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" class="btn btn-default btn-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
											</span>
										</div>
									</div>
									<div class="col-md-2">
										<span id="loader_detalles_debitos"></span>
									</div>
								</div>
							</form>
							<div id="resultados_detalles_debitos"></div><!-- Carga los datos ajax -->
							<div class='outer_div_detalles_debitos'></div><!-- Carga los datos ajax -->
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
	<!-- <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script> -->

	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/validar_fecha.js" type="text/javascript"></script>
	<script src="../js/ordenado.js" type="text/javascript"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	</body>

	</html>
	<script>
		/* function formatFechaEgreso(id) {
			jQuery(function($) {
				$("#fecha_nueva_egreso" + id).mask("99-99-9999");
			});
			calendarioFecha(id);
		} */

		function formatFechaEntregaCheque(id) {
			jQuery(function($) {
				$("#fecha_entrega_cheque" + id).mask("99-99-9999");
			});
			calendarioFecha(id);
		}

		function formatFechaEntregaTransferenciaPago(id) {
			jQuery(function($) {
				$("#fecha_entrega_transferencia_pago" + id).mask("99-99-9999");
			});
			calendarioFecha(id);
		}

		function formatFechaEntregaDebitoPago(id) {
			jQuery(function($) {
				$("#fecha_entrega_debito_pago" + id).mask("99-99-9999");
			});
			calendarioFecha(id);
		}


		function calendarioFecha(id) {
			$(function() {
				$("#fecha_entrega_cheque" + id).datepicker({
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

				/* $("#fecha_nueva_egreso" + id).datepicker({
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

				$("#fecha_entrega_transferencia_pago" + id).datepicker({
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

				$("#fecha_entrega_debito_pago" + id).datepicker({
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

		$(document).ready(function() {
			window.addEventListener("keypress", function(event) {
				if (event.keyCode == 13) {
					event.preventDefault();
				}
			}, false);
			load(1);
		});

		function load(page) {
			var egreso = $("#egreso").val();
			var detegr = $("#detegr").val();
			var detpago = $("#detpago").val();
			var detcheque = $("#detcheque").val();
			var dettransferencia = $("#dettransferencia").val();
			var por = $("#por").val();
			var ordenado = $("#ordenado").val();
			var por_ch = $("#por_ch").val();
			var ordenado_ch = $("#ordenado_ch").val();
			var cuenta_ch = $("#cuenta_ch").val();
			var estado_egreso = $("#estado_egreso").val();
			var anio_egreso = $("#anio_egreso").val();
			var mes_egreso = $("#mes_egreso").val();

			var cuenta_de = $("#cuenta_de").val();
			var por_de = $("#por_de").val();
			var ordenado_de = $("#ordenado_de").val();
			var detdebito = $("#detdebito").val();

			var ordenado_tr = $("#ordenado_tr").val();
			var por_tr = $("#por_tr").val();
			var cuenta_tr = $("#cuenta_tr").val();

			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_egresos.php?action=egresos&page=' + page + '&egreso=' +
					egreso + "&por=" + por + "&ordenado=" + ordenado + '&estado_egreso=' + estado_egreso +
					'&anio_egreso=' + anio_egreso + '&mes_egreso=' + mes_egreso,
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
				url: '../ajax/buscar_egresos.php?action=detalle&page=' + page + '&detegr=' + detegr,
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
				url: '../ajax/buscar_egresos.php?action=pagos_egresos&page=' + page + '&detpago=' + detpago,
				beforeSend: function(objeto) {
					$('#loader_detalles_pagos').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div_detalles_pagos").html(data).fadeIn('slow');
					$('#loader_detalles_pagos').html('');
				}
			})

			$("#loader_detalles_cheques").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_egresos.php?action=detalle_cheques&page=' + page + '&detcheque=' + detcheque + "&por_ch=" + por_ch + "&ordenado_ch=" + ordenado_ch + "&cuenta_ch=" + cuenta_ch,
				beforeSend: function(objeto) {
					$('#loader_detalles_cheques').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div_detalles_cheques").html(data).fadeIn('slow');
					$('#loader_detalles_cheques').html('');
				}
			})

			$("#loader_detalles_transferencias").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_egresos.php?action=detalle_transferencias&page=' + page + '&dettransferencia=' + dettransferencia + "&por_tr=" + por_tr + "&ordenado_tr=" + ordenado_tr + "&cuenta_tr=" + cuenta_tr,
				beforeSend: function(objeto) {
					$('#loader_detalles_transferencias').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div_detalles_transferencias").html(data).fadeIn('slow');
					$('#loader_detalles_transferencias').html('');
				}
			})

			$("#loader_detalles_debitos").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_egresos.php?action=detalle_debitos&page=' + page + '&detdebito=' + detdebito + "&por_de=" + por_de + "&ordenado_de=" + ordenado_de + "&cuenta_de=" + cuenta_de,
				beforeSend: function(objeto) {
					$('#loader_detalles_debitos').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div_detalles_debitos").html(data).fadeIn('slow');
					$('#loader_detalles_debitos').html('');
				}
			})

		};

		function ordenar(ordenado) {
			$("#ordenado").val(ordenado);
			var por = $("#por").val();
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

		function ordenar_ch(ordenado) {
			$("#ordenado_ch").val(ordenado);
			var por = $("#por_ch").val();
			var ordenado = $("#ordenado_ch").val();
			$("#loader").fadeIn('slow');
			var value_por = document.getElementById('por_ch').value;
			if (value_por == "asc") {
				$("#por_ch").val("desc");
			}
			if (value_por == "desc") {
				$("#por_ch").val("asc");
			}
			load(1);
		}
		//para anular egresos
		function anular_egreso(codigo_documento, numero_egreso) {
			var q = $("#q").val();

			if (confirm("Realmente desea anular el egreso " + numero_egreso + " ?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/buscar_egresos.php",
					data: "action=anular_egreso&codigo_documento=" + codigo_documento,
					"q": q,
					beforeSend: function(objeto) {
						$("#loader").html("Actualizando...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						$("#loader").html("");
						load(1);
					}
				});
			}
		}

		//DETALLE de egreso para editar
		function mostrar_detalle_egreso(codigo) {
			$(".outer_divdet_egreso").html('');
			$.ajax({
				url: "../ajax/detalle_documento.php?action=detalle_egreso&codigo_unico=" + codigo,
				beforeSend: function(objeto) {
					$("#loaderdet_egreso").html("Cargando...");
				},
				success: function(data) {
					$(".outer_divdet_egreso").html(data).fadeIn('fast');
					$('#loaderdet_egreso').html('');
				}
			});
		}

		/* function enviar_egreso_mail(id) {
			var mail_receptor = $("#mail_proveedor" + id).val();
			$("#id_documento").val(id);
			$("#mail_receptor").val(mail_receptor);
			$("#tipo_documento").val("egreso");
		} */

		//para enviar por mail el egreso
		/* $("#documento_mail").submit(function(event) {
			$('#enviar_mail').attr("disabled", true);
			$('#mensaje_mail').attr("hidden", true); // para mostrar el mensaje de dar clik para enviar y mas abajo lo desaparece
			var parametros = $(this).serialize();
			var pagina = $("#pagina").val();
			$.ajax({
				type: "GET",
				url: "../documentos_mail/envia_mail.php?",
				data: parametros,
				beforeSend: function(objeto) {
					$("#resultados_ajax_mail").html(
						'<div class="progress"><div class="progress-bar progress-bar-primary progress-bar-striped active" role="progressbar" style="width:100%;">Enviando egreso por mail espere por favor...</div></div>');
				},
				success: function(datos) {
					$("#resultados_ajax_mail").html(datos);
					$('#enviar_mail').attr("disabled", false);
					$('#mensaje_mail').attr("hidden", false); // lo vuelve a mostrar el mensaje cuando ya hace todo el proceso
					load(pagina);
				}
			});
			event.preventDefault();
		}); */

		//para modificar el estado del cheque
		function modificar_estado_cheque(id) {
			var estado_actual = $("#estado_actual_cheque" + id).val();
			var nuevo_estado = $("#estado_cheque" + id).val();
			var por = $("#por_ch").val();
			var ordenado = $("#ordenado_ch").val();
			var cuenta_ch = $("#cuenta_ch").val();
			var pagina = $("#pagina").val();
			var detcheque = $("#detcheque").val();
			if (confirm("Realmente desea cambiar el estado del cheque?")) {
				$.ajax({
					type: "GET",
					url: "../ajax/buscar_egresos.php",
					data: "action=actualizar_estado_cheque&id_cheque=" + id + "&nuevo_estado=" + nuevo_estado + "&ordenado_ch=" + ordenado + "&por_ch=" + por + "&cuenta_ch=" + cuenta_ch,
					beforeSend: function(objeto) {
						$("#loader_detalles_cheques").html("Actualizando...");
					},
					success: function(datos) {
						$("#loader_detalles_cheques").html(datos);
						$.ajax({
							url: '../ajax/buscar_egresos.php?action=detalle_cheques&page=' + pagina + '&detcheque=' + detcheque + "&ordenado_ch=" + ordenado + "&por_ch=" + por + "&cuenta_ch=" + cuenta_ch,
							beforeSend: function(objeto) {
								$('#loader_detalles_cheques').html('<img src="../image/ajax-loader.gif"> Cargando...');
							},
							success: function(data) {
								$(".outer_div_detalles_cheques").html(data).fadeIn('slow');
								$('#loader_detalles_cheques').html('');
								load(pagina);
							}
						});
						event.preventDefault();
					}
				})
			} else {
				$("#estado_cheque" + id).val(estado_actual);
			}

		}

		//para modifcar la fecha de pago y entrega del cheque
		function modificar_fecha_entrega_cheque(id) {
			var fecha_actual = $("#fecha_entrega_actual_cheque" + id).val();
			var nueva_fecha = $("#fecha_entrega_cheque" + id).val();
			var pagina = $("#pagina").val();
			var por = $("#por_ch").val();
			var ordenado = $("#ordenado_ch").val();
			var cuenta_ch = $("#cuenta_ch").val();
			//var detcheque = $("#detcheque").val();
			if (validarFecha(nueva_fecha) != true) {
				alert("Error en fecha, formato: dd-mm-aaaa");
				$("#fecha_entrega_cheque" + id).val(fecha_actual);
				return false;
			}

			if (nueva_fecha == "") {
				$("#fecha_entrega_cheque" + id).val(fecha_actual);
				return false;
			}

			if (confirm("Desea cambiar la fecha?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/buscar_egresos.php",
					data: "action=actualizar_fecha_entrega_cheque&id_registro=" + id + "&nueva_fecha=" + nueva_fecha,
					beforeSend: function(objeto) {
						$("#loader_detalles_cheques").html("Actualizando...");
					},
					success: function(datos) {
						$("#loader_detalles_cheques").html(datos);
						$.ajax({
							url: '../ajax/buscar_egresos.php?action=detalle_cheques&page=' + pagina + '&detcheque=' + detcheque + "&ordenado_ch=" + ordenado + "&por_ch=" + por + "&cuenta_ch=" + cuenta_ch,
							beforeSend: function(objeto) {
								$('#loader_detalles_cheques').html('<img src="../image/ajax-loader.gif"> Cargando...');
							},
							success: function(data) {
								$(".outer_div_detalles_cheques").html(data).fadeIn('slow');
								$('#loader_detalles_cheques').html('');
								load(pagina);
							}
						});
						event.preventDefault();
					}
				})
			} else {
				$("#fecha_entrega_cheque" + id).val(fecha_actual);
			}
		}


		//para modifcar la fecha de pago de la trasnferencia
		function modificar_fecha_entrega_transferencia_pago(id) {
			var fecha_actual = $("#fecha_entrega_actual_transferencia_pago" + id).val();
			var nueva_fecha = $("#fecha_entrega_transferencia_pago" + id).val();
			var pagina = $("#pagina").val();
			var dettransferencia = $("#dettransferencia").val();
			var por_tr = $("#por_tr").val();
			var ordenado_tr = $("#ordenado_tr").val();
			var cuenta_tr = $("#cuenta_tr").val();

			if (validarFecha(nueva_fecha) != true) {
				alert("Error en fecha, formato: dd-mm-aaaa");
				$("#fecha_entrega_transferencia_pago" + id).val(fecha_actual);
				return false;
			}

			if (nueva_fecha == "") {
				$("#fecha_entrega_transferencia_pago" + id).val(fecha_actual);
				return false;
			}

			if (confirm("Desea cambiar la fecha?")) {
				$.ajax({
					type: "GET",
					url: "../ajax/buscar_egresos.php",
					data: "action=actualizar_fecha_pago&id_registro=" + id + "&nueva_fecha=" + nueva_fecha,
					beforeSend: function(objeto) {
						$("#loader_detalles_transferencias").html("Actualizando...");
					},
					success: function(datos) {
						$("#loader_detalles_transferencias").html(datos);
						$.ajax({
							url: '../ajax/buscar_egresos.php?action=detalle_transferencias&page=' + pagina + '&dettransferencia=' + dettransferencia + "&por_tr=" + por_tr + "&ordenado_tr=" + ordenado_tr + "&cuenta_tr=" + cuenta_tr,
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
				$("#fecha_entrega_transferencia_pago" + id).val(fecha_actual);
			}
		}


		//para modifcar la fecha de pago del debito
		function modificar_fecha_entrega_debito_pago(id) {
			var fecha_actual = $("#fecha_entrega_actual_debito_pago" + id).val();
			var nueva_fecha = $("#fecha_entrega_debito_pago" + id).val();
			var pagina = $("#pagina").val();
			var detdebito = $("#detdebito").val();
			var por_de = $("#por_de").val();
			var ordenado_de = $("#ordenado_de").val();
			var cuenta_de = $("#cuenta_de").val();

			if (validarFecha(nueva_fecha) != true) {
				alert("Error en fecha, formato: dd-mm-aaaa");
				$("#fecha_entrega_debito_pago" + id).val(fecha_actual);
				return false;
			}

			if (nueva_fecha == "") {
				$("#fecha_entrega_debito_pago" + id).val(fecha_actual);
				return false;
			}

			if (confirm("Desea cambiar la fecha?")) {
				$.ajax({
					type: "GET",
					url: "../ajax/buscar_egresos.php",
					data: "action=actualizar_fecha_pago&id_registro=" + id + "&nueva_fecha=" + nueva_fecha,
					beforeSend: function(objeto) {
						$("#loader_detalles_debitos").html("Actualizando...");
					},
					success: function(datos) {
						$("#loader_detalles_debitos").html(datos);
						$.ajax({
							url: '../ajax/buscar_egresos.php?action=detalle_debitos&page=' + pagina + '&detdebito=' + detdebito + "&por_de=" + por_de + "&ordenado_de=" + ordenado_de + "&cuenta_de=" + cuenta_de,
							beforeSend: function(objeto) {
								$('#loader_detalles_debitos').html('<img src="../image/ajax-loader.gif"> Cargando...');
							},
							success: function(data) {
								$(".outer_div_detalles_debitos").html(data).fadeIn('slow');
								$('#loader_detalles_debitos').html('');
								load(pagina);
							}
						});
						event.preventDefault();
					}
				})
			} else {
				$("#fecha_entrega_debito_pago" + id).val(fecha_actual);
			}
		}

		//para modificar la fecha de egreso
		/* function modificar_fecha_egreso(id) {
			var fecha_anterior_egreso = $("#fecha_anterior_egreso" + id).val();
			var fecha_nueva_egreso = $("#fecha_nueva_egreso" + id).val();
			var pagina = $("#pagina").val();
			if (validarFecha(fecha_nueva_egreso) != true) {
				alert("Error en fecha, formato: dd-mm-aaaa");
				$("#fecha_nueva_egreso" + id).val(fecha_anterior_egreso);
				return false;
			}

			if (fecha_nueva_egreso == "") {
				$("#fecha_nueva_egreso" + id).val(fecha_anterior_egreso);
				return false;
			}

			if (confirm("Realmente desea cambiar la fecha del egreso?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/buscar_egresos.php",
					data: "action=actualizar_fecha_egreso&id_registro=" + id + "&nueva_fecha=" + fecha_nueva_egreso,
					beforeSend: function(objeto) {
						$("#loader").html("Actualizando...");
					},
					success: function(datos) {
						$("#loader").html(datos);
						load(1);
					}
				})
			} else {
				$("#fecha_nueva_egreso" + id).val(fecha_anterior_egreso);
			}
		} */

		//para modificar detalle
		/* function modificar_detalle_egreso(id) {
			var detalle_adicional_anterior = $("#detalle_adicional_anterior" + id).val();
			var detalle_adicional_nuevo = $("#detalle_adicional_nuevo" + id).val();
			if (confirm("Realmente desea cambiar el detalle del egreso?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/buscar_egresos.php",
					data: "action=actualizar_detalle_egreso&id_registro=" + id + "&nuevo_detalle=" + detalle_adicional_nuevo,
					beforeSend: function(objeto) {
						$("#loader").html("Actualizando...");
					},
					success: function(datos) {
						$("#loader").html(datos);
						load(1);
						//event.preventDefault();	
					}
				})
			} else {
				$("#detalle_adicional_nuevo" + id).val(detalle_adicional_anterior);
			}
		} */


		function buscar_beneficiarios(id) {
			$("#beneficiario_final_cheque" + id).autocomplete({
				source: '../ajax/proveedores_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_beneficiario_final').val(ui.item.id_proveedor);
					$('#beneficiario_final_cheque' + id).val(ui.item.razon_social);
				}
			});

			$("#beneficiario_final_cheque" + id).on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_proveedor").val("");
					$("#beneficiario_final_cheque" + id).val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_proveedor").val("");
					$("#beneficiario_final_cheque" + id).val("");
				}
			});
		}

		//para modifcar el beneficiario
		/* function modificar_beneficiario(id) {
			var nombre_actual_cheque = $("#nombre_actual_cheque" + id).val();
			var beneficiario_final_cheque = $("#beneficiario_final_cheque" + id).val();
			var id_beneficiario_actual = $("#id_beneficiario_actual" + id).val();
			var id_beneficiario_final = $("#id_beneficiario_final").val();
			var codigo_documento = $("#codigo_documento" + id).val();
			var pagina = $("#pagina").val();

			if (beneficiario_final_cheque == "") {
				alert("Seleccione un beneficiario de la lista desplegable");
				$("#beneficiario_final_cheque" + id).val(nombre_actual_cheque);
				$("#id_beneficiario_final" + id).val(id_beneficiario_actual);
				return false;
			}

			if (id_beneficiario_final == "") {
				$("#beneficiario_final_cheque" + id).val(nombre_actual_cheque);
				$("#id_beneficiario_final" + id).val(id_beneficiario_actual);
				return false;
			}

			if (confirm("Realmente desea cambiar el nombre del beneficiario del cheque?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/buscar_egresos.php",
					data: "action=actualizar_beneficiario&codigo_documento=" + codigo_documento + "&beneficiario_final_cheque=" + beneficiario_final_cheque + "&id_beneficiario_final=" + id_beneficiario_final,
					beforeSend: function(objeto) {
						$("#loader").html("Actualizando...");
					},
					success: function(datos) {
						$("#loader").html(datos);
						load(1);
					}
				})
			} else {
				$("#beneficiario_final_cheque" + id).val(nombre_actual_cheque);
				$("#id_beneficiario_final" + id).val(id_beneficiario_actual);
			}
		} */


		$("#actualizar_egreso").submit(function(event) {
			var codigo_unico_egreso = $("#codigo_unico_egreso").val().trim();
			var id_beneficiario = $("#id_beneficiario_editar_egreso").val().trim();
			var nombre_beneficiario = $("#beneficiario_editar_egreso").val().trim();
			var fecha_egreso = $("#fecha_editar_egreso").val().trim();
			var observaciones = $("#observaciones_editar_egreso").val().trim();

			if (codigo_unico_egreso === "") {
				alert("Vuelva a seleccionar un egreso para editar");
				return false;
			}

			if (id_beneficiario === "") {
				alert("Seleccione un beneficiario");
				$("#beneficiario_editar_egreso").focus();
				return false;
			}

			if (fecha_egreso === "") {
				alert("Ingrese fecha de egreso");
				$("#fecha_editar_egreso").focus();
				return false;
			}
			$('#actualiza_egreso').attr("disabled", true);
			var parametros = $(this).serialize();
			if (confirm("Desea actualizar el egreso?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/buscar_egresos.php?action=actualizar_egreso",
					data: parametros,
					beforeSend: function(objeto) {
						$("#loaderdet_egreso").html("Mensaje: Guardando...");
					},
					success: function(datos) {
						$("#loaderdet_egreso").html(datos);
						$('#actualiza_egreso').attr("disabled", false);
						load(1);
					}
				});
				event.preventDefault();
			}
		})

		/* 		function actualizar_egreso() {
					var codigo_unico_egreso = $("#codigo_unico_egreso").val().trim();
					var id_beneficiario = $("#id_beneficiario_editar_egreso").val().trim();
					var nombre_beneficiario = $("#beneficiario_editar_egreso").val().trim();
					var fecha_egreso = $("#fecha_editar_egreso").val().trim();
					var observaciones = $("#observaciones_editar_egreso").val().trim();

					if (codigo_unico_egreso === "") {
						alert("Vuelva a seleccionar un egreso para editar");
						return false;
					}

					if (id_beneficiario === "") {
						alert("Seleccione un beneficiario");
						$("#beneficiario_editar_egreso").focus();
						return false;
					}

					if (fecha_egreso === "") {
						alert("Ingrese fecha de egreso");
						$("#fecha_editar_egreso").focus();
						return false;
					}

					if (confirm("Desea actualizar el egreso?")) {
						$.ajax({
							type: "POST",
							url: "../ajax/buscar_egresos.php",
							data: {
								action: "actualizar_egreso",
								codigo_documento: codigo_unico_egreso,
								id_beneficiario: id_beneficiario,
								nombre_beneficiario: nombre_beneficiario,
								fecha_egreso: fecha_egreso,
								observaciones: observaciones
							},
							beforeSend: function() {
								$("#loaderdet_egreso").html("Actualizando...");
							},
							success: function(datos) {
								$("#loaderdet_egreso").html(datos);
								load(1);
							},
							error: function(jqXHR, textStatus, errorThrown) {
								console.error("Error en la solicitud AJAX: ", textStatus, errorThrown);
								alert("Hubo un error al actualizar el egreso. Intente nuevamente.");
							}
						});
					}
				} */
	</script>