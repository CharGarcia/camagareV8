<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">

<head>
	<title>Períodos contables</title>
	<?php include("../head.php"); ?>
</head>

<body>
	<?php
	session_start();
	if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
		$id_usuario = $_SESSION['id_usuario'];
		$id_empresa = $_SESSION['id_empresa'];
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$con = conenta_login();
		require_once dirname(__DIR__) . '/paginas/verificar_permiso_modulo.php';
		include("../paginas/menu_de_empresas.php");
	?>
		<?php include("../modal/periodo.php"); ?>

		<div class="container">
			<div class="col-md-8 col-md-offset-2">
				<div class="panel panel-info">
					<div class="panel-heading">
						<div class="btn-group pull-right">
							<?php
							if (getPermisos($con, $id_usuario, $ruc_empresa, 'periodos')['w'] == 1) {
							?>
								<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#nuevoPeriodo" onclick="carga_modal();"><span class="glyphicon glyphicon-plus"></span> Nuevo</button>
							<?php
							}
							?>
						</div>
						<h4><i class='glyphicon glyphicon-search'></i> Períodos contables</h4>
					</div>
					<div class="panel-body">
						<form class="form-horizontal">
							<div class="form-group row">
								<label for="q" class="col-md-2 control-label">Período:</label>
								<div class="col-md-6">
									<input type="text" class="form-control" id="q" placeholder="Mes, año" onkeyup='load(1);'>
								</div>

								<div class="col-md-4">
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

	<?php } else {
		header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
		exit;
	}
	?>

	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>

	<script>
		$(document).ready(function() {
			load(1);
		});


		function carga_modal() {
			document.querySelector("#titleModalPeriodo").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo período";
			document.querySelector("#guardar_periodo").reset();
			document.querySelector("#idPeriodo").value = "";
			document.querySelector("#btnActionFormPeriodo").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextPeriodo").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormPeriodo').title = "Guardar período";
		}

		function editar_periodo(id, mes, anio, status) {
			document.querySelector('#titleModalPeriodo').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar Périodo";
			document.querySelector("#guardar_periodo").reset();
			document.querySelector("#idPeriodo").value = id;
			document.querySelector('#btnActionFormPeriodo').classList.replace("btn-primary", "btn-info");
			document.querySelector("#btnTextPeriodo").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Actualizar";
			$("#mes_periodo").val(mes);
			$("#anio_periodo").val(anio);
			$("#listStatus").val(status);
		}


		function load(page) {
			var q = $("#q").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/periodos.php?action=buscar_periodos&page=' + page + '&q=' + q,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');

				}
			})
		}
	</script>
</body>

</html>