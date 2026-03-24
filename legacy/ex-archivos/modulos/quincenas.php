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
		<title>Quincenas</title>
		<?php include("../paginas/menu_de_empresas.php"); ?>
	</head>

	<body>
		<div class="container">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<?php
						$con = conenta_login();
						if (getPermisos($con, $id_usuario, $ruc_empresa, 'quincenas')['w'] == 1) {
						?>
							<button class="btn btn-info" data-toggle="modal" data-target="#modalQuincenas" onclick="openModal();"><span class="glyphicon glyphicon-plus"></span> Nueva</button>
						<?php
						}
						?>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Quincenas</h4>
				</div>
				<div class="panel-body">
					<?php
					include("../modal/quincenas.php");
					include("../modal/enviar_documentos_mail.php");
					?>
					<form class="form-horizontal" method="POST">
						<div class="form-group row">
							<label for="q" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-6">
								<input type="hidden" id="ordenado" value="qui.mes_ano">
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
			document.querySelector("#titleModalQuincenas").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nueva Quincena";
			document.querySelector("#formQuincenas").reset();
			document.querySelector("#idQuincena").value = "";
			document.querySelector("#btnActionFormQuincena").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextQuincena").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormQuincena').title = "Guardar nueva";
		}

		function load(page) {
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			var qui = $("#qui").val();
			var id_quincena = $("#idQuincenaPrint").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/quincenas.php?action=buscar_quincenas&page=' + page + '&q=' + q + '&ordenado=' + ordenado + '&por=' + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Buscando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			})

			//para cargar los detalles de las quincenas
			$("#loader_quincenas").fadeIn('slow');
			$.ajax({
				url: '../ajax/quincenas.php?action=detalle_quincenas&page=' + page + '&qui=' + qui + '&id_quincena=' + id_quincena,
				beforeSend: function(objeto) {
					$('#loader_quincenas').html('Cargando...');
				},
				success: function(data) {
					$(".outer_div_quincenas").html(data).fadeIn('slow');
					$('#loader_quincenas').html('');
					// event.preventDefault();
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

		/* function editar_quincena(id) {
			document.querySelector('#titleModalQuincenas').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar Quincena";
			document.querySelector('#btnActionFormQuincena').classList.replace("btn-primary", "btn-info");
			document.querySelector('#btnActionFormQuincena').title = "Actualizar Quincena";
			document.querySelector('#btnTextQuincena').innerHTML = "Actualizar";
			document.querySelector("#formQuincenas").reset();
            document.querySelector("#idQuincena").value = id;
			
            let request = (window.XMLHttpRequest) ?
				new XMLHttpRequest() :
				new ActiveXObject('Microsoft.XMLHTTP');
			let ajaxUrl = '../ajax/quincenas.php?action=datos_editar_quincena&id_quincena=' + id;
			request.open("GET", ajaxUrl, true);
			request.send();
			request.onreadystatechange = function() {
				if (request.readyState == 4 && request.status == 200) {
					let objData = JSON.parse(request.responseText);
					if (objData.status) {
						let objQuincena = objData.data;
						document.querySelector("#datalistMes").value = objQuincena.mes;
						document.querySelector("#datalistAno").value = objQuincena.ano;
					} else {
						$.notify(objData.msg, "error");
					}
				}
				return false;
			}
		} */

		function eliminar_quincena(id) {
			var q = $("#q").val();
			if (confirm("Realmente desea eliminar el registro?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/quincenas.php?action=eliminar_quincena",
					data: "id_quincena=" + id,
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

		function detalle_quincena(id, mes_ano) {
			actualizar_quincena(id, mes_ano);
			document.querySelector("#formDetQuincenas").reset();
			$("#idQuincenaPrint").val(id);
			$("#periodo_quincena").val(mes_ano);
			var qui = "";
			var page = "1";
			$("#loader_quincenas").fadeIn('slow');
			$.ajax({
				url: '../ajax/quincenas.php?action=detalle_quincenas&page=' + page + '&qui=' + qui + '&id_quincena=' + id,
				beforeSend: function(objeto) {
					
					$('#loader_quincenas').html('Cargando...');
				},
				success: function(data) {
					$(".outer_div_quincenas").html(data).fadeIn('slow');
					$('#loader_quincenas').html('');
					event.preventDefault();
				}
			});
			
		}

		function actualizar_quincena(id, mes_ano) {
			$.ajax({
				url: '../ajax/quincenas.php?action=actualizar_quincena&id_quincena=' + id + '&mes_ano=' + mes_ano,
				beforeSend: function(objeto) {
					$('#loader_quincenas').html('Actualizando...');
				},
				success: function(data) {
					$(".outer_div_quincenas").html(data).fadeIn('slow');
					$('#loader_quincenas').html('');
					load(1);
					event.preventDefault();
				}
			});
		}

		function enviar_quincena_mail(id_quincena, correo) {
			$("#id_documento").val(id_quincena);
			$("#mail_receptor").val(correo);
			$("#tipo_documento").val("quincena");
		};
	</script>