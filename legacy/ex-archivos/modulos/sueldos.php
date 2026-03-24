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
		<title>Sueldos</title>
		<?php include("../paginas/menu_de_empresas.php"); ?>
	</head>
	<body>
		<div class="container-fluid">
				<div class="panel panel-info">
					<div class="panel-heading">
						<div class="btn-group pull-right">
						<?php
							$con = conenta_login();
							if(getPermisos($con, $id_usuario, $ruc_empresa, 'sueldos')['w']==1){
							?>
							<button class="btn btn-info" data-toggle="modal" data-target="#modalSueldos" onclick="openModal();"><span class="glyphicon glyphicon-plus"></span> Nuevo</button>
							<?php
							}
							?>
						</div>
						<h4><i class='glyphicon glyphicon-search'></i> Sueldos nómina</h4>
					</div>
					<div class="panel-body">
						<?php
						include("../modal/sueldos.php");
						?>
						<form class="form-horizontal" method="POST">
							<div class="form-group row">
								<label for="q" class="col-md-1 control-label">Buscar:</label>
								<div class="col-md-6">
									<input type="hidden" id="ordenado" value="emp.nombres_apellidos">
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
			document.querySelector("#titleModalSueldos").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo Sueldo";
			document.querySelector("#formSueldos").reset();
			document.querySelector("#idSueldo").value = "";
			document.querySelector("#btnActionFormSueldo").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextSueldo").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormSueldo').title = "Guardar nuevo sueldo";

			$.ajax({
				url: "../ajax/sueldos.php?action=nuevo_sueldo",
				beforeSend: function(objeto) {
					$("#resultados_ingresos_descuentos").html("Cargando...");
				},
				success: function(data) {
					$('#resultados_ingresos_descuentos').html('');
					$('#outer_div_ingresos_descuentos').html('');
				}
			});

		}

		function load(page) {
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/sueldos.php?action=buscar_sueldos&page=' + page + '&q=' + q + "&ordenado=" + ordenado + "&por=" + por,
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

		function editar_sueldo(id) {
			document.querySelector('#titleModalSueldos').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar sueldo";
			document.querySelector('#btnActionFormSueldo').classList.replace("btn-primary", "btn-info");
			document.querySelector('#btnActionFormSueldo').title = "Actualizar Sueldo";
			document.querySelector('#btnTextSueldo').innerHTML = "Actualizar";
			document.querySelector("#formSueldos").reset();
            document.querySelector("#idSueldo").value = id;
			
            let request = (window.XMLHttpRequest) ?
				new XMLHttpRequest() :
				new ActiveXObject('Microsoft.XMLHTTP');
			let ajaxUrl = '../ajax/sueldos.php?action=datos_editar_sueldo&id_sueldo=' + id;
			request.open("GET", ajaxUrl, true);
			request.send();
			request.onreadystatechange = function() {
				if (request.readyState == 4 && request.status == 200) {
					let objData = JSON.parse(request.responseText);
					if (objData.status) {
						let objSueldo = objData.data;
						document.querySelector("#idSueldo").value = objSueldo.id_sueldo;
						document.querySelector("#idEmpleado").value = objSueldo.id_empleado;
						document.querySelector("#datalistEmpleados").value = objSueldo.empleado;
						document.querySelector("#datalistFR").value = objSueldo.fondo_reserva;
						document.querySelector("#aportaIess").value = objSueldo.aporta_al_iess;
						document.querySelector("#listStatus").value = objSueldo.status;
						document.querySelector("#decimotercero").value = objSueldo.decimo_tercero_mensual;
						document.querySelector("#decimocuarto").value = objSueldo.decimo_cuarto_mensual;
						document.querySelector("#departamento").value = objSueldo.departamento;
                        document.querySelector("#fecha_ingreso").value = objSueldo.fecha_ingreso;
						document.querySelector("#fecha_salida").value = objSueldo.fecha_salida;
						document.querySelector("#cargo").value = objSueldo.cargo_empresa;
						document.querySelector("#ap_personal").value = objSueldo.ap_personal;
						document.querySelector("#ap_patronal").value = objSueldo.ap_patronal;
						document.querySelector("#codigo_iess").value = objSueldo.cargo_iess;
						document.querySelector("#sueldo").value = objSueldo.sueldo;
						document.querySelector("#quincena").value = objSueldo.quincena;
						document.querySelector("#listRegion").value = objSueldo.region;
						muestra_ingresos_descuentos_editar(objSueldo.id_sueldo);
					} else {
						$.notify(objData.msg, "error");
					}
				}
				return false;
			}
		}


		function muestra_ingresos_descuentos_editar(id){
			$.ajax({
				type: "POST",
				url: "../ajax/sueldos.php?action=muestra_ingresos_descuentos_editar",
				data: "id_sueldo=" + id,
				beforeSend: function(objeto) {
					$("#resultados_ingresos_descuentos").html("Cargando...");
				},
				success: function(dataAdicional) {
					$('#resultados_ingresos_descuentos').html(dataAdicional);
				}
				});
		}

		function eliminar_sueldo(id) {
			var q = $("#q").val();
			if (confirm("Realmente desea eliminar el registro?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/sueldos.php?action=eliminar_sueldo",
					data: "id_sueldo=" + id,
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