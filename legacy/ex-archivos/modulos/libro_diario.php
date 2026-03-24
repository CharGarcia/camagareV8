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
		<meta charset="utf-8">
		<title>Diario General</title>
		<?php include("../paginas/menu_de_empresas.php");
		include("../modal/nuevo_diario.php");
		include("../modal/detalle_documento_contable.php");
		$con = conenta_login();
		$delete = mysqli_query($con, "DELETE FROM detalle_diario_tmp WHERE id_usuario='" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "'");
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
						<button type='submit' class="btn btn-info" onclick="iniciar_formulario();" data-toggle="modal" data-target="#NuevoDiarioContable"><span class="glyphicon glyphicon-plus"></span> Nuevo asiento</button>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Diario general</h4>
				</div>

				<ul class="nav nav-tabs nav-justified">
					<li class="active"><a data-toggle="tab" href="#asientos">Asientos</a></li>
					<li><a data-toggle="tab" href="#libro_diario">Libro diario</a></li>
					<!-- <li><a data-toggle="tab" href="#detalle_asientos">Detalle asientos</a></li>
					<li><a data-toggle="tab" href="#opciones_asientos">Opciones asientos en bloque</a></li> -->
				</ul>
				<div class="tab-content">
					<div id="asientos" class="tab-pane fade in active">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Tipo</b></span>
											<select class="form-control input-sm" id="tipo_asiento" name="tipo_asiento" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$tipo = mysqli_query($con, "SELECT distinct(tipo) as tipo FROM encabezado_diario WHERE ruc_empresa ='" . $ruc_empresa . "' order by tipo asc");
												while ($row = mysqli_fetch_array($tipo)) {
												?>
													<option value="<?php echo $row['tipo'] ?>"><?php echo strtoupper($row['tipo']) ?></option>
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
											<select class="form-control input-sm" id="anio_asiento" name="anio_asiento" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$existsCurrentYear = false;
												$tipo = mysqli_query($con, "SELECT distinct(year(fecha_asiento)) as anio FROM encabezado_diario WHERE ruc_empresa ='" . $ruc_empresa . "' and estado != 'Anulado' order by year(fecha_asiento) desc");
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
											<select class="form-control input-sm" id="mes_asiento" name="mes_asiento" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$tipo = mysqli_query($con, "SELECT distinct(DATE_FORMAT(fecha_asiento, '%m')) as mes FROM encabezado_diario WHERE ruc_empresa ='" . $ruc_empresa . "' and estado != 'Anulado' order by month(fecha_asiento) asc");
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
										<input type="hidden" id="ordenado" value="id_diario">
										<input type="hidden" id="por" value="desc">
										<div class="input-group">
											<span class="input-group-addon"><b>Buscar:</b></span>
											<input type="text" class="form-control input-sm" id="q" placeholder="Documento, fecha, detalle" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" onclick='load(1);' class="btn btn-default input-sm"><span class="glyphicon glyphicon-search"></span> Buscar</button>
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
					<div id="libro_diario" class="tab-pane">
						<div class="panel-body">
							<form class="form-horizontal" role="form" action="../pdf/pdf_libro_diario.php" name="librodiario" target="_blank" method="POST">
								<div class="form-group row">
									<div class="col-md-12">
										<div class="form-group">
											<div class="col-sm-2">
												<div class="input-group">
													<span class="input-group-addon"><b>Desde</b></span>
													<input type="text" class="form-control input-sm text-center" name="fecha_desde" id="fecha_desde" value="<?php echo date("d-m-Y"); ?>">
												</div>
											</div>
											<div class="col-sm-2">
												<div class="input-group">
													<span class="input-group-addon"><b>Hasta</b></span>
													<input type="text" class="form-control input-sm text-center" name="fecha_hasta" id="fecha_hasta" value="<?php echo date("d-m-Y"); ?>">
												</div>
											</div>
											<div class="col-sm-3">
												<button type="button" title="Mostrar resultado" class="btn btn-info btn-sm" onclick="libro_diario()"><span class="glyphicon glyphicon-search"></span></button>
												<!--<button type="submit" title="Imprimir pdf" class="btn btn-default btn-sm" title="Pdf">Pdf</button>-->
												<button type="button" onclick="document.librodiario.action = '../excel/libro_diario.php'; document.librodiario.submit()" class="btn btn-success btn-sm" title="Descargar excel" target="_blank"><img src="../image/excel.ico" width="20" height="16"></button>
												<span id="loader_libro_diario"></span>
											</div>
										</div>
									</div>
								</div>
							</form>
							<div id="resultados_libro_diario"></div><!-- Carga los datos ajax -->
							<div class='outer_div_libro_diario'></div><!-- Carga los datos ajax -->
						</div>
					</div>


					<!-- <div id="detalle_asientos" class="tab-pane">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<div class="col-md-6">
										<div class="input-group">
											<span class="input-group-addon"><b>Buscar:</b></span>
											<input type="text" class="form-control" id="d" placeholder="Documento, fecha, detalle" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" onclick='load(1);' class="btn btn-default"><span class="glyphicon glyphicon-search"></span> Buscar</button>
											</span>
										</div>
									</div>
									<span id="loader_detalle_asientos"></span>
								</div>
							</form>
							<div id="resultados_detalle_sientos"></div>
							<div class='outer_div_detalle_asientos'></div>
						</div>
					</div> -->


					<!-- <div id="opciones_asientos" class="tab-pane">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<label for="oa" class="col-md-1 control-label">Buscar:</label>
									<div class="col-md-5">
										<div class="input-group">
											<input type="text" class="form-control" id="oa" placeholder="Tipo, fecha" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
											</span>
										</div>
									</div>
									<span id="loader_opciones_asientos"></span>
								</div>
							</form>
							<div id="resultados_opciones_asientos"></div>
							<div class='outer_div_opciones_asientos'></div>
						</div>
					</div> -->

				</div>
			</div>
		</div>
	<?php
} else {
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
	exit;
}
	?>

	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/ordenado.js" type="text/javascript"></script>
	<script src="../js/siguiente_input.js" type="text/javascript"></script>
	</body>
	<style>
		.fixedHeight {
			padding: 1px;
			max-height: 200px;
			overflow: auto;
		}
	</style>

	</html>
	<script>
		jQuery(function($) {
			$("#fecha_diario").mask("99-99-9999");
			$("#fecha_desde").mask("99-99-9999");
			$("#fecha_hasta").mask("99-99-9999");
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



		$(document).ready(function() {
			load(1);
		});

		function load(page) {
			var q = $("#q").val();
			var d = $("#d").val();
			var oa = $("#oa").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			var tipo_asiento = $("#tipo_asiento").val();
			var mes_asiento = $("#mes_asiento").val();
			var anio_asiento = $("#anio_asiento").val();


			$("#loader").fadeIn('slow');
			$.ajax({
				url: "../ajax/buscar_libro_diario.php?action=libro_diario&page=" + page + "&q=" + q + "&ordenado=" +
					ordenado + "&por=" + por + "&tipo_asiento=" + tipo_asiento + "&mes_asiento=" + mes_asiento + "&anio_asiento=" + anio_asiento,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			});

			/* $("#loader_detalle_asientos").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_libro_diario.php?action=detalle_asientos&page=' + page + '&d=' + d,
				beforeSend: function(objeto) {
					$('#loader_detalle_asientos').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div_detalle_asientos").html(data).fadeIn('slow');
					$('#loader_detalle_asientos').html('');

				}
			}) */


			/* 		$("#loader_opciones_asientos").fadeIn('slow');
					$.ajax({
						url: '../ajax/buscar_opciones_libro_diario.php?action=buscar_asientos_bloque&page=' + page + '&oa=' + oa,
						beforeSend: function(objeto) {
							$('#loader_opciones_asientos').html('<img src="../image/ajax-loader.gif"> Cargando...');
						},
						success: function(data) {
							$(".outer_div_opciones_asientos").html(data).fadeIn('slow');
							$('#loader_opciones_asientos').html('');

						}
					}) */


		}


		//para borrar datos del formulario para nuevo
		function iniciar_formulario() {
			document.querySelector("#form_nuevo_diario").reset();
			$('#resultados_ajax_cuentas').html('');
			$("#codigo_unico").val('');
			$.ajax({
				url: '../ajax/agregar_item_diario_tmp.php?action=borrar_todo',
				beforeSend: function(objeto) {
					$('#muestra_detalle_diario').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('fast');
					$('#muestra_detalle_diario').html('');
				}
			})
		}



		//detalle de diario
		function detalle_asiento(codigo) {
			$("#loaderdet_contable").fadeIn('slow');
			$.ajax({
				url: '../ajax/detalle_documento_contable.php?action=detalle_asiento&codigo_unico=' + codigo,
				beforeSend: function(objeto) {
					$('#loaderdet_contable').html('<img src="../image/ajax-loader.gif"> Cargando detalle de diario...');
				},
				success: function(data) {
					$(".outer_divdet_contable").html(data).fadeIn('slow');
					$('#loaderdet_contable').html('');
				}
			})
		}
		//eliminar asiento
		function eliminar_asiento(id) {
			var q = $("#q").val();
			if (confirm("Realmente desea anular el asiento contable?")) {
				$.ajax({
					type: "GET",
					url: "../ajax/buscar_libro_diario.php",
					data: "action=eliminar_asiento&id_diario=" + id,
					"q": q,
					beforeSend: function(objeto) {
						$('#loader').html('<img src="../image/ajax-loader.gif">Eliminando...');
					},
					success: function(datos) {
						$("#loader").html(datos);
						load(1);
					}
				});
			}
		}


		function duplicar_asiento(id) {
			document.querySelector("#form_nuevo_diario").reset();
			var codigo_unico = $("#mod_codigo_unico" + id).val();
			var codigo_unico_bloque = $("#mod_codigo_unico_bloque" + id).val();
			var concepto_general = $("#mod_concepto_general" + id).val();
			var fecha_asiento = $("#mod_fecha_asiento" + id).val();

			$("#codigo_unico").val('');
			$("#concepto_diario").val(concepto_general);
			$("#fecha_diario").val(fecha_asiento);

			$("#muestra_detalle_diario").fadeIn('fast');
			$.ajax({
				url: '../ajax/agregar_item_diario_tmp.php?action=cargar_detalle_diario&codigo_unico=' + codigo_unico + '&codigo_unico_bloque=' + codigo_unico_bloque,
				beforeSend: function(objeto) {
					$('#muestra_detalle_diario').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('fast');
					$('#muestra_detalle_diario').html('');
					document.getElementById('cuenta_diario').focus();
				}
			});
		}

		function obtener_datos(id) {
			document.querySelector("#form_nuevo_diario").reset();
			var codigo_unico = $("#mod_codigo_unico" + id).val();
			var codigo_unico_bloque = $("#mod_codigo_unico_bloque" + id).val();
			var concepto_general = $("#mod_concepto_general" + id).val();
			var fecha_asiento = $("#mod_fecha_asiento" + id).val();

			$("#codigo_unico").val(codigo_unico);
			$("#concepto_diario").val(concepto_general);
			$("#fecha_diario").val(fecha_asiento);

			$("#muestra_detalle_diario").fadeIn('fast');
			$.ajax({
				url: '../ajax/agregar_item_diario_tmp.php?action=cargar_detalle_diario&codigo_unico=' + codigo_unico + '&codigo_unico_bloque=' + codigo_unico_bloque,
				beforeSend: function(objeto) {
					$('#muestra_detalle_diario').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('fast');
					$('#muestra_detalle_diario').html('');
					document.getElementById('cuenta_diario').focus();
				}
			});
		}

		//eliminar asientos bloque
		function eliminar_asientos_bloque(codigo) {
			var q = $("#oa").val();
			if (confirm("Realmente desea anular el bloque de asientos?")) {
				$.ajax({
					type: "GET",
					url: "../ajax/buscar_opciones_libro_diario.php",
					data: "action=eliminar_asientos_bloque&codigo_bloque=" + codigo,
					"oa": q,
					beforeSend: function(objeto) {
						$("#loader_opciones_asientos").html('<img src="../image/ajax-loader.gif"> Eliminando registros, espere por favor...');
					},
					success: function(datos) {
						$(".outer_div_opciones_asientos").html(datos);
						$("#loader_opciones_asientos").html('');
						load(1);
					}
				});
			}
		}

		function libro_diario() {
			var fecha_desde = $("#fecha_desde").val();
			var fecha_hasta = $("#fecha_hasta").val();
			$.ajax({
				type: "POST",
				url: "../ajax/buscar_libro_diario.php",
				data: "action=reporte_libro_diario&fecha_desde=" + fecha_desde + "&fecha_hasta=" + fecha_hasta,
				beforeSend: function(objeto) {
					$('#loader_libro_diario').html('<img src="../image/ajax-loader.gif">');
				},
				success: function(datos) {
					$(".outer_div_libro_diario").html(datos);
					$("#loader_libro_diario").html('');
				}
			});
		}
	</script>