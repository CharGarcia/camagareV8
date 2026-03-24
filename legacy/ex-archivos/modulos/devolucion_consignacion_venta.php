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
		<title>Retorno CV</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		include("../modal/enviar_documentos_mail.php");
		?>
		<style type="text/css">
			ul.ui-autocomplete {
				z-index: 1100;
			}
		</style>
	</head>

	<body>

		<div class="container-fluid">
			<div class="panel panel-warning">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<button type='submit' class="btn btn-warning" data-toggle="modal" data-target="#devolucion_consignacion_venta" onclick="limpiar_datos()"><span class="glyphicon glyphicon-plus"></span> Nuevo retorno</button>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Retornos de consignaciones en ventas</h4>
				</div>
				<div class="panel-body">
					<?php
					include("../modal/devolucion_consignacion_venta.php");
					include("../modal/consignacion_venta.php");
					?>
					<form class="form-horizontal" method="POST">
						<div class="form-group row">
							<div class="col-md-5">
								<input type="hidden" id="ordenado" value="numero_consignacion">
								<input type="hidden" id="por" value="desc">
								<div class="input-group">
									<span class="input-group-addon"><b>Buscar:</b></span>
									<input type="text" class="form-control input-sm" id="q" placeholder="Cliente, Número, Observaciones" onkeyup='load(1);'>
									<span class="input-group-btn">
										<button type="button" class="btn btn-default input-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
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
	<link rel="stylesheet" href="../css/jquery-ui.css"> <!--para que se vea con fondo blanco el autocomplete -->
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/ordenado.js" type="text/javascript"></script>
	</body>

	</html>
	<script>
		$(document).ready(function() {
			load(1);
		});

		jQuery(function($) {
			$("#fecha_devolucion_consignacion_venta").mask("99-99-9999");
		});


		function load(page) {
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_devolucion_consignacion_venta.php?action=devolucion_consignacion_venta&page=' + page + '&q=' + q + "&ordenado=" + ordenado + "&por=" + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');

				}
			})
		}

		function limpiar_datos() {
			document.querySelector("#guardar_devolucion_consignacion_venta").reset();
			$(".outer_div_detalle_consignacion_venta").html('');

			$.ajax({
				url: "../ajax/detalle_consignaciones.php?action=nueva_devolucion",
				beforeSend: function(objeto) {
					$("#resultados").html("Cargando...");
				},
				success: function(data) {
					$('#resultados').html('');
				}
			});

		}


		//muestra la consignacion venta
		function mostrar_consignacion_venta() {
			var numero_cv = $("#numero_cv").val();
			if (numero_cv == "") {
				alert('Ingrese número de consignación de venta');
				$("#numero_cv").focus();
			} else {
				$.ajax({
					url: "../ajax/detalle_consignaciones.php?action=muestra_detalle_consignacion_para_devolucion&numero_cv=" + numero_cv,
					beforeSend: function(objeto) {
						$("#muestra_detalle_devolucion_consignacion_venta").html("Cargando...");
					},
					success: function(data) {
						$(".outer_div_detalle_consignacion_venta").html(data).fadeIn('fast');
						$('#muestra_detalle_devolucion_consignacion_venta').html('');
					}
				});
			}
		}

		//eliminar opcion consignacion_ventas
		function eliminar_devolucion_consignacion_ventas(codigo, numero) {
			var q = $("#q").val();
			if (confirm("Realmente desea anular el registro?")) {
				// Captura el valor ingresado por el usuario en un campo de entrada
				var valorInput = prompt("Ingrese número de retorno para confirmar la anulación:");

				// Verifica si el usuario ingresó un valor
				if (valorInput === null || valorInput.trim() === "") {
					alert("Debe ingresar un número para anular.");
					return; // Detenemos la ejecución si el valor es inválido
				}

				// Compara si el número ingresado es igual al número recibido
				if (valorInput !== numero) {
					alert("El número de retorno ingresado no coincide.");
					return; // Detenemos la ejecución si los números no coinciden
				}

				$.ajax({
					type: "GET",
					url: "../ajax/detalle_consignaciones.php",
					data: {
						action: "eliminar_devolucion_consignacion_ventas",
						codigo_unico: codigo,
						q: q,
						valor_adicional: valorInput // Enviamos el valor ingresado por el usuario
					},
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

		//DETALLE devolucion consignacion_ventas
		function mostrar_detalle_devolucion_consignacion(codigo) {
			$.ajax({
				url: "../ajax/detalle_consignaciones.php?action=mostrar_detalle_devolucion_consignacion&codigo_unico=" + codigo,
				beforeSend: function(objeto) {
					$("#loaderdet").html("Iniciando...");
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('fast');
					$('#loaderdet').html('');
				}
			});
		}


		//para guardar la devolucion de consignacion venta
		function guardar_devolucion() {
			$("#guardar_datos").hide();
			var fecha_devolucion_consignacion_venta = $("#fecha_devolucion_consignacion_venta").val();
			var numero_cv = $("#numero_cv").val();
			var observacion_devolucion_consignacion_venta = $("#observacion_devolucion_consignacion_venta").val();
			var serie_devolucion_consignacion = $("#serie_devolucion_consignacion").val();
			$.ajax({
				type: "POST",
				url: "../ajax/guardar_devolucion_consignacion_venta.php",
				data: "fecha_devolucion_consignacion_venta=" + fecha_devolucion_consignacion_venta +
					"&numero_cv=" + numero_cv + "&observacion_devolucion_consignacion_venta=" + observacion_devolucion_consignacion_venta +
					"&serie_devolucion_consignacion=" + serie_devolucion_consignacion,
				beforeSend: function(objeto) {
					$("#loader_devolucion").html("Guardando... ");
				},
				success: function(datos) {
					$("#mensajes_devolucion_consignacion_venta").html(datos);
					$("#loader_devolucion").html('');
					$("#guardar_datos").show();
					load(1);
				}
			});
			//event.preventDefault();
		}

		//para verificar que la cantidad ingresada no sea mayor al saldo
		function cantidad_devolucion(id) {
			var devolucion = $("#devolucion" + id).val();
			var saldo = $("#saldo" + id).val();

			if (devolucion > parseFloat(saldo)) {
				alert('El valor de devolución es mayor al saldo existente.');
				$("#devolucion" + id).val('');
				document.getElementById('devolucion' + id).focus();
				return false;
			}

			if (devolucion < 0) {
				alert('Ingrese valor mayor a cero');
				$("#devolucion" + id).val('');
				document.getElementById('devolucion' + id).focus();
				return false;
			}
			//agregar_items_tmp(id);
		}

		function agregar_items_tmp(id) {
			var cantidad = $("#devolucion" + id).val();
			var numero_consignacion = $("#numero_cv").val();
			var serie_factura = $("#serie_devolucion_consignacion").val();
			$.ajax({
				type: "POST",
				url: "../ajax/detalle_consignaciones.php?action=agregar_item_a_devolver",
				data: "id=" + id + "&cantidad=" + cantidad + "&numero_consignacion=" + numero_consignacion + "&serie_factura=" + serie_factura,
				beforeSend: function(objeto) {
					$("#mensajes_devolucion_consignacion_venta").html("Calculando... ");
				},
				success: function(datos) {
					$("#mensajes_devolucion_consignacion_venta").html('');
				}
			});
			event.preventDefault();
		}


		function enviar_retornos_mail(id, mail_receptor) {
			$("#id_documento").val(id);
			$("#mail_receptor").val(mail_receptor);
			$("#tipo_documento").val("retorno_consignacion");
		};
	</script>