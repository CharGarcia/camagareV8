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
		<title>Cuentas Bancarias</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		include("../modal/cuenta_bancaria.php");
		include("../modal/enviar_documentos_mail.php");
		?>
	</head>

	<body>
		<div class="container">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<?php
						if (getPermisos($con, $id_usuario, $ruc_empresa, 'cuentas_bancarias')['w'] == 1) {
						?>
							<button class="btn btn-info" data-toggle="modal" data-target="#CuentaBancaria" onclick="openModal();"><span class="glyphicon glyphicon-plus"></span> Nueva</button>
						<?php
						}
						?>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Cuentas Bancarias</h4>
				</div>
				<div class="panel-body">

					<form class="form-horizontal" role="form" id="datos_cotizacion">

						<div class="form-group row">
							<label for="q" class="col-md-2 control-label">Cuentas:</label>
							<div class="col-md-5">
								<input type="text" class="form-control" id="q" placeholder="Banco, tipo, cuenta" onkeyup='load(1);'>
							</div>

							<div class="col-md-3">
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
			document.querySelector("#titleModalCuentaBancaria").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nueva Cuenta Bancaria";
			document.querySelector("#formCuentaBancaria").reset();
			document.querySelector("#idCuentaBancaria").value = "";
			document.querySelector("#btnActionFormCuentaBancaria").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextCuentaBancaria").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormCuentaBancaria').title = "Guardar nueva cuenta bancaria";
		}

		function editar_cuenta_bancaria(id) {
			document.querySelector('#titleModalCuentaBancaria').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar Cuenta Bancaria";
			document.querySelector('#btnActionFormCuentaBancaria').classList.replace("btn-primary", "btn-info");
			document.querySelector('#btnActionFormCuentaBancaria').title = "Actualizar Cuenta Bancaria";
			document.querySelector('#btnTextCuentaBancaria').innerHTML = "Actualizar";
			document.querySelector("#formCuentaBancaria").reset();
			var banco_mod = $("#banco_mod" + id).val();
			var status = $("#status_mod" + id).val();
			var tipo_mod = $("#tipo_mod" + id).val();
			var cuenta_mod = $("#cuenta_mod" + id).val();

			$("#banco").val(banco_mod);
			$("#listStatusCuentaBancaria").val(status);
			$("#tipo_cuenta").val(tipo_mod);
			$("#numero_cuenta").val(cuenta_mod);
			$("#idCuentaBancaria").val(id);
		}

		function load(page) {
			var q = $("#q").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/cuentas_bancarias.php?action=buscar_cuentas_bancarias&page=' + page + '&q=' + q,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');

				}
			})
		}

		function eliminar_cuenta_bancaria(id_cuenta) {
			var q = $("#q").val();
			if (confirm("Realmente desea eliminar la cuenta bancaria?")) {
				$.ajax({
					type: "GET",
					url: "../ajax/cuentas_bancarias.php?action=eliminar_cuenta_bancaria",
					data: "id_cuenta=" + id_cuenta,
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

		function enviar_cb_whatsapp(id, numero, mensaje) {
			document.querySelector("#resultados_ajax_whatsapp").innerHTML = "";
			document.querySelector("#documento_whatsapp").reset();
			$("#id_documento_whatsapp").val(id);
			$("#whatsapp_receptor").val(numero);
			$("#mensaje").val(mensaje);
			$("#tipo_documento_whatsapp").val("cb");
		};
	</script>