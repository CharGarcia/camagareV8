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
		<title>Aprobar || Adquisiciones</title>
		<?php include("../paginas/menu_de_empresas.php");
		include("../modal/detalle_aprobaciones.php");
		include("../modal/detalle_documento.php"); ?>
	</head>

	<body>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<?php
						$con = conenta_login();
						if (getPermisos($con, $id_usuario, $ruc_empresa, 'aprobar_adquisiciones')['w'] == 1) {
						?>
							<button type='submit' class="btn btn-info" title="Nueva aprobación" data-toggle="modal" data-target="#detalleAprobacionesAdquisiciones" onclick="nueva_aprobacion();"><span class="glyphicon glyphicon-plus"></span> Nueva</button>
						<?php
						}
						?>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Aprobación de adquisiciones</h4>
				</div>
				<div class="panel-body">
					<form class="form-horizontal" role="form">
						<div class="form-group row">
							<label for="q" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
								<input type="hidden" id="ordenado" value="apro.id">
								<input type="hidden" id="por" value="desc">
								<div class="input-group">
									<input type="text" class="form-control" id="q" placeholder="Buscar" onkeyup='load(1);'>
									<span class="input-group-btn">
										<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
									</span>
								</div>
							</div>
							<span id="loader"></span>
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
	<script src="../js/notify.js"></script>
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

		function load(page) {
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/aprobar_adquisiciones.php?action=buscar_aprobaciones&page=' + page + '&q=' + q + "&ordenado=" + ordenado + "&por=" + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Buscando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');

				}
			})
		}


		function nueva_aprobacion() {
			document.querySelector("#titleModalAprobacionesAdquisiciones").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nueva aprobación";
			document.querySelector("#guardar_aprobaciones_adquisiciones").reset();
			document.querySelector("#id_aprobacion_adquisicion").value = "";
			document.querySelector("#btnActionFormAprobacionesAdquisiciones").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextAprobacionesAdquisiciones").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Aprobar";
			document.querySelector('#btnActionFormAprobacionesAdquisiciones').title = "Aprobar";
			buscar_compras_aprobaciones();
		}

		/* function editar_aprobacion(id) {
			document.querySelector('#titleModalAprobacionesAdquisiciones').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar aprobación";
			document.querySelector("#guardar_aprobaciones_adquisiciones").reset();
			document.querySelector("#id_aprobacion_adquisicion").value = id;
			document.querySelector('#btnActionFormAprobacionesAdquisiciones').classList.replace("btn-primary", "btn-info");
			document.querySelector("#btnTextAprobacionesAdquisiciones").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Actualizar";
		} */


		function buscar_compras_aprobaciones() {
			var mes = $("#mes").val();
			var anio = $("#anio").val();
			$.ajax({
				url: '../ajax/aprobar_adquisiciones.php?action=buscar_compras&mes=' + mes + '&anio=' + anio,
				beforeSend: function(objeto) {
					$('#loader_aprobaciones_adquisiciones').html('<img src="../image/ajax-loader.gif">');
				},
				success: function(data) {
					$(".outer_div_aprobaciones_adquisiciones").html(data).fadeIn('slow');
					$('#loader_aprobaciones_adquisiciones').html('');

				}
			})
		}

		function detalle_compras(codigo) {
			$("#loaderdet").fadeIn('slow');
			$.ajax({
				url: '../ajax/detalle_documento.php?action=detalle_documento_adquisiciones&codigo=' + codigo,
				beforeSend: function(objeto) {
					$('#loaderdet').html('<img src="../image/ajax-loader.gif"> Cargando detalles...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('slow');
					$('#loaderdet').html('');
				}
			});

		}

		function detalle_documentos(id) {
			document.querySelector("#titleModalComprasAprobadas").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Documentos aprobados";
			$("#loader_compras_aprobadas").fadeIn('slow');
			$.ajax({
				url: '../ajax/aprobar_adquisiciones.php?action=mostrar_compras_aprobadas&id=' + id,
				beforeSend: function(objeto) {
					$('#loader_compras_aprobadas').html('<img src="../image/ajax-loader.gif"> Cargando detalles...');
				},
				success: function(data) {
					$(".outer_div_compras_aprobadas").html(data).fadeIn('slow');
					$('#loader_compras_aprobadas').html('');
				}
			});

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

		function eliminar_aprobacion(id) {
			if (confirm("Realmente desea eliminar el registro?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/aprobar_adquisiciones.php?action=eliminar_aprobacion",
					data: "id=" + id,
					beforeSend: function(objeto) {
						$("#resultados").html("Mensaje: Cargando...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						load(1);
					}
				});
			}
		}
	</script>