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
		<title>Presupuestos</title>
		<?php include("../paginas/menu_de_empresas.php"); ?>
	</head>
	<body>
		<div class="container-fluid">
				<div class="panel panel-info">
					<div class="panel-heading">
						<div class="btn-group pull-right">
						<?php
							$con = conenta_login();
							if(getPermisos($con, $id_usuario, $ruc_empresa, 'presupuestos')['w']==1){
							?>
							<button class="btn btn-info" data-toggle="modal" data-target="#modalPresupuestos" onclick="openModal();"><span class="glyphicon glyphicon-plus"></span> Nuevo</button>
							<?php
							}
							?>
						</div>
						<h4><i class='glyphicon glyphicon-search'></i> Presupuestos</h4>
					</div>
					<div class="panel-body">
						<?php
						include("../modal/presupuestos.php");
						?>
						<form class="form-horizontal" method="POST">
							<div class="form-group row">
								<label for="q" class="col-md-1 control-label">Buscar:</label>
								<div class="col-md-6">
									<input type="hidden" id="ordenado" value="proyecto">
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
			document.querySelector("#titleModalPresupuestos").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo Presupuesto";
			document.querySelector("#formPresupuestos").reset();
			document.querySelector("#idPresupuesto").value = "";
			document.querySelector("#btnActionFormPresupuestos").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextPresupuestos").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormPresupuestos').title = "Guardar";

			$.ajax({
				url: "../ajax/presupuestos.php?action=nuevo_presupuesto",
				beforeSend: function(objeto) {
					$("#resultados_presupuestos").html("Cargando...");
				},
				success: function(data) {
					$('#resultados_presupuestos').html('');
					$('.outer_div_presupuestos').html('');
				}
			});

		}

	function editar_presupuesto(id) {
			document.querySelector('#titleModalPresupuestos').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar Presupuesto";
			document.querySelector('#btnActionFormPresupuestos').classList.replace("btn-primary", "btn-info");
			document.querySelector('#btnActionFormPresupuestos').title = "Actualizar Presupuesto";
			document.querySelector('#btnTextPresupuestos').innerHTML = "Actualizar";
			document.querySelector("#formPresupuestos").reset();
            document.querySelector("#idPresupuesto").value = id;
			
            let request = (window.XMLHttpRequest) ?
				new XMLHttpRequest() :
				new ActiveXObject('Microsoft.XMLHTTP');
			let ajaxUrl = '../ajax/presupuestos.php?action=datos_editar_presupuesto&id_presupuesto=' + id;
			request.open("GET", ajaxUrl, true);
			request.send();
			request.onreadystatechange = function() {
				if (request.readyState == 4 && request.status == 200) {
					let objData = JSON.parse(request.responseText);
					if (objData.status) {
						let objPresupuesto = objData.data;
						document.querySelector("#idPresupuesto").value = id;
						document.querySelector("#proyecto").value = objPresupuesto.proyecto;
						document.querySelector("#desde").value = objPresupuesto.desde;
						document.querySelector("#hasta").value = objPresupuesto.hasta;
						document.querySelector("#status").value = objPresupuesto.status;
						document.querySelector("#total").value = objPresupuesto.total;
						muestra_cuentas_editar(id);
					} else {
						$.notify(objData.msg, "error");
					}
				}
				return false;
			}
		}

		function load(page) {
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/presupuestos.php?action=buscar_presupuestos&page=' + page + '&q=' + q + "&ordenado=" + ordenado + "&por=" + por,
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



		function muestra_cuentas_editar(id){
			$.ajax({
				type: "POST",
				url: "../ajax/presupuestos.php?action=muestra_cuentas_editar",
				data: "id_presupuesto=" + id,
				beforeSend: function(objeto) {
					$("#resultados_presupuestos").html("Cargando...");
				},
				success: function(data) {
					$('.outer_div_presupuestos').html(data);
					$('#resultados_presupuestos').html('');
				}
				});
		}

		function eliminar_presupuesto(id) {
			var q = $("#q").val();
			if (confirm("Realmente desea eliminar el registro?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/presupuestos.php?action=eliminar_presupuesto",
					data: "id_presupuesto=" + id,
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