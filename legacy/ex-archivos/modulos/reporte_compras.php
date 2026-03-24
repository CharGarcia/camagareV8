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
		<title>Reporte adquisiciones</title>
		<?php include("../paginas/menu_de_empresas.php"); ?>
	</head>

	<body>

		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<h4><i class='glyphicon glyphicon-list-alt'></i> Reporte de adquisiciones de compras y servicios</h4>
				</div>

				<div class="panel-body">
					<form class="form-horizontal" method="POST" target="_blank" action="../excel/reporte_compras_excel.php" name="compras">
						<input type="hidden" name="id_proveedor" id="id_proveedor">
						<div class="form-group">
							<div class="col-sm-10">
								<div class="input-group">
									<span class="input-group-addon"><b>Reporte</b></span>
									<select class="form-control input-sm" id="tipo_reporte" name="tipo_reporte" required>
										<option value="1" selected> Adquisiciones</option>
										<option value="3"> Detalle de adquisiciones</option>
										<option value="2"> Notas de crédito</option>
										<option value="4"> Detalle NC</option>
									</select>

									<span class="input-group-addon"><b>Desde</b></span>
									<input type="text" class="form-control input-sm text-center" name="desde" id="desde" value="<?php echo date("01-m-Y"); ?>">

									<span class="input-group-addon"><b>Hasta</b></span>
									<input type="text" class="form-control input-sm text-center" name="hasta" id="hasta" value="<?php echo date("d-m-Y"); ?>">

									<span class="input-group-addon"><b>Proveedor</b></span>
									<input type="text" class="form-control input-sm text-left" name="nombre_proveedor" id="nombre_proveedor" value="Todos" onkeyup='buscar_proveedor();'>
									</select>
								</div>
							</div>
							<div class="col-sm-2">
								<button type="button" title="Mostrar resultado" class="btn btn-info btn-sm" onclick="mostrar_reporte()"><span class="glyphicon glyphicon-search"></span></button>
								<button type="button" onclick="document.compras.action = '../pdf/reporte_compras.php'; document.compras.submit()" class="btn btn-default btn-sm" title="Pdf" target="_blank">Pdf</button>
								<button type="submit" title="Descargar excel" class="btn btn-success btn-sm"><img src="../image/excel.ico" width="16" height="16"></button>
								<span id="loader"></span>
							</div>
						</div>
					</form>
					<div id="resultados"></div><!-- Carga los datos ajax -->
					<div class='outer_div'></div><!-- Carga los datos ajax -->
				</div>
			</div>
		</div>
	<?php

} else {
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
	exit;
}
	?>

	</body>
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/rango_fechas.js"></script>

	</html>
	<script>
		jQuery(function($) {
			$("#desde").mask("99-99-9999");
			$("#hasta").mask("99-99-9999");
		});
		$(function() {
			$("#desde").datepicker({
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
			$("#hasta").datepicker({
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

		function buscar_proveedor() {
			$("#nombre_proveedor").autocomplete({
				source: '../ajax/proveedores_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_proveedor').val(ui.item.id_proveedor);
					$('#nombre_proveedor').val(ui.item.razon_social);
				}
			});
			$("#nombre_proveedor").autocomplete("widget").addClass("fixedHeight"); //para que aparezca la barra de desplazamiento en el buscar

			$("#nombre_proveedor").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_proveedor").val("");
					$("#nombre_proveedor").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_proveedor").val("");
					$("#nombre_proveedor").val("");
				}
			});
		}

		//generar informe
		function mostrar_reporte() {
			var tipo_reporte = $("#tipo_reporte").val();
			var id_proveedor = $("#id_proveedor").val();
			var desde = $("#desde").val();
			var hasta = $("#hasta").val();

			var rango_fechas = rango_fecha(desde, hasta, 365);
			if (rango_fechas == true) {
				alert('La diferencia entre las fechas no puede ser mayor a 1 año.');
				return false;
			}

			$.ajax({
				type: "POST",
				url: "../ajax/reporte_compras.php",
				data: "action=" + tipo_reporte + "&id_proveedor=" + id_proveedor + "&desde=" + desde + "&hasta=" + hasta,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif">');
				},
				success: function(datos) {
					$(".outer_div").html(datos);
					$("#loader").html('');
				}
			});
		}
	</script>