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
		<title>Roles de pago</title>
		<?php include("../paginas/menu_de_empresas.php"); ?>
	</head>

	<body>
		<div class="container">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<?php
						if (getPermisos($con, $id_usuario, $ruc_empresa, 'rolespago')['w'] == 1) {
						?>
							<button class="btn btn-info" data-toggle="modal" data-target="#modalRolPago" onclick="openModal();"><span class="glyphicon glyphicon-plus"></span> Nuevo</button>
						<?php
						}
						?>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Roles de pago</h4>
				</div>
				<div class="panel-body">
					<?php
					include("../modal/rolespago.php");
					include("../modal/enviar_documentos_mail.php");
					?>
					<form class="form-horizontal" method="POST">
						<div class="form-group row">
							<label for="q" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-6">
								<input type="hidden" id="ordenado" value="rol.mes_ano">
								<input type="hidden" id="por" value="asc">
								<div class="input-group">
									<input type="text" class="form-control" id="q" placeholder="Mes, año, fecha" onkeyup='load(1);'>
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
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
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
			document.querySelector("#titleModalRolPago").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo Rol de Pagos";
			document.querySelector("#formRolPago").reset();
			document.querySelector("#idRolPago").value = "";
			document.querySelector("#btnActionFormRolPago").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextRolPago").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormRolPago').title = "Guardar nuevo";
		}

		function load(page) {
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			var rol = $("#rol").val();
			var id_rol = $("#idRolesPagoPrint").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/rolespago.php?action=buscar_roles_pago&page=' + page + '&q=' + q + '&ordenado=' + ordenado + '&por=' + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Buscando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			})

			//para cargar los detalles de los roles
			$("#loader_RolesPagos").fadeIn('slow');
			$.ajax({
				url: '../ajax/rolespago.php?action=detalle_rolespago&page=' + page + '&rol=' + rol + '&id_rol=' + id_rol,
				beforeSend: function(objeto) {
					$('#loader_RolesPagos').html('Cargando...');
				},
				success: function(data) {
					$(".outer_div_RolesPagos").html(data).fadeIn('slow');
					$('#loader_RolesPagos').html('');
				}
			});
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

		/* 		function editar_rolespago(id) {
					document.querySelector('#titleModalRolPago').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar Rol de Pagos";
					document.querySelector('#btnActionFormRolPago').classList.replace("btn-primary", "btn-info");
					document.querySelector('#btnActionFormRolPago').title = "Actualizar Rol de Pagos";
					document.querySelector('#btnTextRolPago').innerHTML = "Actualizar";
					document.querySelector("#formRolPago").reset();
		            document.querySelector("#idRolPago").value = id;
					
		            let request = (window.XMLHttpRequest) ?
						new XMLHttpRequest() :
						new ActiveXObject('Microsoft.XMLHTTP');
					let ajaxUrl = '../ajax/rolespago.php?action=datos_editar_rolespago&id_rol=' + id;
					request.open("GET", ajaxUrl, true);
					request.send();
					request.onreadystatechange = function() {
						if (request.readyState == 4 && request.status == 200) {
							let objData = JSON.parse(request.responseText);
							if (objData.status) {
								let objRol = objData.data;
								document.querySelector("#datalistMes").value = objRol.mes;
								document.querySelector("#datalistAno").value = objRol.ano;
							} else {
								$.notify(objData.msg, "error");
							}
						}
						return false;
					}
				} */

		function eliminar_rolespago(id) {
			var q = $("#q").val();
			if (confirm("Realmente desea eliminar el registro?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/rolespago.php?action=eliminar_rolespago",
					data: "id_rol=" + id,
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

		function detalle_rolespago(id, mes_ano) {
			actualizar_rolespago(id, mes_ano);
			document.querySelector("#formDetRolesPago").reset();
			$("#idRolesPagoPrint").val(id);
			$("#periodo_RolesPago").val(mes_ano);
			var rol = "";
			var page = "1";
			$("#loader_RolesPagos").fadeIn('slow');
			$.ajax({
				url: '../ajax/rolespago.php?action=detalle_rolespago&page=' + page + '&rol=' + rol + '&id_rol=' + id,
				beforeSend: function(objeto) {
					$('#loader_RolesPagos').html('Cargando...');
				},
				success: function(data) {
					$(".outer_div_RolesPagos").html(data).fadeIn('slow');
					$('#loader_RolesPagos').html('');
					event.preventDefault();
				}
			});
		}

		function actualizar_rolespago(id, mes_ano) {
			let mes = mes_ano.substring(0, 2); // Obtiene los dos primeros caracteres (mes)
			let ano = mes_ano.substring(3);
			$.ajax({
				url: '../ajax/rolespago.php?action=actualizar_rolespago',
				type: 'POST',
				data: { // Pasar los datos como objeto en el cuerpo del POST
					id_rol: id,
					mes: mes,
					ano: ano
				},
				beforeSend: function(objeto) {
					$('#loader_RolesPagos').html('Actualizando...');
				},
				success: function(data) {
					$(".outer_div_RolesPagos").html(data).fadeIn('slow');
					$('#loader_RolesPagos').html('');
					load(1);
					event.preventDefault();
				}
			});
		}

		function enviar_rolpago_mail(id_rol, correo) {
			$("#id_documento").val(id_rol);
			$("#mail_receptor").val(correo);
			$("#tipo_documento").val("rol_pagos");
		};
	</script>