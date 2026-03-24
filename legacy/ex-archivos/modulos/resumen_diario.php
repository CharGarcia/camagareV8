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
		<title>Resumen diario</title>
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
					<h4><i class="glyphicon glyphicon-list-alt"></i> Resumen diario</h4>
				</div>

				<div class="panel-body">
					<form class="form-horizontal" method="POST" target="_blank" action="../pdf/pdf_resumen_diario.php">
						<input type="hidden" name="id_cliente_proveedor" id="id_cliente_proveedor">
						<div class="form-group">
							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Fecha</b></span>
									<input type="text" class="form-control input-sm text-center" name="fecha" id="fecha" value="<?php echo date("d-m-Y"); ?>">
								</div>
							</div>
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon"><b>Usuario</b></span>
									<select class="form-control input-sm" name="usuario" id="usuario">
										<option value="" selected>Todos</option>
										<?php
										$con = conenta_login();
										$usuarios = mysqli_query($conexion, "SELECT emp_asi.id_usuario as id_usuario, usu.nombre as usuario FROM empresa_asignada as emp_asi 
										INNER JOIN empresas as emp ON emp.id=emp_asi.id_empresa INNER JOIN usuarios as usu ON usu.id=emp_asi.id_usuario WHERE emp.ruc ='" . $ruc_empresa . "'order by usu.nombre asc ");
										while ($row_usuarios = mysqli_fetch_assoc($usuarios)) {
										?>
											<option value="<?php echo $row_usuarios['id_usuario'] ?>"><?php echo $row_usuarios['usuario'] ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-sm-2">
								<div class="input-group">
									<button type="button" title="Mostrar resultado" class="btn btn-info btn-sm" onclick="mostrar_reporte()"><span class="glyphicon glyphicon-search"></span></button>&nbsp
									<button type="submit" title='Imprimir pdf' class='btn btn-default btn-sm' title='Pdf'>Pdf</button>
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
			$("#fecha").mask("99-99-9999");
		});

		$(function() {
			$("#fecha").datepicker({
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
		function mostrar_reporte() {
			var fecha = $("#fecha").val();
			var usuario = $("#usuario").val();

			$.ajax({
				type: "POST",
				url: "../ajax/resumen_diario.php",
				data: "action=resumen_diario&fecha=" + fecha + "&usuario=" + usuario,
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