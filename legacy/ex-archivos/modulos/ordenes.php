<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

?>
	<!DOCTYPE html>
	<html lang="en">

	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>Órdenes</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		include("../modal/detalle_ordenes_mesa.php");
		include("../modal/clientes.php");
		ini_set('date.timezone', 'America/Guayaquil');
		?>
		<style type="text/css">
			ul.ui-autocomplete {
				z-index: 1100;
			}
		</style>
	</head>

	<body>
		<div class="container-fluid">
			<div class="col-md-12">
				<div class="panel panel-info">
					<div class="panel-body" style="background-color: #DDDFDF;">
						<input type="hidden" id="mesa_tmp">
						<input type="hidden" id="ordenado" value="nombre_mesa">
						<input type="hidden" id="por" value="asc">
						<div id="resultados"></div><!-- Carga los datos ajax -->
						<div class="outer_div"></div><!-- Carga los datos ajax -->

						<h5><span id="loader"></span><i>*Para mover, arrastre desde la parte celeste izquierda o derecha de la mesa, y ubique en la posición deseada.</i> <span><button type="button" onclick="reset_posiciones();" class="btn btn-info btn-sm" title='Reset posiciones'><i class='glyphicon glyphicon-refresh'></i> Reset posiciones</button></span></h5>

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
	<link rel="stylesheet" href="../css/jquery-ui.css"> <!--para que se vea con fondo blanco el autocomplete -->
	<script src="../js/jquery-1.12.4.js"></script>
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/interact.min.js"></script> <!--para arrastrar los div -->
	</body>

	</html>
	<style>
		#arrastrar {
			width: 13%;
			min-height: 0.8em;
			margin: 1%;
			background-color: #80bced;
			border-radius: 0.75em;
			padding-right: 2%;
			padding-left: 2%;
			touch-action: none;
			user-select: none;
			position: absolute;
			left: 0;
			top: 0;
		}
	</style>
	<script>
		//para copiar el id de mesa cuando se pasa el mouse por arriba de la mesa
		function muestra_id_mesa(id_mesa) {
			var id_mesa = id_mesa;
			$("#mesa_tmp").val(id_mesa);
		}


		// target elements with the "draggable" class
		interact('.arrastrar').draggable({
			// enable inertial throwing
			inertia: true,
			// mantiene a las cajas dentro del padre
			modifiers: [
				interact.modifiers.restrictRect({
					restriction: 'parent',
					endOnly: true
				})
			],

			// enable autoScroll
			autoScroll: true,

			// call this function on every dragmove event
			onmove: dragMoveListener,
			// call this function on every dragend event
			onend: function(event) {
				//preguntar si quiere guardar y enviar a la base de datos
				//if (confirm("Desea guardar la nueva ubicación de la mesa?")) {
				var mesa = $("#mesa_tmp").val();

				var x_anterior = $("#id_x_anterior" + mesa).val();
				var y_anterior = $("#id_y_anterior" + mesa).val();

				var target = event.target;
				var x = ((parseFloat(target.getAttribute('data-x')) || 0) + event.dx).toFixed(2);
				var y = ((parseFloat(target.getAttribute('data-y')) || 0) + event.dy).toFixed(2);

				x = Number(x) + Number(x_anterior);
				y = Number(y) + Number(y_anterior);

				x = x.toFixed(2);
				y = y.toFixed(2);

				$("#resultados").fadeIn('fast');
				$.ajax({
					url: '../ajax/detalle_mesas.php?action=guardar_posiciones_mesas&id_mesa=' + mesa + '&ejex=' + x + '&ejey=' + y,
					beforeSend: function(objeto) {
						$('#resultados').html('Cargando...');
					},
					success: function(data) {
						$(".outer_div").html(data).fadeIn('fast');
						$('#resultados').html('');
						load(1);
					}
				});
				//} 
			}
		})

		//else {load(1);}

		function dragMoveListener(event) {
			var target = event.target
			// keep the dragged position in the data-x/data-y attributes
			var x = (parseFloat(target.getAttribute('data-x')) || 0) + event.dx
			var y = (parseFloat(target.getAttribute('data-y')) || 0) + event.dy

			// translate the element
			target.style.webkitTransform =
				target.style.transform =
				'translate(' + x + 'px, ' + y + 'px)'

			// update the position attributes
			target.setAttribute('data-x', x)
			target.setAttribute('data-y', y)
		}


		//window.dragMoveListener = dragMoveListener

		//hasta aqui el mobible
		$(document).ready(function() {
			//$('#generar_factura').modal({backdrop: 'static', keyboard: false});
			load(1);
		});

		jQuery(function($) {
			$("#fecha_mesa").mask("99-99-9999");
		});

		$(function() {
			$("#fecha_mesa").datepicker({
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

		function load(page) {
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_mesas_ordenes.php?action=mesas_ordenes&ordenado=' + ordenado + '&por=' + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
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


		function carga_modal() {
			document.querySelector("#titleModalCliente").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo Cliente";
			document.querySelector("#guardar_cliente").reset();
			document.querySelector("#id_cliente").value = "";
			document.querySelector("#btnActionFormCliente").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextCliente").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormCliente').title = "Guardar cliente";
		}

		$("#guardar_cliente").submit(function(event) {
			$('#guardar_datos').attr("disabled", true);
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: "../ajax/clientes.php?action=guardar_cliente",
				data: parametros,
				beforeSend: function(objeto) {
					$("#resultados_ajax").html("Guardando...");
				},
				success: function(datos) {
					$("#resultados_ajax").html(datos);
					$('#guardar_datos').attr("disabled", false);
					//load(1);
				}
			});
			event.preventDefault();
		})


		//borrar datos del formulario de nuevo cliente
		$("#borrar_datos").click(function() {
			$("#id_cliente_mesa").val("");
			$("#ruc_cliente_directo").val("");
			$("#nombre_cliente_directo").val("");
			$("#telefono_cliente_directo").val("");
			$("#direccion_cliente_directo").val("");
			$("#plazo_cliente_directo").val("5");
			$("#email_cliente_directo").val("");
			$("#id_cliente_e").val("");
			$("#cliente_ordenes").val("");
			document.getElementById("ruc_cliente_directo").readOnly = false;
		});


		//para buscar los clientes
		function buscar_clientes() {
			$("#cliente_ordenes").autocomplete({
				source: '../ajax/clientes_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_cliente_mesa').val(ui.item.id);
					$('#cliente_ordenes').val(ui.item.nombre);
					$('#tipo_id_cliente').val(ui.item.tipo_id);
					$('#ruc_cliente').val(ui.item.ruc);
					$('#telefono_cliente').val(ui.item.telefono);
					$('#direccion_cliente').val(ui.item.direccion);
					$('#plazo_credito').val(ui.item.plazo);
					$('#email_cliente').val(ui.item.email);
					document.getElementById('nombre_producto_servicio').focus();
				}
			});

			$("#cliente_ordenes").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_cliente_mesa").val("");
					$("#cliente_ordenes").val("");
					$("#tipo_id_cliente").val("");
					$("#ruc_cliente").val("");
					$("#telefono_cliente").val("");
					$("#direccion_cliente").val("");
					$("#plazo_credito").val("");
					$("#email_cliente").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#cliente_ordenes").val("");
					$("#id_cliente_mesa").val("");
					$("#tipo_id_cliente").val("");
					$("#ruc_cliente").val("");
					$("#telefono_cliente").val("");
					$("#direccion_cliente").val("");
					$("#plazo_credito").val("");
					$("#email_cliente").val("");
				}
			});
		}
		//para buscar productos
		function buscar_productos() {
			$("#nombre_producto_servicio").autocomplete({
				source: '../ajax/productos_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_producto_mesa').val(ui.item.id);
					$('#nombre_producto_servicio').val(ui.item.nombre);
					$('#precio_agregar').val(ui.item.precio);
					$('#precio_tmp').val(ui.item.precio);
					$('#tipo_producto_agregar').val(ui.item.tipo);

					var tipo_producto = $("#tipo_producto_agregar").val();
					var configuracion_inventario = document.getElementById('inventario').value;
					var configuracion_medida = document.getElementById('muestra_medida').value;
					var configuracion_lote = document.getElementById('muestra_lote').value;
					var configuracion_bodega = document.getElementById('muestra_bodega').value;
					//var configuracion_vencimiento=document.getElementById('muestra_vencimiento').value;
					var producto = $("#id_producto_mesa").val();

					//para traer todos los precios que esten dentro de la fecha permitida
					$.post('../ajax/select_opciones_inventario.php', {
						opcion: 'precios',
						id_producto: producto
					}).done(function(res_precios) {
						$("#select_precio").html(res_precios);
					});


					if (tipo_producto == "02") {
						document.getElementById("titulo_bodega").style.display = "none";
						document.getElementById("titulo_lote").style.display = "none";
						//document.getElementById("titulo_caducidad").style.display="none";
						document.getElementById("titulo_medida").style.display = "none";
						document.getElementById("titulo_existencia").style.display = "none";
						document.getElementById("lista_bodega").style.display = "none";
						document.getElementById("lista_lote").style.display = "none";
						//document.getElementById("lista_caducidad").style.display="none";
						document.getElementById("lista_medida").style.display = "none";
						document.getElementById("lista_existencia").style.display = "none";
					}
					if (tipo_producto == "01" && (configuracion_inventario == 'NO' || configuracion_inventario == '')) {
						document.getElementById("titulo_bodega").style.display = "none";
						document.getElementById("titulo_lote").style.display = "none";
						//document.getElementById("titulo_caducidad").style.display="none";
						document.getElementById("titulo_medida").style.display = "";
						document.getElementById("lista_bodega").style.display = "none";
						document.getElementById("lista_lote").style.display = "none";
						//document.getElementById("lista_caducidad").style.display="none";
						document.getElementById("lista_medida").style.display = "";
						var producto = $("#id_producto_mesa").val();

						//cuando trae se busca el producto me trae que tipo de medida tiene
						$.post('../ajax/select_tipo_medida.php', {
							id_producto: producto
						}).done(function(res_tipos_medidas) {
							$("#medida_agregar").html(res_tipos_medidas);
						});
					}

					//aqui controla cuando se selecciona producto y trabaja con inventario
					if (tipo_producto == "01" && configuracion_inventario == 'SI') {

						if (configuracion_medida == "SI") {
							document.getElementById("titulo_medida").style.display = "";
							document.getElementById("lista_medida").style.display = "";
						}
						if (configuracion_lote == 'SI') {
							document.getElementById("titulo_lote").style.display = "";
							document.getElementById("lista_lote").style.display = "";
						}
						if (configuracion_bodega == 'SI') {
							document.getElementById("titulo_bodega").style.display = "";
							document.getElementById("lista_bodega").style.display = "";
						}
						/*
						if(configuracion_vencimiento=='SI'){
							document.getElementById("titulo_caducidad").style.display="";
							document.getElementById("lista_caducidad").style.display="";
						}
							*/
						document.getElementById("precio_agregar").disabled = false;
						document.getElementById("existencia_producto").disabled = true;

						$("#existencia_producto").val("0");
						var bodega = $("#bodega_agregar").val();
						var producto = $("#id_producto_mesa").val();

						//cuando trae se busca el producto me trae que tipo de medida tiene
						$.post('../ajax/select_tipo_medida.php', {
							id_producto: producto
						}).done(function(res_id_medidas) {
							$("#medida_agregar").html(res_id_medidas);
						});
						//

						//para que se cargue el stock del producto al momento de buscar el producto dependiendo de la bodega que esta seleeccionada por default
						$.post('../ajax/saldo_producto_restaurante.php', {
							id_bodega: bodega,
							id_producto: producto
						}).done(function(respuesta) {
							var saldo_producto = respuesta;
							$("#existencia_producto").val(saldo_producto);
							$('#stock_tmp').val(saldo_producto);
						});

						//para traer todos los lotes en base a una bodega al momento de buscar un producto
						$.post('../ajax/select_opciones_inventario.php', {
							opcion: 'lote',
							id_producto: producto,
							bodega: bodega
						}).done(function(res_opciones_lote) {
							$("#lote_agregar").html(res_opciones_lote);
						});

						//para traer todos las caducidades en base a una bodega al momento de buscar un producto
						/*
						$.post( '../ajax/select_opciones_inventario.php', {opcion:'caducidad', id_producto: producto, bodega: bodega}).done( function( res_opciones_caducidad ){
									$("#caducidad_agregar").html(res_opciones_caducidad);
								});											
						*/

						document.getElementById("titulo_existencia").style.display = "";
						document.getElementById("lista_existencia").style.display = "";

					}
					//hasta aqui me controla si trabaja con inventario
					document.getElementById('cantidad_agregar').focus();
				}
			});

			//$("#nombre_producto_servicio").autocomplete("widget").addClass("fixedHeight"); //para que aparezca la barra de desplazamiento en el buscar

			$("#nombre_producto_servicio").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_producto_mesa").val("");
					$("#nombre_producto_servicio").val("");
					$("#precio_agregar").val("");
					$("#tipo_producto_agregar").val("");
					$("#existencia_producto").val("");
					$("#medida_agregar").val("");
					$("#stock_tmp").val("");
				}
			});

		}

		$(function() {
			//para cuando se cambia el select del precio
			$('#select_precio').change(function() {
				var precio_seleccionado = $("#select_precio").val();
				$("#precio_agregar").val(precio_seleccionado);
				$("#precio_tmp").val(precio_seleccionado);
			});

			//para cuando se cambia el select una bodega que me cargue el saldo de ese producto
			$('#bodega_agregar').change(function() {
				var bodega = $("#bodega_agregar").val();
				var producto = $("#id_producto_agregar").val();
				var id_medida = $("#medida_agregar").val();

				//reinicia la medida
				$.post('../ajax/select_tipo_medida.php', {
					id_producto: producto
				}).done(function(res_id_medidas) {
					$("#medida_agregar").html(res_id_medidas);
				});
				//reinicie el precio
				var precio_venta = $("#precio_tmp").val();
				$("#precio_agregar").val(precio_venta);

				//trae la existencia en base a la bodega
				$.post('../ajax/saldo_producto_restaurante.php', {
					id_bodega: bodega,
					id_producto: producto
				}).done(function(respuesta) {
					var saldo_producto = respuesta;
					$("#existencia_producto").val(saldo_producto);
					$('#stock_tmp').val(saldo_producto);
				});

				//reinicio el lote
				$.post('../ajax/select_opciones_inventario.php', {
					opcion: 'lote',
					id_producto: producto,
					bodega: bodega
				}).done(function(res_opciones_lote) {
					$("#lote_agregar").html(res_opciones_lote);
				});
			});


			//para traer el valor de conversion de medidas en el producto cuando se cambia el select de medida
			$('#medida_agregar').change(function() {
				var id_medida = $("#medida_agregar").val();
				var id_producto = $("#id_producto_agregar").val();
				var precio_venta = $("#precio_tmp").val(); //este precio es para que no se me cambie del precio que calculo cada vez que cambio el selec de medida
				var stock_tmp = $("#stock_tmp").val();

				$.post('../ajax/saldo_producto_restaurante.php', {
					id_medida_seleccionada: id_medida,
					id_producto: id_producto,
					precio_venta: precio_venta,
					stock_tmp: stock_tmp,
					dato_obtener: 'saldo'
				}).done(function(respuesta_saldo) {
					$("#existencia_producto").val(respuesta_saldo);
				});

				$.post('../ajax/saldo_producto_restaurante.php', {
					id_medida_seleccionada: id_medida,
					id_producto: id_producto,
					precio_venta: precio_venta,
					stock_tmp: stock_tmp,
					dato_obtener: 'precio'
				}).done(function(respuesta_precio) {
					$("#precio_agregar").val(respuesta_precio);
				});

			});

			//para traer el valor de conversion de medidas en el producto cuando se cambia el select de lote
			$('#lote_agregar').change(function() {
				var lote = $("#lote_agregar").val();
				var producto = $("#id_producto_agregar").val();
				var bodega = $("#bodega_agregar").val();

				$.post('../ajax/saldo_producto_restaurante.php', {
					opcion_lote: lote,
					id_producto: producto,
					bodega: bodega
				}).done(function(respuesta_lote) {
					$("#existencia_producto").val(respuesta_lote);
				});

				//reinicia la medida
				$.post('../ajax/select_tipo_medida.php', {
					id_producto: producto
				}).done(function(res_id_medidas) {
					$("#medida_agregar").html(res_id_medidas);
				});
				//reinicie el precio
				var precio_venta = $("#precio_tmp").val();
				$("#precio_agregar").val(precio_venta);

				/*
				//para reinicie vencimiento
				$.post( '../ajax/select_opciones_inventario.php', {opcion:'caducidad', id_producto: producto, bodega: bodega}).done( function( res_opciones_caducidad ){
					$("#caducidad_agregar").html(res_opciones_caducidad);
				});
				*/

			});

		});

		//para cargar automaticamente el numero de factura que sigue al momento de cargar la nueva factura
		$(document).ready(function() {
			document.getElementById("titulo_bodega").style.display = "none";
			document.getElementById("titulo_lote").style.display = "none";
			//document.getElementById("titulo_caducidad").style.display="none";
			document.getElementById("titulo_medida").style.display = "none";
			document.getElementById("titulo_existencia").style.display = "none";
			document.getElementById("lista_bodega").style.display = "none";
			document.getElementById("lista_lote").style.display = "none";
			//document.getElementById("lista_caducidad").style.display="none";
			document.getElementById("lista_medida").style.display = "none";
			document.getElementById("lista_existencia").style.display = "none";

			var id_serie = $("#serie_factura_e").val();
			$('#inventario').val('SI');

			//para traer y ver si trabaja con medida
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'medida',
				serie_consultada: id_serie
			}).done(function(respuesta_medida) {
				var resultado_medida = $.trim(respuesta_medida);
				$('#muestra_medida').val(resultado_medida);
			});

			//para traer y ver si trabaja con lote
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'lote',
				serie_consultada: id_serie
			}).done(function(respuesta_lote) {
				var resultado_lote = $.trim(respuesta_lote);
				$('#muestra_lote').val(resultado_lote);
			});

			//para traer y ver si trabaja con bodega
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'bodega',
				serie_consultada: id_serie
			}).done(function(respuesta_bodega) {
				var resultado_bodega = $.trim(respuesta_bodega);
				$('#muestra_bodega').val(resultado_bodega);
			});

			document.getElementById('nombre_producto_servicio').focus();

		});

		function obtener_datos_mesas(id) {
			var nombre_mesa = $("#nombre_mesa" + id).val();
			$("#label_nombre_mesa").val(nombre_mesa);
			$("#id_mesa").val(id);
			$("#muestra_detalle_mesas").fadeIn('fast');
			$.ajax({
				url: '../ajax/detalle_mesas.php?action=muestra_detalle&id_mesa=' + id,
				beforeSend: function(objeto) {
					$('#muestra_detalle_mesas').html('Cargando...');
				},
				success: function(data) {
					$(".outer_divdet_mesa").html(data).fadeIn('fast');
					$('#muestra_detalle_mesas').html('');
				}
			});
			document.getElementById('nombre_producto_servicio').focus();
		}

		function agregar_detalle_orden() {
			var id_mesa = $("#id_mesa").val();
			var id_cliente = $("#id_cliente_mesa").val();
			var id_producto_agregar = $("#id_producto_mesa").val();
			var fecha_mesa = $("#fecha_mesa").val();
			var cantidad = $("#cantidad_agregar").val();
			var precio_venta = $("#precio_agregar").val();
			var serie_sucursal = $("#serie_factura_e").val();
			var tipo_producto_agregar = $("#tipo_producto_agregar").val();
			var medida_agregar = document.getElementById('medida_agregar').value;
			var lote_agregar = document.getElementById('lote_agregar').value;
			var bodega_agregar = document.getElementById('bodega_agregar').value;
			var existencia_producto = document.getElementById('existencia_producto').value;
			var configuracion_inventario = document.getElementById('inventario').value;
			var control_bodega = document.getElementById('muestra_bodega').value;
			var control_lote = document.getElementById('muestra_lote').value;
			//var control_caducidad=document.getElementById('muestra_vencimiento').value;

			if (id_producto_agregar == "") {
				alert('Seleccione un producto o servicio');
				document.getElementById('nombre_producto_servicio').focus();
				return false;
			}
			if (cantidad == "") {
				alert('Ingrese cantidad');
				document.getElementById('cantidad_agregar').focus();
				return false;
			}
			if (isNaN(cantidad)) {
				alert('El dato ingresado en cantidad, no es un número');
				document.getElementById('cantidad_agregar').focus();
				return false;
			}
			if (precio_venta == '') {
				alert('Ingrese precio');
				document.getElementById('precio_agregar').focus();
				return false;
			}

			if (isNaN(precio_venta)) {
				alert('El dato ingresado en precio, no es un número');
				document.getElementById('precio_agregar').focus();
				return false;
			}

			//if (configuracion_inventario =='SI' && tipo_producto_agregar=='01' && control_bodega=='SI' && bodega_agregar=='0' ){
			if (tipo_producto_agregar == '01' && control_bodega == 'SI' && bodega_agregar == '0') {
				alert('Seleccione una bodega');
				document.getElementById('bodega_agregar').focus();
				return false;
			}

			//if (configuracion_inventario =='SI' && tipo_producto_agregar=='01' && control_lote=='SI' && lote_agregar=='0' ){
			if (tipo_producto_agregar == '01' && control_lote == 'SI' && lote_agregar == '0') {
				alert('Seleccione un lote');
				document.getElementById('lote_agregar').focus();
				return false;
			}

			//Fin validacion
			$("#muestra_detalle_mesas").fadeIn('fast');
			$.ajax({
				url: "../ajax/detalle_mesas.php?action=agregar_orden&id_producto=" + id_producto_agregar + "&precio=" + precio_venta + "&cantidad=" + cantidad + "&serie_sucursal=" + serie_sucursal + "&bodega_agregar=" + bodega_agregar + "&medida_agregar=" + medida_agregar + "&lote_agregar=" + lote_agregar + "&id_mesa=" + id_mesa + "&tipo_producto_agregar=" + tipo_producto_agregar + "&id_cliente=" + id_cliente + "&fecha_mesa=" + fecha_mesa,
				beforeSend: function(objeto) {
					$("#muestra_detalle_mesas").html("Cargando...");
				},
				success: function(data) {
					$(".outer_divdet_mesa").html(data).fadeIn('fast');
					$("#muestra_detalle_mesas").html('');
					$("#nombre_producto_servicio").val("");
					$("#id_producto_mesa").val("");
					$("#precio_agregar").val("");
					$("#tipo_producto_agregar").val("");
					$("#existencia_producto").val("0");
					$("#cantidad_agregar").val("");
					document.getElementById('nombre_producto_servicio').focus();
				}
			});

		}

		function eliminar_orden(id_detalle) {
			var id_mesa = $("#id_mesa_eliminada" + id_detalle).val();
			if (confirm("Realmente desea eliminar el item?")) {
				$("#muestra_detalle_mesas").fadeIn('fast');
				$.ajax({
					url: '../ajax/detalle_mesas.php?action=eliminar_orden_mesa&id_mesa=' + id_mesa + "&id_detalle=" + id_detalle,
					beforeSend: function(objeto) {
						$('#muestra_detalle_mesas').html('Cargando...');
					},
					success: function(data) {
						$(".outer_divdet_mesa").html(data).fadeIn('fast');
						$('#muestra_detalle_mesas').html('');
					}
				});
				$("#nombre_producto_servicio").val("");
				$("#id_producto_mesa").val("");
				$("#precio_agregar").val("");
				$("#tipo_producto_agregar").val("");
				$("#existencia_producto").val("0");
				$("#cantidad_agregar").val("");
				document.getElementById('nombre_producto_servicio').focus();
			}
		}

		//para cerar el modal de detalle y que se actualicen los colores de las mesas para saber sis esta libre
		$("#cerrar_detalle_mesa").click(function() {
			load(1);
		});

		//generar factura
		$("#generar_factura").submit(function(event) {
			$('#guardar_datos').attr("disabled", true);
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: "../ajax/generar_factura_mesa.php",
				data: parametros,
				beforeSend: function(objeto) {
					$("#mensajes_ordenes_mesa").html("Mensaje: Generando factura...");
				},
				success: function(datos) {
					$("#mensajes_ordenes_mesa").html(datos);
					$('#guardar_datos').attr("disabled", false);
					//load(1);
				}
			});
			event.preventDefault();
		})

		//calcula la propina presionando el boton el 10% del subtotal
		function calcular_propina(id_detalle) {
			var propina_calculada = $("#propina_calculada").val();
			$("#propina").val(propina_calculada);
			var id_mesa = $("#id_mesa_eliminada" + id_detalle).val();

			$("#muestra_detalle_mesas").fadeIn('fast');
			$.ajax({
				url: '../ajax/detalle_mesas.php?action=guardar_propina&id_mesa=' + id_mesa + '&propina=' + propina_calculada,
				beforeSend: function(objeto) {
					$('#muestra_detalle_mesas').html('Cargando...');
				},
				success: function(data) {
					$(".outer_divdet_mesa").html(data).fadeIn('fast');
					$('#muestra_detalle_mesas').html('');
				}
			});
		}

		//aplica propina ingresando un valor en el campo propina
		function aplica_propina(id_detalle) {
			var propina_calculada = $("#propina").val();
			$("#propina").val(propina_calculada);
			var id_mesa = $("#id_mesa_eliminada" + id_detalle).val();

			$("#muestra_detalle_mesas").fadeIn('fast');
			$.ajax({
				url: '../ajax/detalle_mesas.php?action=guardar_propina&id_mesa=' + id_mesa + '&propina=' + propina_calculada,
				beforeSend: function(objeto) {
					$('#muestra_detalle_mesas').html('Cargando...');
				},
				success: function(data) {
					$(".outer_divdet_mesa").html(data).fadeIn('fast');
					$('#muestra_detalle_mesas').html('');
				}
			});
		}

		//calcula en base al marcado de cada item
		function editar_check_marcado(id_detalle) {
			var id_detalle = $("#id_detalle_mesa" + id_detalle).val();
			var id_mesa = $("#id_mesa_eliminada" + id_detalle).val(); //uso este id para saber el id de mesa
			var estado_marcado = $("#estado_marcado" + id_detalle).val();
			$("#muestra_detalle_mesas").fadeIn('fast');
			$.ajax({
				url: '../ajax/detalle_mesas.php?action=editar_check_marcado&id_detalle=' + id_detalle + '&id_mesa=' + id_mesa + '&estado_marcado=' + estado_marcado,
				beforeSend: function(objeto) {
					$('#muestra_detalle_mesas').html('Calculando...');
				},
				success: function(data) {
					$(".outer_divdet_mesa").html(data).fadeIn('fast');
					$('#muestra_detalle_mesas').html('');
				}
			});
			document.getElementById('nombre_producto_servicio').focus();
		}


		function imprimir_comandas(opcion) {
			var id_mesa = $("#id_mesa").val();
			window.open('../impresiones/imprimir.php?action=' + opcion + '&id_mesa=' + id_mesa, '_blank');
		}


		function descuento_item(id) {
			var descuento_item = document.getElementById('descuento_item' + id).value;
			var descuento_inicial = document.getElementById('descuento_inicial' + id).value;
			var id_mesa = document.getElementById('id_mesa').value;

			if (isNaN(descuento_item)) {
				alert('El valor ingresado, no es un número');
				$("#descuento_item" + id).val(descuento_inicial);
				document.getElementById('descuento_item' + id).focus();
				return false;
			}

			if ((descuento_item < 0)) {
				alert('El valor ingresado debe ser mayor a cero');
				$("#descuento_item" + id).val(descuento_inicial);
				document.getElementById('descuento_item' + id).focus();
				return false;
			}

			$.ajax({
				type: "POST",
				url: "../ajax/detalle_mesas.php?action=actualiza_descuento_item",
				data: "id=" + id + "&descuento_item=" + descuento_item + "&id_mesa=" + id_mesa,
				beforeSend: function(objeto) {
					$("#muestra_detalle_mesas").html("Cargando...");
				},
				success: function(data) {
					$(".outer_divdet_mesa").html(data).fadeIn('fast');
					$('#muestra_detalle_mesas').html('');

				}
			});
		}


		function reset_posiciones() {
			if (confirm("Desea reiniciar todas las posiciones de las mesas?")) {
				$.ajax({
					url: '../ajax/detalle_mesas.php?action=reset_posiciones',
					beforeSend: function(objeto) {
						$('#resultados').html('Cargando...');
					},
					success: function(data) {
						$(".outer_div").html(data).fadeIn('fast');
						$('#resultados').html('');
						load(1);
					}
				});
			}
		}
	</script>