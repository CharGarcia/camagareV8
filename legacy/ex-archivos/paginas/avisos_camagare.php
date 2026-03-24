<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">

<head>
	<title>Avisos</title>
	<?php
	session_start();
	include("../head.php");
	?>
</head>

<body>
	<meta charset="utf-8">
	<?php
	include("../conexiones/conectalogin.php");
	if ($_SESSION['nivel'] >= 3) {
		$titulo_info = "Avisos CaMaGaRe";
		$conexion = conenta_login();
	?>
		<?php
		include("../modal/nuevo_aviso_camagare.php");
		include("../navbar_confi.php");
		?>
		<div class="container-fluid">
			<div class="col-md-10 col-md-offset-1">
				<div class="panel panel-info">
					<div class="panel-heading">
						<div class="btn-group pull-right">
							<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#nuevoAviso"><span class="glyphicon glyphicon-plus"></span> Nueva Aviso</button>
						</div>
						<h4><i class='glyphicon glyphicon-search'></i> Listado Avisos CaMaGaRe</h4>
					</div>
					<div class="panel-body">
						<form class="form-horizontal">
							<div class="form-group row">
								<label for="q" class="col-md-2 control-label">Buscar:</label>
								<div class="col-md-6">
									<input type="text" class="form-control" id="q" placeholder="Detalle" onkeyup='load(1);'>
								</div>

								<div class="col-md-3">
									<button type="button" class="btn btn-default" onclick='load(1);'>
										<span class="glyphicon glyphicon-search"></span> Buscar</button>
									<span id="loader"></span>
								</div>
							</div>
						</form>
						<div id="resultados"></div><!-- Carga los datos ajax -->
						<div class='outer_div'></div><!-- Carga los datos ajax -->
					</div>
				</div>
			</div>
		</div>

	<?php
	} else {
		header('Location: ../includes/logout.php');
		exit;
	}
	?>
	<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
	<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
</body>

</html>
<script>
	$(document).ready(function() {
		load(1);
	});

	function load(page) {
		var q = $("#q").val();
		$("#loader").fadeIn('slow');
		$.ajax({
			url: '../ajax/buscar_avisos_camagare.php?action=ajax&page=' + page + '&q=' + q,
			beforeSend: function(objeto) {
				$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
			},
			success: function(data) {
				$(".outer_div").html(data).fadeIn('slow');
				$('#loader').html('');
			}
		});

	};

	$(function() {
		$("#guardar_aviso").submit(function(event) {
			$('#guardar_datos').attr("disabled", true);
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: '../ajax/guardar_aviso_camagare.php',
				data: parametros,
				beforeSend: function(objeto) {
					$("#resultados_ajax_avisos").html("Mensaje: Guardando...");
				},
				success: function(datos) {
					$("#resultados_ajax_avisos").html(datos);
					$('#guardar_datos').attr("disabled", false);
					load(1);
				}
			});
			event.preventDefault();
		});
	});

	function eliminar_aviso(id_aviso) {
		var frase = $("#id_aviso" + id_aviso).val();
		if (confirm("Realmente desea eliminar el aviso?")) {
			$.ajax({
				type: "POST",
				url: "../ajax/buscar_avisos_camagare.php",
				data: "id_aviso=" + id_aviso,
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

	function buscar_empresa() {
		$("#nombre_empresa").autocomplete({
			appendTo: "#nuevoAviso", //para indicar el modal que se muestre arriba de todo
			source: '../ajax/empresas_autocompletar.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#ruc_empresa').val(ui.item.ruc);
				$('#nombre_empresa').val(ui.item.nombre);
				document.getElementById('detalle_aviso').focus();
			}
		});

		$("#nombre_empresa").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#ruc_empresa").val("");
				$("#nombre_empresa").val("");
			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#nombre_empresa").val("");
				$("#ruc_empresa").val("");
			}
		});
	}
</script>