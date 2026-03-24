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
	<html lang="en">

	<head>
		<meta charset="utf-8">
		<title>Bodegas</title>
		<?php include("../paginas/menu_de_empresas.php");
		include("../modal/bodegas.php");
		?>
	</head>

	<body>
		<div class="container">
			<div class="col-md-8 col-md-offset-2">
				<div class="panel panel-info">
					<div class="panel-heading">
						<div class="btn-group pull-right">
							<?php
							if (getPermisos($con, $id_usuario, $ruc_empresa, 'bodegas')['w'] == 1) {
							?>
								<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#bodegas" onclick="openModal();"><span class="glyphicon glyphicon-plus"></span> Nueva</button>
							<?php
							}
							?>
						</div>
						<h4><i class='glyphicon glyphicon-search'></i> Bodegas</h4>
					</div>
					<div class="panel-body">
						<form class="form-horizontal" role="form">
							<div class="form-group row">
								<label for="q" class="col-md-1 control-label">Buscar:</label>
								<div class="col-md-8">
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
						<div class='outer_div'></div><!-- Carga los datos ajax -->
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
		//para cargar al entrar a la pagina
		$(document).ready(function() {
			load(1);
		});
		//para buscar las bodegas al cargar
		function load(page) {
			var q = $("#q").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/bodegas.php?action=buscar_bodegas&page=' + page + '&q=' + q,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');

				}
			})
		}

		function openModal() {
			document.querySelector("#titleModalBodega").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nueva bodega";
			document.querySelector("#formBodega").reset();
			document.querySelector("#idBodega").value = "";
			document.querySelector("#btnActionFormBodega").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextBodega").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormBodega').title = "Guardar nueva bodega";
		}

		function editar_bodega(id) {
			document.querySelector('#titleModalBodega').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar Bodega";
			document.querySelector('#btnActionFormBodega').classList.replace("btn-primary", "btn-info");
			document.querySelector('#btnActionFormBodega').title = "Actualizar Bodega";
			document.querySelector('#btnTextBodega').innerHTML = "Actualizar";
			document.querySelector("#formBodega").reset();
			var nombre_bodega = $("#nombre_bodega_mod" + id).val();
			var status_bodega = $("#status_bodega_mod" + id).val();
			$("#idBodega").val(id);
			$("#nombre_bodega").val(nombre_bodega);
			$("#listStatus").val(status_bodega);
		}


		function usuarios_bodega(id, bodega) {
			document.querySelector("#formBodegaAsignada").reset();
			$("#idBodegaAsignada").val(id);
			$("#nombre_bodega_asignada").val(bodega);

			$.ajax({
				type: "GET",
				url: '../ajax/bodegas.php?action=asignaciones',
				data: "id_bodega=" + id,
				beforeSend: function(objeto) {
					$("#resultados_asignaciones").html("Cargardo...");
				},
				success: function(datos) {
					$("#resultados_asignaciones").html('');
					$(".outer_div_asiganciones").html(datos).fadeIn('slow');
				}
			});

		}

		//para eliminar una bodega
		function eliminar_bodega(id) {
			var q = $("#q").val();
			if (confirm("Realmente desea eliminar la bodega?")) {
				$.ajax({
					type: "GET",
					url: '../ajax/bodegas.php?action=eliminar_bodega',
					data: "id_bodega=" + id,
					"q": q,
					beforeSend: function(objeto) {
						$("#loader").html("Eliminando...");
					},
					success: function(datos) {
						$("#loader").html(datos);
						load(1);
					}
				});
			}
		}

		function asignar_bodega(id) {
			var id_bodega = $("#idBodegaAsignada").val();
			var bodega = $("#nombre_bodega_asignada").val();
			if (confirm("Desea asignar la bodega a este usuario?")) {
				$.ajax({
					type: "GET",
					url: '../ajax/bodegas.php?action=asignar_bodega',
					data: "id=" + id,
					beforeSend: function(objeto) {
						$("#resultados_modal_bodega_asignada").html("Agregando...");
					},
					success: function(datos) {
						$("#resultados_modal_bodega_asignada").html(datos);
						usuarios_bodega(id_bodega, bodega);
					}
				});
			}
		}

		function quitar_bodega(id) {
			var id_bodega = $("#idBodegaAsignada").val();
			var bodega = $("#nombre_bodega_asignada").val();
			if (confirm("Desea quitar la bodega a este usuario?")) {
				$.ajax({
					type: "GET",
					url: '../ajax/bodegas.php?action=quitar_bodega',
					data: "id_usu=" + id + "&id_bodega=" + id_bodega,
					beforeSend: function(objeto) {
						$("#resultados_modal_bodega_asignada").html("Eliminando...");
					},
					success: function(datos) {
						$("#resultados_modal_bodega_asignada").html(datos);
						usuarios_bodega(id_bodega, bodega);
					}
				});
			}
		}
	</script>