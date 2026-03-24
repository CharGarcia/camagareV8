<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

?>
	<?php
	// header('Content-Type: text/html; charset=ISO-8859-1');
	?>
	<!DOCTYPE html>
	<html lang="es">

	<head>

		<meta charset="utf-8">
		<title>Plan de cuentas</title>
		<?php include("../paginas/menu_de_empresas.php");
		include("../modal/plan_de_cuentas.php");
		?>
	</head>

	<body>

		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<button type='button' class="btn btn-info" onclick="guarda_plan_inicial();"><span class="glyphicon glyphicon-plus"></span> Crear plan de cuentas inicial</button>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Plan de cuentas</h4>
				</div>

				<ul class="nav nav-tabs nav-justified">
					<li class="active"><a data-toggle="tab" href="#cuentas">Cuentas</a></li>
					<li><a data-toggle="tab" href="#cargar_cuentas">Cargar cuentas</a></li>
				</ul>
				<div class="tab-content">
					<div id="cuentas" class="tab-pane fade in active">
						<div class="panel-body">
							<form class="form-horizontal" role="form" method="POST" target="_blank" action="../excel/reporte_plan_cuentas.php">
								<div class="form-group row">
									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Estado</b></span>
											<select class="form-control input-sm" id="status" name="status" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<option value="1">Activa</option>
												<option value="0">Inactiva</option>
											</select>
										</div>
									</div>

									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Grupo</b></span>
											<select class="form-control input-sm" id="grupo" name="grupo" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<option value="1">Activos</option>
												<option value="2">Pasivos</option>
												<option value="3">Patrimonio</option>
												<option value="4">Ingresos</option>
												<option value="5">Costos</option>
												<option value="6">Gastos</option>
												<option value="7">Resumen</option>
											</select>
										</div>
									</div>
									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Nivel</b></span>
											<select class="form-control input-sm" id="nivel" name="nivel" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<option value="1">Uno</option>
												<option value="2">Dos</option>
												<option value="3">Tres</option>
												<option value="4">Cuatro</option>
												<option value="5">Cinco</option>
											</select>
										</div>
									</div>
									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Proyecto</b></span>
											<select class="form-control input-sm" id="id_proyecto" name="id_proyecto" onchange='load(1);'>
												<option value="0" selected>Ninguno</option>
												<?php
												$sql_proyecto = mysqli_query($con, "SELECT * FROM proyectos WHERE ruc_empresa ='" . $ruc_empresa . "' and status ='1' order by nombre asc");
												foreach ($sql_proyecto as $proyecto) {
												?>
													<option value="<?php echo $proyecto['id'] ?>"><?php echo strtoupper($proyecto['nombre']) ?></option>
												<?php
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-md-3">
										<input type="hidden" id="ordenado" value="codigo_cuenta">
										<input type="hidden" id="por" value="asc">
										<div class="input-group">
											<span class="input-group-addon"><b>Buscar:</b></span>
											<input type="text" class="form-control input-sm" id="q" placeholder="Código, cuenta" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" class="btn btn-default input-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
											</span>
										</div>
									</div>
									<div class="col-md-1">
										<button type="submit" title="Descargar plan de cuentas a excel" class="btn btn-success"><img alt="Brand" src="../image/excel.ico" width="25" height="20"></button>
									</div>
									<span id="loader"></span>
								</div>
							</form>
							<div id="resultados"></div><!-- Carga los datos ajax -->
							<div class='outer_div'></div><!-- Carga los datos ajax -->
						</div>
					</div>

					<div id="cargar_cuentas" class="tab-pane fade">
						<div class="panel-body">
							<div class="row">
								<div class="col-md-4">
									<div class="panel panel-info">
										<div class="table table-bordered">
											<table class="table">
												<tr class="info">
													<th colspan="2">Cargar cuentas desde excel</th>
												</tr>
												<tr>
													<form method="post" action="" id="cargar_archivo_cuentas" name="cargar_archivo_cuentas" enctype="multipart/form-data">
														<div class="form-group row">
															<td class="col-xs-10">
																<input class="filestyle" data-buttonText=" Archivo excel" type="file" id="archivo" name="archivo" data-buttonText="Archivo excel" multiple />
															</td>
															<td class="col-xs-2">
																<button type="submit" class="btn btn-info" name="subir"><span class="glyphicon glyphicon-upload"></span> Cargar</button>
															</td>
														</div>
													</form>
													<span id="loader_cargar_cuentas"></span>
												</tr>
											</table>
										</div>
									</div>

								</div>
								<div class="col-md-8">
									<div class="panel panel-info">
										<div class="table table-bordered">
											<table class="table">
												<tr class="info">
													<th colspan="2">Opciones de plan de cuentas</th>
												</tr>
												<tr>
													<div class="form-group row">
														<td class="col-xs-1">
															<a href="../descargas/plancuentas.xlsx" class="list-group-item list-group-item-warning" target="_blank"><span class="glyphicon glyphicon-download-alt" aria-hidden="true"></span> Descargar plan de cuentas modelo </a>
														</td>
														<td class="col-xs-1">
															<div id="loader_carga"></div><!-- Carga los datos ajax -->
															<div class='outer_div_carga'></div><!-- Carga los datos ajax -->
														</td>
													</div>
												</tr>
											</table>
										</div>
									</div>
								</div>
							</div>
							<div id="resultados_cargar_cuentas"></div><!-- Carga los datos ajax -->
							<div class='outer_div_cargar_cuentas'></div><!-- Carga los datos ajax -->
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
	<script type="text/javascript" src="../js/style_bootstrap.js"> </script>
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
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
			buscar_cargas();
			load(1);
		});

		function guarda_plan_inicial() {
			$.ajax({
				type: "POST",
				url: "../ajax/plan_de_cuentas.php?action=plan_cuentas_inicial",
				beforeSend: function(objeto) {
					$("#loader").html("Guardando...");
				},
				success: function(datos) {
					$("#loader").html(datos);
					load(1);
				}
			});
			event.preventDefault();
		}

		$(function() {
			$("#cargar_archivo_cuentas").on("submit", function(e) {
				e.preventDefault();
				var formData = new FormData(document.getElementById("cargar_archivo_cuentas"));
				formData.append("dato", "valor");
				$.ajax({
						url: "../ajax/plan_de_cuentas.php?action=archivo_excel_plan_de_cuentas",
						type: "post",
						dataType: "html",
						data: formData,
						beforeSend: function(objeto) {
							$('#loader_cargar_cuentas').html('<div class="progress"><div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" style="width:100%;">Procesando archivo excel, espere por favor...</div></div>');
						},
						cache: false,
						contentType: false,
						processData: false
					})
					.done(function(res) {
						$("#resultados_cargar_cuentas").html(res);
						$("#loader_cargar_cuentas").html('');
						load(1);
					});

			});
		});

		function buscar_cargas(page) {
			$("#loader_carga").fadeIn('slow');
			$.ajax({
				url: '../ajax/plan_de_cuentas.php?action=cargas_plan_de_cuentas',
				beforeSend: function(objeto) {
					$('#loader_carga').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div_carga").html(data).fadeIn('slow');
					$('#loader_carga').html('');
				}
			})
		}


		function eliminar_carga(codigo) {
			if (confirm("Realmente desea eliminar la carga?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/plan_de_cuentas.php?action=eliminar_cargas_plan_de_cuentas",
					data: "codigo=" + codigo,
					beforeSend: function(objeto) {
						$("#resultados_cargar_cuentas").html("Mensaje: Cargando...");
					},
					success: function(datos) {
						$("#resultados_cargar_cuentas").html(datos);
						setTimeout(function() {
							location.href = '../modulos/plan_de_cuentas.php'
						}, 2000);
						load(1);
					}
				});
			}
		}


		function load(page) {
			var q = $("#q").val();
			var por = $("#por").val();
			var ordenado = $("#ordenado").val();
			var status = $("#status").val();
			var nivel = $("#nivel").val();
			var grupo = $("#grupo").val();
			var id_proyecto = $("#id_proyecto").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/plan_de_cuentas.php?action=buscar_cuentas&page=' + page +
					'&q=' + q + "&por=" + por + "&ordenado=" + ordenado + "&status=" + status + "&nivel=" +
					nivel + "&grupo=" + grupo + "&id_proyecto=" + id_proyecto,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');

				}
			})
		}


		function eliminar_cuenta_contable(id) {
			var q = $("#q").val();
			if (confirm("Realmente desea eliminar la cuenta contable?")) {
				$.ajax({
					type: "GET",
					url: "../ajax/plan_de_cuentas.php",
					data: "action=eliminar_cuentas_contables&id_cuenta=" + id,
					"q": q,
					beforeSend: function(objeto) {
						$("#loader").html("Eliminando...");
					},
					success: function(datos) {
						$("#loader").html("");
						$("#resultados").html(datos);
						load(1);
					}
				});
			}
		}

		function obtener_datos_editar_cuenta(id) {
			document.querySelector("#editar_cuenta").reset();
			document.querySelector("#mod_id_cuenta").value = id;

			let request = (window.XMLHttpRequest) ?
				new XMLHttpRequest() :
				new ActiveXObject('Microsoft.XMLHTTP');
			let ajaxUrl = '../ajax/plan_de_cuentas.php?action=datos_editar_cuenta&id_cuenta=' + id;
			request.open("GET", ajaxUrl, true);
			request.send();
			request.onreadystatechange = function() {
				if (request.readyState == 4 && request.status == 200) {
					let objData = JSON.parse(request.responseText);
					if (objData.status) {
						let datos = objData.data;
						document.querySelector("#mod_nombre_cuenta").value = datos.nombre_cuenta;
						document.querySelector("#mod_cuenta_sri").innerHTML = datos.nombre_cuenta_sri;
						document.querySelector("#mod_cuenta_supercias").innerHTML = datos.nombre_cuenta_supercias;
						document.querySelector("#mod_codigo_sri").value = datos.codigo_sri;
						document.querySelector("#mod_codigo_supercias").value = datos.codigo_supercias;
						document.querySelector("#mod_nivel_cuenta").value = datos.nivel_cuenta;
						document.querySelector("#mod_codigo_cuenta").value = datos.codigo_cuenta;
						document.querySelector("#listStatus").value = datos.status;
						document.querySelector("#mod_proyecto").value = datos.id_proyecto;

					} else {
						$.notify(objData.msg, "error");
					}
				}
				return false;
			}
		}


		function mostrar_datos_nueva_cuenta(id) {
			document.querySelector("#guardar_nueva_cuenta").reset();
			var nombre_cuenta = $("#nombre_cuenta" + id).val();
			var codigo_cuenta = $("#codigo_cuenta" + id).val();
			var nivel = $("#nivel_cuenta" + id).val();
			var nuevo_nivel = (parseInt(nivel) + parseInt(1));
			$("#nuevo_nivel_cuenta").val(parseInt(nivel) + parseInt(1));
			$("#mostrar_codigo_cuenta").val('La nueva cuenta se creará dentro de: \nCuenta: ' + nombre_cuenta + ' \nCódigo: ' + codigo_cuenta + ' \nNivel: ' + nuevo_nivel);

			$.ajax({
				type: "POST",
				url: "../ajax/plan_de_cuentas.php?action=siguiente_codigo_cuenta",
				data: "id_cuenta=" + id,
				beforeSend: function(objeto) {
					$("#loader_guardar_cuenta").html("Actualizando...");
				},
				success: function(datos) {
					$("#nuevo_codigo_cuenta").val(datos.replace(/^\s*|\s*$/g, ""));
					$("#loader_guardar_cuenta").html("");
				}
			});
		}
	</script>