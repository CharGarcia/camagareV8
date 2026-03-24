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
		<title>Asesores ventas</title>
		<?php include("../paginas/menu_de_empresas.php"); ?>
	</head>

	<body>

		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<?php
						if (getPermisos($con, $id_usuario, $ruc_empresa, 'vendedores')['w'] == 1) {
						?>
							<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#nuevoVendedor" onclick="openModal();"><span class="glyphicon glyphicon-plus"></span> Nuevo</button>
						<?php
						}
						?>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Asesores de ventas</h4>
				</div>
				<div class="panel-body">
					<?php
					include("../modal/vendedores.php");
					?>
					<form class="form-horizontal" method="POST">
						<div class="form-group row">
							<label for="q" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
								<input type="hidden" id="ordenado" value="ven.nombre">
								<input type="hidden" id="por" value="asc">
								<div class="input-group">
									<input type="text" class="form-control" id="q" placeholder="Nombre, dirección, correo, cedula, teléfono" onkeyup='load(1);'>
									<span class="input-group-btn">
										<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
									</span>
								</div>
							</div>
							<span id="loader"></span>
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
	<script src="../js/notify.js"></script>
	</body>

	</html>
	<script>
		$(document).ready(function() {
			load(1);
		});

		$(document).ready(function() {
			window.addEventListener("keypress", function(event) {
				if (event.keyCode == 13) {
					event.preventDefault();
				}
			}, false);
			load(1);
		});

		function load(page) {
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/vendedores.php?action=buscar_vendedores&page=' + page + '&q=' + q + '&ordenado=' + ordenado + '&por=' + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
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
		function openModal() {
			document.querySelector("#titleModalVendedor").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo vendedor";
			document.querySelector("#formVendedores").reset();
			document.querySelector("#idVendedor").value = "";
			document.querySelector("#btnActionFormVendedor").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextVendedor").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormVendedor').title = "Guardar nuevo departamento";
		}

		function editar_vendedor(id) {
			document.querySelector('#titleModalVendedor').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar vendedor";
			document.querySelector('#btnActionFormVendedor').classList.replace("btn-primary", "btn-info");
			document.querySelector('#btnActionFormVendedor').title = "Actualizar vendedor";
			document.querySelector('#btnTextVendedor').innerHTML = "Actualizar";
			document.querySelector("#formVendedores").reset();
			document.querySelector("#idVendedor").value = id;

			let request = (window.XMLHttpRequest) ?
				new XMLHttpRequest() :
				new ActiveXObject('Microsoft.XMLHTTP');
			let ajaxUrl = '../ajax/vendedores.php?action=datos_editar_vendedor&id_vendedor=' + id;
			request.open("GET", ajaxUrl, true);
			request.send();
			request.onreadystatechange = function() {
				if (request.readyState == 4 && request.status == 200) {
					let objData = JSON.parse(request.responseText);
					if (objData.status) {
						let objVendedor = objData.data;
						document.querySelector("#tipo_id").value = objVendedor.tipo_id;
						document.querySelector("#cedula").value = objVendedor.cedula;
						document.querySelector("#nombre").value = objVendedor.vendedor;
						document.querySelector("#correo").value = objVendedor.correo;
						document.querySelector("#direccion").value = objVendedor.direccion;
						document.querySelector("#telefono").value = objVendedor.telefono;
						document.querySelector("#status").value = objVendedor.status;
					} else {
						$.notify(objData.msg, "error");
					}
				}
				return false;
			}
		}

		function eliminar_vendedor(id) {
			var q = $("#q").val();
			if (confirm("Realmente desea eliminar el registro?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/vendedores.php?action=eliminar_vendedor",
					data: "id_vendedor=" + id,
					"q": q,
					beforeSend: function(objeto) {
						$("#resultados").html("Eliminando...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						load(1);
					}
				});
			}
		}

		function usuarios_vendedor(id, vendedor) {
			document.querySelector("#formVendedorAsignado").reset();
			$("#idVendedorAsignado").val(id);
			$("#nombre_vendedor_asignado").val(vendedor);

			$.ajax({
				type: "GET",
				url: '../ajax/vendedores.php?action=asignaciones',
				data: "id_vendedor=" + id,
				beforeSend: function(objeto) {
					$("#resultados_asignaciones").html("Cargardo...");
				},
				success: function(datos) {
					$("#resultados_asignaciones").html('');
					$(".outer_div_asiganciones").html(datos).fadeIn('slow');
				}
			});
		}

		function asignar_vendedor(id) {
			var id_vendedor = $("#idVendedorAsignado").val();
			var vendedor = $("#nombre_vendedor_asignado").val();
			if (confirm("Desea asignar el vendedor a este usuario?")) {
				$.ajax({
					type: "GET",
					url: '../ajax/vendedores.php?action=asignar_vendedor',
					data: "id=" + id,
					beforeSend: function(objeto) {
						$("#resultados_modal_vendedor_asignado").html("Agregando...");
					},
					success: function(datos) {
						$("#resultados_modal_vendedor_asignado").html(datos);
						usuarios_vendedor(id_vendedor, vendedor);
					}
				});
			}
		}

		function quitar_vendedor(id) {
			var id_vendedor = $("#idVendedorAsignado").val();
			var vendedor = $("#nombre_vendedor_asignado").val();
			if (confirm("Desea quitar el vendedor a este usuario?")) {
				$.ajax({
					type: "GET",
					url: '../ajax/vendedores.php?action=quitar_vendedor',
					data: "id_usu=" + id + "&id_vendedor=" + id_vendedor,
					beforeSend: function(objeto) {
						$("#resultados_modal_vendedor_asignado").html("Eliminando...");
					},
					success: function(datos) {
						$("#resultados_modal_vendedor_asignado").html(datos);
						usuarios_vendedor(id_vendedor, vendedor);
					}
				});
			}
		}
	</script>
