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
		<title>Cambio productos</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		include("../modal/cambio_producto.php");
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
						<button type='button' class="btn btn-info" data-toggle="modal" data-target="#cambio_producto" onclick="nuevo_cambio();"><span class="glyphicon glyphicon-plus"></span> Nuevo cambio</button>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Cambio de productos facturados</h4>
				</div>
				<div class="panel-body">
					<?php

					?>
					<form class="form-horizontal" method="POST">
						<div class="form-group row">
							<label for="q" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
								<input type="hidden" id="ordenado" value="id_cambio">
								<input type="hidden" id="por" value="desc">
								<div class="input-group">
									<input type="text" class="form-control" id="q" placeholder="Factura, producto..." onkeyup='load(1);'>
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
	<?php

} else {
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
	exit;
}
	?>

	<!-- <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/ordenado.js" type="text/javascript"></script>
	<script src="../js/notify.js"></script> -->
	<link rel="stylesheet" href="../css/jquery-ui.css"> <!--para que se vea con fondo blanco el autocomplete -->
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	</body>

	</html>
	<script>
		$('#fecha_cambio_producto').css('z-index', 1500);

		$(document).ready(function() {
			load(1);
		});

		jQuery(function($) {
			$("#fecha_cambio_producto").mask("99-99-9999");
		});

		$(function() {
			$("#fecha_cambio_producto").datepicker({
				dateFormat: "dd-mm-yy",
				firstDay: 1,
				dayNamesMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
				dayNamesShort: ["Dom", "Lun", "Mar", "Mie", "Jue", "Vie", "Sab"],
				monthNames: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio",
					"Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
				],
				monthNamesShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun",
					"Jul", "Ago", "Sep", "Oct", "Nov", "Dic"
				]
			});
		});

		function nuevo_cambio() {
			document.querySelector("#titleModalCambioProducto").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo cambio de producto";
			document.querySelector("#guardar_cambio_productos").reset();
			document.querySelector("#id_cambio_producto").value = "";
			document.querySelector("#btnActionFormCambioProducto").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextCambioProducto").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormCambioProducto').title = "Guardar";
			$.ajax({
				url: "../ajax/cambio_producto.php?action=nuevo_cambio",
				beforeSend: function(objeto) {
					$("#loader_cambio_producto").html("Actualizando...");
				},
				success: function(data) {
					$(".outer_div_cambio_producto").html(data).fadeIn('fast');
					$('#loader_cambio_producto').html('');
				}
			});
		}

		//cada que cambia el tipo de cambio
		/* $(function() {
			$('#tipo_cambio').change(function() {
				$("#id_cliente_cambio").val("");
				$("#cliente_cambio").val("");
				$.ajax({
					url: "../ajax/cambio_producto.php?action=nuevo_cambio",
					beforeSend: function(objeto) {
						$("#loader_cambio_producto").html("Actualizando...");
					},
					success: function(data) {
						$(".outer_div_cambio_producto").html(data).fadeIn('fast');
						$('#loader_cambio_producto').html('');
						document.getElementById('cliente_cambio').focus();
					}
				});

			});
		}) */

		//para buscar los clientes
		function buscar_clientes() {
			$("#cliente_cambio").autocomplete({
				source: '../ajax/clientes_autocompletar_facturados.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_cliente_cambio').val(ui.item.id);
					$('#cliente_cambio').val(ui.item.nombre);
					document.getElementById('producto_facturado').focus();
				}
			});

			$("#cliente_cambio").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_cliente_cambio").val("");
					$("#cliente_cambio").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#cliente_cambio").val("");
					$("#id_cliente_cambio").val("");
				}
			});
		}

		function load(page) {
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/cambio_producto.php?action=buscar_cambio_producto&page=' + page + '&q=' + q + "&ordenado=" + ordenado + "&por=" + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			})
		}


		//para buscar productos
		function buscar_producto_facturado() {
			var id_cliente = $("#id_cliente_cambio").val();
			var tipo_cambio = $("#tipo_cambio").val();
			if (id_cliente == "") {
				alert('Seleccione un cliente');
				document.getElementById('cliente_cambio').focus();
				return false
			}

			if (tipo_cambio == 'F') {
				$("#producto_facturado").autocomplete({
					source: '../ajax/productos_autocompletar_facturados.php?id_cliente=' + id_cliente,
					minLength: 2,
					select: function(event, ui) {
						event.preventDefault();
						$('#id_registro_facturado').val(ui.item.id_registro);
						$('#producto_facturado').val(ui.item.nombre);
						$('#cantidad_registrada').val(ui.item.cantidad);
						$('#cantidad_facturado').val(ui.item.cantidad);
					}
				});
			} else {
				$("#producto_facturado").autocomplete({
					source: '../ajax/productos_autocompletar_recambio.php?id_cliente=' + id_cliente,
					minLength: 2,
					select: function(event, ui) {
						event.preventDefault();
						$('#id_registro_facturado').val(ui.item.id_registro);
						$('#producto_facturado').val(ui.item.nombre);
						$('#cantidad_registrada').val(ui.item.cantidad);
						$('#cantidad_facturado').val(ui.item.cantidad);
					}
				});
			}
			//$( "#producto_facturado" ).autocomplete("widget").addClass("fixedHeight");
			$("#producto_facturado").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_registro_facturado").val("");
					$("#producto_facturado").val("");
					$("#cantidad_facturado").val("");
					$("#cantidad_registrada").val("");
				}

				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_registro_facturado").val("");
					$("#producto_facturado").val("");
					$("#cantidad_facturado").val("");
					$("#cantidad_registrada").val("");
				}

				if (event.keyCode == $.ui.keyCode.BACKSPACE) {
					$("#id_registro_facturado").val("");
					$("#producto_facturado").val("");
					$("#cantidad_facturado").val("");
					$("#cantidad_registrada").val("");
				}

			});
		}

		//para buscar productos en consignacion de ventas
		function buscar_producto_cv(id) {
			var cv = $("#numero_consignacion" + id).val();
			if (cv == "") {
				alert('Ingrese número de consignación');
				return false
			}

			$("#nombre_producto_cambio" + id).autocomplete({
				source: '../ajax/productos_autocompletar_cv.php?cv=' + cv,
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#nombre_producto_cambio' + id).val(ui.item.nombre);
					$('#cant_cambio' + id).val(ui.item.saldo);
					$('#cant_producto_cambio' + id).val(ui.item.saldo);
					$('#id_cv' + id).val(ui.item.id_cv);
					document.getElementById('cant_cambio' + id).focus();
				}
			});
			//$( "#nombre_producto_cambio" ).autocomplete("widget").addClass("fixedHeight");
			$("#nombre_producto_cambio" + id).on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_cv" + id).val("");
					$("#cant_cambio" + id).val("");
					$("#cant_producto_cambio" + id).val("");
					$("#nombre_producto_cambio" + id).val("");
				}

				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#cant_cambio" + id).val("");
					$("#cant_producto_cambio" + id).val("");
					$("#nombre_producto_cambio" + id).val("");
					$("#id_cv" + id).val("");

				}

				if (event.keyCode == $.ui.keyCode.BACKSPACE) {
					$("#id_cv" + id).val("");
					$("#cant_cambio" + id).val("");
					$("#cant_producto_cambio" + id).val("");
					$("#nombre_producto_cambio" + id).val("");

				}
			});
		}


		//eliminar registro completo de cambio de producto
		function eliminar_cambio_producto(codigo) {
			var q = $("#q").val();
			if (confirm("Realmente desea eliminar el registro?")) {
				$.ajax({
					type: "GET",
					url: "../ajax/cambio_producto.php",
					data: "action=eliminar_registro_total&codigo_unico=" + codigo,
					"q": q,
					beforeSend: function(objeto) {
						$("#resultados").html("Mensaje: Cargando...");
					},
					success: function(datos) {
						$('#resultados').html(datos);
					}
				});
			}
		}

		//elimina fila del nuevo registro de cambio de medidas
		function eliminar_fila(codigo) {
			var q = $("#q").val();
			if (confirm("Realmente desea eliminar el registro?")) {
				$.ajax({
					type: "GET",
					url: "../ajax/cambio_producto.php",
					data: "action=eliminar_registro_cambio_producto&codigo_unico=" + codigo,
					"q": q,
					beforeSend: function(objeto) {
						$("#muestra_detalle_cambio_productos").html("Mensaje: Cargando...");
					},
					success: function(datos) {
						$(".outer_div_cambio_producto").html(datos).fadeIn('fast');
						$('#muestra_detalle_cambio_productos').html('');
					}
				});
			}
		}

		//DETALLE de productos para cambiar
		function agregar_detalle_productos() {
			var id_cliente = $("#id_cliente_cambio").val();
			var id_registro_facturado = $("#id_registro_facturado").val();
			var cantidad = $("#cantidad_facturado").val();
			var tipo_cambio = $("#tipo_cambio").val();
			var cantidad_registrada = $("#cantidad_registrada").val();
			if (id_cliente == '') {
				alert('Seleccione un cliente');
				document.getElementById('cliente_cambio').focus();
				return false;
			}
			if (id_registro_facturado == '') {
				alert('Seleccione un producto');
				document.getElementById('producto_facturado').focus();
				return false;
			}
			if (cantidad == '') {
				alert('Ingrese cantidad');
				document.getElementById('cantidad_facturado').focus();
				return false;
			}
			if (cantidad > cantidad_registrada) {
				alert('La cantidad es mayor a la cantidad facturada');
				document.getElementById('cantidad_facturado').focus();
				return false;
			}
			if (isNaN(cantidad)) {
				alert('El dato ingresado en cantidad, no es un número');
				document.getElementById('cantidad_facturado').focus();
				return false;
			}
			if (cantidad <= 0) {
				alert('Ingrese una cantidad mayor a 0');
				document.getElementById('cantidad_facturado').focus();
				return false;
			}
			$.ajax({
				url: "../ajax/cambio_producto.php?action=agregar_detalle_productos&id_cliente=" + id_cliente + "&id_registro_facturado=" + id_registro_facturado + "&cantidad=" + cantidad + "&tipo_cambio=" + tipo_cambio,
				beforeSend: function(objeto) {
					$("#loader_cambio_producto").html("Mostrando...");
				},
				success: function(data) {
					$(".outer_div_cambio_producto").html(data).fadeIn('fast');
					$('#loader_cambio_producto').html('');
					$("#id_registro_facturado").val('');
					$("#producto_facturado").val('');
					$("#cantidad_facturado").val('');
					document.getElementById('producto_facturado').focus();
				}
			});
		}

		//para comparar el saldo de la cantidad que quiero cambiar 
		function cantidad_cambio(id) {
			var saldo = $("#cant_producto_cambio" + id).val();
			var cant_cambio = $("#cant_cambio" + id).val();

			if (cant_cambio > parseFloat(saldo)) {
				alert('El saldo actual es menor al ingresado.');
				$("#cant_cambio" + id).val('');
				document.getElementById('cant_cambio' + id).focus();
				return false;
			}

			if (cant_cambio <= 0) {
				alert('Ingrese valor mayor a cero');
				$("#cant_cambio" + id).val('');
				document.getElementById('cant_cambio' + id).focus();
				return false;
			}
		}
	</script>