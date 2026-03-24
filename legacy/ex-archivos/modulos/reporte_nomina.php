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
		<title>Reporte nómina</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		?>
	</head>

	<body>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<h4><i class='glyphicon glyphicon-list-alt'></i> Reporte de nómina</h4>
				</div>
				<div class="panel-body">
					<form class="form-horizontal" role="form" method="POST" target="_blank" action="../excel/reporte_nomina_excel.php">
						<div class="form-group row">
							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Tipo</b></span>
									<input type="hidden" id="ordenado" name="ordenado" value="nombres_apellidos">
									<input type="hidden" id="id_empleado" name="id_empleado">
									<input type="hidden" id="por" name="por" value="asc">
									<select class="form-control input-sm" id="tipo_reporte" name="tipo_reporte" required>
										<option value="R" selected> Rol mensual</option>
										<option value="Q"> Quincena</option>
										<option value="9"> Préstamos empresa</option>
										<option value="7"> Préstamos quirografarios</option>
										<option value="8"> Préstamos hipotecarios</option>
										<option value="4"> Horas Nocturnas</option>
										<option value="5"> Horas Suplementarias</option>
										<option value="6"> Horas Extraordinarias</option>
										<option value="1"> Otros ingresos</option>
										<option value="2"> Descuentos</option>
										<option value="3"> Anticípos</option>
										<option value="14"> Avisos de salida</option>
										<option value="10"> Días no laborados</option>
										<option value="T"> Todas las novedades</option>
									</select>
								</div>
							</div>
							<div class="col-sm-2">
								<div class="input-group" id="label_periodo">
									<span class="input-group-addon"><b>Período</b></span>
									<input type="text" class="form-control input-sm text-center" name="periodo" id="periodo" title="formato: mm-aaaa" value="<?php echo date('m-Y'); ?>">
								</div>
							</div>
							<div class="col-sm-2" id="label_desde">
								<div class="input-group">
									<span class="input-group-addon"><b>Desde</b></span>
									<input type="date" class="form-control input-sm text-center" name="desde" id="desde" title="formato: dd-mm-aaaa">
								</div>
							</div>
							<div class="col-sm-2" id="label_hasta">
								<div class="input-group">
									<span class="input-group-addon"><b>Hasta</b></span>
									<input type="date" class="form-control input-sm text-center" name="hasta" id="hasta" title="formato: dd-mm-aaaa">
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-6">
								<div class="input-group">
									<span class="input-group-addon"><b>Empleado</b></span>
									<input type="text" class="form-control input-sm" name="empleado" id="empleado" onkeyup='agregar_empleado();' placeholder="Todos" autocomplete="off">
								</div>
							</div>
							<div class="col-sm-6">
								<button type="button" class="btn btn-info btn-sm" onclick='mostrar_reporte();'><span class="glyphicon glyphicon-search"></span> Mostrar</button>
								<button type="submit" class="btn btn-success btn-sm"><img src="../image/excel.ico" width="20" height="18">
								</button><span id="loader"></span>
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
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	</body>

	</html>
	<script>
		$(document).ready(function() {
			document.getElementById("label_desde").style.display = "none";
			document.getElementById("label_hasta").style.display = "none";
			window.addEventListener("keypress", function(event) {
				if (event.keyCode == 13) {
					event.preventDefault();
				}
			}, false);
		});

		jQuery(function($) {
			$("#periodo").mask("99-9999");
		});
		//para buscar productos
		function agregar_empleado() {
			$("#empleado").autocomplete({
				source: '../ajax/empleado_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#empleado').val(ui.item.nombres_apellidos);
					$('#id_empleado').val(ui.item.id);
				}
			});
		}

		$("#empleado").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#empleado").val("");
				$("#id_empleado").val("");
			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#empleado").val("");
				$("#id_empleado").val("");
			}
		});

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
			//load(1);
		}

		function mostrar_reporte() {
			var tipo_reporte = $("#tipo_reporte").val();
			var id_empleado = $("#id_empleado").val();
			var periodo = $("#periodo").val();
			var desde = $("#desde").val();
			var hasta = $("#hasta").val();

			$.ajax({
				type: "POST",
				url: "../ajax/reporte_nomina.php",
				data: "action=" + tipo_reporte + "&id_empleado=" + id_empleado + "&periodo=" + periodo + "&desde=" + desde + "&hasta=" + hasta,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif">');
				},
				success: function(datos) {
					$(".outer_div").html(datos);
					$("#loader").html('');
				}
			});
		}

		$('#tipo_reporte').change(function() {
			var tipo = $("#tipo_reporte").val();
			if (tipo > 0  || tipo == 'T') {
				document.getElementById("label_desde").style.display = "";
				document.getElementById("label_hasta").style.display = "";
				document.getElementById("label_periodo").style.display = "none";
			} else {
				document.getElementById("label_desde").style.display = "none";
				document.getElementById("label_hasta").style.display = "none";
				document.getElementById("label_periodo").style.display = "";
			}
		});
	</script>