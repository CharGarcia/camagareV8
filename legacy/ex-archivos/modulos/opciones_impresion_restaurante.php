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
		<title>Opciones</title>
		<?php
		include("../paginas/menu_de_empresas.php");
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
						<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#opciones_impresiones" onclick="carga_modal_impresion();"><span class="glyphicon glyphicon-plus"></span> Nueva</button>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Opciones de impresión</h4>
				</div>
				<div class="panel-body">
					<?php
					include("../modal/opciones_impresion_restaurante.php");
					?>
					<form class="form-horizontal" method="POST">
						<div class="form-group row">
							<label for="q" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
								<input type="hidden" id="ordenado" value="opc.id">
								<input type="hidden" id="por" value="desc">
								<div class="input-group">
									<input type="text" class="form-control" id="q" placeholder="Categoría" onkeyup='load(1);'>
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
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<!--para que se vea con fondo blanco el autocomplete -->
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/ordenado.js" type="text/javascript"></script>
	<script src="../js/notify.js"></script>
	</body>

	</html>
	<script>
		$(document).ready(function() {
			load(1);
		});

		function load(page) {
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/opciones_impresion_restaurante.php?action=buscar_opciones_impresion_restaurante&page=' + page + '&q=' + q + "&ordenado=" + ordenado + "&por=" + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			})
		}


		function carga_modal_impresion() {
			document.querySelector("#titleModalImpresion").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nueva opción de impresión";
			document.querySelector("#guardar_opciones_mpresiones").reset();
			document.querySelector("#idRegistro").value = "";
			document.querySelector("#btnActionFormopciones_impresion").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextopciones_impresion").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormopciones_impresion').title = "Guardar";
		
		}

		//para guardar
		$("#guardar_opciones_mpresiones").submit(function(event) {
			$('#btnActionFormopciones_impresion').attr("disabled", true);
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: "../ajax/opciones_impresion_restaurante.php?action=guardar_opciones_impresion_restaurante",
				data: parametros,
				beforeSend: function(objeto) {
					$("#loader_opciones_impresion").html("Guardando...");
				},
				success: function(datos) {
					$("#loader_opciones_impresion").html(datos);
					$("#loader_opciones_impresion").html('');
					$('#btnActionFormopciones_impresion').attr("disabled", false);
					load(1);
				}
			});
			event.preventDefault();
		});

		//eliminar 
		function eliminar_opciones_impresion(id) {
			if (confirm("Realmente desea anular el registro?")) {
				$.ajax({
					type: "GET",
					url: "../ajax/opciones_impresion_restaurante.php?action=eliminar_opciones_impresion_restaurante&id="+id,
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

		function editar_opciones_impresion_restaurante(id) {
			document.querySelector('#titleModalImpresion').innerHTML ="<i class='glyphicon glyphicon-edit'></i> Actualizar opciones de impresión";
			document.querySelector("#guardar_opciones_mpresiones").reset();
			document.querySelector("#idRegistro").value = id;
			document.querySelector('#btnActionFormopciones_impresion').classList.replace("btn-primary", "btn-info");
			document.querySelector("#btnTextopciones_impresion").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Actualizar";
			var id_categoria = $("#categoria_mod" + id).val();
			var id_opcion = $("#opcion_mod" + id).val();
			$("#idRegistro").val(id);
			$("#listCategoria").val(id_categoria);
			$("#listOpcion").val(id_opcion);
		}

	</script>