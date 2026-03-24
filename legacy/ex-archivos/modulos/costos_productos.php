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
		<title>Costos Productos/servicios</title>
		<?php include("../paginas/menu_de_empresas.php"); ?>
	</head>

	<body>

		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<h4><i class="glyphicon glyphicon-search"></i> Costos de Productos y Servicios</h4>
				</div>
				<div class="panel-body">
					<?php
					?>
					<form class="form-horizontal" method="POST" action="">
						<div class="form-group row">
							<label for="q" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
								<input type="hidden" id="ordenado" value="id">
								<input type="hidden" id="por" value="desc">
								<div class="input-group">
									<input type="text" class="form-control" id="q" placeholder="Nombre" onkeyup='load(1);'>
									<span class="input-group-btn">
										<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
									</span>
								</div>
							</div>
							<div class="col-md-2">
								<button type="button" class="btn btn-info" onclick="actualiza_todos_los_costos()" title="Cargar costos promedios obtenidos desde inventarios">Cargar costos promedio</button>
							</div>
							<div class="col-md-1">
							<a href="../excel/costos_productos.php" class="btn btn-success" title='Descargar a Excel' target="_blank"><img src="../image/excel.ico" width="25" height="20"></a>												
							</div>
							<div id="loader"></div>
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
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
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
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			var q = $("#q").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/costos_productos.php?action=buscar_costos_productos&page=' + page + '&q=' + q + "&ordenado=" + ordenado + "&por=" + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');

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



		//actualizar costo indivudual
		function actualiza_costo_producto(id) {
			var costo_producto = document.getElementById('costo_producto' + id).value;
			var costo_original = document.getElementById('costo_original' + id).value;
			var pagina = document.getElementById('pagina').value;
			//var id_producto_costo = document.getElementById('id_producto_costo'+ id).value;

			if (isNaN(costo_producto)) {
				alert('El valor ingresado, no es un número');
				$("#costo_original" + id).val(costo_original);
				document.getElementById('costo_producto' + id).focus();
				return false;
			}

			if ((costo_producto < 0)) {
				alert('El valor ingresado debe ser mayor a cero');
				$("#costo_original" + id).val(costo_original);
				document.getElementById('costo_producto' + id).focus();
				return false;
			}

			$.ajax({
				type: "POST",
				url: "../ajax/costos_productos.php?action=actualiza_costo_directo",
				data: "id_producto=" + id + "&costo_producto=" + costo_producto,
				beforeSend: function(objeto) {
					$("#loader").html("Actualizando...");
				},
				success: function(datos) {
					$("#loader").html(datos);
					load(pagina);
				}
			});
		}

		function actualiza_todos_los_costos() {
			if (confirm("Al cargar los costos promedio obtenidos desde inventarios se eliminaran los costos actuales, desea continuar?")){
				$.ajax({
					type: "POST",
					url: "../ajax/costos_productos.php?action=actualiza_todos_los_costos",
					data: "",
					beforeSend: function(objeto) {
						$("#loader").html("Actualizando...");
					},
					success: function(datos) {
						$("#loader").html(datos);
						load(1);
					}
				});
			}
		}
	</script>