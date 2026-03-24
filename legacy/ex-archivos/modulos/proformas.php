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
		<title>Proformas</title>
		<?php include("../paginas/menu_de_empresas.php");
		include("../modal/enviar_documentos_mail.php");
		include("../modal/detalle_documento.php");
		include("../modal/editar_factura_e.php");
		include("../modal/formas_de_pago.php");
		?>
	</head>

	<body>

		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<form method="POST" action="../modulos/nueva_proforma.php">
							<?php
							if (getPermisos($con, $id_usuario, $ruc_empresa, 'proformas')['w'] == 1) {
							?>
								<button type='submit' class="btn btn-info"><span class="glyphicon glyphicon-plus"></span> Nueva proforma</button>
							<?php
							}
							?>
						</form>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Proformas</h4>
				</div>

				<ul class="nav nav-tabs nav-justified">
					<li class="active"><a data-toggle="tab" href="#proformas">Proformas</a></li>
					<li><a data-toggle="tab" href="#detalle_proformas">Detalle de proformas</a></li>
					<li><a data-toggle="tab" href="#detalle_adicionales">Detalles adicionales</a></li>
				</ul>

				<div class="tab-content">
					<div id="proformas" class="tab-pane fade in active">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<label for="q" class="col-md-1 control-label">Buscar:</label>
									<div class="col-md-5">
										<input type="hidden" id="ordenado" value="id_encabezado_proforma">
										<input type="hidden" id="por" value="desc">
										<div class="input-group">
											<input type="text" class="form-control" id="q" placeholder="Cliente, serie, factura, fecha, ruc, estado" onkeyup='load(1);'>
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

					<div id="detalle_proformas" class="tab-pane fade">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<label for="d" class="col-md-1 control-label">Buscar:</label>
									<div class="col-md-5">
										<div class="input-group">
											<input type="text" class="form-control" id="d" placeholder="Productos, servicios, código" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
											</span>
										</div>
									</div>
									<span id="loader_detalles"></span>
								</div>
							</form>
							<div id="resultados_detalles_proformas"></div><!-- Carga los datos ajax -->
							<div class='outer_div_detalles'></div><!-- Carga los datos ajax -->
						</div>
					</div>
					<div id="detalle_adicionales" class="tab-pane fade">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<label for="d" class="col-md-1 control-label">Buscar:</label>
									<div class="col-md-5">
										<div class="input-group">
											<input type="text" class="form-control" id="a" placeholder="Detalle adicionales" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
											</span>
										</div>
									</div>
									<span id="loader_adicionales"></span>
								</div>
							</form>
							<div id="resultados_detalles_adicionales"></div><!-- Carga los datos ajax -->
							<div class='outer_div_adicionales'></div><!-- Carga los datos ajax -->
						</div>
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
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/ordenado.js"></script>
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

		function load(page) {
			var por = $("#por").val();
			var ordenado = $("#ordenado").val();
			var q = $("#q").val();
			var d = $("#d").val();
			var a = $("#a").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_proformas.php?action=proformas&page=' + page + '&q=' + q + "&por=" + por + "&ordenado=" + ordenado,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			});
		};

		function anular_proforma(id_proforma) {
			var q = $("#q").val();
			var secuencial = $("#secuencial_proforma" + id_proforma).val();

			if (confirm("Realmente desea anular la proforma " + secuencial + " ?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/buscar_proformas.php",
					data: "action=anular_proforma&id_proforma=" + id_proforma,
					"q": q,
					beforeSend: function(objeto) {
						$("#loader").html("Actualizando...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						$("#loader").html("");
						load(1);
					}
				});
			};

		}

		function enviar_mail_proforma(id) {
			var mail_receptor = $("#mail_cliente" + id).val();
			$("#id_documento_proforma").val(id);
			$("#mail_receptor_proforma").val(mail_receptor);
			$("#tipo_documento_proforma").val("proforma");
		}

		function validarEmail(valor) {
			emailRegex = /^[-\w.%+]{1,64}@(?:[A-Z0-9-]{1,63}\.){1,125}[A-Z]{2,63}$/i;

			// if (emailRegex.test(valor)){
			if (valor != "") {
				return "correcto";
			} else {
				return "incorrecto";
			}
		}

		//para enviar la proforma al sri
		$("#documento_proforma").submit(function(event) {
			$('#enviar_proforma').attr("disabled", true);
			var id_documento = $("#id_documento_proforma").val();
			var mail_receptor = $("#mail_receptor_proforma").val();
			var tipo_documento = "proforma";
			var pagina = $("#pagina").val(); //esta variable me la trae de buscar proformas

			//modificar el correo en la proforma
			$.post('../ajax/buscar_proformas.php', {
				action: 'actualizar_mail_cliente',
				id_documento: id_documento,
				mail: mail_receptor
			}).done(function(respuesta) {
				var actualizado = respuesta;
				//		alert(respuesta);
			});

			$.ajax({
				type: "POST",
				url: '../facturacion_electronica/enviarComprobantesSri.php',
				data: 'id_documento_sri=' + id_documento + '&tipo_documento_sri=' + tipo_documento + '&modo_envio=online',
				beforeSend: function(objeto) {
					$('#resultados_ajax_proforma').html('<div class="progress"><div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" style="width:100%;">Enviando proforma espere por favor...</div></div>');
				},
				success: function(datos) {
					$("#resultados_ajax_proforma").html(datos);
					$('#enviar_proforma').attr("disabled", false);
					load(pagina);
				}
			});


			event.preventDefault();
		});


		//para que cuando se cierre el modal de enviar mail se reseteen los datos y se limpie
		/* $("#cerrar_mail").click(function(){
			$("#resultados_ajax_mail").empty();
		    }); */

		function detalle_proforma(id) {
			var codigo_unico = id;
			$("#loaderdet").fadeIn('slow');
			$.ajax({
				url: '../ajax/detalle_documento.php?action=proformas&codigo_unico=' + codigo_unico,
				beforeSend: function(objeto) {
					$('#loaderdet').html('<img src="../image/ajax-loader.gif"> Cargando detalle de proforma...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('slow');
					$('#loaderdet').html('');
				}
			})
		}


		//facturar proforma
		function facturar_proforma(id_documento, codigo, serie, secuencial) {
			var q = $("#q").val();
			var tipo_documento = 'proforma';

			if (confirm("Realmente desea facturar la proforma " + serie + "-" + secuencial + "?")) {
				$.post('../ajax/buscar_ultima_factura.php', {
					serie_fe: serie
				}).done(function(respuesta) {
					var factura_final = respuesta;
					//generar el pdf de la proforma

					//modificar el correo en la proforma
					//$.post( '../ajax/buscar_proformas.php', {action: 'actualizar_mail_cliente', id_documento: id_documento, mail: '' }).done( function( respuesta ){
					//	var actualizado = respuesta;
					//	});

					$.ajax({
						type: "POST",
						url: '../facturacion_electronica/enviarComprobantesSri.php',
						data: 'id_documento_sri=' + id_documento + '&tipo_documento_sri=' + tipo_documento + '&modo_envio=offline',
						beforeSend: function(objeto) {
							$('#loader').html('Generando pdf...');
						},
						success: function(datos) {
							$("#loader").html(datos);
							//$("#loader").html('');
						}
					});


					//hasta aqui generar el pdf

					$.ajax({
						type: "GET",
						url: "../ajax/buscar_proformas.php",
						data: "action=facturar_proforma&codigo_unico=" + codigo + "&factura_final=" + factura_final + "&serie=" + serie + "&secuencial=" + secuencial,
						"q": q,
						beforeSend: function(objeto) {
							$("#loader").html("Cargando...");
						},
						success: function(datos) {
							$("#resultados").html(datos);
							$("#loader").html('');
							load(1);
						}
					});
				});

			}

		}


		function generar_pedido(id_documento, codigo, serie, secuencial) {
			var q = $("#q").val();

			if (confirm("Realmente desea generar un pedido?")) {
				$.ajax({
					type: "GET",
					url: "../ajax/buscar_proformas.php",
					data: "action=generar_pedido&codigo_unico=" + codigo + "&serie=" + serie + "&secuencial=" + secuencial,
					"q": q,
					beforeSend: function(objeto) {
						$("#loader").html("Cargando...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						$("#loader").html('');
						load(1);
					}
				});
			}
		}
	</script>