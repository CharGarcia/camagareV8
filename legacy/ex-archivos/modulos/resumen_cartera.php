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
		<title>Cartera</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		date_default_timezone_set('America/Guayaquil');
		?>
	</head>

	<body>
		<?php
		include("../modal/detalle_cobranza.php");
		?>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<h4><i class='glyphicon glyphicon-list-alt'></i> Reporte de cartera</h4>
				</div>
				<div class="panel-body">
					<form class="form-horizontal" method="POST" action="" target="_blank" name="cartera">
						<input type="hidden" name="id_cliente" id="id_cliente">
						<div class="form-group">
							<div class="col-md-5">
								<div class="input-group">
									<span class="input-group-addon"><b>Cliente</b></span>
									<input type="text" class="form-control input-sm" name="nombre_cliente" id="nombre_cliente" onkeyup='buscar_cliente();' placeholder="Todos" autocomplete="off">
								</div>
							</div>

							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Desde</b></span>
									<input type="text" class="form-control input-sm text-center" name="fecha_desde" id="fecha_desde" value="<?php echo date("01-01-Y"); ?>">
								</div>
							</div>

							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Hasta</b></span>
									<input type="text" class="form-control input-sm text-center" name="fecha_hasta" id="fecha_hasta" value="<?php echo date("d-m-Y"); ?>">
								</div>
							</div>

							<div class="col-md-2">
								<button type="button" title="Mostrar resultado" class="btn btn-info btn-sm" onclick="mostrar_informe()"><span class="glyphicon glyphicon-search"></span></button>
								<button type="button" onclick="document.cartera.action = '../pdf/resumen_cartera.php'; document.cartera.submit()" class="btn btn-default btn-sm" title="Pdf" target="_blank">Pdf</button>
								<button type="button" onclick="document.cartera.action = '../excel/resumen_cartera.php?action=generar_informe_excel'; document.cartera.submit()" class='btn btn-success btn-sm' title="Descargar excel" target="_blank"><img src="../image/excel.ico" width="20" height="16"></button>
							</div>
							<span id="loader"></span>
						</div>

					</form>
					<div id="resultados"></div><!-- Carga los datos ajax href="../excel/reporte_ventas_excel.php"-->
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
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>

	</html>
	<script>
		jQuery(function($) {
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

		//generar informe
		function mostrar_informe() {
			var id_cliente = $("#id_cliente").val();
			var desde = $("#fecha_desde").val();
			var hasta = $("#fecha_hasta").val();

			$.ajax({
				type: "POST",
				url: "../ajax/resumen_cartera.php",
				data: "action=generar_informe&id_cliente=" + id_cliente + "&desde=" + desde + "&hasta=" + hasta,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif">Generando...');
				},
				success: function(datos) {
					$(".outer_div").html(datos);
					$("#loader").html('');
				}
			});
		}



		function buscar_cliente() {
			$("#nombre_cliente").autocomplete({
				source: '../ajax/clientes_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_cliente').val(ui.item.id);
					$('#nombre_cliente').val(ui.item.nombre);
				}
			});

			//$("#nombre_cliente").autocomplete("widget").addClass("fixedHeight"); //para que aparezca la barra de desplazamiento en el buscar
			$("#nombre_cliente").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_cliente").val("");
					$("#nombre_cliente").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_cliente").val("");
					$("#nombre_cliente").val("");
				}
			});
		}

		function detalle_cobranza(id) {
			var codigo_unico = id;

			$("#loaderdet").fadeIn('slow');
			$.ajax({
				url: '../ajax/resumen_cartera.php?action=resumen_cartera&id_documento=' + codigo_unico,
				beforeSend: function(objeto) {
					$('#loaderdet').html('<img src="../image/ajax-loader.gif"> Cargando detalle...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('slow');
					$('#loaderdet').html('');
				}
			})
		}
	</script>