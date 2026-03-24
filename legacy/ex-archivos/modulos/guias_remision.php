<?php
header('Content-Type: text/html; charset=UTF-8');
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
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>Guías de remisión</title>
		<?php include("../paginas/menu_de_empresas.php");
		include("../modal/enviar_documentos_mail.php");
		//include("../modal/enviar_documentos_sri.php");
		include("../modal/anular_documentos_sri.php");
		?>
	</head>

	<body>

		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<form method="post" action="../modulos/nueva_guia_remision.php">
							<?php
							if (getPermisos($con, $id_usuario, $ruc_empresa, 'guias_remision')['w'] == 1) {
							?>
								<button type='submit' class="btn btn-info"><span class="glyphicon glyphicon-plus"></span> Nueva Guía de remisión</button>
							<?php
							}
							?>
						</form>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Guías de remisión</h4>
				</div>


				<div class="panel-body">
					<form class="form-horizontal" role="form" id="datos_cotizacion">
						<div class="form-group row">
							<input type="hidden" id="ordenado" value="id_encabezado_gr">
							<input type="hidden" id="por" value="desc">
							<label for="q" class="col-md-2 control-label">Buscar:</label>
							<div class="col-md-5">
								<input type="text" class="form-control" id="q" placeholder="Cliente, serie, guías, fecha, transportista" onkeyup='load(1);'>
							</div>

							<div class="col-md-3">
								<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
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
	<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
	<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
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

		function load(page) {
			var por = $("#por").val();
			var ordenado = $("#ordenado").val();
			var q = $("#q").val();
			var d = $("#d").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_guias_remision.php?action=buscar_guia&page=' + page + '&q=' + q + "&por=" + por + "&ordenado=" + ordenado,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
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

		function eliminar_guía(id_guia) {
			var serie = $("#serie_guia" + id_guia).val();
			var secuencial = $("#secuencial_guia" + id_guia).val();
			if (confirm("Realmente desea eliminar la guía de remisión " + serie + "-" + secuencial + " ?")) {
				$.ajax({
					type: "POST",
					url: '../ajax/buscar_guias_remision.php?action=eliminar_guia',
					data: "id_guia=" + id_guia,
					beforeSend: function(objeto) {
						$("#resultados").html("Mensaje: Cargando...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						load(1);
					}
				});
			};
		};

		function enviar_gr_mail(id) {
			var mail_guia = $("#mail_cliente" + id).val();
			$("#id_documento").val(id);
			$("#mail_receptor").val(mail_guia);
			$("#tipo_documento").val("gr");
		};


		//pasa el codigo del id del documento a anularse al modal de anular documentos sri
		function anular_documento_en_sri(id) {
			document.querySelector("#anular_documento_sri").reset();
			document.getElementById('resultados_ajax_anular').innerHTML = '';
			$('#anular_sri').attr("disabled", true);
			$("#resultados_anular").html('<div class="progress"><div class="progress-bar progress-bar-primary progress-bar-striped active" role="progressbar" style="width:100%;">Consultando SRI...</div></div>');
			let documento = '06';
			document.querySelector("#id_documento_modificar").value = id;
			document.querySelector("#codigo_documento_modificar").value = documento;
			let request = (window.XMLHttpRequest) ?
				new XMLHttpRequest() :
				new ActiveXObject('Microsoft.XMLHTTP');
			let ajaxUrl = '../ajax/detalle_documento.php?action=info_anular_sri&documento=' + documento + '&id=' + id;
			request.open("GET", ajaxUrl, true);
			request.send();
			request.onreadystatechange = function() {
				if (request.readyState == 4 && request.status == 200) {
					let objData = JSON.parse(request.responseText);
					if (objData.status) {
						let objInfoSRI = objData.data;
						document.querySelector("#cliente_proveedor").value = objInfoSRI.cliente_proveedor;
						document.querySelector("#tipo_comprobante").value = objInfoSRI.tipo_comprobante;
						document.querySelector("#fecha_autorizacion").value = objInfoSRI.fecha_autorizacion;
						document.querySelector("#fecha_documento").value = objInfoSRI.fecha_documento;
						document.querySelector("#clave_acceso").value = objInfoSRI.clave_acceso;
						document.querySelector("#numero_autorizacion").value = objInfoSRI.clave_acceso;
						document.querySelector("#ruc_receptor").value = objInfoSRI.identificacion_receptor;
						document.querySelector("#correo_receptor").value = objInfoSRI.correo_electronico_receptor;
						$('#anular_sri').attr("disabled", false);
						$("#resultados_anular").html('');
					} else {
						$.notify(objData.msg, "error");
					}
				}
				return false;
			}
		}

		function enviar_guia_sri(id) {
			var serie_guia = $("#serie_guia" + id).val();
			var secuencial_guia = $("#secuencial_guia" + id).val();
			var numero_guia = String("000000000" + secuencial_guia).slice(-9);
			var pagina = $("#pagina").val();
			if (confirm("Seguro desea enviar la guía de remisión " + serie_guia + '-' + numero_guia + " al SRI?")) {
				$.ajax({
					type: "POST",
					url: "../facturacion_electronica/procesarEnvioSri.php",
					data: "id_documento_sri=" + id + "&modo_envio=online&tipo_documento_sri=gr",
					beforeSend: function(objeto) {
						$("#loader").html("Enviando guía...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						$("#loader").html("");
						load(pagina);
					}
				});
			}
		}

		function cancelar_envio_sri(id) {
			var pagina = $("#pagina").val();
			if (confirm("Seguro desea cancelar el envio del documento al SRI?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/buscar_guias_remision.php?action=cancelar_envio_sri",
					data: "id=" + id + "&page=" + pagina,
					beforeSend: function(objeto) {
						$("#loader").html("Actualizando...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						$("#loader").html("");
					}
				})
			}
		}

		function actualizar_estado_sri() {
			var por = $("#por").val();
			var ordenado = $("#ordenado").val();
			var q = $("#q").val();
			var page = $("#pagina").val();
			$.ajax({
				url: '../ajax/buscar_guias_remision.php?action=buscar_guia&page=' + page + '&q=' + q + "&por=" + por + "&ordenado=" + ordenado,
				beforeSend: function(objeto) {
					$('#loader').html('');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			});
		}

		setInterval(actualizar_estado_sri, 5000); //para actualizar los estados del sri cada 5 segundos
	</script>