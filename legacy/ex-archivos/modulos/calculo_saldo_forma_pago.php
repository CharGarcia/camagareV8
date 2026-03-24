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
		<title>Opciones</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		?>
		<style type="text/css">
			ul.ui-autocomplete {
				z-index: 1100;
			}
		</style>
	</head>

	<body>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#opciones_csfp" onclick="carga_modal_cfp();"><span class="glyphicon glyphicon-plus"></span> Nuevo</button>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Calculo para saldo de formas de pago</h4>
				</div>
				<div class="panel-body">
					<?php
					include("../modal/calculo_saldo_forma_pago.php");
					?>
					<form class="form-horizontal" method="POST">
						<div class="form-group row">
							<label for="q" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
								<input type="hidden" id="ordenado" value="ofp.id">
								<input type="hidden" id="por" value="desc">
								<div class="input-group">
									<input type="text" class="form-control" id="q" placeholder="Forma de pago" onkeyup='load(1);'>
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
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<!--para que se vea con fondo blanco el autocomplete -->
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/ordenado.js" type="text/javascript"></script>
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
				url: '../ajax/calculo_saldo_forma_pago.php?action=buscar_opciones_scfp&page=' + page + '&q=' + q + "&ordenado=" + ordenado + "&por=" + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			})
		}


		function carga_modal_cfp() {
			document.querySelector("#titleModalcsfp").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo";
			document.querySelector("#guardar_csfp").reset();
			document.querySelector("#idRegistro").value = "";
			document.querySelector("#btnActionFormopciones_scfp").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextopciones_scfp").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormopciones_scfp').title = "Guardar";
			$("#muestra_detalle_scfp").fadeIn('fast');
			$.ajax({
				url: "../ajax/calculo_saldo_forma_pago.php?action=nuevo_registro",
				beforeSend: function(objeto) {
					$("#muestra_detalle_scfp").html("Cargando detalle...");
				},
				success: function(data) {
					$(".outer_divdet_scfp").html(data).fadeIn('fast');
					$('#muestra_detalle_scfp').html('');
					}
			});
		}


		//eliminar 
		function eliminar_registro(id) {
			if (confirm("Realmente desea eliminar el registro?")) {
				$.ajax({
					type: "GET",
					url: "../ajax/calculo_saldo_forma_pago.php?action=eliminar_registro_scfp&id="+id,
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

		
		//agrega un item
		function agregar_item() {
			var fp_uno = $("#listFormaPagoPrincipal").val();
			var fp_dos = $("#listFormaPagoOpcional").val();

			//Inicia validacion
			if (fp_uno == '') {
				alert('Seleccione una forma de pago para a obtener el saldo.');
				document.getElementById('listFormaPagoPrincipal').focus();
				return false;
			}
			if (fp_dos == '') {
				alert('Seleccione forma de pago a restar.');
				document.getElementById('listFormaPagoOpcional').focus();
				return false;
			}

			//Fin validacion
			$("#muestra_detalle_scfp").fadeIn('fast');
			$.ajax({
				url: "../ajax/calculo_saldo_forma_pago.php?action=agregar_item&uno=" + fp_uno + "&dos=" + fp_dos,
				beforeSend: function(objeto) {
					$("#muestra_detalle_scfp").html("Cargando detalle...");
				},
				success: function(data) {
					$(".outer_divdet_scfp").html(data).fadeIn('fast');
					$('#muestra_detalle_scfp').html('');
				}
			});
		}

		function eliminar_item(id) {
			//Inicia validacion
			if (id == '') {
				alert('Seleccione una forma de pago para eliminar.');
				return false;
			}
			//Fin validacion
			$("#muestra_detalle_scfp").fadeIn('fast');
			$.ajax({
				url: "../ajax/calculo_saldo_forma_pago.php?action=eliminar_item&id=" + id,
				beforeSend: function(objeto) {
					$("#muestra_detalle_scfp").html("Cargando detalle...");
				},
				success: function(data) {
					$(".outer_divdet_scfp").html(data).fadeIn('fast');
					$('#muestra_detalle_scfp').html('');
				}
			});
		}

		//para guardar
		$("#guardar_csfp").submit(function(event) {
			$('#btnActionFormopciones_scfp').attr("disabled", true);
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: "../ajax/calculo_saldo_forma_pago.php?action=guardar_opciones_scfp",
				data: parametros,
				beforeSend: function(objeto) {
					$("#loader_opciones_scfp").html("Guardando...");
				},
				success: function(datos) {
					$("#loader_opciones_scfp").html(datos);
					$("#loader_opciones_scfp").html('');
					$('#btnActionFormopciones_scfp').attr("disabled", false);
					load(1);
				}
			});
			event.preventDefault();
		});
	</script>