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
		<title>Empleados</title>
		<?php include("../paginas/menu_de_empresas.php"); ?>
	</head>

	<body>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<?php
						if (getPermisos($con, $id_usuario, $ruc_empresa, 'empleados')['w'] == 1) {
						?>
							<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#modalEmpleados" onclick="openModal();"><span class="glyphicon glyphicon-plus"></span> Nuevo</button>
						<?php
						}
						?>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Empleados</h4>
				</div>
				<div class="panel-body">
					<?php
					include("../modal/empleados.php");
					?>
					<form class="form-horizontal" method="POST">
						<div class="form-group row">
							<label for="q" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-6">
								<input type="hidden" id="ordenado" value="nombres_apellidos">
								<input type="hidden" id="por" value="asc">
								<div class="input-group">
									<input type="text" class="form-control" id="q" placeholder="Nombre" onkeyup='load(1);'>
									<span class="input-group-btn">
										<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
									</span>
								</div>
							</div>
							<div class="col-md-1">
								<a href="../excel/empleados.php" class="btn btn-success" title='Descargar en Excel' target="_blank"><img src="../image/excel.ico" width="25" height="20"></a>
							</div>
							<span id="loader"></span>
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
			document.querySelector("#titleModalEmpleado").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo Empleado";
			document.querySelector("#formEmpleados").reset();
			document.querySelector("#idEmpleado").value = "";
			document.querySelector("#btnActionFormEmpleado").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextEmpleado").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormEmpleado').title = "Guardar nuevo empleado";
		}

		function load(page) {
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/empleados.php?action=buscar_empleados&page=' + page + '&q=' + q + "&ordenado=" + ordenado + "&por=" + por,
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

		function editar_empleado(id) {
			document.querySelector('#titleModalEmpleado').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar Empleado";
			document.querySelector('#btnActionFormEmpleado').classList.replace("btn-primary", "btn-info");
			document.querySelector('#btnActionFormEmpleado').title = "Actualizar Empleado";
			document.querySelector('#btnTextEmpleado').innerHTML = "Actualizar";
			document.querySelector("#formEmpleados").reset();
			var tipo_documento_emp_mod = $("#tipo_documento_emp_mod" + id).val();
			var documento_emp_mod = $("#documento_emp_mod" + id).val();
			var nombre_emp_mod = $("#nombre_emp_mod" + id).val();
			var direccion_emp_mod = $("#direccion_emp_mod" + id).val();
			var email_emp_mod = $("#email_emp_mod" + id).val();
			var telefono_emp_mod = $("#telefono_emp_mod" + id).val();
			var sexo_emp_mod = $("#sexo_emp_mod" + id).val();
			var nacimiento_emp_mod = $("#nacimiento_emp_mod" + id).val();
			var status_emp_mod = $("#status_emp_mod" + id).val();
			var id_banco_emp_mod = $("#id_banco_emp_mod" + id).val();
			var tipo_cta_emp_mod = $("#tipo_cta_emp_mod" + id).val();
			var numero_cta_emp_mod = $("#numero_cta_emp_mod" + id).val();

			$("#listTipoId").val(tipo_documento_emp_mod);
			$("#txtDocumento").val(documento_emp_mod);
			$("#txtNombres").val(nombre_emp_mod);
			$("#txtDireccion").val(direccion_emp_mod);
			$("#txtEmail").val(email_emp_mod);
			$("#txtTelefono").val(telefono_emp_mod);
			$("#listSexo").val(sexo_emp_mod);
			$("#txtFechaNacimiento").val(nacimiento_emp_mod);
			$("#listStatus").val(status_emp_mod);
			$("#listBanco").val(id_banco_emp_mod);
			$("#listTipoCta").val(tipo_cta_emp_mod);
			$("#txtNumeroCuenta").val(numero_cta_emp_mod);
			$("#idEmpleado").val(id);
		}

		function eliminar_empleado(id) {
			var q = $("#q").val();
			if (confirm("Realmente desea eliminar el empleado?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/empleados.php?action=eliminar_empleado",
					data: "id_empleado=" + id,
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