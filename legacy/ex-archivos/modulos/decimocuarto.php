<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$con = conenta_login();
	require_once dirname(__DIR__) . '/paginas/verificar_permiso_modulo.php';
?>
	<!DOCTYPE html>
	<html lang="es">

	<head>
		<title>Décimo Cuarto</title>
		<?php include("../paginas/menu_de_empresas.php"); ?>
	</head>

	<body>
		<div class="container">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<?php
						if (getPermisos($con, $id_usuario, $ruc_empresa, 'decimocuarto')['w'] == 1) {
						?>
							<button class="btn btn-info" data-toggle="modal" data-target="#modalDecimoCuarto" onclick="nuevo_decimo_cuarto();"><span class="glyphicon glyphicon-plus"></span> Nuevo</button>
						<?php
						}
						?>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Décimo cuarto sueldo</h4>
				</div>
				<div class="panel-body">
					<?php
					include("../modal/decimocuarto.php");
					include("../modal/enviar_documentos_mail.php");
					?>
					<form class="form-horizontal" method="POST">
						<div class="form-group row">
							<label for="q" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-6">
								<input type="hidden" id="ordenado" value="dc.anio">
								<input type="hidden" id="por" value="asc">
								<div class="input-group">
									<input type="text" class="form-control" id="q" placeholder="Año" onkeyup='load(1);'>
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

		function nuevo_decimo_cuarto() {
			document.querySelector("#titleModalDecimoCuarto").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nueva Décimo Cuarto";
			document.querySelector("#formDecimoCuarto").reset();
			document.querySelector("#idDecimoCuarto").value = "";
			document.querySelector("#btnActionFormDecimoCuarto").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextDecimoCuarto").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormDecimoCuarto').title = "Guardar";
		}

		function load(page) {
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			var dc = $("#dc").val();
			var id_dc = $("#idDcPrint").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/decimocuarto.php?action=buscar_decimocuarto&page=' + page + '&q=' + q + '&ordenado=' + ordenado + '&por=' + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Buscando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			})

			//para cargar los detalles 
			$("#loader_decimocuarto").fadeIn('slow');
			$.ajax({
				url: '../ajax/decimocuarto.php?action=detalle_decimocuarto&page=' + page + '&dc=' + dc + '&id_dc=' + id_dc,
				beforeSend: function(objeto) {
					$('#loader_decimocuarto').html('Cargando...');
				},
				success: function(data) {
					$(".outer_div_decimocuarto").html(data).fadeIn('slow');
					$('#loader_decimocuarto').html('');
					// event.preventDefault();
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

		function eliminar_decimocuarto(id) {
			var q = $("#q").val();
			if (confirm("Realmente desea eliminar el registro?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/decimocuarto.php?action=eliminar_decimocuarto",
					data: "id_dc=" + id,
					"q": q,
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

		function detalle_decimocuarto(id, anio, region) {
			actualizar_decimocuarto(id, anio, region);
			document.querySelector("#formDetDecimoCuarto").reset();
			$("#idDcPrint").val(id);
			$("#periodo_decimocuarto").val(anio);
			$("#region_generada").val(region);
			var dc = "";
			var page = "1";
			$("#loader_decimocuarto").fadeIn('slow');
			$.ajax({
				url: '../ajax/decimocuarto.php?action=detalle_decimocuarto&page=' + page + '&dc=' + dc + '&id_dc=' + id,
				beforeSend: function(objeto) {

					$('#loader_decimocuarto').html('Cargando...');
				},
				success: function(data) {
					$(".outer_div_decimocuarto").html(data).fadeIn('slow');
					$('#loader_decimocuarto').html('');
					event.preventDefault();
				}
			});

		}

		function actualizar_decimocuarto(id, anio, region) {
			$.ajax({
				url: '../ajax/decimocuarto.php?action=actualizar_decimocuarto&id_dc=' + id + '&anio=' + anio + '&region=' + region,
				beforeSend: function(objeto) {
					$('#loader_decimocuarto').html('Actualizando...');
				},
				success: function(data) {
					$(".outer_div_decimocuarto").html(data).fadeIn('slow');
					$('#loader_decimocuarto').html('');
					load(1);
					event.preventDefault();
				}
			});
		}

		function enviar_decimocuarto_mail(id_dc, correo) {
			$("#id_documento").val(id_dc);
			$("#mail_receptor").val(correo);
			$("#tipo_documento").val("decimocuarto");
		};
	</script>