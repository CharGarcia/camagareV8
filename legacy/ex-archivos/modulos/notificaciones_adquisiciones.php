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
		<title>Notificaciones</title>
		<?php include("../paginas/menu_de_empresas.php");
		include("../modal/detalle_documento.php");
		include("../modal/enviar_documentos_mail.php");
		?>
	</head>

	<body>

		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<h4><i class='glyphicon glyphicon-search'></i> Notificaciones de adquisiciones</h4>
				</div>
				<div class="panel-body">
					<form class="form-horizontal" method="POST" action="">
						<div class="form-group row">
							<label for="q" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
								<input type="hidden" id="ordenado" value="fecha_compra">
								<input type="hidden" id="por" value="desc">
								<div class="input-group">
									<input type="text" class="form-control" id="q" placeholder="" onkeyup='load(1);'>
									<span class="input-group-btn">
										<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
									</span>
								</div>
							</div>
							<div class="col-md-2">
								<span id="loader"></span>
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
	<script src="../js/notify.js"></script>
	</body>

	</html>
	<script>
		$(document).ready(function() {
			load(1);
		});

		function load(page) {
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/notificaciones_adquisiciones.php?action=buscar_adquisiciones&page=' + page + '&q=' + q + "&ordenado=" + ordenado + "&por=" + por,
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

		function detalle_notificaciones_adquisiciones(codigo) {
			$("#loaderdet").fadeIn('slow');
			$.ajax({
				url: '../ajax/detalle_documento.php?action=detalle_notificaciones_adquisiciones&codigo=' + codigo,
				beforeSend: function(objeto) {
					$('#loaderdet').html('<img src="../image/ajax-loader.gif"> Cargando detalles...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('slow');
					$('#loaderdet').html('');
				}
			});

		}

		function agregar_observacion(codigo_documento, numero_documento, id_proveedor) {
			var respuesta = prompt("Ingrese observación:");
			if ((respuesta)) {
				$.ajax({
					type: "POST",
					url: "../ajax/detalle_documento.php",
					data: "action=agregar_observacion_compra&codigo_documento=" + codigo_documento + "&numero_documento=" + numero_documento + "&id_proveedor=" + id_proveedor + "&observacion=" + respuesta,
					beforeSend: function(objeto) {
						$("#outer_divdet").html("Agregando...");
					},
					success: function(datos) {
						$(".outer_divdet").html(datos).fadeIn('fast');
						$('#outer_divdet').html('');
						detalle_notificaciones_adquisiciones(codigo_documento);
						load(1);
					}
				})
			}
		}


		function enviar_notificacion_mail(id, correo) {
			$("#id_documento").val(id); //uso el id del mismo formulario para no crear un nuevo modal
			$("#mail_receptor").val(correo);
			$("#tipo_documento").val("aprobacion_compra");
		};

		function eliminar_notificacion(id, codigo_documento) {
			if (confirm("Realmente desea eliminar el registro?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/detalle_documento.php",
					data: "action=eliminar_observacion_compra&id=" + id,
					beforeSend: function(objeto) {
						$("#outer_divdet").html("Agregando...");
					},
					success: function(datos) {
						$(".outer_divdet").html(datos).fadeIn('fast');
						$('#outer_divdet').html('');
						detalle_notificaciones_adquisiciones(codigo_documento);
						load(1);
					}
				});
			}
		};

		function actualizar_status(id) {
			var codigo_documento = $("#codigo_documento_aprobar_compra").val();
			var status = $("#listStatus" + id).val();
			if (confirm("Realmente desea actualizar el status?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/detalle_documento.php",
					data: "action=actualizar_status_observacion_compra&id=" + id + "&status=" + status,
					beforeSend: function(objeto) {
						$("#outer_divdet").html("Agregando...");
					},
					success: function(datos) {
						$(".outer_divdet").html(datos).fadeIn('fast');
						$('#outer_divdet').html('');
						detalle_notificaciones_adquisiciones(codigo_documento);
						load(1);
					}
				});
			}
		};

		function actualizar_correo(id) {
			var codigo_documento = $("#codigo_documento_aprobar_compra").val();
			var id_receptor = $("#listUsuarios" + id).val();
			if (confirm("Realmente desea actualizar el responsable?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/detalle_documento.php",
					data: "action=actualizar_correo_observacion_compra&id=" + id + "&id_receptor=" + id_receptor,
					beforeSend: function(objeto) {
						$("#outer_divdet").html("Agregando...");
					},
					success: function(datos) {
						$(".outer_divdet").html(datos).fadeIn('fast');
						$('#outer_divdet').html('');
						detalle_notificaciones_adquisiciones(codigo_documento);
						load(1);
					}
				});
			}
		};
	</script>