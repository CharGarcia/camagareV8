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
		<title>Departamentos</title>
		<?php include("../paginas/menu_de_empresas.php"); ?>
	</head>

	<body>
		<div class="container">
			<div class="col-md-8 col-md-offset-2">
				<div class="panel panel-info">
					<div class="panel-heading">
						<div class="btn-group pull-right">
						<?php
							$con = conenta_login();
							if(getPermisos($con, $id_usuario, $ruc_empresa, 'departamentos')['w']==1){
							?>
							<button class="btn btn-info" data-toggle="modal" data-target="#modalDepartamentos" onclick="openModal();"><span class="glyphicon glyphicon-plus"></span> Nuevo</button>
							<?php
							}
							?>
						</div>
						<h4><i class='glyphicon glyphicon-search'></i> Departamentos</h4>
					</div>
					<div class="panel-body">
						<?php
						include("../modal/departamentos.php");
						?>
						<form class="form-horizontal" method="POST">
							<div class="form-group row">
								<label for="q" class="col-md-1 control-label">Buscar:</label>
								<div class="col-md-8">
									<input type="hidden" id="ordenado" value="nombre">
									<input type="hidden" id="por" value="asc">
									<div class="input-group">
										<input type="text" class="form-control" id="q" placeholder="Nombre" onkeyup='load(1);'>
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
		</div>
	<?php

} else {
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
	exit;
}
	?>
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

		function openModal() {
			document.querySelector("#titleModalDepartamento").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo Departamento";
			document.querySelector("#formDepartamentos").reset();
			document.querySelector("#idDepartamento").value = "";
			document.querySelector("#btnActionFormDepartamento").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextDepartamento").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormDepartamento').title = "Guardar nuevo departamento";
		}

		function load(page) {
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/departamentos.php?action=buscar_departamentos&page=' + page + '&q=' + q + "&ordenado=" + ordenado + "&por=" + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Buscando...');
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

		function editar_departamento(id) {
			document.querySelector('#titleModalDepartamento').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar Departamento";
			document.querySelector('#btnActionFormDepartamento').classList.replace("btn-primary", "btn-info");
			document.querySelector('#btnActionFormDepartamento').title = "Actualizar Departamento";
			document.querySelector('#btnTextDepartamento').innerHTML = "Actualizar";
			document.querySelector("#formDepartamentos").reset();
			var nombre = $("#nombre_dep_mod" + id).val();
			var status = $("#status_departamento_mod" + id).val();

			$("#txtNombreDepartamento").val(nombre);
			$("#listStatusDepartamento").val(status);
			$("#idDepartamento").val(id);
		}

		function eliminar_departamento(id) {
			var q = $("#q").val();
			if (confirm("Realmente desea eliminar el departamento?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/departamentos.php?action=eliminar_departamento",
					data: "id_departamento=" + id,
					"q": q,
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
	</script>