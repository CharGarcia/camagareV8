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
		<title>Facturación</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		include("../modal/consignacion_venta.php");
		?>
		<style type="text/css">
			ul.ui-autocomplete {
				z-index: 1100;
			}
		</style>
	</head>

	<body>
		<div class="container-fluid">
			<div class="panel panel-success">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<button type='submit' class="btn btn-success" data-toggle="modal" data-target="#facturacion_consignacion_venta" onclick="resetea_datos();"><span class="glyphicon glyphicon-plus"></span> Nueva Factura</button>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Facturación de consignaciones en ventas</h4>
				</div>
				<div class="panel-body">
					<?php
					include("../modal/facturacion_consignacion_venta.php");
					include("../modal/detalle_numero_consignacion.php");
					?>
					<form class="form-horizontal" method="POST">
						<div class="form-group row">
							<div class="col-md-5">
								<div class="input-group">
									<span class="input-group-addon"><b>Buscar:</b></span>
									<input type="hidden" id="ordenado" value="numero_consignacion">
									<input type="hidden" id="por" value="desc">
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
	<script src="../js/ordenado.js" type="text/javascript"></script>
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>

	</body>

	</html>
	<script>
		$(document).ready(function() {
			load(1);
		});

		jQuery(function($) {
			$("#fecha_factura_consignacion_salida").mask("99-99-9999");
		});


		function load(page) {
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_facturacion_consignacion_venta.php?action=facturacion_consignacion_venta&page=' + page + '&q=' + q + "&ordenado=" + ordenado + "&por=" + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');

				}
			})
		}

		//para buscar los clientes
		function buscar_clientes() {
			$("#cliente_factura_consignacion_venta").autocomplete({
				source: '../ajax/clientes_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_cliente_factura_consignacion_venta').val(ui.item.id);
					$('#cliente_factura_consignacion_venta').val(ui.item.nombre);
					$('#vendedor').val(ui.item.id_vendedor);
					document.getElementById('observacion_factura_consignacion_venta').focus();
				}
			});

			$("#cliente_factura_consignacion_venta").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_cliente_factura_consignacion_venta").val("");
					$("#cliente_factura_consignacion_venta").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#cliente_factura_consignacion_venta").val("");
					$("#id_cliente_factura_consignacion_venta").val("");
				}
			});
		}

		function eliminar_item_factura_consignacion(id) {
			$.ajax({
				url: "../ajax/detalle_consignaciones.php?action=eliminar_item_factura_consignacion&id_registro=" + id,
				beforeSend: function(objeto) {
					$("#muestra_detalle_facturacion_consignacion").html("Eliminando item...");
				},
				success: function(data) {
					$('#muestra_detalle_facturacion_consignacion').html('');
					$('.outer_div_facturacion_consignacion').html(data);
					//$(".outer_div_facturacion_consignacion").html(data).fadeIn('fast');
				}
			});
		}

		function resetea_datos() {
			document.querySelector("#guardar_facturacion_consignacion_venta").reset();
			$.ajax({
				url: "../ajax/detalle_consignaciones.php?action=limpiar_info_entrada",
				beforeSend: function(objeto) {
					$("#mensajes_facturacion_consignacion_venta").html("Iniciando...");
				},
				success: function(data) {
					$(".outer_div_facturacion_consignacion").html(data).fadeIn('fast');
					$('#mensajes_facturacion_consignacion_venta').html('');
				}
			});
		}

		//para guardar la consignacion de opciones de ventas, devolucion o factura
		$("#guardar_facturacion_consignacion_venta").submit(function(event) {
			$("#guardar_datos").hide();
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: "../ajax/guardar_factura_consignacion_venta.php",
				data: parametros,
				beforeSend: function(objeto) {
					$("#loader_facturacion").html("Guardando...");
				},
				success: function(datos) {
					$("#loader_facturacion").html(datos);
					$("#guardar_datos").show();
					load(1);
				}
			});
			event.preventDefault();
		});


		//eliminar factura consignacion_ventas
		function eliminar_factura_consignacion_venta(codigo) {
			var q = $("#q").val();
			if (confirm("Realmente desea eliminar la factura?")) {
				$.ajax({
					type: "GET",
					url: "../ajax/detalle_consignaciones.php",
					data: "action=eliminar_factura_consignacion_venta&codigo_unico=" + codigo,
					"q": q,
					beforeSend: function(objeto) {
						$("#loader").html("Mensaje: Cargando...");
					},
					success: function(datos) {
						$("#loader").html("");
						$("#resultados").html(datos);
						load(1);
					}
				});
			}
		}


		//DETALLE de factura
		function mostrar_detalle_factura_consignacion(codigo) {
			$(".outer_divdet").html('');
			$.ajax({
				url: "../ajax/detalle_consignaciones.php?action=detalle_factura&codigo_unico=" + codigo,
				beforeSend: function(objeto) {
					$("#loaderdet").html("Cargando...");
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('fast');
					$('#loaderdet').html('');
				}
			});
		}

		//cuando doy clic en mostrar detalle de consignacion para mostrar todos los items a facturar
		function mostrar_detalle_numero_consignacion() {
			$(".outer_div_numero_consignacion").html('');
			var numero_consignacion = $("#numero_consignacion").val();
			var serie_factura = $("#serie_factura_consignacion").val();
			if (numero_consignacion == '') {
				alert('Ingrese un número de consignación');
				document.getElementById('numero_consignacion').focus();
				return false;
			}
			if (serie_factura == null) {
				alert('Seleccione una serie o sucursal');
				document.getElementById('serie_factura_consignacion').focus();
				return false;
			}

			$.ajax({
				url: "../ajax/detalle_consignaciones.php?action=muestra_detalle_consignacion_para_facturacion&numero_consignacion=" + numero_consignacion + "&serie_factura=" + serie_factura,
				beforeSend: function(objeto) {
					$("#loaderdetnumfac").html("Cargando...");
				},
				success: function(data) {
					$(".outer_div_numero_consignacion").html(data).fadeIn('fast');
					$('#loaderdetnumfac').html('');
				}
			});
		}

		function precio_facturacion(id) {
			var precio = $("#precio" + id).val();
			var cantidad = $("#cantidad" + id).val();
			var descuento = $("#descuento" + id).val();

			if (isNaN(precio)) {
				alert('El dato ingresado, no es un número');
				$("#precio" + id).val('');
				document.getElementById('precio' + id).focus();
				return false;
			}

			if (precio < 0) {
				alert('Ingrese valor mayor a cero');
				$("#precio" + id).val('');
				document.getElementById('precio' + id).focus();
				return false;
			}
			$("#subtotal" + id).val(Number.parseFloat(cantidad * precio - descuento).toFixed(2));
			agregar_items_tmp(id);
		}

		function cantidad_facturacion(id) {
			var cantidad = $("#cantidad" + id).val();
			var saldo = $("#saldo" + id).val();
			var precio = $("#precio" + id).val();
			var descuento = $("#descuento" + id).val();

			if (isNaN(cantidad)) {
				alert('El dato ingresado, no es un número');
				$("#cantidad" + id).val('');
				document.getElementById('cantidad' + id).focus();
				return false;
			}

			if (cantidad > parseFloat(saldo)) {
				alert('El cantidad es mayor al saldo existente.');
				$("#cantidad" + id).val('');
				document.getElementById('cantidad' + id).focus();
				return false;
			}

			if (cantidad < 0) {
				alert('Ingrese valor mayor a cero');
				$("#cantidad" + id).val('');
				document.getElementById('cantidad' + id).focus();
				return false;
			}
			$("#subtotal" + id).val(Number.parseFloat(cantidad * precio - descuento).toFixed(2));
			agregar_items_tmp(id);
		}

		function descuento_facturacion(id) {
			var descuento = $("#descuento" + id).val();
			var precio = $("#precio" + id).val();
			var cantidad = $("#cantidad" + id).val();

			if (isNaN(descuento)) {
				alert('El dato ingresado, no es un número');
				$("#descuento" + id).val('');
				document.getElementById('descuento' + id).focus();
				return false;
			}

			if (descuento > parseFloat(precio * cantidad)) {
				alert('El descuento es mayor al subtotal.');
				$("#descuento" + id).val('');
				document.getElementById('descuento' + id).focus();
				return false;
			}

			if (descuento < 0) {
				alert('Ingrese valor mayor a cero');
				$("#descuento" + id).val('');
				document.getElementById('descuento' + id).focus();
				return false;
			}
			$("#subtotal" + id).val(Number.parseFloat(cantidad * precio - descuento).toFixed(2));
			agregar_items_tmp(id);
		}

		function agregar_items_tmp(id) {
			var descuento = $("#descuento" + id).val();
			var precio = $("#precio" + id).val();
			var cantidad = $("#cantidad" + id).val();

			var numero_consignacion = $("#numero_consignacion").val();
			var serie_factura = $("#serie_factura_consignacion").val();
			$.ajax({
				type: "POST",
				url: "../ajax/detalle_consignaciones.php?action=agregar_detalle_facturacion_consignacion_venta",
				data: "id=" + id + "&cantidad=" + cantidad + "&precio=" + precio + "&descuento=" + descuento + "&numero_consignacion=" + numero_consignacion + "&serie_factura=" + serie_factura,
				beforeSend: function(objeto) {
					$("#loaderdetnumfac").html("Calculando... ");
				},
				success: function(datos) {
					$("#loaderdetnumfac").html('');
				}
			});
			event.preventDefault();
		}

		//pasar del arreglo de sesion a la factura tmp
		function agregar_items_factura() {
			$(".outer_div_facturacion_consignacion").html('');
			var numero_consignacion = $("#numero_consignacion").val();
			var serie_factura = $("#serie_factura_consignacion").val();
			$('#btn_agregar_items_factura').attr("disabled", true);
			$.ajax({
				type: "POST",
				url: "../ajax/detalle_consignaciones.php?action=items_a_facturar",
				data: "numero_consignacion=" + numero_consignacion + "&serie_factura=" + serie_factura,
				beforeSend: function(objeto) {
					$("#loaderdetnumfac").html("Agregando... ");
				},
				success: function(datos) {
					$(".outer_div_facturacion_consignacion").html(datos).fadeIn('fast');
					mostrar_detalle_numero_consignacion();
					$('#loaderdetnumfac').html('');
					document.querySelector("#agregar_items_facturacion_consignacion").reset();
					$('#btn_agregar_items_factura').attr("disabled", false);
				}
			});
		}


		function precios(id) {
			var precio_seleccionado = $("#lista_precios" + id).val();
			$("#precio" + id).val(precio_seleccionado);
			var descuento = $("#descuento" + id).val();
			var precio = $("#precio" + id).val();
			var cantidad = $("#cantidad" + id).val();
			$("#subtotal" + id).val(Number.parseFloat(cantidad * precio - descuento).toFixed(2));
			agregar_items_tmp(id);
			document.getElementById('cantidad' + id).focus();
		}
	</script>