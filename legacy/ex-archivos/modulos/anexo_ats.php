<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

	$files = glob('../xml/*.xml'); //obtenemos todos los nombres de los ficheros
	foreach ($files as $file) {
		if (is_file($file))
			unlink($file); //elimino el fichero
	}
?>
	<!DOCTYPE html>
	<html lang="es">

	<head>
		<title>Anexo ATS</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		//include ("../clases/empresas.php");
		?>
		<!-- <script src="../js/jquery.maskedinput.js" type="text/javascript"></script>  -->
	</head>

	<body>
		<div class="container">
			<div class="panel panel-info">
				<div class="panel-heading">
					<h4><i class='glyphicon glyphicon-list-alt'></i> Anexo ATS</h4>
				</div>
				<div class="panel-body">
					<form class="form-horizontal" method="POST">
						<div class="form-group row">
							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Mes</b></span>
									<select class="form-control" name="mes" id="mes">
									</select>
								</div>
							</div>

							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Año</b></span>
									<select class="form-control" name="anio_periodo" id="anio_periodo">
										<option value="<?php echo date("Y") ?>" selected> <?php echo date("Y") ?></option>
										<?php for ($i = $anio2 = date("Y") - 1; $i > $anio1 = date("Y") - 5; $i += -1) {
										?>
											<option value="<?php echo $i ?>"> <?php echo $i ?></option>
										<?php }  ?>
									</select>
								</div>
							</div>
							<div class="col-sm-6">
								<div class="input-group">
									<span class="input-group-addon" id="titulo_micro"><b>Desea declarar el anexo de forma semestral?</b></span>
									<select class="form-control" name="microempresa" id="microempresa">
										<option value="1"> SI</option>
										<option value="2" selected> NO</option>
									</select>
								</div>
							</div>
							<div class="col-sm-2">
								<div class="input-group">
									<button type="button" class="btn btn-info" onclick='generar_ats();'><span class="glyphicon glyphicon-refresh"></span> Generar</button>
									<span id="loader"></span>
								</div>
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
	<script type="text/javascript" src="../js/style_bootstrap.js"> </script>
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	</body>

	</html>

	<script>
		$(document).ready(function() {
			document.getElementById("titulo_micro").style.display = "none";
			document.getElementById("microempresa").style.display = "none";
			$.post('../ajax/select_tipo_declaracion.php', {
				declaracion: 'iva',
				tipo_periodo: 'mensual'
			}).done(function(res_tipo_periodo) {
				$("#mes").html(res_tipo_periodo);
			});

		});

		$(function() {

			$('#mes').change(function() {
				var mes = $("#mes").val();
				if (mes == "06" || mes == "12") {
					document.getElementById("titulo_micro").style.display = "";
					document.getElementById("microempresa").style.display = "";
				} else {
					document.getElementById("titulo_micro").style.display = "none";
					document.getElementById("microempresa").style.display = "none";
				}
			});

		});


		function generar_ats() {
			var mes = $("#mes").val();
			var anio_periodo = $("#anio_periodo").val();
			var microempresa = $("#microempresa").val();

			$("#resultados").fadeIn('slow');
			$.ajax({
				type: "POST",
				url: '../ajax/anexo_ats.php',
				data: 'action=anexo_ats&mes=' + mes + '&anio_periodo=' + anio_periodo + '&microempresa=' + microempresa,
				beforeSend: function(objeto) {
					$('#resultados').html('<div class="progress"><div class="progress-bar progress-bar-info progress-bar-striped active" role="progressbar" style="width:100%;">Generando Anexo ATS, espere por favor...</div></div>');
				},
				success: function(datos) {
					$("#resultados").html(datos);
				}
			});
		}
	</script>