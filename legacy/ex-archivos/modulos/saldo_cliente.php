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
		<title>Saldo clientes</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		//include("../modal/detalle_ingreso_egreso.php");
		$con = conenta_login(); ?>
	</head>

	<body>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<span id="loader"></span>
					</div>
					<h4><i class="glyphicon glyphicon-list-alt"></i> Saldos de clientes</h4>
				</div>

				<div class="panel-body">
					<form class="form-horizontal" method="POST" target="_blank" action="../pdf/pdf_resumen_diario.php">
						<input type="hidden" name="id_cliente" id="id_cliente">
						<div class="form-group">
							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Desde</b></span>
									<input type="text" class="form-control input-sm text-center" name="fecha_desde" id="fecha_desde" value="<?php echo date("01-01-Y"); ?>">
								</div>
							</div>
							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Hasta</b></span>
									<input type="text" class="form-control input-sm text-center" name="fecha_hasta" id="fecha_hasta" value="<?php echo date("d-m-Y"); ?>">
								</div>
							</div>
							<div class="col-md-5">
								<div class="input-group">
									<span class="input-group-addon"><b>Cliente</b></span>
									<input type="text" class="form-control input-sm" name="nombre_cliente" id="nombre_cliente" onkeyup='buscar_cliente();' placeholder="Todos" autocomplete="off">
								</div>
							</div>
							<div class="col-sm-2">
								<div class="input-group">
									<button type="button" title="Mostrar resultado" class="btn btn-info btn-sm" onclick="mostrar_reporte()"><span class="glyphicon glyphicon-search"></span></button>&nbsp
									<!-- <button type="submit" title='Imprimir pdf' class='btn btn-default btn-sm' title='Pdf'>Pdf</button> -->
								</div>
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

	</body>
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<!-- <script type="text/javascript" src="../js/style_bootstrap.js"> </script>
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script> -->

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


		//generar informe
		function mostrar_reporte() {
			var fecha_desde = $("#fecha_desde").val();
			var fecha_hasta = $("#fecha_hasta").val();
			var id_cliente = $("#id_cliente").val();

			if (id_cliente === "") {
				alert("Por favor seleccione un cliente.");
				$("#nombre_cliente").focus();
				return; // detiene la ejecución
			}

			$.ajax({
				type: "POST",
				url: "../ajax/saldo_cliente.php",
				data: "action=saldo_cliente&fecha_hasta=" + fecha_hasta + "&fecha_desde=" + fecha_desde + "&id_cliente=" + id_cliente,
				beforeSend: function(objeto) {
					$('#loader').html('Cargando...');
				},
				success: function(datos) {
					$(".outer_div").html(datos);
					$("#loader").html('');
				}
			});
		}
	</script>